<?php
/**
 * Uploader Testing Controller
 *
 * @author      Miles Johnson - http://milesj.me
 * @copyright   Copyright 2006-2011, Miles Johnson, Inc.
 * @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link        http://milesj.me/resources/script/uploader-plugin
 */

App::import('Vendor', 'Uploader.Uploader');

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

/**
 * Test controller.
 */
class UploadController extends AppController {

	/**
	 * Include plugin.
	 */
	public $uses = array('Upload');

	/**
	 * Test case for uploading an image with no transformations.
	 */
	public function index() {
		if (!empty($this->request->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'uploaderFilename', 'overwrite' => true))) {
				debug($data);
			}
		}

		$this->set('title_for_layout', 'Upload: Test Case');
		$this->render('single_upload');
	}

	/**
	 * Test case for getting a files meta data: dimensions, extension, mimetype, etc.
	 */
	public function meta() {
		$this->testPath = $this->testPath . 'test_dimensions.jpg';

		debug($this->Uploader->dimensions($this->testPath));

		debug(Uploader::mimeType($this->testPath));

		debug(Uploader::ext($this->testPath));

		$this->set('title_for_layout', 'Upload: Meta Data');
		$this->render('single_upload');
	}

	/**
	 * Test case for crop()ping images.
	 */
	public function crop() {
		if (!empty($this->request->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'crop', 'overwrite' => true))) {
				debug($data);

				debug($this->Uploader->crop(array('width' => 100, 'height' => 100, 'quality' => 90, 'append' => '_top', 'location' => Uploader::LOC_TOP)));

				debug($this->Uploader->crop(array('width' => 100, 'height' => 100, 'quality' => 70, 'append' => '_bottom', 'location' => Uploader::LOC_BOT)));

				debug($this->Uploader->crop(array('width' => 100, 'height' => 100, 'quality' => 50, 'append' => '_left', 'location' => Uploader::LOC_LEFT)));

				debug($this->Uploader->crop(array('width' => 100, 'height' => 100, 'quality' => 30, 'append' => '_right', 'location' => Uploader::LOC_RIGHT)));

				debug($this->Uploader->crop(array('width' => 100, 'height' => 100, 'quality' => 10, 'append' => '_middle', 'location' => Uploader::LOC_CENTER)));
			}
		}

		$this->set('title_for_layout', 'Upload: Crop');
		$this->render('single_upload');
	}

	/**
	 * Test case for flip()ping images.
	 */
	public function flip() {
		if (!empty($this->request->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'flip', 'overwrite' => true))) {
				debug($data);

				debug($this->Uploader->flip(array('append' => '_both', 'quality' => 75, 'dir' => Uploader::DIR_BOTH)));

				debug($this->Uploader->flip(array('append' => '_hori', 'quality' => 45, 'dir' => Uploader::DIR_HORI)));

				debug($this->Uploader->flip(array('append' => '_vert', 'quality' => 25, 'dir' => Uploader::DIR_VERT)));
			}
		}

		$this->set('title_for_layout', 'Upload: Flip');
		$this->render('single_upload');
	}

	/**
	 * Test case for resize()ing images.
	 */
	public function resize() {
		if (!empty($this->request->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'resize', 'overwrite' => true))) {
				debug($data);

				debug($this->Uploader->resize(array('width' => 250, 'expand' => false)));

				debug($this->Uploader->resize(array('height' => 250, 'expand' => false)));

				debug($this->Uploader->resize(array('width' => 250, 'height' => 500, 'expand' => false)));

				debug($this->Uploader->resize(array('width' => 1000, 'expand' => true)));

				debug($this->Uploader->resize(array('height' => 2000, 'expand' => true)));

				debug($this->Uploader->resize(array('width' => 1250, 'height' => 1750, 'expand' => true, 'aspect' => false)));

				debug($this->Uploader->resize(array('width' => 1250, 'height' => 1750, 'expand' => true, 'aspect' => true)));

				debug($this->Uploader->resize(array('width' => 5000, 'height' => 5000, 'expand' => true, 'aspect' => true)));

				debug($this->Uploader->resize(array('width' => 200, 'height' => 600, 'expand' => false, 'aspect' => true)));

				debug($this->Uploader->resize(array('width' => 100, 'height' => 200, 'expand' => true, 'aspect' => true, 'mode' => Uploader::MODE_WIDTH)));

				debug($this->Uploader->resize(array('width' => 100, 'height' => 200, 'expand' => true, 'aspect' => true, 'mode' => Uploader::MODE_HEIGHT)));
			}
		}

		$this->set('title_for_layout', 'Upload: Resize');
		$this->render('single_upload');
	}

	/**
	 * Test case for scale()ing images.
	 */
	public function scale() {
		if (!empty($this->request->data)) {
			if ($data = $this->Uploader->upload('file', array('name' => 'scale', 'overwrite' => true))) {
				debug($data);

				debug($this->Uploader->scale(array('append' => '_30', 'percent' => .3)));

				debug($this->Uploader->scale(array('append' => '_77', 'percent' => .77)));

				debug($this->Uploader->scale(array('append' => '_150', 'percent' => 1.50)));

				debug($this->Uploader->scale(array('append' => '_175', 'percent' => 2.75)));
			}
		}

		$this->set('title_for_layout', 'Upload: Scale');
		$this->render('single_upload');
	}

	/**
	 * Test case for uploading multiple images.
	 */
	public function upload_all() {
		if (!empty($this->request->data)) {
			if ($data = $this->Uploader->uploadAll(array('file1', 'file2', 'file3'), true)) {
				debug($data);
			}
		}

		$this->set('title_for_layout', 'Upload: Upload All');
		$this->render('multi_upload');
	}

	/**
	 * Test case for uploading multiple images with validation.
	 */
	public function upload_all_validate() {
		if (!empty($this->request->data)) {
			$this->Upload->set($this->request->data);

			if ($this->Upload->validates()) {
				if ($data = $this->Uploader->uploadAll(array('file1', 'file2', 'file3'), true)) {
					debug($data);
				}
			}
		}

		$this->set('title_for_layout', 'Upload: Upload All with Validation');
		$this->render('multi_upload');
	}

	/**
	 * Test case for uploading multiple images from different models.
	 */
	public function multi_models() {
		if (!empty($this->request->data)) {
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
		if (!empty($this->request->data)) {
			if ($this->Upload->save($this->request->data)) {
				debug('Image uploaded and row saved!');
			}
		}

		$this->set('title_for_layout', 'Upload: Behavior Validation and Attachment Testing');
	}

	/**
	 * Test case for checking the behavior validation and upload for imported files (remote URLs).
	 */
	public function behaviors_import() {
		if (!empty($this->request->data)) {
			if ($this->Upload->save($this->request->data)) {
				debug('Image uploaded and row saved!');
			}
		}

		$this->set('title_for_layout', 'Upload: Behavior Validation and Attachment Testing');
	}

	/**
	 * Test case for importing a file from a local source.
	 */
	public function import() {
		$this->testPath = $this->testPath . 'test_import.jpg';

		if (!empty($this->request->data)) {
			if ($data = $this->Uploader->import($this->testPath, array('name' => 'imported', 'overwrite' => true))) {
				debug($data);

				debug($this->Uploader->scale(array('percent' => .3)));

				debug($this->Uploader->crop(array('width' => 100, 'height' => 100)));
			}
		}

		$this->set('title_for_layout', 'Upload: Import Local File');
		$this->render('import');
	}

	/**
	 * Test case for importing a file from a remote source.
	 */
	public function import_remote() {
		if (!empty($this->request->data)) {
			if ($data = $this->Uploader->importRemote('http://www.google.com/images/logos/ps_logo2.png', array('name' => 'remote', 'overwrite' => false))) {
				debug($data);

				debug($this->Uploader->crop(array('width' => 100, 'height' => 100)));

				debug($this->Uploader->flip(array('dir' => Uploader::DIR_BOTH)));
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
		$this->Uploader = new Uploader(array(
			'ajaxField' => 'qqfile'
		));

		$this->testPath = $this->Uploader->baseDir . $this->Uploader->uploadDir;
	}

}
