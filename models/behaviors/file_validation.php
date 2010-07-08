<?php
/** 
 * File Validation Behavior
 *
 * A CakePHP Behavior that adds validation model rules to file uploading.
 *
 * @author      Miles Johnson - www.milesj.me
 * @copyright   Copyright 2006-2010, Miles Johnson, Inc.
 * @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link        http://milesj.me/resources/script/uploader-plugin
 */

Configure::load('Uploader.config');

class FileValidationBehavior extends ModelBehavior {

	/**
	 * Default settings.
	 *
	 * @access private
	 * @var array
	 */ 
	private $__defaults = array(
		'dimension' => array(
			'width' => null,
			'height' => null
		),
        'minWidth'  => null,
        'minHeight' => null,
        'maxWidth'  => null,
        'maxHeight' => null,
		'filesize'  => null,
		'extension' => null,
		'optional'  => false
	);	
	
	/**
	 * The accepted file mime types; imported from config.
	 *
	 * @access private
	 * @var array
	 */
	private $__mimeTypes = array();
	
	/**
	 * Current settings.
	 *
	 * @access private
	 * @var array
	 */
	private $__settings = array();  
	
	/**
	 * Default list of validation sets.
	 *
	 * @access private
	 * @var array
	 */
	private $__validations = array( 
		'dimension' => array(
			'rule' => array('dimension'),
			'message' => 'Your dimensions are incorrect'
		),
		'minWidth' => array(
			'rule' => array('minWidth'),
			'message' => 'Your image width is too small'
		),
		'minHeight' => array(
			'rule' => array('minHeight'),
			'message' => 'Your image height is too small'
		),
		'maxWidth' => array(
			'rule' => array('maxWidth'),
			'message' => 'Your image width is too large'
		),
		'maxHeight' => array(
			'rule' => array('maxHeight'),
			'message' => 'Your image height is too large'
		),
		'filesize' => array(
			'rule' => array('filesize'),
			'message' => 'Your filesize is too large'
		),
		'mimetype' => array(
			'rule' => array('mimetype'),
			'message' => 'Your file type is not allowed'
		),
		'required' => array(
			'rule' => array('required'),
			'message' => 'This file is required'
		)
	); 
	
	/**
	 * Setup the validation and model settings.
	 *
	 * @access public
	 * @uses UploaderConfig
	 * @param object $Model
	 * @param array $settings
	 * @return void
	 */
	public function setup(&$Model, $settings = array()) {
		if (!empty($settings) && is_array($settings)) {
			foreach ($settings as $field => $options) {
				$this->__settings[$Model->alias][$field] = $options + $this->__defaults;
			}
		}
	}
	
	/**
	 * Checks an image dimensions.
	 *
	 * @access public
	 * @param object $Model
	 * @param array $data
	 * @param int $width
	 * @param int $height
	 * @return boolean
	 */
	public function dimension(&$Model, $data, $width = 100, $height = 100) {
		return $this->_validateImage($Model, $data, 'dimension', $width, $height);
	}
	
	/**
	 * Validates an image filesize. Default max size is 5 MB.
	 *
	 * @access public
	 * @param object $Model
	 * @param array $data
	 * @param int $maxSize
	 * @return boolean
	 */
	public function filesize(&$Model, $data, $maxSize = null) {
		if (empty($maxSize) || !is_numeric($maxSize)) {
			$maxSize = 5242880;
		}
		
		foreach ($data as $fieldName => $field) {
			if ($this->__settings[$Model->alias][$fieldName]['optional'] === true && empty($field['tmp_name'])) {
				return true;
			} else if (empty($field['tmp_name'])) {
				return false;
			}
			
            return ($field['size'] <= $maxSize);
		}
		
		return true;
	}

	/**
	 * Checks the maximum image height.
	 *
	 * @access public
	 * @param object $Model
	 * @param array $data
	 * @param int $size
	 * @return boolean
	 */
	public function maxHeight(&$Model, $data, $size = 100) {
		return $this->_validateImage($Model, $data, 'maxHeight', null, $size);
	}

	/**
	 * Checks the maximum image width.
	 *
	 * @access public
	 * @param object $Model
	 * @param array $data
	 * @param int $size
	 * @return boolean
	 */
	public function maxWidth(&$Model, $data, $size = 100) {
		return $this->_validateImage($Model, $data, 'maxWidth', $size, null);
	}

	/**
	 * Checks the minimum image height.
	 *
	 * @access public
	 * @param object $Model
	 * @param array $data
	 * @param int $size
	 * @return boolean
	 */
	public function minHeight(&$Model, $data, $size = 100) {
		return $this->_validateImage($Model, $data, 'minHeight', null, $size);
	}

	/**
	 * Checks the minimum image width.
	 *
	 * @access public
	 * @param object $Model
	 * @param array $data
	 * @param int $size
	 * @return boolean
	 */
	public function minWidth(&$Model, $data, $size = 100) {
		return $this->_validateImage($Model, $data, 'minWidth', $size, null);
	}
	
