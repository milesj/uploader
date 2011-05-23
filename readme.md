# Uploader v2.7 #

A CakePHP plugin that will upload multiple types of files. Adds support for file validation and attachments within the model layer, the ability to transform image files (crop, resize, scale, etc) and minor support for Amazon S3 transfers.

## Requirements ##

* CakePHP 1.2.x., 1.3.x
* PHP 5.2.x, 5.3.x
* ClamAV module (for virus detection) - http://sourceforge.net/projects/php-clamav/

## Features ##

* Automatically sets all ini settings required for file uploading
* Support for a wide range of mime types: text, images, archives, audio, video, application
* Logs all internal errors that can be retrieved and displayed
* Saves a log for all uploads happening during the current request
* Automatically validates against the default mime types and internal errors
* Can scan the uploaded files for viruses using the ClamAV module
* Files can be uploaded anywhere within the webroot folder
* Convenience methods for deleting a file, moving/renaming a file and getting the file extension or dimensions
* Built in methods for resizing images and generating thumbnails
* Custom Behavior to add validation rules to your Models validation set
* Custom Behavior that allows models to attach files to automatically upload the file and save its information to a database
* Component to support the Amazon S3 system, allowing you to transfer your files to the bucket
* S3 Transfer functionality is also inherited into the Attachment Behavior

## Documentation ##

Thorough documentation can be found here: http://milesj.me/code/cakephp/uploader

## Installation ##

Clone the repo into a folder called "uploader" within your plugins directory.
