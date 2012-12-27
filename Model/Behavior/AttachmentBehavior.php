<?php
/**
 * AttachmentBehavior
 *
 * A CakePHP Behavior that attaches a file to a model, and uploads automatically, then stores a value in the database.
 *
 * @author      Miles Johnson - http://milesj.me
 * @copyright   Copyright 2006-2011, Miles Johnson, Inc.
 * @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link        http://milesj.me/code/cakephp/uploader
 */

App::uses('Set', 'Utility');
App::uses('String', 'Utility');
App::uses('ModelBehavior', 'Model');

use Transit\Transit;
use Transit\Exception\ValidationException;
use Transit\Transformer\Image\CropTransformer;
use Transit\Transformer\Image\FlipTransformer;
use Transit\Transformer\Image\ResizeTransformer;
use Transit\Transformer\Image\ScaleTransformer;
use Transit\Transporter\Aws\S3Transporter;
use Transit\Transporter\Aws\GlacierTransporter;
use \Exception;

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
	 * All user defined attachments indexed by column name.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_attachments = array();

	/**
	 * Mapping of database columns to form fields.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_columns = array();

	/**
	 * The default settings for attachments.
	 *
	 * 		nameCallback	- Method to format filename with
	 * 		append			- What to append to the end of the filename
	 * 		prepend			- What to prepend to the beginning of the filename
	 * 		uploadDir		- Directory to upload files to
	 * 		finalPath		- The final path to prepend to file names (like a domain)
	 * 		dbColumn		- Database column to write file path to
	 * 		metaColumns		- Database columns to write meta data to
	 * 		defaultPath		- Default image if no file is uploaded
	 * 		overwrite		- Overwrite a file with the same name if it exists
	 * 		stopSave		- Stop save() if error exists during upload
	 * 		allowEmpty		- Allow an empty file upload to continue
	 * 		saveAsFilename	- Save only the filename instead of the relative path
	 * 		transforms		- List of transforms to apply to the image
	 * 		transport		- Settings for file transportation
	 *
	 * @access protected
	 * @var array
	 */
	protected $_defaults = array(
		'nameCallback' => '',
		'append' => '',
		'prepend' => '',
		'uploadDir' => TMP,
		'finalPath' => 'files/uploads/',
		'dbColumn' => 'path',
		'metaColumns' => array(),
		'defaultPath' => '',
		'overwrite' => false,
		'stopSave' => true,
		'allowEmpty' => true,
		'transforms' => array(),
		'transport' => array()
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
				$attachment = Set::merge($this->_defaults, $attachment);
				$attachment['field'] = $field;

				$columns = array($attachment['dbColumn'] => $field);

				if ($attachment['transforms']) {
					foreach ($attachment['transforms'] as $transform) {
						$columns[$transform['dbColumn']] = $field;
					}
				}

				$this->_attachments[$model->alias][$field] = $attachment;
				$this->_columns[$model->alias] = $columns;
			}
		}
	}

	/**
	 * Deletes any files that have been attached to this model.
	 *
	 * @access public
	 * @param Model $model
	 * @param boolean $cascade
	 * @return mixed
	 */
	public function beforeDelete(Model $model, $cascade = true) {
		if (empty($model->id)) {
			return false;
		}

		$data = $model->read(null, $model->id);
		$columns = $this->_columns[$model->alias];

		if ($data[$model->alias]) {
			foreach ($data[$model->alias] as $column => $value) {
				// @TODO
				if (isset($columns[$column])) {
					$attachment = $this->_attachments[$model->alias][$columns[$column]];

					$this->uploader->setup($attachment);
					$this->s3 = $this->s3($attachment['s3']);

					$path = $attachment['saveAsFilename'] ? rtrim($attachment['uploadDir'], '/') . '/' . $value : $value;
					$this->delete($path);
				}
			}
		}

		return true;
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
			if (empty($this->_attachments[$alias][$field])) {
				continue;
			}

			// Gather attachment settings
			$attachment = $this->_attachments[$alias][$field];
			$attachment = $this->_callback($model, 'beforeUpload', $attachment);
			$data = array();

			// Initialize Transit
			$transit = new Transit($file);
			$transit->setDirectory($attachment['uploadDir']);

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

					// Rename the file before processing
					$nameCallback = null;

					if ($attachment['nameCallback'] && method_exists($model, $attachment['nameCallback'])) {
						$nameCallback = array($model, $attachment['nameCallback']);
					}

					$originalFile->rename($nameCallback, $attachment['append'], $attachment['prepend']);

					$data[$attachment['dbColumn']] = $originalFile->basename();

					// Transform the files and save their file path
					if ($attachment['transforms']) {
						$transit->transform();

						foreach ($transit->getTransformedFiles() as $i => $transformedFile) {
							$data[$attachment['transforms'][$i]['dbColumn']] = $transformedFile->basename();
						}
					}

					// Transport the files and save their remote path
					if ($attachment['transport']) {
						if ($transportedFiles = $transit->transport()) {
							foreach ($transportedFiles as $i => $transportedFile) {
								$data[$attachment['transforms'][$i]['dbColumn']] = $transportedFile;
							}
						}
					}
				}

			// Trigger form errors if validation fails
			} catch (ValidationException $e) {
				$model->invalidate($field, __d('uploader', $e->getMessage()));

				if ($attachment['stopSave'] && !$attachment['allowEmpty']) {
					return false;
				}

				if ($attachment['allowEmpty']) {
					if (empty($attachment['defaultPath'])) {
						unset($model->data[$alias][$attachment['dbColumn']]);
					} else {
						$model->data[$alias][$attachment['dbColumn']] = $attachment['defaultPath'];
					}

					continue;
				}

			// Log exceptions that shouldn't be shown to the client
			} catch (Exception $e) {
				$this->log($e->getMessage(), LOG_DEBUG);
			}

			// Save file meta data
			if ($attachment['metaColumns']) {
				foreach ($attachment['metaColumns'] as $method => $column) {
					$fileMetaData = $transit->getOriginalFile()->toArray();

					if (isset($fileMetaData[$method]) && $column) {
						$data[$column] = $fileMetaData[$method];
					}
				}
			}

			// Generate final paths
			if ($attachment['finalPath']) {
				foreach ($data as $key => $value) {
					if (strpos($value, 'http') === false) {
						$data[$key] = $attachment['finalPath'] . $value;
					}
				}
			}

			// Merge upload data with model data
			$model->data[$alias] = $data + $model->data[$alias];
		}

		return true;
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
	 * @throws \Exception
	 */
	protected function _addTransformers(Model $model, Transit $transit, array $attachment) {
		if (empty($attachment['transforms'])) {
			return;
		}

		foreach ($attachment['transforms'] as $options) {
			$transformer = null;
			$options = $this->_callback($model, 'beforeTransform', $options + array(
				'method' => '',
				'self' => false,
				'overwrite' => $attachment['overwrite']
			));

			switch ($options['method']) {
				case self::CROP:
					$transformer = new CropTransformer($options);
				break;
				case self::FLIP:
					$transformer = new FlipTransformer($options);
				break;
				case self::RESIZE:
					$transformer = new ResizeTransformer($options);
				break;
				case self::SCALE:
					$transformer = new ScaleTransformer($options);
				break;
				default:
					throw new Exception(sprintf('Invalid transformation method %s', $options['method']));
				break;
			}

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
	 * @throws \Exception
	 */
	protected function _setTransporter(Model $model, Transit $transit, array $attachment) {
		if (empty($attachment['transport'])) {
			return;
		}

		$options = $this->_callback($model, 'beforeTransport', $attachment['transport'] + array(
			'class' => '',
			'overwrite' => $attachment['overwrite']
		));

		switch ($options['class']) {
			case self::S3:
				$transit->setTransporter(new S3Transporter($options['accessKey'], $options['secretKey'], $options));
			break;
			case self::GLACIER:
				$transit->setTransporter(new GlacierTransporter($options['accessKey'], $options['secretKey'], $options));
			break;
			default:
				throw new Exception(sprintf('Invalid transport class %s', $options['class']));
			break;
		}
	}

}