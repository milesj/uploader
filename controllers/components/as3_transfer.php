<?php
/**
 * AS3 Transfer Component
 *
 * @todo
 *
 * @author 		Miles Johnson - www.milesj.me
 * @copyright	Copyright 2006-2009, Miles Johnson, Inc.
 * @license 	http://www.opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link		www.milesj.me/resources/script/uploader-plugin
 */

App::import('Vendor', 'S3');

class As3TransferComponent extends Object {

	/**
	 * Is the behavior configured correctly and usable.
	 *
	 * @access private
	 * @var boolean
	 */
	private $__enabled = false;

	/**
	 * User defined config.
	 *
	 * @access private
	 * @var array
	 */
	private $__config = array();

	/**
	 * The default settings for attachments.
	 *
	 * @access private
	 * @var array
	 */
	private $__defaults = array(
		'useSsl' 	=> true,
		'accessKey'	=> null,
		'secretKey' => null
	);

	/**
	 * Initialize transfer and classes.
	 *
	 * @access public
	 * @param object $Controller
	 * @param array $settings
	 * @return boolean
	 */
	public function initialize(&$Controller, $settings = array()) {
		$this->__config = array_merge($this->__defaults, $settings);

		if (empty($this->__config['accessKey']) && empty($this->__config['secretKey'])) {
			trigger_error('Uploader.As3Transfer::setup(): You must enter an Amazon S3 access key and secret key.', E_USER_WARNING);
		} else {
			$this->S3 = new S3($this->__config['accessKey'], $this->__config['secretKey'], $this->__config['useSsl']);
			$this->__enabled = true;
		}
	}

	/**
	 * Delete an object from a bucket.
	 *
	 * @access public
	 * @param string $bucket
	 * @param string $url	- Full URL or Object file name
	 * @return boolean
	 */
	public function delete($bucket, $url) {
		if (strpos($url, 'http') !== false) {
			$parts = parse_url($url);

			if (isset($parts['path'])) {
				$url = trim($parts['path'], '/');
			} else {
				$url = false;
			}
		}

		if ($url && $this->__enabled) {
			return $this->S3->deleteObject($bucket, $url);
		}

		return false;
	}

	/**
	 * Get a certain amount of objects from a bucket.
	 *
	 * @access public
	 * @param string $bucket
	 * @param int $limit
	 * @return array
	 */
	public function getBucket($bucket, $limit = 15) {
		if ($this->__enabled) {
			return $this->S3->getBucket($bucket, null, null, $limit);
		}
	}

	/**
	 * List out all the buckets under this S3 account.
	 *
	 * @access public
	 * @param boolean $detailed
	 * @return array
	 */
	public function listBuckets($detailed = false) {
		if ($this->__enabled) {
			return $this->S3->listBuckets($detailed);
		}
	}

	/**
	 * Transfer an object to the storage bucket.
	 *
	 * @access public
	 */
	public function transfer() {

	}

}
