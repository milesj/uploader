<?php 
/** 
 * Uploader Component
 *
 * A CakePHP Component that will upload a wide range of file types. Each file will be uploaded into app/webroot/<upload dir> (the path your provide). 
 * Security and type checking have been integrated to only allow valid files. Additionally, images have the option of transforming an image.
 *
 * @author      Miles Johnson - www.milesj.me
 * @copyright   Copyright 2006-2010, Miles Johnson, Inc.
 * @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link        http://milesj.me/resources/script/uploader-plugin
 */
 
App::import('Core', array('Folder', 'HttpSocket'));
Configure::load('Uploader.config');

class UploaderComponent extends Object {

	/**
	 * The direction to flip: vertical.
	 *
	 * @constant
	 * @var int
	 */
	const DIR_VERT = 1;
	
	/**
	 * The direction to flip: horizontal.
	 *
	 * @constant
	 * @var int
	 */
	const DIR_HORI = 2;
	
	/**
	 * The direction to flip: vertical and horizontal.
	 *
	 * @constant
	 * @var int
	 */
	const DIR_BOTH = 3;

	/**
	 * The location to crop: top.
	 *
	 * @constant
	 * @var int
	 */
	const LOC_TOP = 1;

	/**
	 * The location to crop: bottom.
	 *
	 * @constant
	 * @var int
	 */
	const LOC_BOT = 2;

	/**
	 * The location to crop: left.
	 *
	 * @constant
	 * @var int
	 */
	const LOC_LEFT = 3;

	/**
	 * The location to crop: right.
	 *
	 * @constant
	 * @var int
	 */
	const LOC_RIGHT = 4;

	/**
	 * The location to crop: center.
	 *
	 * @constant
	 * @var int
	 */
	const LOC_CENTER = 5;
	
	/**
	 * Should we allow file uploading for this request?
	 *
	 * @access public
	 * @var boolean
	 */
	public $enableUpload = true;
	
	/**
	 * Max filesize using shorthand notation: http://us2.php.net/manual/en/faq.using.php#faq.using.shorthandbytes.
	 *
	 * @access public
	 * @var string
	 */ 
	public $maxFileSize = '5M'; 
	
	/**
	 * How long should file names be?
	 *
	 * @access public
	 * @var int
	 */
	public $maxNameLength = 40;
	
	/**
	 * Should we scan the file for viruses? Requires ClamAV module: http://www.clamav.net/
	 *
	 * @access public
	 * @var boolean
	 */
	public $scanFile = false;
	
	/**
	 * Temp upload directory.
	 *
	 * @access public
	 * @var string
	 */
	public $tempDir = TMP;
	
	/**
	 * Destination upload directory within app/webroot/.
	 *
	 * @access public
	 * @var string
	 */
	public $uploadDir = 'files/uploads/';
	
	/**
	 * The accepted file/mime types; imported from vendor.
	 *
	 * @access private
	 * @var array
	 */
	private $__mimeTypes = array();
	
	/**
	 * Holds the the current $_FILES data.
	 *
	 * @access private
	 * @var array
	 */
	private $__data = array(); 

	/**
	 * Holds the the logged uploads.
	 *
	 * @access private
	 * @var array
	 */
	private $__logs = array(); 
	
	/**
	 * The current file being processed.
	 *
	 * @access private
	 * @var string
	 */
	private $__current;
	
	/**
	 * Load the controllers file data into the component.
	 *
	 * @access public
	 * @uses UploaderConfig
	 * @param object $Controller
	 * @param array $settings
	 * @return void
	 */
	public function initialize(&$Controller, $settings = array()) {
		$this->__mimeTypes = Configure::read('Uploader.mimeTypes');
		
		if (!extension_loaded('gd')) {
			@dl('gd.'. PHP_SHLIB_SUFFIX);
		}

        $data = $Controller->data;
		unset($data['_Token']);

		$this->__parseData($data, null, count($data));
	}
	
