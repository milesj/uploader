<?php
/**
 * Uploader Testing Controller
 *
 * @author      Miles Johnson - www.milesj.me
 * @copyright   Copyright 2006-2010, Miles Johnson, Inc.
 * @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link        http://milesj.me/resources/script/uploader-plugin
 */

class UploadController extends AppController {

	var $uses = array('Upload');
	var $components = array('Uploader.Uploader');

	/**
	 * Test case for uploading an image with no transformations.
	 */
	function index() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'default', 'overwrite' => true))) {
				debug($data);
			}
		}

		$this->pageTitle = 'Upload: Test Case';
	}

	/**
	 * Test case for crop()ping images.
	 */
	function crop() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'crop', 'overwrite' => true))) {
				debug($data);

				$crop = $this->Uploader->crop(array('width' => 100, 'height' => 100));
				debug($crop);
			}
		}

		$this->pageTitle = 'Upload: Crop';
		$this->render('index');
	}

	/**
	 * Test case for getting an images dimensions.
	 */
	function dimensions() {
		debug($this->Uploader->dimensions($this->testPath));

		$this->pageTitle = 'Upload: Dimensions';
		$this->render('index');
	}

	/**
	 * Test case for getting an images ext.
	 */
	function ext() {
		debug($this->Uploader->ext($this->testPath));

		$this->pageTitle = 'Upload: Extension';
		$this->render('index');
	}

	/**
	 * Test case for flip()ping images.
	 */
	function flip() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'flip', 'overwrite' => true))) {
				debug($data);

				$flip = $this->Uploader->flip(array('dir' => UploaderComponent::DIR_BOTH));
				debug($flip);
			}
		}

		$this->pageTitle = 'Upload: Flip';
		$this->render('index');
	}

	/**
	 * Test case for resize()ing images.
	 */
	function resize() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'resize', 'overwrite' => true))) {
				debug($data);

				$resize = $this->Uploader->resize(array('width' => 1000, 'expand' => false));
				debug($resize);
			}
		}

		$this->pageTitle = 'Upload: Resize';
		$this->render('index');
	}

	/**
	 * Test case for scale()ing images.
	 */
	function scale() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'scale', 'overwrite' => true))) {
				debug($data);

				$scale = $this->Uploader->scale(array('percent' => .3));
				debug($scale);
			}
		}

		$this->pageTitle = 'Upload: Scale';
		$this->render('index');
	}

	/**
	 * Test case for uploading multiple images.
	 */
	function upload_all() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->uploadAll(array('file1', 'file2', 'file3'), true)) {
				debug($data);
			}
		}

		$this->pageTitle = 'Upload: Upload All';
	}

	/**
	 * Test case for uploading multiple images from different models.
	 */
	function multi_models() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->uploadAll(array('Upload.file', 'Upload1.file', 'Upload2.file'), true)) {
				debug($data);
			}
		}

		$this->pageTitle = 'Upload: Multiple Models';
	}

	/**
	 * Test case for checking the behavior validation.
	 */
	function behaviors() {
		if (!empty($this->data)) {
			$this->Upload->set($this->data);

			if ($this->Upload->validates()) {
				if ($this->Upload->save($this->data, false, array('caption', 'path', 'path_alt'))) {
					debug('Image uploaded and row saved!');
				}
			}
		}

		$this->pageTitle = 'Upload: Behavior Validation and Attachment Testing';
	}

	/**
	 * Executed before each action
	 */
	function beforeFilter() {
		parent::beforeFilter();

		$this->testPath = WWW_ROOT .'files'. DS .'uploads'. DS .'test.jpg';
	}

}