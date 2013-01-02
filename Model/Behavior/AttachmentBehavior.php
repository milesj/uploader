<?php
/**
 * @copyright	Copyright 2006-2013, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/uploader
 */

App::uses('Set', 'Utility');
App::uses('ModelBehavior', 'Model');

use Transit\Transit;
use Transit\File;
use Transit\Exception\ValidationException;
use Transit\Transformer\Image\CropTransformer;
use Transit\Transformer\Image\FlipTransformer;
use Transit\Transformer\Image\ResizeTransformer;
use Transit\Transformer\Image\ScaleTransformer;
use Transit\Transporter\Aws\S3Transporter;
use Transit\Transporter\Aws\GlacierTransporter;

/**
 * A CakePHP Behavior that attaches a file to a model, uploads automatically,
 * and then stores a value in the database.
 */
class AttachmentBehavior extends ModelBehavior {

	/**
	 * Transformation types.
	 */
	const CROP = 'crop';
	const FLIP = 'flip';
	const RESIZE = 'resize';
	const SCALE = 'scale';

	/**
	 * Transportation types.
	 */
	const S3 = 's3';
	const GLACIER = 'glacier';

	/**
	 * Transit instances indexed by model alias.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_uploads = array();

	/**
	 * Mapping of database columns to attachment fields.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_columns = array();

	/**
	 * Default attachment settings.
	 *
	 * 		nameCallback	- Method to format filename with
	 * 		append			- What to append to the end of the filename
	 * 		prepend			- What to prepend to the beginning of the filename
	 * 		tempDir			- Directory to upload files to temporarily
	 * 		uploadDir		- Directory to move file to after upload to make it publicly accessible
	 * 		finalPath		- The final path to prepend to file names (like a domain)
	 * 		dbColumn		- Database column to write file path to
	 * 		metaColumns		- Database columns to write meta data to
	 * 		defaultPath		- Default image if no file is uploaded
	 * 		overwrite		- Overwrite a file with the same name if it exists
	 * 		stopSave		- Stop save() if error exists during upload
	 * 		allowEmpty		- Allow an empty file upload to continue
	 * 		transforms		- List of transforms to apply to the image
	 * 		transport		- Settings for file transportation
	 *
	 * @access protected
	 * @var array
	 */
	protected $_defaultSettings = array(
		'nameCallback' => '',
		'append' => '',
		'prepend' => '',
		'tempDir' => TMP,
		'uploadDir' => '',
		'finalPath' => '',
		'dbColumn' => '',
		'metaColumns' => array(),
		'defaultPath' => '',
		'overwrite' => false,
		'stopSave' => true,
		'allowEmpty' => true,
		'transforms' => array(),
		'transport' => array()
	);

	/**
	 * Default transform settings.
	 *
	 * 		method			- The transform method
	 * 		nameCallback	- Method to format filename with
	 * 		append			- What to append to the end of the filename
	 * 		prepend			- What to prepend to the beginning of the filename
	 * 		uploadDir		- Directory to move file to after upload to make it publicly accessible
	 * 		finalPath		- The final path to prepend to file names (like a domain)
	 * 		dbColumn		- Database column to write file path to
	 * 		overwrite		- Overwrite a file with the same name if it exists
	 * 		self			- Should the transforms apply to the uploaded file instead of creating new images
	 *
	 * @access protected
	 * @var array
	 */
	protected $_transformSettings = array(
		'method' => '',
		'nameCallback' => '',
		'append' => '',
		'prepend' => '',
		'uploadDir' => '',
		'finalPath' => '',
		'dbColumn' => 'thumbnail',
		'overwrite' => false,
		'self' => false
	);

	/**
	 * Save attachment settings.
	 *
	 * @access public
	 * @param Model $model
	 * @param array $settings
	 */
	public function setup(Model $model, $settings = array()) {
		if ($settings) {
			foreach ($settings as $field => $attachment) {
				$attachment = Set::merge($this->_defaultSettings, $attachment);
				$attachment['field'] = $field;

				// Set defaults if not defined
				if (!$attachment['dbColumn']) {
					$attachment['dbColumn'] = $field;
				}

				if (!$attachment['tempDir']) {
					$attachment['tempDir'] = TMP;
				}

				if (!$attachment['uploadDir']) {
					$attachment['uploadDir'] = WWW_ROOT . 'files/uploads/';
					$attachment['finalPath'] = 'files/uploads/';
				}

				$attachment = $this->_callback($model, 'beforeUpload', $attachment);
				$columns = array($attachment['dbColumn'] => $field);

				// Merge transform settings with defaults
				if ($attachment['transforms']) {
					foreach ($attachment['transforms'] as &$transform) {
						$transform = Set::merge($this->_transformSettings, $transform + array(
							'uploadDir' => $attachment['uploadDir'],
							'finalPath' => $attachment['finalPath']
						));

						if ($transform['self']) {
							$transform['dbColumn'] = $attachment['dbColumn'];
						}

						$transform = $this->_callback($model, 'beforeTransform', $transform);

						$columns[$transform['dbColumn']] = $field;
					}
				}

				if ($attachment['transport']) {
					$attachment['transport'] = $this->_callback($model, 'beforeTransport', $attachment['transport']);
				}

				$this->settings[$model->alias][$field] = $attachment;
				$this->_columns[$model->alias] = $columns;
			}
		}
	}