	/**
	 * Set our ini settings for future use.
	 *
	 * @access public
	 * @uses Folder
	 * @param object $Controller
	 * @return void
	 */
	public function startup(&$Controller) {
		if (!isset($this->Folder)) {
			$this->Folder = new Folder();
		}
		
		$fileUploads = ini_get('file_uploads');
		if ($fileUploads === 0 || $fileUploads === false) {
			$this->enableUpload = false;
		} else if (!is_bool($this->enableUpload)) {
			$this->enableUpload = $fileUploads;
		}
		
		if (empty($this->maxFileSize)) {
			$this->maxFileSize = ini_get('upload_max_filesize');
		}
		
		$byte = preg_replace('/[^0-9]/i', '', $this->maxFileSize);
		$last = $this->bytes($this->maxFileSize, 'byte');
		
		if ($last == 'T' || $last == 'TB') {
			$multiplier = 1;
			$execTime = 20;
		} else if ($last == 'G' || $last == 'GB') {
			$multiplier = 3;
			$execTime = 10;
		} else if ($last == 'M' || $last == 'MB') {
			$multiplier = 5;
			$execTime = 5;
		} else {
			$multiplier = 10;
			$execTime = 3;
		}
		
		ini_set('memory_limit', (($byte * $multiplier) * $multiplier) . $last);
		ini_set('post_max_size', ($byte * $multiplier) . $last);
		ini_set('upload_tmp_dir', $this->tempDir);
		ini_set('upload_max_filesize', $this->maxFileSize);
		ini_set('max_execution_time', ($execTime * 10));
		ini_set('max_input_time', ($execTime * 10));
		
		if (!is_writable($this->tempDir)) {
			$this->Folder->chmod($this->tempDir, 0777, false); 
		}
		
		$this->_directory();
	}
		
	/**
	 * Return the bytes based off the shorthand notation.
	 *
	 * @access public
	 * @param int $size
	 * @param string $return
	 * @return string
	 */
	public function bytes($size, $return = '') {
		if (!is_numeric($size)) {
			$byte = preg_replace('/[^0-9]/i', '', $size);
			$last = mb_strtoupper(preg_replace('/[^a-zA-Z]/i', '', $size));
			
			if ($return == 'byte') {
				return $last;
			}

			switch ($last) {
				case 'T': case 'TB': $byte *= 1024;
				case 'G': case 'GB': $byte *= 1024;
				case 'M': case 'MB': $byte *= 1024;
				case 'K': case 'KB': $byte *= 1024;
			}
			
			$size = $byte;
		}
		
		if ($return == 'size') {
			return $size;
		}
		
		$sizes = array('YB', 'ZB', 'EB', 'PB', 'TB', 'GB', 'MB', 'KB', 'B');
		$total = count($sizes);
	
		while($total-- && $size > 1024) {
			$size /= 1024;
		}
		
		$bytes = round($size, 0) .' '. $sizes[$total];
		return $bytes;
	}
	
	/**
	 * Crops a photo, resizes first depending on which side is larger.
	 *
	 * @access public
	 * @param array $options
	 * - location: Which area of the image should be grabbed for the crop: center, left, right, top, bottom
	 * - width, height: The width and height to resize the image to before cropping
	 * - append: What should be appended to the end of the filename
	 * - quality: The quality of the image
	 * @return mixed
	 */
	public function crop($options = array()) {
		if ($this->__data[$this->__current]['group'] != 'image' || $this->enableUpload === false) {
			return false;
		}
		
		$defaults = array('location' => self::LOC_CENTER, 'quality' => 100, 'width' => null, 'height' => null, 'append' => null);
		$options = array_merge($defaults, $options);
		
		$width 	= $this->__data[$this->__current]['width'];
		$height = $this->__data[$this->__current]['height'];
		$src_x 	= 0;
		$src_y 	= 0;
		$dest_w = $width;
		$dest_h = $height;
		$location = $options['location'];
		
		if (is_numeric($options['width']) && is_numeric($options['height'])) {
			$newWidth = $options['width'];
			$newHeight = $options['height'];
			
			if ($width > $height) {
				$dest_h = $options['height'];
				$dest_w = round(($width / $height) * $options['height']);
			} else if ($height > $width) {
				$dest_w = $options['width'];
				$dest_h = round(($height / $width) * $options['width']);
			}
		} else {
			if ($width > $height) {
				$newWidth = $height;
				$newHeight = $height;
			} else {
				$newWidth = $width;
				$newHeight = $width;
			}
			
			$dest_h = $newHeight;
			$dest_w = $newWidth;
		}
		
		if ($dest_w > $dest_h) {
			if ($location == self::LOC_CENTER) {
				$src_x = ceil(($width - $height) / 2);
				$src_y = 0;
			} else if ($location == self::LOC_BOT || $location == self::LOC_RIGHT) {
				$src_x = ($width - $height);
				$src_y = 0;
			}
		} else if ($dest_h > $dest_w) {
			if ($location == self::LOC_CENTER) {
				$src_x = 0;
				$src_y = ceil(($height - $width) / 2);
			} else if ($location == self::LOC_BOT || $location == self::LOC_RIGHT) {
				$src_x = 0;
				$src_y = ($height - $width);
			}
		}
		
		$append = 'cropped_'. $newWidth .'x'. $newHeight;
		if ($options['append'] !== null) {
			$append = $options['append'];
		}
		
		$transform = array(
			'width'		=> $newWidth,
			'height'	=> $newHeight,
			'source_x'	=> $src_x,
			'source_y'	=> $src_y,
			'source_w'	=> $width,
			'source_h'	=> $height,
			'dest_w'	=> $dest_w,
			'dest_h'	=> $dest_h,
			'target'	=> $this->_destination($this->__data[$this->__current]['name'], true, $append, false),
			'quality'	=> $options['quality']
		);
		
		if ($this->transform($transform)) {
			return $this->_return($transform['target'], 'cropped_'. $newWidth .'x'. $newHeight);
		} 
		
		return false;
	}
	
