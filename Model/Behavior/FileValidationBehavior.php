<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/uploader/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/uploader
 */

App::uses('ModelBehavior', 'Model');

use Transit\Transit;
use Transit\File;
use Transit\Validator\ImageValidator;

/**
 * A CakePHP Behavior that adds validation model rules to file uploading.
 */
class FileValidationBehavior extends ModelBehavior {

    /**
     * Default list of validation sets.
     *
     * @type array
     */
    protected $_defaults = array(
        'width' => array(
            'rule' => array('width'),
            'message' => 'Your image width is invalid; required width is %s'
        ),
        'height' => array(
            'rule' => array('height'),
            'message' => 'Your image height is invalid; required height is %s'
        ),
        'minWidth' => array(
            'rule' => array('minWidth'),
            'message' => 'Your image width is too small; minimum width %s'
        ),
        'minHeight' => array(
            'rule' => array('minHeight'),
            'message' => 'Your image height is too small; minimum height %s'
        ),
        'maxWidth' => array(
            'rule' => array('maxWidth'),
            'message' => 'Your image width is too large; maximum width %s'
        ),
        'maxHeight' => array(
            'rule' => array('maxHeight'),
            'message' => 'Your image height is too large; maximum height %s'
        ),
        'filesize' => array(
            'rule' => array('filesize'),
            'message' => 'Your file size is too large; maximum size %s'
        ),
        'extension' => array(
            'rule' => array('extension'),
            'message' => 'Your file extension is not allowed; allowed extensions: %s'
        ),
        'type' => array(
            'rule' => array('type'),
            'message' => 'Your file type is not allowed; allowed types: %s'
        ),
        'mimeType' => array(
            'rule' => array('mimeType'),
            'message' => 'Your file type is not allowed; allowed types: %s'
        ),
        'required' => array(
            'rule' => array('required'),
            'message' => 'This file is required',
            'on' => 'create',
            'allowEmpty' => true
        )
    );

    /**
     * Generated list of validation rules.
     *
     * @type array
     */
    protected $_validations = array();

    /**
     * Temporary file used for validation only.
     *
     * @type \Transit\File
     */
    protected $_tempFile;

    /**
     * Setup the validation and model settings.
     *
     * @param Model $model
     * @param array $settings
     */
    public function setup(Model $model, $settings = array()) {
        if ($settings) {
            foreach ($settings as $field => $options) {
                $this->settings[$model->alias][$field] = (array) $options + array('required' => true);
            }
        }
    }

    /**
     * Validates an image file size. Default max size is 5 MB.
     *
     * @param Model $model
     * @param array $data
     * @param int $size
     * @return bool
     */
    public function filesize(Model $model, $data, $size = 5242880) {
        return $this->_validate($model, $data, 'size', array($size));
    }

    /**
     * Checks that the image height is exact.
     *
     * @param Model $model
     * @param array $data
     * @param int $size
     * @return bool
     */
    public function height(Model $model, $data, $size) {
        return $this->_validate($model, $data, 'height', array($size));
    }

    /**
     * Checks that the image width is exact.
     *
     * @param Model $model
     * @param array $data
     * @param int $size
     * @return bool
     */
    public function width(Model $model, $data, $size) {
        return $this->_validate($model, $data, 'width', array($size));
    }

    /**
     * Checks the maximum image height.
     *
     * @param Model $model
     * @param array $data
     * @param int $size
     * @return bool
     */
    public function maxHeight(Model $model, $data, $size) {
        return $this->_validate($model, $data, 'maxHeight', array($size));
    }

    /**
     * Checks the maximum image width.
     *
     * @param Model $model
     * @param array $data
     * @param int $size
     * @return bool
     */
    public function maxWidth(Model $model, $data, $size) {
        return $this->_validate($model, $data, 'maxWidth', array($size));
    }

    /**
     * Checks the minimum image height.
     *
     * @param Model $model
     * @param array $data
     * @param int $size
     * @return bool
     */
    public function minHeight(Model $model, $data, $size) {
        return $this->_validate($model, $data, 'minHeight', array($size));
    }

    /**
     * Checks the minimum image width.
     *
     * @param Model $model
     * @param array $data
     * @param int $size
     * @return bool
     */
    public function minWidth(Model $model, $data, $size) {
        return $this->_validate($model, $data, 'minWidth', array($size));
    }

    /**
     * Validates the extension.
     *
     * @param Model $model
     * @param array $data
     * @param array $allowed
     * @return bool
     */
    public function extension(Model $model, $data, array $allowed = array()) {
        return $this->_validate($model, $data, 'ext', array($allowed));
    }

    /**
     * Validates the type, e.g., image.
     *
     * @param Model $model
     * @param array $data
     * @param array $allowed
     * @return bool
     */
    public function type(Model $model, $data, array $allowed = array()) {
        return $this->_validate($model, $data, 'type', array($allowed));
    }

    /**
     * Validates the mime type, e.g., image/jpeg.
     *
     * @param Model $model
     * @param array $data
     * @param array|string $mimeType
     * @return bool
     */
    public function mimeType(Model $model, $data, $mimeType) {
        return $this->_validate($model, $data, 'mimeType', array($mimeType));
    }