	/**
	 * Cleanup and reset the behavior when its detached.
	 *
	 * @access public
	 * @param Model $model
	 * @return void
	 */
	public function cleanup(Model $model) {
		parent::cleanup($model);

		$this->_uploads = array();
		$this->_columns = array();
	}

	/**
	 * Deletes any files that have been attached to this model.
	 *
	 * @access public
	 * @param Model $model
	 * @param boolean $cascade
	 * @return boolean
	 */
	public function beforeDelete(Model $model, $cascade = true) {
		if (empty($model->id)) {
			return false;
		}

		return $this->deleteImages($model, $model->id);
	}

	/**
	 * Before saving the data, try uploading the image, if successful save to database.
	 *
	 * @access public
	 * @param Model $model
	 * @return boolean
	 */
	public function beforeSave(Model $model) {
		$alias = $model->alias;

		if (empty($model->data[$alias])) {
			return true;
		}

		foreach ($model->data[$alias] as $field => $file) {
			if (empty($this->settings[$alias][$field])) {
				continue;
			}

			// Gather attachment settings
			$attachment = $this->settings[$alias][$field];
			$data = array();

			// Initialize Transit
			$transit = new Transit($file);
			$transit->setDirectory($attachment['tempDir']);

			$this->_uploads[$alias] = $transit;

			// Set transformers and transporter
			$this->_addTransformers($model, $transit, $attachment);
			$this->_setTransporter($model, $transit, $attachment);

			// Attempt upload or import
			try {
				$overwrite = $attachment['overwrite'];

				// File upload
				if (is_array($file)) {
					$response = $transit->upload($overwrite);

				// Remote import
				} else if (strpos($file, 'http') === 0) {
					$response = $transit->importFromRemote($overwrite);

				// Local import
				} else if (file_exists($file)) {
					$response = $transit->importFromLocal($overwrite);

				// Stream import
				} else {
					$response = $transit->importFromStream($overwrite);
				}

				// Successful upload or import
				if ($response) {
					$originalFile = $transit->getOriginalFile();

					// Rename and move file
					$data[$attachment['dbColumn']] = $this->_renameAndMove($model, $originalFile, $attachment);

					// Transform the files and save their path
					if ($attachment['transforms']) {
						$transit->transform();

						foreach ($transit->getTransformedFiles() as $i => $transformedFile) {
							$transformSettings = $attachment['transforms'][$i];

							$data[$transformSettings['dbColumn']] = $this->_renameAndMove($model, $transformedFile, $transformSettings);
						}
					}

					// Transport the files and save their remote path
					if ($attachment['transport']) {
						if ($transportedFiles = $transit->transport()) {
							foreach ($transportedFiles as $i => $transportedFile) {
								if ($i === 0) {
									$dbColumn = $attachment['dbColumn'];
								} else {
									$dbColumn = $attachment['transforms'][($i - 1)]['dbColumn'];
								}

								$data[$dbColumn] = $transportedFile;
							}
						}
					}
				}

			// Trigger form errors if validation fails
			} catch (ValidationException $e) {
				if ($attachment['allowEmpty']) {
					if (empty($attachment['defaultPath'])) {
						unset($model->data[$alias][$attachment['dbColumn']]);
					} else {
						$model->data[$alias][$attachment['dbColumn']] = $attachment['defaultPath'];
					}

					continue;
				}

				$model->invalidate($field, __d('uploader', $e->getMessage()));

				if ($attachment['stopSave']) {
					return false;
				}

			// Log exceptions that shouldn't be shown to the client
			} catch (Exception $e) {
				$model->invalidate($field, __d('uploader', 'An unknown error has occurred'));

				$this->log($e->getMessage(), LOG_DEBUG);

				// Rollback the files since it threw errors
				$transit->rollback();
			}

			// Save file meta data
			if ($attachment['metaColumns'] && $data) {
				foreach ($attachment['metaColumns'] as $method => $column) {
					$fileMetaData = $transit->getOriginalFile()->toArray();

					if (isset($fileMetaData[$method]) && $column) {
						$data[$column] = $fileMetaData[$method];
					}
				}
			}

			// Merge upload data with model data
			if ($data) {
				$model->data[$alias] = $data + $model->data[$alias];
			}
		}

		return true;
	}

