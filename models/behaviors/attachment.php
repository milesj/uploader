<?php
/** 
 * attachment.php
 *
 * A CakePHP Behavior that attaches a file to a model, and uploads automatically, then stores a value in the database.
 *
 * @author 		Miles Johnson - www.milesj.me
 * @copyright	Copyright 2006-2009, Miles Johnson, Inc.
 * @license 	http://www.opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @package		Uploader Plugin - Attachment Behavior
 * @link		www.milesj.me/resources/script/uploader-plugin
 */
 
App::import('Component', 'Uploader.Uploader');

class AttachmentBehavior extends ModelBehavior { 

	/**
	 * Files that have been uploaded / attached; used for fallback functions
	 * @access private
	 * @var array
	 */
	private $__attached = array();
	
	/**
	 * All user defined attachments; images => model
	 * @access private
	 * @var array
	 */
	private $__attachments = array();
	
	/**
	 * The default settings for attachments
	 * @access private
	 * @var array
	 */
	private $__defaults = array(
		'uploadDir' 	=> null,
		'dbColumn'		=> 'uploadPath',
		'maxNameLength' => null,
		'overwrite'		=> true,
		'name'			=> null,
		'transforms'	=> array()
	);

	/**
	 * Initialize uploader and save attachments
	 * @access public
	 * @uses UploaderComponent
	 * @param object $Model
	 * @param array $settings
	 * @return boolean
	 */
	public function setup(&$Model, $settings = array()) {
		$this->Uploader = new UploaderComponent();
		
		if (!empty($settings) && is_array($settings)) {
			foreach ($settings as $field => $attachment) {
				$this->__attachments[$Model->alias][$field] = array_merge($this->__defaults, $attachment);
			}
		}
	}
	
	/**
	 * Deletes any files that have been attached to this model
	 * @access public
	 * @param object $Model
	 * @return boolean
	 */
	public function beforeDelete(&$Model) {
		$data = $Model->read(null, $Model->id);
		
		if (!empty($data[$Model->alias])) {
			foreach ($data[$Model->alias] as $field => $value) {
				if (is_file(WWW_ROOT . $value)) {
					$this->Uploader->delete($value);
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Before saving the data, try uploading the image, if successful save to database
	 * @access public
	 * @param object $Model
	 * @return boolean
	 */
	public function beforeSave(&$Model) {
		$this->Uploader->initialize($Model);
		$this->Uploader->startup($Model);
		
		if (!empty($Model->data[$Model->alias])) {
			foreach ($Model->data[$Model->alias] as $file => $data) {
				if (isset($this->__attachments[$Model->alias][$file])) {
					$attachment = $this->__attachments[$Model->alias][$file];
					$options = array();
					
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

					if ($data['error'] == UPLOAD_ERR_NO_FILE) {
						continue;
					}

					// Upload file and attache to model data
					if ($data = $this->Uploader->upload($file, $options)) {
						$Model->data[$Model->alias][$attachment['dbColumn']] = $data['path'];
						$this->__attached[$file][$attachment['dbColumn']] = $data['path'];
						
						// Apply transformations
						if (!empty($attachment['transforms'])) {
							foreach ($attachment['transforms'] as $method => $options) {
								if (is_array($options) && isset($options['dbColumn'])) {
									if (!method_exists($this->Uploader, $method)) {
										trigger_error('Uploader.Attachment::beforeSave(): "'. $method .'" is not a defined transformation method', E_USER_WARNING);
										return false;
									}
									
									if ($path = $this->Uploader->$method($options)) {
										$Model->data[$Model->alias][$options['dbColumn']] = $path;
										$this->__attached[$file][$options['dbColumn']] = $path;
									
										// Delete original if same column name
										if ($options['dbColumn'] == $attachment['dbColumn']) {
											$this->Uploader->delete($data['path']);
										}
										
									} else {
										$this->__deleteAttached($file);
										$Model->validationErrors[$file] = 'An error occured during '. $method .' transformation!';
										return false;
									}
								}
							}
						}
						
					} else {
						$Model->validationErrors[$file] = 'There was an error attaching this file!';
						return false;
					}
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Applies dynamic settings to an attachment
	 * @access public
	 * @param string $model
	 * @param string $file
	 * @param array $settings
	 * @return void
	 */
	public function update($model, $file, $settings) {
		if (isset($this->__attachments[$model][$file])) {
			$this->__attachments[$model][$file] = array_merge($this->__attachments[$model][$file], $settings);
		}
	}
	
	/**
	 * Delete all attached images if attaching fails midway
	 * @access private
	 * @param string $file
	 * @return void
	 */
	private function __deleteAttached($file) {
		if (!empty($this->__attached[$file])) {
			foreach ($this->__attached[$file] as $column => $path) {
				$this->Uploader->delete($path);
			}
		}
	}
	
}
