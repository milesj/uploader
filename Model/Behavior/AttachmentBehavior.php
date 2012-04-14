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

App::import('Vendor', 'Uploader.S3');
App::import('Vendor', 'Uploader.Uploader');

class AttachmentBehavior extends ModelBehavior {

	/**
	 * AS3 domain snippet.
	 */
	const AS3_DOMAIN = 's3.amazonaws.com';
	
	/**
	 * Uploader instance.
	 * 
	 * @access public
	 * @var Uploader
	 */
	public $uploader = null;
	
	/**
	 * S3 instance.
	 * 
	 * @access public
	 * @var S3
	 */
	public $s3 = null;

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
		'name' => '',
		'baseDir' => '',
		'uploadDir' => '',
		'append' => '',
		'prepend' => '',
		'dbColumn' => 'uploadPath',
		'importFrom' => '',
		'defaultPath' => '',
		'maxNameLength' => null,
		'overwrite' => false,			// Overwrite a file with the same name if it exists
		'stopSave' => true,				// Stop model save() on form upload error
		'allowEmpty' => true,			// Allow an empty file upload to continue
		'saveAsFilename' => false,		// If true, will only save the filename and not relative path
		'transforms' => array(),
		's3' => array(
			'accessKey' => '',
			'secretKey' => '',
			'ssl' => true,
			'bucket' => '',
			'path' => ''
		),
		'metaColumns' => array(
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
	public function setup($model, $settings = array()) {
		$this->uploader = new Uploader();
		
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
	 * @param boolean $cascade
	 * @return boolean
	 */
	public function beforeDelete($model, $cascade = true) {
		if (empty($model->id)) {
			return false;
		}

		$data = $model->read(null, $model->id);
		$columns = $this->_columns[$model->alias];

		if (!empty($data[$model->alias])) {
			foreach ($data[$model->alias] as $column => $value) {
				if (isset($columns[$column])) {
					$attachment = $this->_attachments[$model->alias][$columns[$column]];
					
					$this->uploader->setup($attachment);
					$this->s3 = $this->s3($attachment['s3']);

					$this->delete($value);
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
			$data = array();
			
			// Not a form upload, so lets treat it as an import
			if (is_string($file) && !empty($file)) {
				$attachment['importFrom'] = $file;
			}

			// Should we continue if a file threw errors during upload?
			if ((isset($file['error']) && $file['error'] == UPLOAD_ERR_NO_FILE) || (is_string($file) && empty($attachment['importFrom']))) {
				if ($attachment['stopSave'] && !$attachment['allowEmpty']) {
					return false;
				} else {
					unset($model->data[$model->alias][$attachment['dbColumn']]);
					continue;
				}
			}

			// Save model method for formatting function
			if (!empty($attachment['name']) && method_exists($model, $attachment['name'])) {
				$attachment['name'] = array($model, $attachment['name']);
			}

			// Setup instances
			$this->uploader->setup($attachment);
			$this->s3 = $this->s3($attachment['s3']);

			// Upload or import the file and attach to model data
			$uploadResponse = $this->upload($file, $attachment, array(
				'overwrite' => $attachment['overwrite'],
				'name' => $attachment['name'],
				'append' => $attachment['append'],
				'prepend' => $attachment['prepend']
			));
			
			if (empty($uploadResponse)) {
				return $model->invalidate($field, __d('uploader', 'There was an error uploading this file, please try again.'));
			}
			
			$basePath = $this->transfer($uploadResponse['path']);
			$data[$attachment['dbColumn']] = ($attachment['saveAsFilename'] && $this->s3 === null) ? basename($basePath) : $basePath;
					
			$toDelete = array();
			$lastPath = $basePath;

			// Apply image transformations
			if (!empty($attachment['transforms'])) {
				foreach ($attachment['transforms'] as $options) {
					$method = $options['method'];

					if (!method_exists($this->uploader, $method)) {
						trigger_error(sprintf('Uploader.Attachment::beforeSave(): "%s" is not a defined transformation method.', $method), E_USER_WARNING);
						return false;
					}

					$transformResponse = $this->uploader->{$method}($options);
					
					// Rollback uploaded files if one fails
					if (empty($transformResponse)) {
						foreach ($data as $path) {
							$this->delete($path);
						}

						return $model->invalidate($field, __d('uploader', 'An error occured during image %s transformation.', $method));
					}
					
					// Transform successful
					$transformPath = $this->transfer($transformResponse);
					$data[$options['dbColumn']] = ($attachment['saveAsFilename'] && $this->s3 === null) ? basename($transformPath) : $transformPath;

					// Delete original if same column name and transform name are not the same file
					if ($options['dbColumn'] == $attachment['dbColumn'] && $lastPath != $transformPath) {
						$toDelete[] = $lastPath;
					}
					
					$lastPath = $transformPath;
				}
			}
			
			// Delete old files if replacing them
			if ($toDelete) {
				foreach ($toDelete as $deleteFile) {
					$this->delete($deleteFile);
				}
			}

			// Apply meta columns
			if (!empty($attachment['metaColumns'])) {
				foreach ($attachment['metaColumns'] as $field => $column) {
					if (isset($uploadResponse[$field]) && !empty($column)) {
						$data[$column] = $uploadResponse[$field];
					}
				}
			}

			// Reset S3 and delete original files
			if ($this->s3 !== null) {
				foreach ($this->s3->uploads as $path) {
					$this->delete($path);
				}
				
				$this->s3 = null;
			}

			// Merge upload data with model data
			$model->data[$model->alias] = $data + $model->data[$model->alias];
		}
		
		return true;
	}
	
	/**
	 * Delete a file from Amazon S3 or locally.
	 * 
	 * @access public
	 * @param string $path
	 * @return boolean
	 */
	public function delete($path) {
		if (strpos($path, self::AS3_DOMAIN) !== false && $this->s3 !== null) {
			return $this->s3->deleteObject($this->s3->bucket, $this->s3->path . basename($path));
		}
		
		return $this->uploader->delete($path);
	}

	/**
	 * Return an S3 instance.
	 * 
	 * @access public
	 * @param array $settings
	 * @return S3 
	 */
	public function s3(array $settings) {
		if (empty($settings['accessKey']) || empty($settings['secretKey'])) {
			return null;
		}
		
		$ssl = isset($settings['useSsl']) ? $settings['useSsl'] : $settings['ssl'];
		
		$s3 = new S3($settings['accessKey'], $settings['secretKey'], (bool) $ssl);
		$s3->bucket = $settings['bucket'];
		$s3->path = trim($settings['path'], '/') . '/';
		$s3->uploads = array();
		
		return $s3;
	}

	/**
	 * Attempt to upload a file via remote import, file system import or standard upload.
	 *
	 * @access public
	 * @param string|array $file
	 * @param array $attachment
	 * @param array $options
	 * @return array
	 */
	public function upload($file, $attachment, $options) {
		if (!empty($attachment['importFrom'])) {
			if (preg_match('/(http|https)/', $attachment['importFrom'])) {
				return $this->uploader->importRemote($attachment['importFrom'], $options);

			} else {
				return $this->uploader->import($attachment['importFrom'], $options);
			}
		}

		return $this->uploader->upload($file, $options);
	}

	/**
	 * Transfer an object to the S3 storage bucket.
	 *
	 * @access public
	 * @param string $path
	 * @return string
	 */
	public function transfer($path) {
		if ($this->s3 === null) {
			return $path;
		}
		
		$name = $this->s3->path . basename($path);
		$bucket = $this->s3->bucket;

		if ($this->s3->putObjectFile($this->uploader->formatPath($path), $bucket, $name, S3::ACL_PUBLIC_READ)) {
			$this->s3->uploads[] = $path;
			
			return sprintf('http://%s.%s/%s', $bucket, self::AS3_DOMAIN, $name);
		}
		
		return $path;
	}

}
