# Uploader v3.5 #

A CakePHP plugin that will upload multiple types of files. Adds support for file validation and attachments within the model layer, the ability to transform image files (crop, resize, scale, etc) and minor support for Amazon S3 transfers.

This version is only compatible with CakePHP 2.

## Compatibility ##

* v2.x - CakePHP 1.3
* v3.x - CakePHP 2

## Requirements ##

* PHP 5.2, 5.3
* Multibyte - http://php.net/manual/book.mbstring.php
* ClamAV module (for virus detection) - http://sourceforge.net/projects/php-clamav/

## Features ##

* AttachmentBehavior allows models to attach files that automatically upload the file and save its information to a database
* FileValidationBehavior adds file upload validation rules to your Models validation set
* Support for a wide range of mime types: text, images, archives, audio, video, application
* Logs all internal errors that can be retrieved and displayed
* Automatically validates against the default mime types and internal errors
* Can scan the uploaded files for viruses using the ClamAV module
* Files can be uploaded anywhere with custom settings
* Convenience methods for deleting a file, moving/renaming a file and getting the file extension or dimensions
* Supports the Amazon S3 system, allowing you to transfer your files to a bucket

## Documentation ##

Thorough documentation can be found here: http://milesj.me/code/cakephp/uploader