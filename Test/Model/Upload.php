<?php
/**
 * Uploader Testing Model
 *
 * @author      Miles Johnson - http://milesj.me
 * @copyright   Copyright 2006-2011, Miles Johnson, Inc.
 * @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link        http://milesj.me/code/cakephp/uploader
 */

/**
CREATE TABLE IF NOT EXISTS `uploads` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`caption` varchar(255) NOT NULL,
	`path` varchar(255) NOT NULL,
	`path_alt` varchar(255) NOT NULL,
	`created` datetime DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
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
				'extension' => array(
					'value' => array('gif', 'jpg', 'jpeg'),
					'error' => 'Only gif, jpg and jpeg images are allowed!'
				),
				'minWidth' => 500,
				'minHeight' => 500,
				'required' => true
			),
			'import' => array(
				'required' => false
			),
			'file1' => array(
				'required' => true
			),
			'file2' => array(
				'required' => false
			),
			'file3' => array(
				'required' => true
			)
		),
		'Uploader.Attachment' => array(
			'file' => array(
				'name' => 'uploaderFilename',
				'uploadDir' => '/files/uploads/',
				'dbColumn' => 'path',
				'maxNameLength' => 30,
				'overwrite' => true,
				'stopSave' => false,
				'transforms' => array(
					// Save additional images in the databases after transforming
					array(
						'method' => 'resize',
						'width' => 100,
						'height' => 100,
						'dbColumn' => 'path_alt'
					)
				),
				'metaColumns' => array(
					'size' => 'filesize',   // The size value will be saved to the filesize column
					'type' => 'type'        // And the same for the mimetype
				)
			),
			'import' => array(
				'uploadDir' => '/files/uploads/',
				'name' => 'uploaderFilename',
				'dbColumn' => 'path_import',
				'overwrite' => true,
				'stopSave' => false,
				'transforms' => array(
					array(
						'method' => 'scale',
						'percent' => .5,
						'dbColumn' => 'path' // Overwrite the original image
					)
				)
			)
		)
	);

}