	/**
	 * Deletes a file, path is relative to webroot/.
	 *
	 * @access public
	 * @param string $path
	 * @return boolean
	 */
	public function delete($path = '') {
		if (empty($path)) {
			return false;
		}

		if (strpos($path, WWW_ROOT) === false) {
			$path = WWW_ROOT . $path;
		}
		
		if (file_exists($path)) {
			clearstatcache();
			return unlink($path);
		}
		
		return false;
	}
	
	/**
	 * Get the dimensions of an image.
	 *
	 * @access public
	 * @uses HttpSocket
	 * @param string $path
	 * @return array
	 */
	public function dimensions($path = '') {
		if (empty($path)) {
			return null;
		}

		$dim = array();
		$data = @getimagesize($path);
		
		if (!empty($data) && is_array($data)) {
			$dim = array('width' => $data[0], 'height' => $data[1], 'type' => $data['mime']);
			unset($data);
			
		} else {
			if (!isset($this->Http)) {
				$this->Http = new HttpSocket();
			}
			
			$data = $this->Http->request($path);
			$image = @imagecreatefromstring($data);
			
			$dim = array(
				'width' 	=> @imagesx($image),
				'height' 	=> @imagesy($image),
				'type'		=> function_exists('mime_content_type') ? mime_content_type($path) : null
			);
			
			if (empty($dim['type'])) {
				$ext = $this->ext($path);
				
				foreach ($this->__mimeTypes as $group => $mimes) {
					if (in_array($ext, array_keys($mimes))) {
						$dim['type'] = $this->__mimeTypes[$group][$ext];
						break;
					}
				}
			}
					
			unset($image, $data, $ext);
		}
		
		return $dim;
	}
	
	/**
	 * Get the extension.
	 *
	 * @access public
	 * @param string $file
	 * @return string
	 */
	public function ext($file) {
		return mb_strtolower(trim(mb_strrchr($file, '.'), '.'));
	}
	
	/**
	 * Flips an image in 3 possible directions.
	 *
	 * @access public
	 * @param array $options
	 * - dir: The direction the image should be flipped
	 * - append: What should be appended to the end of the filename
	 * - quality: The quality of the image
	 * @return string
	 */
	public function flip($options = array()) {
		if ($this->__data[$this->__current]['group'] != 'image' || $this->enableUpload === false) {
			return false;
		}
		
		$defaults = array('dir' => self::DIR_VERT, 'quality' => 100, 'append' => null);
		$options = array_merge($defaults, $options);
		
		$width 	= $this->__data[$this->__current]['width'];
		$height = $this->__data[$this->__current]['height'];
		$src_x	= 0;
		$src_y	= 0;
		$src_w	= $width;
		$src_h 	= $height;
		
		switch ($options['dir']) {
			// vertical
			case self::DIR_VERT:
				$src_y = --$height;
				$src_h = -$height;
				$adir = 'vert';
			break;
			// horizontal
			case self::DIR_HORI:
				$src_x = --$width;
				$src_w = -$width;
				$adir = 'hor';
			break;
			// both
			case self::DIR_BOTH:
				$src_x = --$width;
				$src_y = --$height;
				$src_w = -$width;
				$src_h = -$height;
				$adir = 'both';
			break;
			default: return false; break;
		}
		
		$append = 'flip_'. $adir;
		if ($options['append'] !== null) {
			$append = $options['append'];
		}
		
		$transform = array(
			'width'		=> $width,
			'height'	=> $height,
			'source_x'	=> $src_x,
			'source_y'	=> $src_y,
			'source_w'	=> $src_w,
			'source_h'	=> $src_h,
			'target'	=> $this->_destination($this->__data[$this->__current]['name'], true, $append, false),
			'quality'	=> $options['quality']
		);
		
		if ($this->transform($transform)) {
			return $this->_return($transform['target'], 'flip_'. $adir);
		} 
		
		return false;
	}
	
