<?php
/** 
 * Attachment Behavior
 *
 * A CakePHP Behavior that attaches a file to a model, and uploads automatically, then stores a value in the database.
 *
 * @author      Miles Johnson - http://milesj.me
 * @copyright   Copyright 2006-2011, Miles Johnson, Inc.
 * @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link        http://milesj.me/code/cakephp/uploader
 */

App::import('Component', 'Uploader.S3Transfer');
App::import('Vendor', 'Uploader.Uploader');

class AttachmentBehavior extends ModelBehavior {

	/**
	 * AS3 domain snippet.
	 */
	const AS3_DOMAIN = 's3.amazonaws.com';

	/**
	 * Files that have been uploaded or attached; used for rollback functions.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_attached = array();

	/**
	 * All user defined attachments; images => model.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_attachments = array();

	/**
	 * The default settings for attachments.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_defaults = array(
		'name'			=> '',
		'baseDir'		=> '',
		'uploadDir'		=> '',
		'dbColumn'		=> 'uploadPath',
		'importFrom'	=> '',
		'defaultPath'	=> '',
		'maxNameLength'	=> null,
		'overwrite'		=> true,
		'stopSave'		=> true,
		'transforms'	=> array(),
		's3'			=> array(),
		'metaColumns'	=> array(
			'ext' => '',
			'type' => '',
			'size' => '',
			'group' => '',
			'width' => '',
			'height' => '',
			'filesize' => ''
		)
	);

	/**
	 * Initialize uploader and save attachments.
	 *
	 * @access public
	 * @uses UploaderComponent, S3TransferComponent
	 * @param Model $model
	 * @param array $settings
	 * @return boolean
	 */
	public function setup($model, array $settings = array()) {
		$this->uploader = new Uploader();
		$this->S3Transfer = new S3TransferComponent();

		if (!empty($settings) && is_array($settings)) {
			foreach ($settings as $field => $attachment) {
				if (isset($attachment['skipSave'])) {
					$attachment['stopSave'] = $attachment['skipSave'];
				}

				$this->_attachments[$model->alias][$field] = $attachment + $this->_defaults;
			}
		}
	}

