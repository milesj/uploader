# Changelog #

*These logs may be outdated or incomplete.*

## 4.5.4 ##

* Includes changes from previous versions
* Added RackSpace CloudFiles support
* Fixed a bug where merging settings fails with invalid types [[#162](https://github.com/milesj/uploader/issues/162)]
* Fixed a bug where association transformation images were not being deleted [[#163](https://github.com/milesj/uploader/issues/163)]
* Fixed a bug where exif data could throw nulls
* Fixed a bug where virtual fields cause find queries to fail [[#174](https://github.com/milesj/uploader/issues/174)]

## 4.5.0 ##

* Added a `cleanup` setting to `AttachmentBehavior`
* Fixed a bug where exif data wasn't fetched before transforming occurs [[#157](https://github.com/milesj/uploader/issues/157)]
* Streams and local files are now imported before file validation occurs [[#155](https://github.com/milesj/uploader/issues/155)]

## 4.4.0 ##

* Includes changes from previous versions
* Fixed interface changes for CakePHP 2.4
* Fixed an issue with file validation failing on PHP 5.3 [[#136](https://github.com/milesj/uploader/issues/136)]
* Fixed empty upload error logging problem [[#152](https://github.com/milesj/uploader/issues/152)]
* Fixed record deletion problem because of invalid model ID [[#149](https://github.com/milesj/uploader/issues/149)]
* Refactored so that `defaultPath` is applied in `afterFind()` [[#147](https://github.com/milesj/uploader/issues/147)]
* Added support for custom transformers and transporters

## 4.3.1 ##

* Erase meta data when calling `AttachmentBehavior.deleteFiles()` [[#130](https://github.com/milesj/Uploader/issues/130)]

## 4.3.0 ##

* Includes changes from previous minor versions
* Updated Transit to v1.4.0
* Added `transportDir` setting for uploads and transforms which allow custom transport directory paths [[Issue #125](https://github.com/milesj/Uploader/issues/125)]
* Added `fit` transformer that allows for fitting an image to a certain size while applying a background fill
* Added 4th argument to `AttachmentBehavior.deleteFiles()` to circumvent database hit
* Reversed move and rename logic so transformed files do not conflict
* Fixed bug where empty file paths trigger error on deletion [[Issue #126](https://github.com/milesj/Uploader/issues/126)]

## 4.2.0 ##

* Updated [Transit](http://milesj.me/code/php/transit) requirement to `1.3`
* Added a `rotate` transformer that accepts a `degrees` setting
* Added a `exif` transformer that fixes orientation and strips Exif data (should be applied first with `self`)
* Added a `defaultPath` setting to transforms

## 4.1.2 ##

* Added a `curl` settings array for remote importing

## 4.1.1 ##

* Updated to save absolute path into database
* Updated `finalPath` to take precedence over `uploadPath` (now optional)
* Fixed a bug where save would fail when `dbColumn` is empty

## 4.1.0 ##

* Includes changes from 4.0.12 - 4.0.15
* Updated [Transit](http://milesj.me/code/php/transit) requirement to `1.2`
* Updated extension parsing to extract the value from the filename
* Updated mimetype parsing to extract the value from multiple sources: `file -b --mime` command, then `fileinfo`, then `$_FILES['type']`
* Fixed a bug where `beforeUpload()`, `beforeTransform()` and `beforeTransport()` callbacks were not being triggered for record deletions
* Fixed a bug where `$this->data` is not available within callbacks

## 4.0.11 ##

* AWS SDK is no longer installed automatically via Composer (add "aws/aws-sdk-php": "~2.2" to your composer.json)
* Fixed bug where multiple transforms and transports get mixed up

## 4.0.10 ##

* Fixed a bug where empty file uploads transform fields don't get reset

## 4.0.9 ##

* Fixed problems with import validation
* Fixed existent model validation rules conflicting with the upload rules
* Updated import validation to use Transit

## 4.0.8 ##

* Removed special extension validation as Transit now handles it properly
* Added stopSave functionality to the final exception catch block

## 4.0.7 ##

* Support for file imports to use file validation
* Switched type and mimeType validation

## 4.0.6 ##

* Update to set database fields to empty when deleting files

## 4.0.5 ##

* Replaced findById() with find('first')
* Removed AttachmentBehavior::deleteImages() method
* Update to allow for transported files to grab meta data

## 4.0.4 ##

* Fixed transforms failing when transform schema index was a string
* Updated PHP requirements to 5.3.3

## 4.0.3 ##

* Added multibyte and curl check to composer

## 4.0.2 ##

* Don't allow defaultPath to be used when doing an update

## 4.0.1 ##

* Fixed a bug where multiple attachment configs weren't being handled correctly
* Renamed deleteImages() to deleteFiles()
* Changed thrown exceptions to InvalidArgumentException

## 4.0.0 ##

* Updated to use Composer extensively
* Updated to use [Transit](https://github.com/milesj/php-transit) and [AWS SDK](https://github.com/aws/aws-sdk-php) internally
* Uploader and S3 classes have been removed (uploading is done purely in the model layer)
* Transformations can be applied to the original file or used to create new files
* Transformations now support the following options: nameCallback, append, prepend, uploadDir, finalPath, overwrite and self
* Added Model::deleteImages($id) to delete uploaded files and not the record
* Added automatic file deletion when a record is deleted, or a path is being overwritten with a record update
* Added built in support for file uploading and importing (local, remote or stream)
* Added rollback file deletion if the upload process fails
* Added Model::beforeTransport() callback
* Added logging for critical errors
* Added AWS S3 and Glacier transport support
* Added type and mimeType validation rules
* Improved the error handling
* Improved file renaming and moving
* Removed config and mime type mapping
* Removed Test and Vendor files
* Option name was renamed to nameCallback
* Option importFrom was removed as importing is built in
* Option s3 was replaced with transport
* Option metaColumns had keys renamed
* Options baseDir and uploadDir were replaced with tempDir, uploadDir and finalPath
* Options maxNameLength and saveAsFilename were removed
* [View the updated documentation for help](http://milesj.me/code/cakephp/uploader)

## 3.6.3 ##

* Added a way to customize the S3 hostname
* Added defaultPath support to AttachmentBehavior
* Fixed 5.4 strict errors
* Fixed typo "spritnf" to "sprintf" in Decoda

## 3.6.1 ##

* Fixed a bug where extension validation threw errors

## 3.6 ##

* Fixed PHP 5.4 strict errors
* Fixed a bug where empty file uploads will still trigger validation
* Added an allowEmpty option for Uploader::uploadAll()
* Added width and height validation rules to FileValidationBehavior
* Refactored Uploader::uploadAll() to accept an array of options instead of arguments (backwards compatible)
* Refactored so that on/allowEmpty rules can be passed to FileValidationBehavior like regular validation rules
* Required validation rule is now by default, allowEmpty true and on create
* Replaced errors with exceptions
* Allow empty extension validation to use all mime types [[Issue #56](https://github.com/milesj/cake-uploader/pull/56)]
* Allow empty file uploads to continue when multiple uploads are used [[Issue #62](https://github.com/milesj/cake-uploader/pull/62)]
* Use uploadDir in AttachmentBehavior::beforeDelete() if saveAsFilename is true [[Issue #63](https://github.com/milesj/cake-uploader/pull/63)]
* Use cURL to grab the ext/mimetype for Uploader::importRemote() [[Issue #55](https://github.com/milesj/cake-uploader/pull/55)]
* Grab image dimensions at the end of Uploader::upload() to work for all upload formats [[Issue #60](https://github.com/milesj/cake-uploader/pull/60)]

## 3.5 ##

* Added file overwrite settings for transform methods [[Issue #50](https://github.com/milesj/cake-uploader/issues/50)] (overwrite settings are set to false by default)
* Fixed weirdness with append and prepend [[Issue #46](https://github.com/milesj/cake-uploader/issues/46)]
* Fixed a bug with uppercase file extensions
* Rewrote Uploader::crop() to maintain aspect ratio and fix wrong calculations [[Issue #51](https://github.com/milesj/cake-uploader/issues/51)]

## 3.4 ##

* Updated Uploader::upload() to accept a $_FILES array to support nested file input uploading [Issue #44]

## 3.3 ##

* Added a saveAsFilename option to AttachmentBehavior to save an upload without the relative path [[Issue #41](https://github.com/milesj/cake-uploader/issues/41)]
* Added more mime type support [[Issue #42](https://github.com/milesj/cake-uploader/issues/42)]
* Added mode orientation support to Uploader::resize()
* Added tons more test cases
* Fixed an undefined offset error when uploading without a model
* Fixed a bug where a custom name wasn't being used in chained transforms
* Refactored Uploader::resize() so that aspect and expand work in unison

## 3.2 ##

* Fixed a Linux upload issue regarding multiple transforms
* Fixed Uploader::addMimeType() to not overwrite existing values
* Fixed Uploader::crop() when both height and width are equal
* Fixed strict errors
* Fixed issue with transforms not inheriting custom name [[Issue #36](https://github.com/milesj/cake-uploader/issues/36)]
* Fixed a bug with multiple model uploading with AttachmentBehavior
* Recursively delete files if transforming overwrites the original
* Can now accept a model method for file name formatting [[Issue #35](https://github.com/milesj/cake-uploader/issues/35)]
* Added an aspect setting to Uploader::resize() [[Issue #40](https://github.com/milesj/cake-uploader/issues/40)]
* Added an allowEmpty setting to AttachmentBehavior to allow empty file uploads to continue [[Issue #38](https://github.com/milesj/cake-uploader/issues/38)]

## 3.1 ##

* Added append and prepend support to Uploader::upload() and AttachmentBehavior
* Added support for slashes within append and prepend options to allow for folder organization
* Fixed a bug with FileValidationBehavior::extension()

## 3.0 ##

* Updated to CakePHP 2.0 (not backwards compatible with 1.3)
* Converted the UploaderComponent into a stand-alone vendor class
* Converted Uploader::bytes(), addMimeType(), checkMimeType(), mimeType() and ext() to static methods
* Added an Uploader::setup() method to apply settings
* Added localized messages for upload errors and file validation
* Added dynamic variables to file validation messages
* Deleted the S3TransferComponent; use Vendor/S3 instead
* Refactored the Uploader class properties
* Refactored AttachmentBehavior::beforeDelete() to smartly detect column names and delete appropriate files
* Refactored and improved S3 support within the AttachmentBehavior
* Replaced HttpSocket with file_get_contents()

## 2.8 ##

* Added support for AJAX iframe uploading
* Added support for file uploads via XHR (AJAX)
* Changed it so that initialize() isn't called by FileValidation
* Changed the filename formatting to require a global function, so that the component and behavior can utilize it
* Using $_FILES instead of Controller::$data since it would be more accurate depending on the request type
* Fixed incorrectly named variable in S3Transfer::transfer()
* Fixed problems with empty uploads and imports

## 2.7 ##

* Added a $baseDir property so you can define your own base
* Added a formatPath() and formatFilename() to support these changes
* Added better GD library validation
* Added recursive folder creation
* Added support to allow a custom model method to format a filename
* Added import() to copy a local file
* Added importRemote() to copy a remote file
* Added import functionality to Attachment
* Adding _loadExtension() and checkMimeType() methods
* Can pass false to append to not append anything
* Converted private members to protected
* Fixed a bug with uppercase extensions
* Fixed a bug with file validation passing arrays
* Fixed incorrectly named variable for S3Transfer
* Updated dimensions() to validate multiple paths
* Refactoring _validates() and FileValidation
* Refactoring uploading within attachments to logically choose import or upload

## 2.6 ##

* Added a $rollback argument to uploadAll() to break and delete all previously uploaded files
* Added a minimum/maximum validation setting for image height/width
* Added a prepend option to certain transformers
* Added a skipSave option
* Fixed a bug when Security component is enabled and it breaking uploads
* Fixed a bug when parsing out the correct files data from a multi-dimensional array
* Fixed a bug where pjpeg's were not transforming
* Converted to using the built in Config setup
* Removed the AppController and AppModel
* Removed dimensions() in place of new validation rules
* Rewrote the file validation class
* Can now save the file meta info into the database with the Attachment behavior
* Can now return an array of data for transforming methods by passing true as a second param
* Can now define a default path in Attachment if the file field is left empty

## 2.5 ##

* Added a controller and model that I use for testing purposes
* Added 5 constants to use for UploaderComponent::crop(): LOC_TOP, LOC_BOT, LOC_LEFT, LOC_RIGHT, LOC_CENTER
* Added root path checking in UploaderComponent::delete()
* Added an S3 (Amazon Simple Storage) transfer component
* Added the S3 support into the AttachmentBehavior
* Fixed a bug with UploaderComponent::dimensions() throwing errors
* Fixed a bug with UploaderComponent::flip() not working
* Rewrote UploaderComponent::__validates() with better logic and checking
* Rewrote UploaderComponent::__parseData() to support multiple models and files
* Rewrote AttachmentBehavior to accept multiple transforms of the same type
* Changed filename auto-append from timestamp to incremental numbering
* Allow for nothing to be appended to the filename

## 2.4 ##

* Added IE8 specific mime types: image/pjpeg, image/x-png
* You can now use multiple types of transforms when using the Attachment behavior, simply pass a new index of method ('method' => 'resize'). If no method is found, it will use the array index.

## 2.3 ##

* Upgraded to PHP 5 only
* Added the Attachment Behavior so that files can be attached to Models for automatic file uploading and database saving
* Added 3 constants for the components flip() method: DIR_VERT, DIR_HORI, DIR_BOTH
* Rewrote flip() so that the direction is now an option and not an argument
* Fixed a misspelling of memory_limit

## 2.2 ##

* Added the method parseData() to the uploader component to properly parse the controllers data for $_FILES related data
* Added support for multi-byte and UTF-8 characters
* Renamed the uploader config class
* Fixed a huge bug where file validation didn't work, all thanks to a missing variable reference to the parent Model

## 2.1 ##

* Removed all constructors and placed their code in initialize() or setup()
* Rewrote utility libraries to only initialize when needed
* Rewrote the file uploading checking in setup(), if file uploads are disabled in php.ini, the uploader is disabled
* Rewrote bytes() to return is uppercase
* Fixed some problems with crop() returning weird results, still a problem when putting weird custom dimensions
* Added a "expand" option to resize(), to check if it should expand larger then its original
* Added more file validation in validates()
* Renamed the behavior to FileValidation, as well as its filename

## 2.0 ##

* Added a check for the GD library
* Added the initialize() method to grab the model data automatically
* Added the crop(), flip(), resize(), scale() and transform() methods for image transformation
* Added the uploadAll() method for multiple uploads
* Added a $current property to allow multiple files
* Removed all interal errors and logging
* Removed the vendors directory and added a config directory
* Removed unneeded methods
* Rewrote all methods to remove excess code
* Rewrote the _destination() method to be useable within all methods, not just upload()
* Rewrote the upload() method to be faster and to save the height and width if file is an image
* Rewrote the upload() method to accept a string as the input name, instead of the $this->data path
* Rewrote the validates() method to be combined into one method, instead of 3 sub methods
* Rewrote the _return() method to work with image transformations and returned data
* Rewrote how the behavior validaton settings are written
* Fixed a problem with the behavior required() always failing
* Cleaned up the convenience methods

## 1.3 ##

* First initial release of the Uploader plugin