	/**
	 * Adds a mime type to the list of allowed types.
	 *
	 * @access public
	 * @param string $group
	 * @param string $ext
	 * @param string $type
	 * @return void
	 */
	public function mime($group = '', $ext = '', $type = '') {
		if (empty($group)) {
			$group = 'misc';
		}
		
		if (!empty($ext) && !empty($type)) {
			$this->__mimeTypes[$group][$ext] = $type;
		}
	}
	
	/**
	 * Move a file to another destination.
	 *
	 * @access public
	 * @param string $origPath
	 * @param string $destPath
	 * @param boolean $overwrite
	 * @return boolean
	 */
	public function move($origPath, $destPath, $overwrite = false) {
		$destFull = WWW_ROOT . $destPath;
		$origFull = WWW_ROOT . $origPath;
		
		if (($origPath === $destPath) || (!file_exists($origFull)) || (!is_writable(dirname($destFull)))) {
			return false;
		}
		
		if ($overwrite === true) {
			if (file_exists($destFull)) {
				$this->delete($destPath);
			}
		} else {
			if (file_exists($destFull)) {
				$destination = $this->_destination(basename($destPath), false, 'moved', false);
				rename($destFull, $destination);
			}
		}
		
		return rename($origFull, $destFull);
	}
	
	/**
	 * Rename a file / Alias for move().
	 *
	 * @access public
	 * @param string $origPath
	 * @param string $destPath
	 * @param boolean $overwrite
	 * @return boolean
	 */
	public function rename($origPath, $destPath, $overwrite = false) {
		return $this->move($origPath, $destPath, $overwrite);
	}
	
	/**
	 * Resizes and image based off a previously uploaded image.
	 *
	 * @access public
	 * @param array $options
	 * - width, height: The width and height to resize the image to
	 * - quality: The quality of the image
	 * - append: What should be appended to the end of the filename
	 * - expand: Should the image be resized if the dimension is greater than the original dimension
	 * @return string
	 */
	public function resize($options) {
		if ($this->__data[$this->__current]['group'] != 'image' || $this->enableUpload === false) {
			return false;
		}
		
		$defaults = array('width' => null, 'height' => null, 'quality' => 100, 'append' => null, 'expand' => false);
		$options = array_merge($defaults, $options);
		
		$width = $this->__data[$this->__current]['width'];
		$height = $this->__data[$this->__current]['height'];
		$maxWidth = $options['width'];
		$maxHeight = $options['height'];
		
		if ($options['expand'] === false && (($maxWidth > $width) || ($maxHeight > $height))) {
			$newWidth = $width;
			$newHeight = $height;
		} else {
			if (is_numeric($maxWidth) && empty($maxHeight)) {
				$newWidth = $maxWidth;
				$newHeight = round(($height / $width) * $maxWidth);
			} else if (is_numeric($maxHeight) && empty($maxWidth)) {
				$newWidth = round(($width / $height) * $maxHeight);
				$newHeight = $maxHeight;
			} else if (is_numeric($maxHeight) && is_numeric($maxWidth)) {
				$newWidth = $maxWidth;
				$newHeight = $maxHeight;
			} else {
				return false;
			}
		}
		
		$append = $newWidth .'x'. $newHeight;
		if ($options['append'] !== null) {
			$append = $options['append'];
		}
		
		$transform = array(
			'width'		=> round($newWidth),
			'height'	=> round($newHeight),
			'target'	=> $this->_destination($this->__data[$this->__current]['name'], true, $append, false),
			'quality'	=> $options['quality']
		);
		
		if ($this->transform($transform)) {
			return $this->_return($transform['target'], $newWidth .'x'. $newHeight);
		} 
		
		return false;
	}
	