    /**
     * Makes sure a file field is required and not optional.
     *
     * @param Model $model
     * @param array $data
     * @param bool $required
     * @return bool
     */
    public function required(Model $model, $data, $required = true) {
        foreach ($data as $value) {
            if ($required && $this->_isEmpty($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build the validation rules and validate.
     *
     * @param Model $model
     * @param array $options
     * @return bool
     */
    public function beforeValidate(Model $model, $options = array()) {
        if (empty($this->settings[$model->alias])) {
            return true;
        }

        foreach ($this->settings[$model->alias] as $field => $rules) {
            $validations = array();

            foreach ($rules as $rule => $setting) {
                $set = $this->_defaults[$rule];

                // Parse out values
                if (!is_array($setting) || !isset($setting['value'])) {
                    $setting = array('value' => $setting);
                }

                switch ($rule) {
                    case 'required':
                        $arg = (bool) $setting['value'];
                    break;
                    case 'type':
                    case 'mimeType':
                    case 'extension':
                        $arg = (array) $setting['value'];
                    break;
                    default:
                        $arg = (int) $setting['value'];
                    break;
                }

                if (!isset($setting['rule'])) {
                    $setting['rule'] = array($rule, $arg);
                }

                if (isset($setting['error'])) {
                    $setting['message'] = $setting['error'];
                    unset($setting['error']);
                }

                unset($setting['value']);

                // Merge settings
                $set = array_merge($set, $setting);

                // Apply validations
                if (is_array($arg)) {
                    $arg = implode(', ', $arg);
                }

                $set['message'] = __d('uploader', $set['message'], $arg);

                $validations[$rule] = $set;
            }

            if ($validations) {
                if (!empty($model->validate[$field])) {
                    $currentRules = $model->validate[$field];

                    // Fix single rule validate
                    if (isset($currentRules['rule'])) {
                        $currentRules = array(
                            $currentRules['rule'] => $currentRules
                        );
                    }

                    $validations = $currentRules + $validations;
                }

                // Remove notEmpty for uploads
                if (isset($model->data[$model->alias][$field]['tmp_name']) && isset($validations['notEmpty'])) {
                    unset($validations['notEmpty']);
                }

                $this->_validations[$field] = $validations;
                $model->validate[$field] = $validations;
            }
        }

        return true;
    }

    /**
     * Delete the temporary file.
     *
     * @param Model $model
     * @return bool
     */
    public function afterValidate(Model $model) {
        if ($this->_tempFile) {
            $this->_tempFile->delete();
        }

        return true;
    }

    /**
     * Allow empty file uploads to circumvent file validations.
     *
     * @param Model $model
     * @param string $field
     * @param array $value
     * @return bool
     */
    protected function _allowEmpty(Model $model, $field, $value) {
        if (isset($this->_validations[$field]['required'])) {
            $rule = $this->_validations[$field]['required'];
            $required = isset($rule['rule'][1]) ? $rule['rule'][1] : true;

            if ($this->_isEmpty($value)) {
                if ($rule['allowEmpty']) {
                    return true;

                } else if ($required) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Check if a file input field is empty.
     *
     * @param string|array $value
     * @return bool
     */
    protected function _isEmpty($value) {
        return (
            is_array($value) && empty($value['tmp_name']) || // uploads
            is_string($value) && !$value // imports
        );
    }

    /**
     * Validate the field against the validation rules.
     *
     * @uses Transit\Transit
     * @uses Transit\File
     * @uses Transit\Validator\ImageValidator
     *
     * @param Model $model
     * @param array $data
     * @param string $method
     * @param array $params
     * @return bool
     * @throws UnexpectedValueException
     */
    protected function _validate(Model $model, $data, $method, array $params) {
        foreach ($data as $field => $value) {
            if ($this->_allowEmpty($model, $field, $value)) {
                return true;

            } else if ($this->_isEmpty($value)) {
                return false;
            }

            $file = null;

            // Upload, use temp file
            if (is_array($value)) {
                $file = new File($value);

            // Import, copy file for validation
            } else if (!empty($value)) {
                $target = TMP . md5($value);

                $transit = new Transit($value);
                $transit->setDirectory(TMP);

                // Already imported from previous validation
                if (file_exists($target)) {
                    $file = new File($target);

                // Local file
                } else if (file_exists($value)) {
                    $file = new File($value);

                // Attempt to copy from remote
                } else if (preg_match('/^http/i', $value)) {
                    if ($transit->importFromRemote()) {
                        $file = $transit->getOriginalFile();
                        $file->rename(basename($target));
                    }

                // Or from stream
                } else {
                    if ($transit->importFromStream()) {
                        $file = $transit->getOriginalFile();
                        $file->rename(basename($target));
                    }
                }

                // Save temp so we can delete later
                if ($file) {
                    $this->_tempFile = $file;
                }
            }

            if (!$file) {
                $this->log(sprintf('Invalid upload or import for validation: %s', json_encode($value)), LOG_DEBUG);
                return false;
            }

            $validator = new ImageValidator();
            $validator->setFile($file);

            return call_user_func_array(array($validator, $method), $params);
        }

        return false;
    }

}