	/**
	 * Delete all images associated with a record but do not delete the record.
	 *
	 * @access public
	 * @param Model $model
	 * @param int $id
	 * @return boolean
	 */
	public function deleteImages(Model $model, $id) {
		$data = $model->read(null, $id);
		$columns = $this->_columns[$model->alias];

		if (empty($data[$model->alias])) {
			return false;
		}

		foreach ($data[$model->alias] as $column => $value) {
			if (empty($columns[$column]) || empty($this->settings[$model->alias][$columns[$column]])) {
				continue;
			}

			$attachment = $this->settings[$model->alias][$columns[$column]];
			$basePath = $attachment['uploadDir'] ?: $attachment['tempDir'];

			try {
				// Delete remote file
				if ($attachment['transport']) {
					$transporter = $this->_getTransporter($attachment['transport']);
					$transporter->delete($value);

				// Delete local file
				} else {
					$file = new File($basePath . basename($value));
					$file->delete();
				}
			} catch (Exception $e) {
				$this->log($e->getMessage(), LOG_DEBUG);
			}
		}

		return true;
	}

	/**
	 * Return the uploaded original File object.
	 *
	 * @access public
	 * @param Model $model
	 * @return \Transit\File
	 */
	public function getUploadedFile(Model $model) {
		if (isset($this->_uploads[$model->alias])) {
			return $this->_uploads[$model->alias]->getOriginalFile();
		}

		return null;
	}

	/**
	 * Return the transformed File objects.
	 *
	 * @access public
	 * @param Model $model
	 * @return array
	 */
	public function getTransformedFiles(Model $model) {
		if (isset($this->_uploads[$model->alias])) {
			return $this->_uploads[$model->alias]->getTransformedFiles();
		}

		return null;
	}

	/**
	 * Trigger a callback function to modify data.
	 *
	 * @access protected
	 * @param Model $model
	 * @param string $method
	 * @param array $options
	 * @return array
	 */
	protected function _callback(Model $model, $method, array $options) {
		if (method_exists($model, $method)) {
			return $model->{$method}($options);
		}

		return $options;
	}

	/**
	 * Add Transit Transformers based on the attachment settings.
	 *
	 * @access protected
	 * @param Model $model
	 * @param \Transit\Transit $transit
	 * @param array $attachment
	 */
	protected function _addTransformers(Model $model, Transit $transit, array $attachment) {
		if (empty($attachment['transforms'])) {
			return;
		}

		foreach ($attachment['transforms'] as $options) {
			$transformer = $this->_getTransformer($options);

			if ($options['self']) {
				$transit->addSelfTransformer($transformer);
			} else {
				$transit->addTransformer($transformer);
			}
		}
	}

	/**
	 * Set the Transit Transporter to use based on the attachment settings.
	 *
	 * @access protected
	 * @param Model $model
	 * @param \Transit\Transit $transit
	 * @param array $attachment
	 */
	protected function _setTransporter(Model $model, Transit $transit, array $attachment) {
		if (empty($attachment['transport'])) {
			return;
		}

		$transit->setTransporter($this->_getTransporter($attachment['transport']));
	}

	/**
	 * Return a Transformer based on the options.
	 *
	 * @access protected
	 * @param array $options
	 * @return \Transit\Transformer\Transformer
	 * @throws \Exception
	 */
	protected function _getTransformer(array $options) {
		switch ($options['method']) {
			case self::CROP:
				return new CropTransformer($options);
			break;
			case self::FLIP:
				return new FlipTransformer($options);
			break;
			case self::RESIZE:
				return new ResizeTransformer($options);
			break;
			case self::SCALE:
				return new ScaleTransformer($options);
			break;
			default:
				throw new Exception(sprintf('Invalid transformation method %s', $options['method']));
			break;
		}
	}

	/**
	 * Return a Transporter based on the options.
	 *
	 * @access protected
	 * @param array $options
	 * @return \Transit\Transporter\Transporter
	 * @throws \Exception
	 */
	protected function _getTransporter(array $options) {
		switch ($options['class']) {
			case self::S3:
				return new S3Transporter($options['accessKey'], $options['secretKey'], $options);
			break;
			case self::GLACIER:
				return new GlacierTransporter($options['accessKey'], $options['secretKey'], $options);
			break;
			default:
				throw new Exception(sprintf('Invalid transport class %s', $options['class']));
			break;
		}
	}

	/**
	 * Rename or move the file and return its relative path.
	 *
	 * @access protected
	 * @param Model $model
	 * @param \Transit\File $file
	 * @param array $options
	 * @return string
	 */
	protected function _renameAndMove(Model $model, File $file, array $options) {
		$nameCallback = null;

		if ($options['nameCallback'] && method_exists($model, $options['nameCallback'])) {
			$nameCallback = array($model, $options['nameCallback']);
		}

		$file->rename($nameCallback, $options['append'], $options['prepend']);

		if ($options['uploadDir']) {
			$file->move($options['uploadDir'], $options['overwrite']);
		}

		return (string) $options['finalPath'] . $file->basename();
	}

}