	/**
	 * Scale the image based on a percentage.
	 *
	 * @access public
	 * @param array $options
	 * - percent: What percentage should the image be scaled to, defaults to %50 (.5)
	 * - append: What should be appended to the end of the filename
	 * - quality: The quality of the image
	 * @return string
	 */
	public function scale($options = array()) {
		if ($this->__data[$this->__current]['group'] != 'image' || $this->enableUpload === false) {
			return false;
		}
		
		$defaults = array('percent' => .5, 'quality' => 100, 'append' => null);
		$options = array_merge($defaults, $options);
		
		$width = round($this->__data[$this->__current]['width'] * $options['percent']);
		$height = round($this->__data[$this->__current]['height'] * $options['percent']);
		
		$append = 'scaled_'. $width .'x'. $height;
		if ($options['append'] !== null) {
			$append = $options['append'];
		}
		
		$transform = array(
			'width'		=> $width,
			'height'	=> $height,
			'target'	=> $this->_destination($this->__data[$this->__current]['name'], true, $append, false),
			'quality'	=> $options['quality']
		);
		
		if ($this->transform($transform)) {
			return $this->_return($transform['target'], 'scaled_'. $width .'x'. $height);
		} 
		
		return false;
	}
	
	/**
	 * Main function for transforming an image.
	 *
	 * @access public
	 * @param array $options
	 * @return boolean
	 */
	public function transform($options) {
		$defaults = array('dest_x' => 0, 'dest_y' => 0, 'source_x' => 0, 'source_y' => 0, 'dest_w' => null, 'dest_h' => null, 'source_w' => $this->__data[$this->__current]['width'], 'source_h' => $this->__data[$this->__current]['height'], 'quality' => 100);
		$options = array_merge($defaults, $options);
		
		$original = $this->__data[$this->__current]['path'];
		$mimeType = $this->__data[$this->__current]['type'];
		
		if (empty($options['dest_w'])) {
			$options['dest_w'] = $options['width'];
		}
		
		if (empty($options['dest_h'])) {
			$options['dest_h'] = $options['height'];
		}
		
		// Create an image to work with
		switch ($mimeType) {
			case 'image/gif':  $source = imagecreatefromgif($original); break;
			case 'image/png':  $source = imagecreatefrompng($original); break;
			case 'image/jpg':
			case 'image/jpeg': $source = imagecreatefromjpeg($original);  break;
			default: return false; break;
		}
	
		$target = imagecreatetruecolor($options['width'], $options['height']);
		
		// If gif,png allow transparencies
		if ($mimeType == 'image/gif' || $mimeType == 'image/png') {
			imagealphablending($target, false);
			imagesavealpha($target, true);
			imagefilledrectangle($target, 0, 0, $options['width'], $options['height'], imagecolorallocatealpha($target, 255, 255, 255, 127));
		}
		
		// Lets take our source and apply it to the temporary file and resize
		imagecopyresampled($target, $source, $options['dest_x'], $options['dest_y'], $options['source_x'], $options['source_y'], $options['dest_w'], $options['dest_h'], $options['source_w'], $options['source_h']);
		
		// Now write the resized image to the server
		switch ($mimeType) {
			case 'image/gif':  imagegif($target, $options['target']); break;
			case 'image/png':  imagepng($target, $options['target']); break;
			case 'image/jpg':
			case 'image/jpeg': imagejpeg($target, $options['target'], $options['quality']); break;
			default:  
				imagedestroy($source);
				imagedestroy($target);
				return false;
			break;
		}
		
		// Clear memory
		imagedestroy($source);
		imagedestroy($target);
		return true;
	}
	
