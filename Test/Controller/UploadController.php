<?php
/**
 * Uploader Testing Controller
 *
 * @author      Miles Johnson - http://milesj.me
 * @copyright   Copyright 2006-2011, Miles Johnson, Inc.
 * @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link        http://milesj.me/resources/script/uploader-plugin
 */

/**
 * Custom global function to handle filename formatting; works within the component or behavior.
 * Simply pass the function name to the the name option.
 * 
 * @access public
 * @param string $name
 * @param string $field
 * @param array $data
 * @return string 
 */
function uploaderFilename($name, $field, $data) {
	return md5($name);
}

class UploadController extends AppController {

	/**
	 * Include plugin.
	 */
	public $uses = array('Upload');
	public $components = array('Uploader.Uploader', 'Security');

	/**
	 * Test case for uploading an image with no transformations.
	 */
	public function index() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'uploaderFilename', 'overwrite' => true))) {
				debug($data);
			}
		}

		$this->set('title_for_layout', 'Upload: Test Case');
	}

	/**
	 * Test case for getting a files meta data: dimensions, extension, mimetype, etc.
	 */
	public function meta() {
		$this->testPath = $this->testPath .'test_dimensions.jpg';

		debug($this->Uploader->dimensions($this->testPath));

		debug($this->Uploader->mimeType($this->testPath));

		debug($this->Uploader->ext($this->testPath));

		$this->set('title_for_layout', 'Upload: Meta Data');
		$this->render('index');
	}

	/**
	 * Test case for crop()ping images.
	 */
	public function crop() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'crop', 'overwrite' => true))) {
				debug($data);

				$crop = $this->Uploader->crop(array('width' => 100, 'height' => 100));
				debug($crop);
			}
		}

		$this->set('title_for_layout', 'Upload: Crop');
		$this->render('index');
	}

	/**
	 * Test case for flip()ping images.
	 */
	public function flip() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'flip', 'overwrite' => true))) {
				debug($data);

				$flip = $this->Uploader->flip(array('dir' => UploaderComponent::DIR_BOTH));
				debug($flip);
			}
		}

		$this->set('title_for_layout', 'Upload: Flip');
		$this->render('index');
	}

	/**
	 * Test case for resize()ing images.
	 */
	public function resize() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'resize', 'overwrite' => true))) {
				debug($data);

				$resize = $this->Uploader->resize(array('width' => 1000, 'expand' => false));
				debug($resize);
			}
		}

		$this->set('title_for_layout', 'Upload: Resize');
		$this->render('index');
	}

	/**
	 * Test case for scale()ing images.
	 */
	public function scale() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'scale', 'overwrite' => true))) {
				debug($data);

				$scale = $this->Uploader->scale(array('percent' => .3));
				debug($scale);
			}
		}

		$this->set('title_for_layout', 'Upload: Scale');
		$this->render('index');
	}

	/**
	 * Test case for uploading multiple images.
	 */
	public function upload_all() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->uploadAll(array('file1', 'file2', 'file3'), true)) {
				debug($data);
			}
		}

		$this->set('title_for_layout', 'Upload: Upload All');
	}

	/**
	 * Test case for uploading multiple images with validation.
	 */
	public function upload_all_validate() {
		if (!empty($this->data)) {
			$this->Upload->set($this->data);

			if ($this->Upload->validates()) {
				if ($data = $this->Uploader->uploadAll(array('file1', 'file2', 'file3'), true)) {
					debug($data);
				}
			}
		}

		$this->set('title_for_layout', 'Upload: Upload All with Validation');
		$this->render('upload_all');
	}

	/**
	 * Test case for uploading multiple images from different models.
	 */
	public function multi_models() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->uploadAll(array('Upload.file', 'Upload1.file', 'Upload2.file'), true)) {
				debug($data);
			}
		}

		$this->set('title_for_layout', 'Upload: Multiple Models');
	}

	/**
	 * Test case for checking the behavior validation and upload for form submitted files.
	 */
	public function behaviors() {
		if (!empty($this->data)) {
			if ($this->Upload->save($this->data)) {
				debug('Image uploaded and row saved!');
			}
		}

		$this->set('title_for_layout', 'Upload: Behavior Validation and Attachment Testing');
	}

	/**
	 * Test case for checking the behavior validation and upload for imported files (remote URLs).
	 */
	public function behaviors_import() {
		if (!empty($this->data)) {
			if ($this->Upload->save($this->data)) {
				debug('Image uploaded and row saved!');
			}
		}

		$this->set('title_for_layout', 'Upload: Behavior Validation and Attachment Testing');
	}

	/**
	 * Test case for importing a file from a local source.
	 */
	public function import() {
		$this->testPath = $this->testPath .'test_import.jpg';

		if (!empty($this->data)) {
			if ($data = $this->Uploader->import($this->testPath, array('name' => 'imported', 'overwrite' => true))) {
				debug($data);

				$scale = $this->Uploader->scale(array('percent' => .3));
				debug($scale);

				$crop = $this->Uploader->crop(array('width' => 100, 'height' => 100));
				debug($crop);
			}
		}

		$this->set('title_for_layout', 'Upload: Import Local File');
		$this->render('import');
	}

	/**
	 * Test case for importing a file from a remote source.
	 */
	public function import_remote() {
		if (!empty($this->data)) {
			if ($data = $this->Uploader->importRemote('http://www.google.com/images/logos/ps_logo2.png', array('name' => 'remote', 'overwrite' => false))) {
				debug($data);

				$crop = $this->Uploader->crop(array('width' => 100, 'height' => 100));
				debug($crop);

				$flip = $this->Uploader->flip(array('dir' => UploaderComponent::DIR_BOTH));
				debug($flip);
			}
		}

		$this->set('title_for_layout', 'Upload: Import Remote File');
		$this->render('import');
	}
	
	/**
	 * Test case for uploading files via XHR or AJAX iframe hack.
	 */
	public function ajax() {
		$this->set('title_for_layout', 'Upload: AJAX File Upload');
		$this->render('ajax');
	}
	
	/**
	 * URL to handle the AJAX call.
	 */
	public function ajax_upload() {
		$this->autoLayout = $this->autoRender = false;
		
		if ($data = $this->Uploader->upload($this->Uploader->ajaxField, array('overwrite' => true))) {
			header('Content-Type: application/json');
			echo json_encode(array('success' => true, 'data' => $data));
		}
	}

	/**
	 * Set the test path.
	 */
	public function beforeFilter() {
		// qqfile comes from the fileuploader.js script
		// Customize for your application
		$this->Uploader->ajaxField = 'qqfile';
		
		$this->testPath = $this->Uploader->baseDir . $this->Uploader->uploadDir;
	}

}
