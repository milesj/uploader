<?php
/**
 * Uploader Testing Model
 *
 * @author      Miles Johnson - www.milesj.me
 * @copyright   Copyright 2006-2010, Miles Johnson, Inc.
 * @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link        http://milesj.me/resources/script/uploader-plugin
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