	/**
	 * Deletes any files that have been attached to this model.
	 *
	 * @access public
	 * @param Model $model
	 * @return boolean
	 */
	public function beforeDelete($model) {
		if (empty($model->id)) {
			return false;
		}

		$data = $model->read(null, $model->id);

		if (!empty($data[$model->alias])) {
			foreach ($data[$model->alias] as $field => $value) {
				if (strpos($value, self::AS3_DOMAIN) !== false) {
					$this->S3Transfer->delete($value);
					
				} else {
					if (!$this->uploader->delete($value)) {
						@unlink($value);
					}
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
	public function beforeSave($model) {
		if (empty($model->data[$model->alias])) {
			return true;
		}

		foreach ($model->data[$model->alias] as $field => $file) {
			if (empty($this->_attachments[$model->alias][$field])) {
				continue;
			}

			$attachment = $this->_attachments[$model->alias][$field];
			$options = array();
			$s3 = false;
			
			if (is_string($file) && !empty($file)) {
				$attachment['importFrom'] = $file;
			}

			// Should we continue if a file error'd during upload?
			if ((isset($file['error']) && $file['error'] == UPLOAD_ERR_NO_FILE) || (is_string($file) && empty($attachment['importFrom']))) {
				if ($attachment['stopSave']) {
					return false;
				} else {
					unset($model->data[$model->alias][$attachment['dbColumn']]);
					continue;
				}
			}

			// Amazon S3
			if (!empty($attachment['s3'])) {
				if (!empty($attachment['s3']['bucket']) && !empty($attachment['s3']['accessKey']) && !empty($attachment['s3']['secretKey'])) {
					$this->S3Transfer->bucket = $attachment['s3']['bucket'];
					$this->S3Transfer->accessKey = $attachment['s3']['accessKey'];
					$this->S3Transfer->secretKey = $attachment['s3']['secretKey'];

					if (isset($attachment['s3']['useSsl'])) {
						$this->S3Transfer->useSsl = (bool)$attachment['s3']['useSsl'];
					}

					$this->S3Transfer->startup($model);
					$s3 = true;
				} else {
					trigger_error('Uploader.Attachment::beforeSave(): To use the S3 transfer, you must supply an accessKey, secretKey and bucket.', E_USER_WARNING);
				}
			}

			// Uploader
			if (!empty($attachment['baseDir'])) {
				$this->uploader->baseDir = $attachment['baseDir'];
			}

			if (!empty($attachment['uploadDir'])) {
				$this->uploader->uploadDir = $attachment['uploadDir'];
			}

			if (is_numeric($attachment['maxNameLength']) && $attachment['maxNameLength'] > 0) {
				$this->uploader->maxNameLength = $attachment['maxNameLength'];
			}

			if (!empty($attachment['overwrite'])) {
				$options['overwrite'] = $attachment['overwrite'];
			}

			if (!empty($attachment['name'])) {
				$options['name'] = $attachment['name'];
			}

			// Upload or import the file and attach to model data
			if ($upload = $this->upload($field, $attachment, $options)) {
				$basePath = ($s3) ? $this->S3Transfer->transfer($upload['path']) : $upload['path'];

				$model->data[$model->alias][$attachment['dbColumn']] = $basePath;
				$this->_attached[$field][$attachment['dbColumn']] = $basePath;

				// Apply transformations
				if (!empty($attachment['transforms'])) {
					foreach ($attachment['transforms'] as $method => $options) {
						if (isset($options['method'])) {
							$method = $options['method'];
							unset($options['method']);
						}

						if (!method_exists($this->uploader, $method)) {
							trigger_error(sprintf('Uploader.Attachment::beforeSave(): "%s" is not a defined transformation method.', $method), E_USER_WARNING);
							return false;
						}

						if ($path = $this->uploader->{$method}($options)) {
							if ($s3) {
								$path = $this->S3Transfer->transfer($path);
							}

							$model->data[$model->alias][$options['dbColumn']] = $path;
							$this->_attached[$field][$options['dbColumn']] = $path;

							// Delete original if same column name and are not the same file
							// Which can happen if 'append' => '' is defined in the options
							if ($options['dbColumn'] == $attachment['dbColumn'] && $basePath != $model->data[$model->alias][$attachment['dbColumn']]) {
								if ($s3) {
									$this->S3Transfer->delete($basePath);
								} else {
									$this->uploader->delete($basePath);
								}
							}
						} else {
							$this->deleteAttached($field);
							$model->validationErrors[$field] = sprintf(__('An error occured during "%s" transformation!', true), $method);
							return false;
						}
					}
				}

				if (!empty($attachment['metaColumns'])) {
					foreach ($attachment['metaColumns'] as $field => $dbCol) {
						if (isset($upload[$field])) {
							$model->data[$model->alias][$dbCol] = $upload[$field];
						}
					}
				}
			} else {
				$model->validationErrors[$field] = __('There was an error attaching this file!', true);
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Delete all attached images if attaching fails midway.
	 *
	 * @access public
	 * @param string $file
	 * @return void
	 */
	public function deleteAttached($file) {
		if (!empty($this->_attached[$file])) {
			foreach ($this->_attached[$file] as $column => $path) {
				if (strpos($path, self::AS3_DOMAIN) !== false) {
					$this->S3Transfer->delete($path);
				} else {
					$this->uploader->delete($path);
				}
			}
		}
	}

	/**
	 * Applies dynamic settings to an attachment.
	 *
	 * @access public
	 * @param string $model
	 * @param string $file
	 * @param array $settings
	 * @return void
	 */
	public function update($model, $file, $settings) {
		if (isset($this->_attachments[$model][$file])) {
			$this->_attachments[$model][$file] = $settings + $this->_attachments[$model][$file];
		}
	}

	/**
	 * Attempt to upload a file via remote import, file system import or standard upload.
	 *
	 * @access public
	 * @param string $field
	 * @param array $attachment
	 * @param array $options
	 * @return array
	 */
	public function upload($field, $attachment, $options) {
		if (!empty($attachment['importFrom'])) {
			if (preg_match('/(http|https)/', $attachment['importFrom'])) {
				return $this->uploader->importRemote($attachment['importFrom'], $options);

			} else {
				return $this->uploader->import($attachment['importFrom'], $options);
			}
		}

		return $this->uploader->upload($field, $options);
	}

}
