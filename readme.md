# Uploader v4.5.2 #

A CakePHP plugin that will validate and upload files through the model layer.
Provides support for image transformation and remote storage transportation.

## Requirements ##

* PHP 5.3.3
    * Fileinfo
    * Multibyte
    * Curl
* CakePHP 2
* Composer

## Compatibility ##

* v2.x - CakePHP 1.3, PHP 5.2
* v3.x - CakePHP 2, PHP 5.2
* v4.x - CakePHP 2, PHP 5.3, Composer

## Features ##

* Upload files automatically through Model::save() by using the AttachmentBehavior
* Validate files automatically through Model::save() by using the FileValidationBehavior
* Extensive list of validation rules: size, ext, type, height, width, etc
* Transform the uploaded image or create new images: crop, scale, rotate, flip, etc
* Automatic file deletion when a database record is deleted or updated
* Supports transporting files to remote storage systems like AWS

## Documentation ##

Thorough documentation can be found here: http://milesj.me/code/cakephp/uploader