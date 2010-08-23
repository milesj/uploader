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
    var $components = array('Uploader.Uploader', 'Security');

    /**
     * Test case for uploading an image with no transformations.
     */
    function index() {
        if (!empty($this->data)) {
            if ($data = $this->Uploader->upload('file', array('name' => 'default', 'overwrite' => true))) {
                debug($data);
            }
        }

        $this->set('title_for_layout', 'Upload: Test Case');
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

        $this->set('title_for_layout', 'Upload: Crop');
        $this->render('index');
    }

    /**
     * Test case for getting an images dimensions.
     */
    function dimensions() {
        debug($this->Uploader->dimensions($this->testPath));

        $this->set('title_for_layout', 'Upload: Dimensions');
        $this->render('index');
    }

    /**
     * Test case for getting an images ext.
     */
    function ext() {
        debug($this->Uploader->ext($this->testPath));

        $this->set('title_for_layout', 'Upload: Extension');
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

        $this->set('title_for_layout', 'Upload: Flip');
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

        $this->set('title_for_layout', 'Upload: Resize');
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

        $this->set('title_for_layout', 'Upload: Scale');
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

        $this->set('title_for_layout', 'Upload: Upload All');
    }

    /**
     * Test case for uploading multiple images with validation.
     */
    function upload_all_validate() {
        if (!empty($this->data)) {
            $this->Upload->set($this->data);

            if ($this->Upload->validates()) {
                if ($data = $this->Uploader->uploadAll(array('file1', 'file2', 'file3'), true)) {
                    debug($data);
                }
            }
        }

        $this->set('title_for_layout', 'Upload: Upload All with Validation');
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

        $this->set('title_for_layout', 'Upload: Multiple Models');
    }

    /**
     * Test case for checking the behavior validation.
     */
    function behaviors() {
        if (!empty($this->data)) {
            $this->Upload->set($this->data);

            if ($this->Upload->validates()) {
                if ($this->Upload->save($this->data, false)) {
                    debug('Image uploaded and row saved!');
                }
            }
        }

        $this->set('title_for_layout', 'Upload: Behavior Validation and Attachment Testing');
    }

    /**
     * Executed before each action
     */
    function beforeFilter() {
        parent::beforeFilter();

        $this->testPath = WWW_ROOT .'files'. DS .'uploads'. DS .'test.jpg';
    }

}