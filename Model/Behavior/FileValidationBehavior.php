<?php
/**
 * @copyright	Copyright 2006-2013, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/uploader
 */

App::uses('ModelBehavior', 'Model');

use Transit\File;
use Transit\Validator\ImageValidator;

/**
 * A CakePHP Behavior that adds validation model rules to file uploading.
 */
class FileValidationBehavior extends ModelBehavior {

	/**
	 * Default list of validation sets.
	 *
	 * @var array
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
	 * @var array
	 */
	protected $_validations = array();

	/**
	 * Setup the validation and model settings.
	 *
	 * @param Model $model
	 * @param array $settings
	 */
	public function setup(Model $model, $settings = array()) {
		if ($settings) {
			foreach ($settings as $field => $options) {
				$this->settings[$model->alias][$field] = $options + array('required' => true);
			}
		}
	}

	/**
	 * Validates an image file size. Default max size is 5 MB.
	 *
	 * @param Model $model
	 * @param array $data
	 * @param int $size
	 * @return boolean
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
	 * @return boolean
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
	 * @return boolean
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
	 * @return boolean
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
	 * @return boolean
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
	 * @return boolean
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
	 * @return boolean
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
	 * @return boolean
	 */
	public function extension(Model $model, $data, array $allowed = array()) {
		return $this->_validate($model, $data, 'ext', array($allowed));
	}

	/**
	 * Validates the type.
	 *
	 * @param Model $model
	 * @param array $data
	 * @param array $allowed
	 * @return boolean
	 */
	public function type(Model $model, $data, array $allowed = array()) {
		return $this->_validate($model, $data, 'type', array($allowed));
	}

	/**
	 * Validates the mime type.
	 *
	 * @param Model $model
	 * @param array $data
	 * @param array|string $mimeType
	 * @return boolean
	 */
	public function mimeType(Model $model, $data, $mimeType) {
		return $this->_validate($model, $data, 'mimeType', array($mimeType));
	}

	/**
	 * Makes sure a file field is required and not optional.
	 *
	 * @param Model $model
	 * @param array $data
	 * @param boolean $required
	 * @return boolean
	 */
	public function required(Model $model, $data, $required = true) {
		foreach ($data as $field => $value) {
			if ($required && (!$value || empty($value['tmp_name']))) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Build the validation rules and validate.
	 *
	 * @param Model $model
	 * @return boolean
	 */
	public function beforeValidate(Model $model) {
		if (empty($this->settings[$model->alias])) {
			return true;
		}

		foreach ($this->settings[$model->alias] as $field => $rules) {
			$validations = array();

			foreach ($rules as $rule => $setting) {
				$set = $this->_defaults[$rule];

				// Parse out values
				if (!isset($setting['value'])) {
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
					$validations = $validations + $model->validate[$field];
				}

				$this->_validations[$field] = $validations;
				$model->validate[$field] = $validations;
			}
		}

		return true;
	}

	/**
	 * Allow empty file uploads to circumvent file validations.
	 *
	 * @param Model $model
	 * @param string $field
	 * @param array $value
	 * @return boolean
	 */
	protected function _allowEmpty(Model $model, $field, $value) {
		if (isset($this->_validations[$field]['required'])) {
			$rule = $this->_validations[$field]['required'];
			$required = isset($rule['rule'][1]) ? $rule['rule'][1] : true;

			if (empty($value['tmp_name'])) {
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
	 * Validate the field against the validation rules.
	 *
	 * @param Model $model
	 * @param array $data
	 * @param string $method
	 * @param array $params
	 * @return boolean
	 */
	protected function _validate(Model $model, $data, $method, array $params) {
		foreach ($data as $field => $value) {
			if ($this->_allowEmpty($model, $field, $value)) {
				return true;

			} else if (empty($value['tmp_name'])) {
				return false;
			}

			// Extension is special as the tmp_name uses the .tmp extension
			if ($method === 'ext') {
				return in_array(mb_strtolower(pathinfo($value['name'], PATHINFO_EXTENSION)), $params[0]);

			// Use robust validator
			} else {
				$validator = new ImageValidator();
				$validator->setFile(new File($value['tmp_name']));

				return call_user_func_array(array($validator, $method), $params);
			}
		}

		return false;
	}

}