	/**
	 * Validates the ext and mimetype.
	 *
	 * @access public
	 * @param object $Model
	 * @param array $data
	 * @param array $allowed
	 * @return boolean
	 */
	public function mimetype(&$Model, $data, $allowed = array()) {
		foreach ($data as $fieldName => $field) {
			if ($this->__settings[$Model->alias][$fieldName]['optional'] === true && empty($field['tmp_name'])) {
				return true;
			} else if (empty($field['tmp_name'])) {
				return false;
			} else {
				$ext = mb_strtolower(trim(mb_strrchr($field['name'], '.'), '.'));
			}
			
			if (!empty($allowed) && is_array($allowed)) {
				return in_array($ext, $allowed);
			} else {
                $this->__mimeTypes = Configure::read('Uploader.mimeTypes');
                
				$validExt = false;
				$validMime = false;
			
				foreach ($this->__mimeTypes as $grouping => $mimes) {
					if (isset($mimes[mb_strtolower($ext)])) {
						$validExt = true;
					}
					
					if (in_array(mb_strtolower($ext), $mimes)) {
						$validMime = true;
					}
				}
			
				if ($validExt === false && $validMime === false) {
					return false;
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Makes sure a file field is required and not optional.
	 *
	 * @access public
	 * @param object $Model
	 * @param array $data
	 * @return boolean
	 */
	public function required(&$Model, $data) {
		foreach ($data as $fieldName => $field) {
			if (($this->__settings[$Model->alias][$fieldName]['optional'] === false || $this->__settings[$Model->alias][$fieldName]['optional']['value'] === false) && empty($field['tmp_name'])) {
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Build the validation rules and validate.
	 * 
	 * @access public
	 * @param object $Model
	 * @return boolean
	 */
	public function beforeValidate(&$Model) {
		$this->Model = $Model;
		
		if (!empty($this->__settings)) {
			foreach ($this->__settings as $model => $fields) {
				foreach ($fields as $field => $setting) {
					$validations = array();
				
					// Dimensions
					if (!empty($setting['dimension'])) {
						if (isset($setting['dimension']['width']) || isset($setting['dimension']['height'])) {
							$validations['dimension'] = $this->__validations['dimension'];
							$validations['dimension']['rule'] = array('dimension', $setting['dimension']['width'], $setting['dimension']['height']);
							
							if ($setting['optional'] === true || $setting['optional']['value'] === true) {
								$validations['dimension']['allowEmpty'] = true;
							}	
							
							if (isset($setting['dimension']['error'])) {
								$validations['dimension']['message'] = $setting['dimension']['error'];
							}
						}
					}
					
					// Filesize
					if (!empty($setting['filesize'])) {
						if (isset($setting['filesize']['value'])) {
							$maxFilesize = $setting['filesize']['value'];
						} else if (is_numeric($setting['filesize'])) {
							$maxFilesize = $setting['filesize'];
						} else {
							$maxFilesize = null;
						}
						
						if ($maxFilesize > 0) {
							$validations['filesize'] = $this->__validations['filesize'];
							$validations['filesize']['rule'] = array('filesize', $maxFilesize);
							
							if ($setting['optional'] === true || $setting['optional']['value'] === true) {
								$validations['filesize']['allowEmpty'] = true;
							}	
							
							if (isset($setting['filesize']['error'])) {
								$validations['filesize']['message'] = $setting['filesize']['error'];
							}
						}
					}
					
					// Mimetypes
					if (!empty($setting['extension'])) {
						if (!empty($setting['extension']['value']) && is_array($setting['extension']['value'])) {
							$mimeTypes = $setting['extension']['value'];
						} else if (is_array($setting['extension'])) {
							$mimeTypes = $setting['extension'];
						} else {
							$mimeTypes = null;
						}
						
						if (is_array($mimeTypes)) {
							$validations['mimetype'] = $this->__validations['mimetype'];
							$validations['mimetype']['rule'] = array('mimetype', $mimeTypes);
							
							if ($setting['optional'] === true || $setting['optional']['value'] === true) {
								$validations['mimetype']['allowEmpty'] = true;
							}	
							
							if (isset($setting['extension']['error'])) {
								$validations['mimetype']['message'] = $setting['extension']['error'];
							}
						}
					}
					
					// Required
					if (($setting['optional'] === false) || (is_array($setting['optional']) && $setting['optional']['value'] === false)) {
						$validations['required'] = $this->__validations['required'];
						
						if (isset($setting['optional']['error'])) {
							$validations['required']['message'] = $setting['optional']['error'];
						}
					}
					
					if (!empty($validations) && !empty($this->Model->data[$this->Model->name][$field])) {
						if (!empty($this->Model->validate[$field])) {
							$validations = array_merge($this->Model->validate[$field], $validations);
						}
						
						$this->Model->validate[$field] = $validations;
					}
				}
			}
		}
		
		return true;
	}

    /**
	 * Validates multiple combinations of height and width for an image.
	 *
	 * @access protected
	 * @param object $Model
	 * @param array $data
     * @param string $type
	 * @param int $width
	 * @param int $height
	 * @return boolean
	 */
	protected function _validateImage(&$Model, $data, $type, $width = 100, $height = 100) {
		foreach ($data as $fieldName => $field) {
			if ($this->__settings[$Model->alias][$fieldName]['optional'] === true && empty($field['tmp_name'])) {
				return true;
			} else if (empty($field['tmp_name'])) {
				return false;
            }

            $file = getimagesize($field['tmp_name']);

            if (!$file) {
                return false;
            }

            $w = $file[0];
            $h = $file[1];

            switch ($type) {
                case 'maxWidth':    return ($w <= $width); break;
                case 'maxHeight':   return ($h <= $height); break;
                case 'minWidth':    return ($w >= $width); break;
                case 'minHeight':   return ($h >= $height); break;
                case 'dimensions':
                default:
                    if ($width > 0 && $height > 0) {
                        return ($w <= $width || $h <= $height);
                    } else if ($width > 0 && !$height) {
                        return ($w <= $width);
                    } else if ($height > 0 && !$width) {
                        return ($h <= $height);
                    } else {
                        return false;
                    }
                break;
            }
		}

		return true;
	}
	
}
