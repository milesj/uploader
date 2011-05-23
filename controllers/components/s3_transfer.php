<?php
/**
 * S3 Transfer Component
 *
 * A component that can transfer a file into Amazon's storage bucket (AS3) - defined in the config.
 *
 * @author      Miles Johnson - http://milesj.me
 * @copyright   Copyright 2006-2011, Miles Johnson, Inc.
 * @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link        http://milesj.me/code/cakephp/uploader
 */

App::import(array(
	'type' => 'Vendor',
	'name' => 'Uploader.S3',
	'file' => 'S3.php'
));

class S3TransferComponent extends Object {

	/**
	 * Components.
	 *
	 * @access public
	 * @var array
	 */
	public $components = array('Uploader.Uploader');

	/**
	 * The bucket to use globally. Can be overwritten in each method.
	 *
	 * @access public
	 * @var string
	 */
	public $bucket;

	/**
	 * Your S3 access key.
	 *
	 * @access public
	 * @var boolean
	 */
	public $accessKey;

	/**
	 * Your S3 secret key.
	 *
	 * @access public
	 * @var boolean
	 */
	public $secretKey;

	/**
	 * Should the request use SSL?
	 *
	 * @access public
	 * @var boolean
	 */
	public $useSsl = true;

	/**
	 * Is the behavior configured correctly and usable.
	 *
	 * @access private
	 * @var boolean
	 */
	private $__enabled = false;

	/**
	 * Initialize transfer and classes.
	 *
	 * @access public
	 * @param object $Controller
	 * @return boolean
	 */
	public function startup($Controller) {
		if (empty($this->accessKey) && empty($this->secretKey)) {
			trigger_error('Uploader.S3Transfer::setup(): You must enter an Amazon S3 access key and secret key.', E_USER_WARNING);

		} else if (!function_exists('curl_init')) {
			trigger_error('Uploader.S3Transfer::setup(): You must have the cURL extension loaded to use the S3Transfer.', E_USER_WARNING);

		} else {
			$this->S3 = new S3($this->accessKey, $this->secretKey, $this->useSsl);
			$this->__enabled = true;
		}
	}

	/**
	 * Delete an object from a bucket.
	 *
	 * @access public
	 * @param string $url	- Full URL or Object file name
	 * @param string $bucket
	 * @return boolean
	 */
	public function delete($url, $bucket = null) {
		if ($this->__enabled) {
			$bucket = !empty($bucket) ? $bucket : $this->bucket;

			return $this->S3->deleteObject($bucket, basename($url));
		}

		return false;
	}

	/**
	 * Get a certain amount of objects from a bucket.
	 *
	 * @access public
	 * @param int $limit
	 * @param string $bucket
	 * @return array
	 */
	public function getBucket($limit = 15, $bucket = null) {
		if ($this->__enabled) {
			$bucket = !empty($bucket) ? $bucket : $this->bucket;

			return $this->S3->getBucket($bucket, null, null, $limit);
		}

		return false;
	}

	/**
	 * List out all the buckets under this S3 account.
	 *
	 * @access public
	 * @param boolean $detailed
	 * @return array
	 */
	public function listBuckets($detailed = true) {
		if ($this->__enabled) {
			return $this->S3->listBuckets($detailed);
		}

		return false;
	}

	/**
	 * Transfer an object to the storage bucket.
	 *
	 * @access public
	 * @param string $path
	 * @param boolean $delete
	 * @param string $bucket
	 * @return string
	 */
	public function transfer($path, $delete = true, $bucket = null) {
		if (empty($path)) {
			trigger_error('Uploader.S3Transfer::transfer(): File path missing, please try again.', E_USER_WARNING);
			return false;
		}

		if ($this->__enabled) {
			$bucket = !empty($bucket) ? $bucket : $this->bucket;
			$name = basename($path);

			if ($this->S3->putObjectFile($this->Uploader->formatPath($fullPath), $bucket, $name, S3::ACL_PUBLIC_READ)) {
				if ($delete) {
					$this->Uploader->delete($path);
				}

				return 'http://'. $bucket .'.s3.amazonaws.com/'. $name;
			}
		}

		return false;
	}

}
