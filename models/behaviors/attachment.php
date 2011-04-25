<?php
/** 
* Attachment Behavior
*
* A CakePHP Behavior that attaches a file to a model, and uploads automatically, then stores a value in the database.
*
* @author      Miles Johnson - http://milesj.me
* @copyright   Copyright 2006-2011, Miles Johnson, Inc.
* @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
* @link        http://milesj.me/resources/script/uploader-plugin
*/

App::import('Component', array('Uploader.Uploader', 'Uploader.S3Transfer'));

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
		'baseDir'		=> '',
		'uploadDir'		=> '',
		'dbColumn'		=> 'uploadPath',
		'defaultPath'	=> '',
		'name'			=> '',
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
     * @param object $Model
     * @param array $settings
     * @return boolean
     */
    public function setup($Model, $settings = array()) {
        $this->Uploader = new UploaderComponent();
        $this->S3Transfer = new S3TransferComponent();

        if (!empty($settings) && is_array($settings)) {
            foreach ($settings as $field => $attachment) {
				if (isset($attachment['skipSave'])) {
					$attachment['stopSave'] = $attachment['skipSave'];
				}
				
                $this->_attachments[$Model->alias][$field] = $attachment + $this->_defaults;
            }
        }
    }

    /**
     * Deletes any files that have been attached to this model.
     *
     * @access public
     * @param object $Model
     * @return boolean
     */
    public function beforeDelete($Model) {
		if (empty($Model->id)) {
			return false;
		}
		
        $data = $Model->read(null, $Model->id);

        if (!empty($data[$Model->alias])) {
            foreach ($data[$Model->alias] as $field => $value) {
                if (strpos($value, self::AS3_DOMAIN) !== false) {
                    $this->S3Transfer->delete($value);
                } else if (file_exists($value)) {
                    $this->Uploader->delete($value);
                }
            }
        }

        return true;
    }

    /**
     * Before saving the data, try uploading the image, if successful save to database.
     *
     * @access public
     * @param object $Model
     * @return boolean
     */
    public function beforeSave($Model) {
        $this->Uploader->initialize($Model);
        $this->Uploader->startup($Model);

        if (!empty($Model->data[$Model->alias])) {
            foreach ($Model->data[$Model->alias] as $file => $data) {
                if (isset($this->_attachments[$Model->alias][$file])) {
                    $attachment = $this->_attachments[$Model->alias][$file];
                    $options = array();
                    $s3 = false;

					// Let the save work even if the image is empty.
					// If the image should be required, use the FileValidation behavior.
					if (empty($data['tmp_name'])) {
						if (!empty($attachment['defaultPath'])) {
							$Model->data[$Model->alias][$attachment['dbColumn']] = $attachment['defaultPath'];
						}

						continue;
					}

					// Should we continue if a file error'd during upload?
					if ($data['error'] == UPLOAD_ERR_NO_FILE) {
						if ($attachment['stopSave']) {
							return false;
						} else {
							continue;
						}
					}

                    // S3
                    if (!empty($attachment['s3'])) {
                        if (!empty($attachment['s3']['bucket']) && !empty($attachment['s3']['accessKey']) && !empty($attachment['s3']['secretKey'])) {
                            $this->S3Transfer->bucket = $attachment['s3']['bucket'];
                            $this->S3Transfer->accessKey = $attachment['s3']['accessKey'];
                            $this->S3Transfer->secretKey = $attachment['s3']['secretKey'];

                            if (isset($attachment['s3']['useSsl']) && is_bool($attachment['s3']['useSsl'])) {
                                $this->S3Transfer->useSsl = $attachment['s3']['useSsl'];
                            }

                            $this->S3Transfer->startup($Model);
                            $s3 = true;
                        } else {
                            trigger_error('Uploader.Attachment::beforeSave(): To use the S3 transfer, you must supply an accessKey, secretKey and bucket.', E_USER_WARNING);
                        }
                    }

                    // Uploader
                    if (!empty($attachment['baseDir'])) {
                        $this->Uploader->baseDir = $attachment['baseDir'];
                    }

                    if (!empty($attachment['uploadDir'])) {
                        $this->Uploader->uploadDir = $attachment['uploadDir'];
                    }

                    if (is_numeric($attachment['maxNameLength'])) {
                        $this->Uploader->maxNameLength = $attachment['maxNameLength'];
                    }

                    if (is_bool($attachment['overwrite'])) {
                        $options['overwrite'] = $attachment['overwrite'];
                    }

                    if (!empty($attachment['name'])) {
                        $options['name'] = $attachment['name'];
                    }

                    // Upload file and attache to model data
					$fileData = $this->Uploader->upload($file, $options);

                    if (!empty($fileData)) {
                        $basePath = $fileData['path'];

                        if ($s3) {
                            $basePath = $this->S3Transfer->transfer($basePath);
                        }

                        $Model->data[$Model->alias][$attachment['dbColumn']] = $basePath;
                        $this->_attached[$file][$attachment['dbColumn']] = $basePath;

                        // Apply transformations
                        if (!empty($attachment['transforms'])) {
                            foreach ($attachment['transforms'] as $method => $options) {
                                if (is_array($options) && isset($options['dbColumn'])) {
                                    if (isset($options['method'])) {
                                        $method = $options['method'];
                                        unset($options['method']);
                                    }

                                    if (!method_exists($this->Uploader, $method)) {
                                        trigger_error('Uploader.Attachment::beforeSave(): "'. $method .'" is not a defined transformation method.', E_USER_WARNING);
                                        return false;
                                    }

									$path = $this->Uploader->{$method}($options);

                                    if (!empty($path)) {
                                        if ($s3) {
                                            $path = $this->S3Transfer->transfer($path);
                                        }

                                        $Model->data[$Model->alias][$options['dbColumn']] = $path;
                                        $this->_attached[$file][$options['dbColumn']] = $path;

                                        // Delete original if same column name
                                        if ($options['dbColumn'] == $attachment['dbColumn']) {
                                            if ($s3) {
                                                $this->S3Transfer->delete($basePath);
                                            } else {
                                                $this->Uploader->delete($basePath);
                                            }
                                        }

                                    } else {
                                        $this->deleteAttached($file);
                                        $Model->validationErrors[$file] = sprintf(__('An error occured during "%s" transformation!', true), $method);
                                        return false;
                                    }
                                }
                            }
                        }

                        if (!empty($attachment['metaColumns'])) {
                            foreach ($attachment['metaColumns'] as $field => $dbCol) {
                                if (isset($fileData[$field])) {
                                    $Model->data[$Model->alias][$dbCol] = $fileData[$field];
                                }
                            }
                        }
						
                    } else {
                        $Model->validationErrors[$file] = __('There was an error attaching this file!', true);
                        return false;
                    }
                }
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
                    $this->Uploader->delete($path);
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

}
