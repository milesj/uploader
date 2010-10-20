<?php
/** 
* File Validation Behavior
*
* A CakePHP Behavior that adds validation model rules to file uploading.
*
* @author      Miles Johnson - http://milesj.me
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
        'minWidth'  => null,
        'minHeight' => null,
        'maxWidth'  => null,
        'maxHeight' => null,
        'filesize'  => null,
        'extension' => null,
        'required'  => true
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
        'extension' => array(
            'rule' => array('extension'),
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
     * @param object $Model
     * @param array $settings
     * @return void
     */
    public function setup(&$Model, $settings = array()) {
        if (!empty($settings) && is_array($settings)) {
            foreach ($settings as $field => $options) {
                $this->__settings[$Model->alias][$field] = $options + array('required' => true);
            }
        }
    }

    /**
     * Validates an image filesize. Default max size is 5 MB.
     *
     * @access public
     * @param object $Model
     * @param array $data
     * @param int $size
     * @return boolean
     */
    public function filesize(&$Model, $data, $size = 5242880) {
        if (empty($size) || !is_numeric($size)) {
            $size = 5242880;
        }

        foreach ($data as $fieldName => $field) {
            if (!$this->__settings[$Model->alias][$fieldName]['required'] && empty($field['tmp_name'])) {
                return true;
            } else if (empty($field['tmp_name'])) {
                return false;
            }

            return ($field['size'] <= $size);
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
        return $this->_validateImage($Model, $data, 'maxHeight', $size);
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
        return $this->_validateImage($Model, $data, 'maxWidth', $size);
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
        return $this->_validateImage($Model, $data, 'minHeight', $size);
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
        return $this->_validateImage($Model, $data, 'minWidth', $size);
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
    public function extension(&$Model, $data, $allowed = array()) {
        foreach ($data as $fieldName => $field) {
            if (!$this->__settings[$Model->alias][$fieldName]['required'] && empty($field['tmp_name'])) {
                return true;
            } else if (empty($field['tmp_name'])) {
                return false;
            } else {
                $ext = mb_strtolower(trim(mb_strrchr($field['name'], '.'), '.'));
            }

            if (!empty($allowed) && is_array($allowed)) {
                return in_array($ext, $allowed);
            } else {
                if (empty($this->__mimeTypes)) {
                    $this->__mimeTypes = Configure::read('Uploader.mimeTypes');
                }
                
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

                if (!$validExt && !$validMime) {
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
            $required = $this->__settings[$Model->alias][$fieldName]['required'];

            if (is_array($required)) {
                $required = $required['value'];
            }

            if ($required && empty($field['tmp_name'])) {
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
        if (!empty($this->__settings[$Model->alias])) {
            foreach ($this->__settings[$Model->alias] as $field => $rules) {
                $validations = array();

                foreach ($rules as $rule => $setting) {
                    $set = $this->__validations[$rule];

                    if (is_array($setting) && !isset($setting[0])) {
                        if (!empty($setting['error'])) {
                            $set['message'] = $setting['error'];
                        }

                        switch ($rule) {
                            case 'required':
                                $set['rule'] = array($rule);
                            break;
                            case 'extension':
                                $set['rule'] = array($rule, (array)$setting['value']);
                            break;
                            default:
                                $set['rule'] = array($rule, (int)$setting['value']);
                            break;
                        }
                    } else {
                        $set['rule'] = array($rule, $setting);
                    }

                    if (isset($rules['required'])) {
                        if (is_array($rules['required'])) {
                            $set['allowEmpty'] = !(bool)$rules['required']['value'];
                        } else {
                            $set['allowEmpty'] = !(bool)$rules['required'];
                        }
                    }

                    $validations[$rule] = $set;
                }

                if (!empty($validations)) {
                    if (!empty($Model->validate[$field])) {
                        $validations = $validations + $Model->validate[$field];
                    }

                    $Model->validate[$field] = $validations;
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
     * @param int $size
     * @return boolean
     */
    protected function _validateImage(&$Model, $data, $type, $size = 100) {
        foreach ($data as $fieldName => $field) {
            if (!$this->__settings[$Model->alias][$fieldName]['required'] && empty($field['tmp_name'])) {
                return true;
            } else if (empty($field['tmp_name'])) {
                return false;
            }

            $file = getimagesize($field['tmp_name']);

            if (!$file) {
                return false;
            }

            $width = $file[0];
            $height = $file[1];

            switch ($type) {
                case 'maxWidth':    return ($width <= $size); break;
                case 'maxHeight':   return ($height <= $size); break;
                case 'minWidth':    return ($width >= $size); break;
                case 'minHeight':   return ($height >= $size); break;
            }
        }

        return true;
    }

}
