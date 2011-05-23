<?php 
/** 
 * Uploader Component
 *
 * A CakePHP Component that will upload a wide range of file types. Each file will be uploaded into app/webroot/<upload dir> (the path your provide).
 * Security and type checking have been integrated to only allow valid files. Additionally, images have the option of transforming an image.
 *
 * @author      Miles Johnson - http://milesj.me
 * @copyright   Copyright 2006-2011, Miles Johnson, Inc.
 * @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link        http://milesj.me/code/cakephp/uploader
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
	 * Max filesize using shorthand notation: http://php.net/manual/faq.using.php#faq.using.shorthandbytes
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
	 * Should we scan the file for viruses? Requires ClamAV module: http://clamav.net/
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
	 * Base upload directory; usually Cake webroot.
	 *
	 * @access public
	 * @var string
	 */
	public $baseDir = WWW_ROOT;

	/**
	 * Destination upload directory within $baseDir.
	 *
	 * @access public
	 * @var string
	 */
	public $uploadDir = 'files/uploads/';

	/**
	 * The final formatted directory.
	 *
	 * @access public
	 * @var string
	 */
	public $finalDir;

	/**
	 * The accepted file/mime types; imported from config.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_mimeTypes = array();

	/**
	 * Holds the current $_FILES data.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_data = array();

	/**
	 * Holds the the logged uploads.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_logs = array();

	/**
	 * The current file being processed.
	 *
	 * @access protected
	 * @var string
	 */
	protected $_current;

	/**
	 * Load the controllers file data into the component.
	 *
	 * @access public
	 * @param object $Controller
	 * @param array $settings
	 * @return void
	 */
	public function initialize($Controller, array $settings = array()) {
		$this->_mimeTypes = Configure::read('Uploader.mimeTypes');

		if (!$this->_loadExtension('gd')) {
			$this->enableUpload = false;
			trigger_error('Uploader.Uploader::initialize(): GD image library is not installed.', E_USER_WARNING);
		}

		$data = $Controller->data;
		unset($data['_Token']);

		$this->_set($settings);
		$this->_parseData($data, null, count($data));
	}

	/**
	 * Set our ini settings for future use.
	 *
	 * @access public
	 * @uses Folder
	 * @param object $Controller
	 * @return void
	 */
	public function startup($Controller) {
		$fileUploads = ini_get('file_uploads');

		if (!$fileUploads) {
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
			$Folder = new Folder();
			$Folder->chmod($this->tempDir, 0777, false);
		}

		$this->baseDir = str_replace('\\', '/', $this->baseDir);
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
	public function addMimeType($group = null, $ext = null, $type = null) {
		if (empty($group)) {
			$group = 'misc';
		}

		if (!empty($ext) && !empty($type)) {
			$this->_mimeTypes[$group][$ext] = $type;
		}
	}

	/**
	 * Return the bytes based off the shorthand notation.
	 *
	 * @access public
	 * @param int $size
	 * @param string $return
	 * @return string
	 */
	public function bytes($size, $return = null) {
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

		while ($total-- && $size > 1024) {
			$size /= 1024;
		}

		$bytes = round($size, 0) .' '. $sizes[$total];
		return $bytes;
	}

	/**
	 * Check the destination folder. If it does not exist or isn't writable, fix it!
	 *
	 * @access public
	 * @uses Folder
	 * @return void
	 */
	public function checkDirectory() {
		$Folder = new Folder();
		$uploadDir = trim($this->uploadDir, '/');
		$finalDir = $this->formatPath($uploadDir .'/');

		if (!is_dir($finalDir)) {
			$dirParts = explode('/', $uploadDir);
			$dirCurrent = rtrim($this->baseDir, '/');

			foreach ($dirParts as $part) {
				$Folder->create($dirCurrent . DS . $part, 0777);
				$dirCurrent .= DS . $part;
			}
		} else if (!is_writable($finalDir)) {
			$Folder->chmod($finalDir, 0777, false);
		}

		$this->finalDir = $finalDir;
	}

	/**
	 * Check the extension and mimetype against the supported list. If found, return the grouping.
	 *
	 * @access public
	 * @param string $ext
	 * @param string $type
	 * @return mixed
	 */
	public function checkMimeType($ext, $type) {
		$validExt = false;
		$validMime = false;
		$currType = mb_strtolower($type);

		foreach ($this->_mimeTypes as $grouping => $mimes) {
			if (isset($mimes[$ext])) {
				$validExt = true;
			}

			foreach ($mimes as $mimeExt => $mimeType) {
				if (($currType == $mimeType) || (is_array($mimeType) && in_array($currType, $mimeType))) {
					$validMime = true;
					break 2;
				}
			}
		}

		if ($validExt && $validMime) {
			return $grouping;
		}

		return false;
	}

	/**
	 * Crops a photo, resizes first depending on which side is larger.
	 *
	 * @access public
	 * @param array $options
	 *		- location: Which area of the image should be grabbed for the crop: center, left, right, top, bottom
	 *		- width,
	 *		- height: The width and height to resize the image to before cropping
	 *		- append: What should be appended to the end of the filename (defaults to dimensions if not set)
	 *		- prepend: What should be prepended to the front of the filename
	 *		- quality: The quality of the image
	 * @param boolean $explicit
	 * @return mixed
	 */
	public function crop(array $options = array(), $explicit = false) {
		if ($this->_data[$this->_current]['group'] != 'image' || $this->enableUpload === false) {
			return false;
		}

		$options = $options + array('location' => self::LOC_CENTER, 'quality' => 100, 'width' => null, 'height' => null, 'append' => null, 'prepend' => null);
		$width	= $this->_data[$this->_current]['width'];
		$height = $this->_data[$this->_current]['height'];
		$src_x	= 0;
		$src_y	= 0;
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

		$append = '_cropped_'. $newWidth .'x'. $newHeight;

		if ($options['append'] !== false && empty($options['append'])) {
			$options['append'] = $append;
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
			'target'	=> $this->setDestination($this->_data[$this->_current]['name'], true, $options, false),
			'quality'	=> $options['quality']
		);

		if ($this->transform($transform)) {
			return $this->_returnData($transform, $append, $explicit);
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
	public function delete($path) {
		$path = $this->formatPath($path);

		if (is_file($path)) {
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
	public function dimensions($path) {
		$dim = array();

		foreach (array($path, $this->formatPath($path)) as $newPath) {
			$data = @getimagesize($path);

			if (!empty($data) && is_array($data)) {
				$dim = array(
					'width' => $data[0],
					'height' => $data[1],
					'type' => $data['mime']
				);
			}
		}

		if (empty($dim)) {
			$Http = new HttpSocket();
			$data = $Http->request($path);
			$image = @imagecreatefromstring($data);

			$dim = array(
				'width' => @imagesx($image),
				'height' => @imagesy($image),
				'type' => $this->mimeType($path)
			);
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
	 *		- dir: The direction the image should be flipped
	 *		- append: What should be appended to the end of the filename (defaults to flip direction if not set)
	 *		- prepend: What should be prepended to the front of the filename
	 *		- quality: The quality of the image
	 * @param boolean $explicit
	 * @return string
	 */
	public function flip(array $options = array(), $explicit = false) {
		if ($this->_data[$this->_current]['group'] != 'image' || $this->enableUpload === false) {
			return false;
		}

		$options = $options + array('dir' => self::DIR_VERT, 'quality' => 100, 'append' => null, 'prepend' => null);
		$width	= $this->_data[$this->_current]['width'];
		$height = $this->_data[$this->_current]['height'];
		$src_x	= 0;
		$src_y	= 0;
		$src_w	= $width;
		$src_h	= $height;

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
			default:
				return false;
			break;
		}

		$append = '_flip_'. $adir;

		if ($options['append'] !== false && empty($options['append'])) {
			$options['append'] = $append;
		}

		$transform = array(
			'width'		=> $width,
			'height'	=> $height,
			'source_x'	=> $src_x,
			'source_y'	=> $src_y,
			'source_w'	=> $src_w,
			'source_h'	=> $src_h,
			'target'	=> $this->setDestination($this->_data[$this->_current]['name'], true, $options, false),
			'quality'	=> $options['quality']
		);

		if ($this->transform($transform)) {
			return $this->_returnData($transform, $append, $explicit);
		}

		return false;
	}


	/**
	 * Determines the name of the file.
	 *
	 * @access public
	 * @param string $name
	 * @param string $append
	 * @param string $prepend
	 * @param boolean $truncate
	 * @return void
	 */
	public function formatFilename($name = '', $append = '', $prepend = '', $truncate = true) {
		if (empty($name)) {
			$name = $this->_data[$this->_current]['name'];
		}

		$ext = $this->ext($name);

		if (empty($ext)) {
			$ext = $this->_data[$this->_current]['ext'];
		}

		$name = str_replace('.'. $ext, '', $name);
		$name = preg_replace(array('/[^-_.a-zA-Z0-9\s]/i', '/[\s]/'), array('', '_'), $name);

		if (is_numeric($this->maxNameLength) && $truncate) {
			if (mb_strlen($name) > $this->maxNameLength) {
				$name = mb_substr($name, 0, $this->maxNameLength);
			}
		}

		$append = (string)$append;
		$prepend = (string)$prepend;

		if (!empty($append)) {
			$append = preg_replace(array('/[^-_.a-zA-Z0-9\s]/i', '/[\s]/'), array('', '_'), $append);
			$name = $name . $append;
		}

		if (!empty($prepend)) {
			$prepend = preg_replace(array('/[^-_.a-zA-Z0-9\s]/i', '/[\s]/'), array('', '_'), $prepend);
			$name = $prepend . $name;
		}

		$name = $name .'.'. $ext;
		$name = trim($name, '/');

		return $name;
	}

	/**
	 * Return the path with the base directory if it is absent.
	 *
	 * @access public
	 * @param string $path
	 * @return string
	 */
	public function formatPath($path) {
		if (substr($this->baseDir, -1) != '/') {
			$this->baseDir .= '/';
		}

		if (strpos($path, $this->baseDir) === false) {
			$path = $this->baseDir . $path;
		}

		return $path;
	}

	/**
	 * Import a file from the local filesystem and create a copy of it. Requires a full absolute path.
	 *
	 * @access public
	 * @param string $path
	 * @param array $options
	 *		- name: What should the filename be changed to
	 *		- overwrite: Should we overwrite the existant file with the same name?
	 *		- delete: Delete the original file after importing
	 * @return mixed - Array on success, false on failure
	 */
	public function import($path, array $options = array()) {
		if (!$this->enableUpload || !is_file($path)) {
			return false;
		} else {
			$this->checkDirectory();
		}

		$options = $options + array('name' => null, 'overwrite' => false, 'delete' => false);

		$this->_current = basename($path);
		$this->_data[$this->_current]['path'] = $path;
		$this->_data[$this->_current]['type'] = $this->mimeType($path);
		$this->_data[$this->_current]['ext'] = $this->ext($path);

		// Validate everything
		if ($this->_validates(true)) {
			if ($this->_data[$this->_current]['group'] == 'image') {
				$dimensions = $this->dimensions($path);

				$this->_data[$this->_current]['width'] = $dimensions['width'];
				$this->_data[$this->_current]['height'] = $dimensions['height'];
			}
		} else {
			return false;
		}

		// Make a copy of the local file
		$dest = $this->setDestination($options['name'], $options['overwrite']);

		if (copy($path, $dest)) {
			$this->_data[$this->_current]['uploaded'] = date('Y-m-d H:i:s');
			$this->_data[$this->_current]['filesize'] = $this->bytes(filesize($path));

			if ($options['delete']) {
				@unlink($path);
			}
		} else {
			return false;
		}

		chmod($dest, 0777);
		return $this->_returnData();
	}

	/**
	 * Import a file from an external remote URL. Must be an absolute URL.
	 *
	 * @access public
	 * @uses HttpSocket
	 * @param string $path
	 * @param array $options
	 *		- name: What should the filename be changed to
	 *		- overwrite: Should we overwrite the existant file with the same name?
	 * @return mixed - Array on success, false on failure
	 */
	public function importRemote($url, array $options = array()) {
		if (!$this->enableUpload) {
			return false;
		} else {
			$this->checkDirectory();
		}

		$options = $options + array('name' => null, 'overwrite' => false);

		$this->_current = basename($url);
		$this->_data[$this->_current]['path'] = $url;
		$this->_data[$this->_current]['type'] = $this->mimeType($url);
		$this->_data[$this->_current]['ext'] = $this->ext($url);
		
		// Validate everything
		if (!$this->_validates(true)) {
			return false;
		}

		// Make a copy of the remote file
		$dest = $this->setDestination($options['name'], $options['overwrite']);
		$Http = new HttpSocket();

		if (file_put_contents($dest, $Http->request($url))) {
			$this->_data[$this->_current]['uploaded'] = date('Y-m-d H:i:s');
			$this->_data[$this->_current]['filesize'] = $this->bytes(filesize($dest));

			if ($this->_data[$this->_current]['group'] == 'image') {
				$dimensions = $this->dimensions($dest);

				$this->_data[$this->_current]['width'] = $dimensions['width'];
				$this->_data[$this->_current]['height'] = $dimensions['height'];
			}
		} else {
			return false;
		}

		chmod($dest, 0777);
		return $this->_returnData();
	}

	/**
	 * Returns the mimetype of a given file.
	 *
	 * @access public
	 * @param string $path
	 * @return string
	 */
	public function mimeType($path) {
		if (function_exists('mime_content_type') && is_file($path)) {
			return mime_content_type($path);
		}

		$ext = $this->ext($path);
		$type = null;

		foreach ($this->_mimeTypes as $group => $mimes) {
			if (in_array($ext, array_keys($mimes))) {
				$type = $this->_mimeTypes[$group][$ext];
				break;
			}
		}

		if (is_array($type)) {
			$type = $type[0];
		}

		return $type;
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
		$destFull = $this->formatPath($destPath);
		$origFull = $this->formatPath($origPath);

		if (($origPath === $destPath) || !file_exists($origFull) || !is_writable(dirname($destFull))) {
			return false;
		}

		if ($overwrite) {
			if (file_exists($destFull)) {
				$this->delete($destPath);
			}
		} else {
			if (file_exists($destFull)) {
				$destination = $this->setDestination(basename($destPath), false, array('append' => '_moved'), false);
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
	 *		- width,
	 *		- height: The width and height to resize the image to
	 *		- quality: The quality of the image
	 *		- append: What should be appended to the end of the filename (defaults to dimensions if not set)
	 *		- prepend: What should be prepended to the front of the filename
	 *		- expand: Should the image be resized if the dimension is greater than the original dimension
	 * @param boolean $explicit
	 * @return string
	 */
	public function resize(array $options, $explicit = false) {
		if ($this->_data[$this->_current]['group'] != 'image' || $this->enableUpload === false) {
			return false;
		}

		$options = $options + array('width' => null, 'height' => null, 'quality' => 100, 'append' => null, 'prepend' => null, 'expand' => false);
		$width = $this->_data[$this->_current]['width'];
		$height = $this->_data[$this->_current]['height'];
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

		$newWidth = round($newWidth);
		$newHeight = round($newHeight);
		$append = '_'. $newWidth .'x'. $newHeight;

		if ($options['append'] !== false && empty($options['append'])) {
			$options['append'] = $append;
		}

		$transform = array(
			'width'		=> $newWidth,
			'height'	=> $newHeight,
			'target'	=> $this->setDestination($this->_data[$this->_current]['name'], true, $options, false),
			'quality'	=> $options['quality']
		);

		if ($this->transform($transform)) {
			return $this->_returnData($transform, $append, $explicit);
		}

		return false;
	}

	/**
	 * Scale the image based on a percentage.
	 *
	 * @access public
	 * @param array $options
	 *		- percent: What percentage should the image be scaled to, defaults to %50 (.5)
	 *		- append: What should be appended to the end of the filename (defaults to dimensions if not set)
	 *		- prepend: What should be prepended to the front of the filename
	 *		- quality: The quality of the image
	 * @param boolean $explicit
	 * @return string
	 */
	public function scale(array $options = array(), $explicit = false) {
		if ($this->_data[$this->_current]['group'] != 'image' || $this->enableUpload === false) {
			return false;
		}

		$options = $options + array('percent' => .5, 'quality' => 100, 'append' => null, 'prepend' => null);
		$width = round($this->_data[$this->_current]['width'] * $options['percent']);
		$height = round($this->_data[$this->_current]['height'] * $options['percent']);

		$append = '_scaled_'. $width .'x'. $height;

		if ($options['append'] !== false && empty($options['append'])) {
			$options['append'] = $append;
		}

		$transform = array(
			'width'		=> $width,
			'height'	=> $height,
			'target'	=> $this->setDestination($this->_data[$this->_current]['name'], true, $options, false),
			'quality'	=> $options['quality']
		);

		if ($this->transform($transform)) {
			return $this->_returnData($transform, $append, $explicit);
		}

		return false;
	}

	/**
	 * Determine the filename and path of the file.
	 *
	 * @access public
	 * @param string $name
	 * @param boolean $overwrite
	 * @param array $options
	 * @param boolean $update
	 * @return string
	 */
	public function setDestination($name = '', $overwrite = false, array $options = array(), $update = true) {
		$append = isset($options['append']) ? $options['append'] : '';
		$prepend = isset($options['prepend']) ? $options['prepend'] : '';
		$name = $this->formatFilename($name, $append, $prepend);
		$dest = $this->finalDir . $name;

		if (file_exists($dest) && !$overwrite) {
			$no = 1;

			while (file_exists($this->finalDir . $this->formatFilename($name, $append . $no, $prepend))) {
				$no++;
			}

			$name = $this->formatFilename($name, $append . $no, $prepend);
			$dest = $this->finalDir . $name;
		}

		if ($update) {
			$this->_data[$this->_current]['name'] = $name;
			$this->_data[$this->_current]['path'] = $dest;
		}

		return $dest;
	}

	/**
	 * Main function for transforming an image.
	 *
	 * @access public
	 * @param array $options
	 * @return boolean
	 */
	public function transform(array $options) {
		$options = $options + array('dest_x' => 0, 'dest_y' => 0, 'source_x' => 0, 'source_y' => 0, 'dest_w' => null, 'dest_h' => null, 'source_w' => $this->_data[$this->_current]['width'], 'source_h' => $this->_data[$this->_current]['height'], 'quality' => 100);
		$original = $this->_data[$this->_current]['path'];
		$mimeType = $this->_data[$this->_current]['type'];

		if (empty($options['dest_w'])) {
			$options['dest_w'] = $options['width'];
		}

		if (empty($options['dest_h'])) {
			$options['dest_h'] = $options['height'];
		}

		// Create an image to work with
		switch ($mimeType) {
			case 'image/gif':
				$source = imagecreatefromgif($original);
			break;
			case 'image/png':
				$source = imagecreatefrompng($original);
			break;
			case 'image/jpg':
			case 'image/jpeg':
			case 'image/pjpeg':
				$source = imagecreatefromjpeg($original);
			break;
			default:
				return false;
			break;
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
			case 'image/gif':
				imagegif($target, $options['target']);
			break;
			case 'image/png':
				imagepng($target, $options['target']);
			break;
			case 'image/jpg':
			case 'image/jpeg':
			case 'image/pjpeg':
				imagejpeg($target, $options['target'], $options['quality']);
			break;
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
	 *		- name: What should the filename be changed to
	 *		- overwrite: Should we overwrite the existant file with the same name?
	 *		- multiple: Is this method being called from uploadAll()
	 * @return mixed - Array on success, false on failure
	 */
	public function upload($file, array $options = array()) {
		$options = $options + array('name' => null, 'overwrite' => false, 'multiple' => false);

		if (!$options['multiple']) {
			if (!$this->enableUpload) {
				return false;
			} else {
				$this->checkDirectory();
			}
		}

		if (isset($this->_data[$file])) {
			$this->_current = $file;
			$this->_data[$this->_current]['filesize'] = $this->bytes($this->_data[$this->_current]['size']);
			$this->_data[$this->_current]['ext'] = $this->ext($this->_data[$this->_current]['name']);
		} else {
			return false;
		}

		// Validate everything
		if ($this->_validates()) {
			if ($this->_data[$this->_current]['group'] == 'image') {
				$dimensions = $this->dimensions($this->_data[$this->_current]['tmp_name']);

				$this->_data[$this->_current]['width'] = $dimensions['width'];
				$this->_data[$this->_current]['height'] = $dimensions['height'];
			}
		} else {
			return false;
		}

		// Upload! Try both functions, one should work!
		$dest = $this->setDestination($options['name'], $options['overwrite']);

		if (move_uploaded_file($this->_data[$this->_current]['tmp_name'], $dest)) {
			$this->_data[$this->_current]['uploaded'] = date('Y-m-d H:i:s');

		} else if (copy($this->_data[$this->_current]['tmp_name'], $dest)) {
			$this->_data[$this->_current]['uploaded'] = date('Y-m-d H:i:s');

		} else {
			return false;
		}

		chmod($dest, 0777);
		return $this->_returnData();
	}

	/**
	 * Upload multiple files, but have less configuration options and no transforming.
	 *
	 * @access public
	 * @param array $fields
	 * @param boolean $overwrite
	 * @param boolean $rollback
	 * @return array
	 */
	public function uploadAll(array $fields = array(), $overwrite = false, $rollback = true) {
		if (!$this->enableUpload) {
			return false;
		} else {
			$this->checkDirectory();
		}

		if (empty($fields) || !$fields) {
			$fields = array_keys($this->_data);
		}

		$data = array();
		$fail = false;

		if (!empty($fields)) {
			foreach ($fields as $field) {
				if (isset($this->_data[$field])) {
					$upload = $this->upload($field, array('overwrite' => $overwrite, 'multiple' => true));

					if (!empty($upload)) {
						$data[$field] = $upload;
					} else {
						$fail = true;
						break;
					}
				}
			}
		}

		if ($fail) {
			if ($rollback && !empty($data)) {
				foreach ($data as $file) {
					$this->delete($file['path']);
				}
			}

			return false;
		}

		return $data;
	}

	/**
	 * Attempt to load a missing extension.
	 *
	 * @access protected
	 * @param string $name
	 * @return boolean
	 */
	protected function _loadExtension($name) {
		if (!extension_loaded($name)) {
			@dl((PHP_SHLIB_SUFFIX == 'dll' ? 'php_' : '') . $name .'.'. PHP_SHLIB_SUFFIX);
		}

		return extension_loaded($name);
	}

	/**
	 * Parses the controller data to only grab $_FILES related data.
	 *
	 * @access protected
	 * @param array $data
	 * @param string $model
	 * @param int $count
	 * @return void
	 */
	protected function _parseData($data, $model = null, $count = 1) {
		if (is_array($data)) {
			foreach ($data as $field => $value) {
				if (is_array($value) && isset($value['tmp_name'])) {
					if ($count == 1) {
						$slug = $field;
					} else {
						$slug = $model .'.'. $field;
					}

					$this->_data[$slug] = $value;
				} else {
					$this->_parseData($value, $field, $count);
				}
			}
		}
	}

	/**
	 * Formates and returns the data array.
	 *
	 * @access protected
	 * @param array $data
	 * @param string $append
	 * @param boolean $explicit
	 * @return array
	 */
	protected function _returnData($data = '', $append = '', $explicit = false) {
		if (!empty($data) && !empty($append)) {
			$this->_data[$this->_current]['path_'. trim($append, '_')] = $data['target'];
			$this->_logs[$this->_current]['path_'. trim($append, '_')] = $data['target'];

			chmod($data['target'], 0777);
			$path = str_replace($this->baseDir, '/', $data['target']);

			if ($explicit) {
				return array(
					'path' => $path,
					'width' => $data['width'],
					'height' => $data['height']
				);
			} else {
				return $path;
			}

		} else {
			$data = $this->_data[$this->_current];
			unset($data['tmp_name'], $data['error']);

			foreach ($data as $key => $value) {
				if (strpos($key, 'path') !== false) {
					$data[$key] = str_replace($this->baseDir, '/', $data[$key]);
				}
			}

			$this->_logs[$this->_current] = $data;
			return $data;
		}
	}

	/**
	 * Does validation on the current upload.
	 *
	 * @access protected
	 * @param boolean $import
	 * @return boolean
	 */
	protected function _validates($import = false) {
		$current = $this->_data[$this->_current];
		$grouping = $this->checkMimeType($current['ext'], $current['type']);

		if ($grouping) {
			$this->_data[$this->_current]['group'] = $grouping;
			
		} else if (!$import) {
			return false;
		}

		// Only validate uploaded files, not imported
		if (!$import) {
			if (($current['error'] > 0) || !is_uploaded_file($current['tmp_name']) || !is_file($current['tmp_name'])) {
				return false;
			}

			// Requires the ClamAV module to be installed
			if ($this->scanFile && $this->_loadExtension('clamav')) {
				cl_setlimits(5, 1000, 200, 0, 10485760);

				if (cl_scanfile($current['tmp_name'])) {
					return false;
				}
			}
		}

		return true;
	}

}
