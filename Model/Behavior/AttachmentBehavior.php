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

App::import('Vendor', 'Uploader.S3');
App::import('Vendor', 'Uploader.Uploader');

class AttachmentBehavior extends ModelBehavior {

	/**
	 * AS3 domain snippet.
	 */
	const AS3_DOMAIN = 's3.amazonaws.com';

	/**
	 * All user defined attachments; images => model.
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
	 * @param Model $model
	 * @param array $settings
	 * @return void
	 */
	public function setup($model, array $settings = array()) {
		if (!empty($settings)) {
			foreach ($settings as $field => $attachment) {
				if (isset($attachment['skipSave'])) {
					$attachment['stopSave'] = $attachment['skipSave'];
				}
				
				$attachment = $attachment + $this->_defaults;
				$columns = array($attachment['dbColumn'] => $field);
				
				if (!empty($attachment['transforms'])) {
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
	 * @return boolean
	 */
	public function beforeDelete($model) {
		if (empty($model->id)) {
			return false;
		}

		$data = $model->read(null, $model->id);
		$columns = $this->_columns[$model->alias];

		if (!empty($data[$model->alias])) {
			foreach ($data[$model->alias] as $column => $value) {
				if (isset($columns[$column])) {
					$attachment = $this->_attachments[$model->alias][$columns[$column]];

					if (strpos($value, self::AS3_DOMAIN) !== false && !empty($attachment['s3'])) {
						$this->getS3($attachment['s3'])->deleteObject($attachment['s3']['bucket'], basename($value));
					} else {
						$this->getUploader($attachment, true)->delete($value);
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
			$uploaded = array();
			$data = array();
			
			// Not a form upload, so lets treat it as an import
			if (is_string($file) && !empty($file)) {
				$attachment['importFrom'] = $file;
			}

			// Should we continue if a file threw errors during upload?
			if ((isset($file['error']) && $file['error'] == UPLOAD_ERR_NO_FILE) || (is_string($file) && empty($attachment['importFrom']))) {
				if ($attachment['stopSave']) {
					return false;
				} else {
					unset($model->data[$model->alias][$attachment['dbColumn']]);
					continue;
				}
			}

			// Get instances
			$this->uploader = $this->getUploader($attachment);
			$this->s3 = $this->getS3($attachment['s3']);

			// Gather options for uploading
			$bucket = isset($attachment['s3']['bucket']) ? $attachment['s3']['bucket'] : null;
			$baseOptions = array(
				'overwrite' => $attachment['overwrite'],
				'name' => $attachment['name']
			);

			// Upload or import the file and attach to model data
			if ($uploadResponse = $this->upload($field, $attachment, $baseOptions)) {
				$basePath = $this->transfer($uploadResponse['path'], $bucket);

				$data[$attachment['dbColumn']] = $basePath;
				$uploaded[$attachment['dbColumn']] = $basePath;

				// Apply image transformations
				if (!empty($attachment['transforms'])) {
					foreach ($attachment['transforms'] as $transformOptions) {
						$method = $transformOptions['method'];

						if (!method_exists($this->uploader, $method)) {
							trigger_error(sprintf('Uploader.Attachment::beforeSave(): "%s" is not a defined transformation method.', $method), E_USER_WARNING);
							return false;
						}

						if ($transformPath = $this->uploader->{$method}($transformOptions)) {
							$transformPath = $this->transfer($transformPath, $bucket);

							$data[$transformOptions['dbColumn']] = $transformPath;
							$uploaded[$transformOptions['dbColumn']] = $transformPath;

							// Delete original if same column name and are not the same file
							// Which can happen if 'append' => '' is defined in the options
							if ($transformOptions['dbColumn'] == $attachment['dbColumn'] && $basePath != $model->data[$model->alias][$attachment['dbColumn']]) {
								if ($this->s3 !== null) {
									$this->s3->deleteObject($bucket, basename($transformPath));
								} else {
									$this->uploader->delete($basePath);
								}
							}
						} else {
							// Rollback attached files
							$this->deleteAttached($uploaded, $bucket);
							
							$model->validationErrors[$field] = __d('uploader', 'An error occured during "%s" transformation!', $method);
							return false;
						}
					}
				}

				// Apply meta columns
				if (!empty($attachment['metaColumns'])) {
					foreach ($attachment['metaColumns'] as $field => $column) {
						if (isset($uploadResponse[$field])) {
							$data[$column] = $uploadResponse[$field];
						}
					}
				}
				
				$model->data[$model->alias] = $data + $model->data[$model->alias];
				
			} else {
				$model->validationErrors[$field] = __d('uploader', 'There was an error attaching this file!');
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Delete all attached images if attaching fails midway.
	 *
	 * @access public
	 * @param array $files
	 * @param string $bucket
	 * @return void
	 */
	public function deleteAttached($files, $bucket) {
		foreach ($files as $column => $path) {
			if (strpos($path, self::AS3_DOMAIN) !== false) {
				$this->s3->deleteObject($bucket, basename($path));
			} else {
				$this->uploader->delete($path);
			}
		}
	}
	
	/**
	 * Return an S3 instance.
	 * 
	 * @access public
	 * @param array $settings
	 * @return S3 
	 */
	public function getS3(array $settings) {
		if (isset($settings['accessKey']) && isset($settings['accessKey'])) {
			$ssl = isset($settings['useSsl']) ? $settings['useSsl'] : false;
			
			return new S3($settings['accessKey'], $settings['secretKey'], $ssl);
		}
		
		return null;
	}
	
	/**
	 * Return an Uploader instance.
	 * 
	 * @access public
	 * @param array $settings
	 * @param boolean $exit
	 * @return Uploader 
	 */
	public function getUploader(array $settings, $exit = false) {
		$clean = array();
		
		if (!empty($settings['baseDir'])) {
			$clean['baseDir'] = $settings['baseDir'];
		}

		if (!empty($settings['uploadDir'])) {
			$clean['uploadDir'] = $settings['uploadDir'];
		}

		if (is_numeric($settings['maxNameLength']) && $settings['maxNameLength'] > 0) {
			$clean['maxNameLength'] = $settings['maxNameLength'];
		}
		
		return new Uploader($clean, $exit);
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

	/**
	 * Transfer an object to the S3 storage bucket.
	 *
	 * @access public
	 * @param string $path
	 * @param string $bucket
	 * @return string
	 */
	public function transfer($path, $bucket) {
		if ($this->s3 !== null) {
			$name = basename($path);

			if ($this->s3->putObjectFile($path, $bucket, $name, S3::ACL_PUBLIC_READ)) {
				$this->uploader->delete($path);
				
				return sprintf('http://%s.s3.amazonaws.com/%s', $bucket, $name);
			}
		}
		
		return $path;
	}

}