	/**
	 * Upload the file to the destination.
	 *
	 * @access public
	 * @param string $file
	 * @param array $options
	 *	- name: What should the filename be changed to
	 *	- overwrite: Should we overwrite the existant file with the same name?
	 *	- multiple: Is this method being called from uploadAll()
	 * @return mixed - Array on success, false on failure
	 */
	public function upload($file, $options = array()) {
		$defaults = array('name' => null, 'overwrite' => false, 'multiple' => false);
		$options = array_merge($defaults, $options);
		
		if ($options['multiple'] === false) {
			if ($this->enableUpload === false) {
				return false;
			} else {
				$this->_directory();
			}
		}
		
		if (isset($this->__data[$file])) {
			$this->__current = $file;
			$this->__data[$this->__current]['filesize'] = $this->bytes($this->__data[$this->__current]['size']);
			$this->__data[$this->__current]['ext'] = $this->ext($this->__data[$this->__current]['name']);
		} else {
			return false;
		}
		
		// Valid everything
		if ($this->__validates()) {
			if ($this->__data[$this->__current]['group'] == 'image') {
				$dimensions = $this->dimensions($this->__data[$this->__current]['tmp_name']);
				$this->__data[$this->__current]['width'] = $dimensions['width'];
				$this->__data[$this->__current]['height'] = $dimensions['height'];
			}
		} else {
			return false;
		}
		
		// Upload! Try both functions, one should work!
		$dest = $this->_destination($options['name'], $options['overwrite']);
		
		if (move_uploaded_file($this->__data[$this->__current]['tmp_name'], $dest)) { 
			$this->__data[$this->__current]['uploaded'] = date('Y-m-d H:i:s');
			
		} else if (copy($this->__data[$this->__current]['tmp_name'], $dest)) { 
			$this->__data[$this->__current]['uploaded'] = date('Y-m-d H:i:s');
			
		} else {
			return false;
		}
		
		chmod($dest, 0777);
		return $this->_return();
	}
	
	/**
	 * Upload multiple files, but have less configuration options and no transforming.
	 *
	 * @access public
	 * @param array $fields
	 * @param boolean $overwrite
	 * @return array
	 */
	public function uploadAll($fields = array(), $overwrite = false) {
		if ($this->enableUpload === false) {
			return false;
		} else {
			$this->_directory();
		}
		
		if (empty($fields) || !$fields) {
			$fields = array_keys($this->__data);
		}
		
		$data = array();
		if (!empty($fields)) {
			foreach ($fields as $field) {
				if (isset($this->__data[$field])) {
					if ($upload = $this->upload($field, array('overwrite' => $overwrite, 'multiple' => true))) {
						$data[$field] = $upload;
					}
				}
			}
		}
		
		return $data;
	}
	
	/**
	 * Check the destination folder.
	 *
	 * @access protected
	 * @uses Folder
	 * @return void
	 */
	protected function _directory() {
		if (!isset($this->Folder)) {
			$this->Folder = new Folder();
		}
		
		if (mb_substr($this->uploadDir, 0, 1) == '/') {
			$this->uploadDir = mb_substr($this->uploadDir, 1);
		}
		
		if (mb_substr($this->uploadDir, -1) != '/' && $this->uploadDir != '') {
			$this->uploadDir .'/';
		}
		
		$fullUploadDir = WWW_ROOT . $this->uploadDir;
		
		if (!is_dir($fullUploadDir)) {
			$this->Folder->create($fullUploadDir, 0777);
			
		} else if (!is_writable($fullUploadDir)) {
			$this->Folder->chmod($fullUploadDir, 0777, false); 
		}
		
		$this->finalDir = $fullUploadDir;
	}
	
	/**
	 * Determine the filename and path of the file.
	 *
	 * @access protected
	 * @param string $name
	 * @param boolean $overwrite
	 * @param string $append
	 * @param boolean $update
	 * @return string
	 */
	protected function _destination($name = '', $overwrite = false, $append = '', $update = true) {
		$name = $this->_filename($name, $append);
		$dest = $this->finalDir . $name;
		
		if (file_exists($dest) && $overwrite === false) {
			if (empty($append)) {
				$no = 1;
				while (file_exists($this->finalDir . $this->_filename($name, $no))) {
					$no++;
				}
				$name = $this->_filename($name, $no);
			} else {
				$name = $this->_filename($name, $append);
			}
			
			$dest = $this->finalDir . $name;
		}
		
		if ($update === true) {
			$this->__data[$this->__current]['name'] = $name;
			$this->__data[$this->__current]['path'] = $dest;
		}
		
		return $dest;
	}
	
