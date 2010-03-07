<?php
/**
 * Uploader Testing Model
 *
 * @author 		Miles Johnson - www.milesj.me
 * @copyright	Copyright 2006-2009, Miles Johnson, Inc.
 * @license 	http://www.opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link		www.milesj.me/resources/script/uploader-plugin
 */

class Upload extends AppModel {

	/**
	 * Validation for default forms. Testing to make sure it combines with FileValidation.
	 *
	 * @access public
	 * @var array
	 */
	public $validate = array('caption' => 'notEmpty');

	/**
	 * Behavior settings for both FileValidation and Attachment.
	 *
	 * @access public
	 * @var array
	 */
	public $actsAs = array(
		'Uploader.FileValidation' => array(
			'file' => array(
				'dimension' => array(
					'width' => 500,
					'height' => 500,
					'error' => 'Your dimensions are too large!'
				),
				'extension' => array(
					'value' => array('gif', 'jpg', 'jpeg'),
					'error' => 'Only gif, jpg and jpeg images are allowed!'
				),
				'optional' => false
			)
		),
		'Uploader.Attachment' => array(
			'file' => array(
				'uploadDir' 	=> '/files/uploads/',
				'dbColumn'		=> 'path',
				'maxNameLength'	=> 30,
				'overwrite'		=> true,
				'transforms' 	=> array(
					array(
						'method' => 'resize',
						'width' => 100,
						'height' => 100,
						'dbColumn' => 'path_alt'
					)
				)
			)
		)
	);
	
}