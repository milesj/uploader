<?php
/**
 * S3 Transfer Component
 *
 * A component that can transfer a file into Amazon's storage bucket (AS3) - defined in the config.
 *
 * @author 		Miles Johnson - www.milesj.me
 * @copyright	Copyright 2006-2009, Miles Johnson, Inc.
 * @license 	http://www.opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link		www.milesj.me/resources/script/uploader-plugin
 */

App::import('Vendor', 'Uploader.S3');

class S3TransferComponent extends Object {

	/**
	 * Components.
	 *
	 * @access public
	 * @var array
	 */
	public $components = array('Uploader.Uploader');

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
	public function startup(&$Controller) {
		if (empty($this->accessKey) && empty($this->secretKey)) {
			trigger_error('Uploader.S3Transfer::setup(): You must enter an Amazon S3 access key and secret key.', E_USER_WARNING);

		} else if (!function_exists('curl_init')) {
			trigger_error('Uploader.S3Transfer::setup(): You must have the cURL extension loaded to use the AS3Transfer.', E_USER_WARNING);

		} else {
			$this->S3 = new S3($this->accessKey, $this->secretKey, $this->useSsl);
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
		if ($this->__enabled) {
			return $this->S3->deleteObject($bucket, basename($url));
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

		return false;
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

		return false;
	}

	/**
	 * Transfer an object to the storage bucket.
	 *
	 * @access public
	 * @param string $bucket
	 * @param array $data
	 * @param boolean $delete
	 * @return string
	 */
	public function transfer($bucket, array $data = array(), $delete = true) {
		if (empty($data['path']) || empty($data['name'])) {
			trigger_error('Uploader.S3Transfer::transfer(): File data incomplete, please try again.', E_USER_WARNING);
			
			return false;
		}

		if ($this->__enabled) {
			if ($this->S3->putObjectFile($data['path'], $bucket, $data['name'], S3::ACL_PUBLIC_READ)) {
				if ($delete) {
					$this->Uploader->delete($data['path']);
				}

				return 'http://'. $bucket .'.s3.amazonaws.com/'. $data['name'];
			}
		}
		
		return false;
	}

}