	/**
	 * Determines the name of the file.
	 *
	 * @access protected
	 * @param string $name
	 * @param string $append
	 * @param boolean $update
	 * @return void
	 */
	protected function _filename($name = '', $append = '', $truncate = true) {
		if (empty($name)) {
			$name = $this->__data[$this->__current]['name'];
		}
		
		$ext = $this->ext($name);
		if (empty($ext)) {
			$ext = $this->__data[$this->__current]['ext'];
		}
		
		$name = str_replace('.'. $ext, '', $name);
		$name = preg_replace(array('/[^-_.a-zA-Z0-9\s]/i', '/[\s]/'), array('', '_'), $name);
		
		if (is_numeric($this->maxNameLength) && $truncate === true) {
			if (mb_strlen($name) > $this->maxNameLength) {
				$name = mb_substr($name, 0, $this->maxNameLength);
			}
		}
		
		if (!empty($append) && (is_string($append) || is_numeric($append))) {
			$append = preg_replace(array('/[^-_.a-zA-Z0-9\s]/i', '/[\s]/'), array('', '_'), $append);
			$name = $name .'_'. $append;
		} 
			
		$name = $name .'.'. $ext;
		$name = trim($name, '/');
		
		return $name;
	}
	
	/**
	 * Formates and returns the data array.
	 *
	 * @access protected
	 * @param string $target
	 * @param string $append
	 * @return array
	 */
	protected function _return($target = '', $append = '') {
		$root = WWW_ROOT;
		
		if (!empty($target) && !empty($append)) {
			$this->__data[$this->__current]['path_'. $append] = $target;
			$this->__logs[$this->__current]['path_'. $append] = $target;
			chmod($target, 0777);
			
			return str_replace($root, '/', $target);
			
		} else {
			$data = $this->__data[$this->__current];
			unset($data['tmp_name'], $data['error']);
			
			foreach ($data as $key => $value) {
				if (strpos($key, 'path') !== false) {
					$data[$key] = str_replace($root, '/', $data[$key]);
				}
			}
			
			$this->__logs[$this->__current] = $data;
			return $data;
		}
	}	
	
	/**
	 * Parses the controller data to only grab $_FILES related data.
	 *
	 * @access private
	 * @param array $data
     * @param string $model
     * @param int $count
	 * @return void
	 */
	private function __parseData($data, $model = null, $count = 1) {
		if (is_array($data)) {
			foreach ($data as $field => $value) {
                if (is_array($value) && isset($value['tmp_name'])) {
                    if ($count == 1) {
                        $slug = $field;
                    } else {
                        $slug = $model .'.'. $field;
                    }

                    $this->__data[$slug] = $value;
                } else {
                    $this->__parseData($value, $field, $count);
                }
			}
		}
	}

	/**
	 * Does validation on the current upload.
	 * 
	 * @access private
	 * @return boolean
	 */
	private function __validates() {
		$validExt = false;
		$validMime = false;
	
		// Check valid mime type!
		if (!isset($this->__data[$this->__current]['group'])) {
			$this->__data[$this->__current]['group'] = '';
		}

		foreach ($this->__mimeTypes as $grouping => $mimes) {
			if (isset($mimes[$this->__data[$this->__current]['ext']])) {
				$validExt = true;
			}

			$currType = mb_strtolower($this->__data[$this->__current]['type']);
			
			foreach ($mimes as $mimeExt => $mimeType) {
				if (($currType == $mimeType) || (is_array($mimeType) && in_array($currType, $mimeType))) {
					$validMime = true;
					break 2;
				}
			}
		}
		
		if ($validExt === true && $validMime === true) {
			$this->__data[$this->__current]['group'] = $grouping;
		} else {
			return false;
		}
		
		// Correctly uploaded?
		if (
			($this->__data[$this->__current]['error'] > 0) ||
			(!is_uploaded_file($this->__data[$this->__current]['tmp_name'])) ||
			(!is_file($this->__data[$this->__current]['tmp_name'])))
		{
			return false;
		}
			
		// Requires the ClamAV module to be installed
		// http://www.clamav.net/
		if ($this->scanFile === true) {
			if (!extension_loaded('clamav')) {
				@dl('clamav.'. PHP_SHLIB_SUFFIX);
			}
			
			if (extension_loaded('clamav')) {
				cl_setlimits(5, 1000, 200, 0, 10485760);
				//clam_get_version();
			
				if ($malware = cl_scanfile($this->__data[$this->__current]['tmp_name'])) {
					return false;
				}
			}
		}
		
		return true;
	}
	
}
