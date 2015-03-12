# Uploader #

*Documentation may be outdated or incomplete as some URLs may no longer exist.*

*Warning! This codebase is deprecated and will no longer receive support; excluding critical issues.*

A CakePHP plugin that will validate and upload files through the model layer. Provides support for image transformation and remote storage transportation.

* Upload files automatically through Model::save() by using the AttachmentBehavior
* Validate files automatically through Model::save() by using the FileValidationBehavior
* Extensive list of validation rules: size, ext, type, height, width, etc
* Transform the uploaded image or create new images: crop, scale, rotate, flip, etc
* Automatic file deletion when a database record is deleted or updated
* Supports transporting files to remote storage systems like AWS
* Exif reading and processing support

## Installation ##

The current documentation only refers to v4.x of the plugin. If you are installing v3.x, most of the installation and configuration processes are similar, the major differences are the setting names. I suggest combing over the source code to determine what the differences are.

### Installing v4.x ###

The plugin *must* use [Composer](http://getcomposer.org/) for installation so that all dependencies are also installed, there is no alternative (use v3.x if you cannot use Composer). [Learn more about using Composer in CakePHP](http://milesj.me/blog/read/using-composer-in-cakephp). The plugin uses [Transit](https://github.com/milesj/php-transit) and [AWS SDK](https://github.com/aws/aws-sdk-php) internally for all file uploading functionality.

```javascript
{
    "config": {
        "vendor-dir": "Vendor"
    },
    "require": {
        "mjohnson/uploader": "4.*"
    }
}
```

Be sure to enable Composer at the top of `Config/core.php`.

```php
require_once dirname(__DIR__) . '/Vendor/autoload.php';
```

### Installing v3.x ###

Since this version has no dependencies, it can be installed without Composer (but still supports it). Being an older version, many of its features are not up to date and most support is deprecated.

To install the plugin, simply download/clone the project and place the contents into `app/Plugin/Uploader`.

## Configuration ##

All file uploading is done through the model layer and is configured with the `AttachmentBehavior`. For every database column that contains a file path (either relative or absolute), an attachment configuration should be defined. Once a configuration exists, calling a simple `Model::save()` will upload the file, trigger any image transformations and finally save the file path in the database. Below are the default and all available configuration settings. For a more detailed explanation on what each setting does, jump to the next chapters.

 The key for each configuration is the database column. In the following example, the file path will be uploaded to the "image" column.

```php
public $actsAs = array(
    'Uploader.Attachment' => array(
        // Do not copy all these settings, it's merely an example
        'image' => array(
            'nameCallback' => '',
            'append' => '',
            'prepend' => '',
            'tempDir' => TMP,
            'uploadDir' => '',
            'transportDir' => '',
            'finalPath' => '',
            'dbColumn' => '',
            'metaColumns' => array(),
            'defaultPath' => '',
            'overwrite' => false,
            'stopSave' => true,
            'allowEmpty' => true,
            'transforms' => array(),
            'transformers' => array(),
            'transport' => array(),
            'transporters' => array(),
            'curl' => array()
        )
    )
);
```

Once you have your attachment defined, you will need to add the input field in the form. Both the form and input will need the file type applied.

```php
echo $this->Form->create('Model', array('type' => 'file'));
// Other inputs
echo $this->Form->input('image', array('type' => 'file'));
echo $this->Form->end('Submit');
```

And finally, just call `Model::save()` and your file should upload! It's as easy as that. Be sure to add validation to the file using the `FileValidationBehavior`.

```php
if ($this->Model->save($this->request->data, true)) {
    // Do something
}
```

## Changing Upload Directories ##

There are 4 settings that deal with determining the destination folder for uploaded files, they are `tempDir`, `uploadDir`, `transportDir`, and `finalPath`. 

The `tempDir` (string) setting determines where the files should be uploaded temporarily. Files are uploaded to a temporary directory so that any image transformations can be executed and moved in a staging like environment. The default value is set to CakePHP's application tmp directory and usually does not need to change.  

The `uploadDir` (string) setting determines where the files should be moved to permanently after being uploaded to the temporary directory. This value should be an absolute path to another location on the file system. The default value is set to the `files/uploads` folder within CakePHP's webroot folder.

The `finalPath` (string) setting is a path that is prepended onto the file name before being saved into the database. This value can be an absolute path (like a domain name), or a relative path (like the files/uploads path). This setting is best used to prepend a relative path that is publicly accessible via an HTTP URL, else only the file name would be saved into the database.

```php
public $actsAs = array(
    'Uploader.Attachment' => array(
        'image' => array(
            'tempDir' => TMP,
            'uploadDir' => '/var/www/app/webroot/img/uploads/',
            'finalPath' => '/img/uploads/'
        )
    )
);
```

The `transportDir` (string) setting allows uploads to be placed in a custom folder when being transported. It works exactly like `finalPath`, but only applies to transports.

Based on the configuration above, if a file with the name `test.jpg` was uploaded, the value in the database would be saved as `img/uploads/test.jpg` and the file would reside at `/var/www/app/webroot/img/uploads/test.jpg`. Furthermore, if you only want to save the file name in the database, you could set `finalPath` to an empty string. There are multiple ways to configure both these settings but they are always used in conjunction.

Only `uploadDir`, `transportDir` and `finalPath` can be used in transformation configurations.

 To make configuration easier, the finalPath will automatically be appended to the default uploadDir. This permits the uploadDir setting to be omitted.

## Modifying File Names ##

There are 3 settings that deal with modifying the file name, they are `nameCallback`, `append` and `prepend`.

The `nameCallback` (string) setting will accept the name of a method found with the current model. This method will be triggered and the return value will be used as the new file name. The first argument of the method will be the current file name, and the second argument will be a `Transit\File` object. This object can be used to grab information like the extension, mime type, file size, etc.

```php
public $actsAs = array(
    'Uploader.Attachment' => array(
        'image' => array(
            'nameCallback' => 'formatName'
        )
    )
);

public function formatName($name, $file) {
    return sprintf('%s-%s', $name, $file->size());
}
```

The `prepend` and `append` (string) settings can be used to append and prepend static text to the file name.

```php
public $actsAs = array(
    'Uploader.Attachment' => array(
        'image' => array(
            'append' => '-original',
            'prepend' => 'hd-'
        )
    )
);
```

All 3 of these settings can be used within each transformation configuration.

## Process Flow Handling ##

There are 3 settings that deal with process flow handling, they are `overwrite`, `stopSave` and `allowEmpty`. Process flow refers to the flow of processing a file, executing all its configuration, and returning a response. The flow can be interrupted at any time, especially when an upload or transform error occurs.

The `overwrite` (boolean:false) setting determines whether existent files should be overwritten by the new file. If this setting is turned off, the new file will have an incremental number appended to its name.

The `stopSave` (boolean:true) setting will completely stop the `Model::save()` query from executing if some sort of error occurs. If this setting is turned off, the query will execute without an image being uploaded. It's usually best to leave this value at true.

The `allowEmpty` (boolean:true) setting will allow the `Model::save()` query to continue if the input file field is empty. This setting is useful on edit pages where uploading a new image is not required.

The `defaultPath` (string) setting allows for a path to be used when an empty file upload happens. This allows for default or fallback images to be used more easily. This setting will only trigger if `allowEmpty` is true. This also applies to transforms.

## Transforming Images (Resize, Crop, etc) ##

The greatest aspect of the plugin is the image transformation system. Transformations can be applied to the uploaded image, or can be used to generate additional images (like thumbnails). There are no limits to how many transforms can be defined, but the more you add, the longer the processing time. Like other settings, transforms can be defined within the `transforms` setting.

 Like before, the file path will be set to the "image" column and the transformed paths will be set to "imageSmall" and "imageMedium" respectively.

```php
App::uses('AttachmentBehavior', 'Uploader.Model/Behavior');

// In the model
public $actsAs = array(
    'Uploader.Attachment' => array(
        'image' => array(
            'overwrite' => true,
            'transforms' => array(
                'imageSmall' => array(
                    'class' => 'crop',
                    'append' => '-small',
                    'overwrite' => true
                    'self' => false,
                    'width' => 100,
                    'height' => 100
                ),
                'imageMedium' => array(
                    'class' => 'resize',
                    'append' => '-medium',
                    'width' => 800,
                    'height' => 600,
                    'aspect' => false
                )
            )
        )
    )
);
```

Like the parent setting, each transform accepts the following settings: `nameCallback`, `append`, `prepend`, `uploadDir`, `transportDir`, `finalPath`, `defaultPath`, and `overwrite`. There are 2 settings which are specific to transforms, they are `class` and `self`. The `class` setting defines which type of transformation to use. The `self` setting determines whether or not the image transformations should be applied to the uploaded file, or whether to create additional files (defaults to false).

There are 7 types of image transformations that are currently available, they are resize, crop, flip, scale, rotate, fit, and exif. Each method has additional settings that can also be applied.

**Resize**
Allows one to change the width or height of an image programmatically.

* `width` (int) - The width to resize to
* `height` (int) - The height to resize to
* `quality` (int:100) - The quality of the image (jpg only)
* `expand` (boolean:false) - If false, will not allow the image to be resized larger than its original dimensions
* `aspect` (boolean:true) - If true, will maintain aspect ratio when scaling up or down
* `mode` (string:width) - Use the width or height for aspect ratio calculations (accepts width or height)

**Crop**
Allows one to crop out an area of the image.

* `width` (int) - The width to crop out
* `height` (int) - The height to crop out
* `quality` (int:100) - The quality of the image (jpg only)
* `location` (string:center) - Which area to crop (accepts center, top, right, bottom, left)

**Flip**
Allows one to flip the image horizontally or vertically.

* `quality` (int:100) - The quality of the image (jpg only)
* `direction` (string:vertical) - The direction to flip (accepts vertical, horizontal, both)

**Scale**
Allows one to scale an image up or down programmatically.

* `quality` (int:100) - The quality of the image (jpg only)
* `percent` (float:0.5) - The percentage to scale with

**Rotate**
Allows one to rotate an image to a certain degree.

* `quality` (int:100) - The quality of the image (jpg only)
* `degrees` (int:180) - The degrees to rotate with

**Fit**
Allows one to fit an image within a certain dimension. Any gap in the background will be filled with a color. If no background fill is provided, the image will simply be resized.

* `width` (int) - The width to fit to
* `height` (int) - The height to fit to
* `quality` (int:100) - The quality of the image (jpg only)
* `fill` (array) - An array of RGB values
* `vertical` (string:center) - Direction to align image vertically
* `horizontal` (string:center) - Direction to align image horizontally

**Exif**
The Exif transformation will rotate, flip, and fix orientation issues for Exif images. It will also strip Exif data so that it isn't publicly available. Exif transformation **should** be applied as the first self-transformation.

```php
public $actsAs = array(
    'Uploader.Attachment' => array(
        'image' => array(
            'transforms' => array(
                array(
                    'class' => 'exif',
                    'self' => true
                ),
                // Other transformations
            )
        )
    )
);
```

## Transporting To The Cloud ##

Now a days it's best to store your images in the cloud instead of your local server. The Uploader supports the transportation of files to remote storage systems like AWS. A transport can be defined using the `transport` setting.

 To use AWS functionality, add the SDK as a composer dependency: aws/aws-sdk-php

```php
App::uses('AttachmentBehavior', 'Uploader.Model/Behavior');

// In the model
public $actsAs = array(
    'Uploader.Attachment' => array(
        'image' => array(
            'overwrite' => true,
            'transport' => array(
                'class' => AttachmentBehavior::S3,
                'accessKey' => 'ACCESS',
                'secretKey' => 'SECRET',
                'bucket' => 'bucket',
                'region' => Aws\Common\Enum\Region::US_EAST_1,
                'folder' => 'sub/folder/'
            )
        )
    )
);
```

Only one transport can be defined per configuration; it also applies to any child transformations. Currently only Amazon S3 and Glacier are supported. The Uploader uses the [AWS PHP SDK](https://github.com/aws/aws-sdk-php) internally to transport files &mdash; it's best to read the documentation and source code for the SDK to determine which constants and settings to use.

**[Amazon Simple Storage Service](http://aws.amazon.com/s3/)**
The full AWS S3 URL will be saved as the path in the database.

* `accessKey` (string) - The access key given to you by AWS
* `secretKey` (string) - The secret key given to you by AWS
* `bucket` (string) - The bucket to move files to
* `folder` (string) - The folder to place the file in within the bucket
* `scheme` (string:https) - The HTTP protocol scheme to use (accepts http or https)
* `region` (string) - The region the bucket is located in (should use Aws\Common\Enum\Region constants)
* `storage` (string:standard) - The storage setting for each file (should use Aws\S3\Enum\Storage constants, defaults to Storage::STANDARD)
* `acl` (string:public-read) - The access permissions for each file (should use Aws\S3\Enum\CannedAcl constants, defaults to CannedAcl::PUBLIC_READ)
* `encryption` (string) - Server side encryption algorithm to use (accepts AES256 or an empty string)
* `meta` (array) - A mapping of meta data for each file
* `returnUrl` (bool:true) - Return the full S3 URL or the S3 key

**[Amazon Glacier](http://aws.amazon.com/glacier/)**
The archive ID will be saved in place of the path in the database.

* `accessKey` (string) - The access key given to you by AWS
* `secretKey` (string) - The secret key given to you by AWS
* `vault` (string) - The vault to move files to
* `accountId` (string) - An AWS IAM account ID for authorization (can usually be blank)
* `region` (string) - The region the vault is located in (should use Aws\Common\Enum\Region constants)

## Custom Transformers and Transporters ##

If there's ever a situation where the current list of transformers or transporters do not suffice, a custom class can be written. The custom transformer must extend the `Transit\Transformer` interface, while the custom transporter must extend the `Transit\Transporter` interface. [Take a look at the Transit repository for examples](https://github.com/milesj/transit).

Once the custom class is created, the class name will need to be defined within the attachment settings. This can be done within the `transformers` and `transporters` arrays.

```php
public $actsAs = array(
    'Uploader.Attachment' => array(
        'image' => array(
            'transformers' => array(
                'grayscale' => 'Namespace\Class\GrayscaleTransformer'
            ),
            'transporters' => array(
                'dropbox' => 'Namespace\Class\DropboxTransporter'
            )
        )
    )
);
```

Now set the `class` setting within each transform or transport setting to the key for the custom class. This will now permit custom classes to be used when saving records by autoloading and instantiating the defined classes.

```php
public $actsAs = array(
    'Uploader.Attachment' => array(
        'image' => array(
            'transforms' => array(
                'image_gray' => array('class' => 'grayscale')
            )
        )
    )
);
```

## Importing Remote Files ##

By default, the `AttachmentBehavior` uploads files through HTTP post and grabs its data from `$_FILES` (while using a file input field). There are times where you want to import a file from another location, instead of uploading a file from the client. Currently there are 3 methods of importing.

 In the examples below, the "image" attachment will be used.

### Copying remote files ###

This allows the user to paste an HTTP URL to a file (most likely an image) and have the Uploader copy it. To set this up, all you need to do is create an input field that is not a file. Remote file importing will only work if the URL begins with http (so add a validation rule!).

```php
// Change this
echo $this->Form->input('image', array('label' => 'Upload', 'type' => 'file'));
// To this
echo $this->Form->input('image', array('label' => 'Remote URL'));
```

Remote imports support customizeable cURL options through the `curl` setting. The array should map cURL constants to option values.

```php
public $actsAs = array(
    'Uploader.Attachment' => array(
        'image' => array(
            'curl' => array(CURLOPT_SSL_VERIFYPEER => true)
        )
    )
);
```

### Copying local files ###

This works in a similar fashion to remote files, but the primary difference is that it copies a file from the local file system. This should really only be used by administrators and developers, or some sort of system that has a pre-defined set of file system paths.

```php
echo $this->Form->input('image', array('label' => 'Local Path'));
```

### Copying from PHPs input stream ###

The last line of defense for importing is copying a file from PHPs input stream. This functionality is primarily used for AJAX file uploading purposes and will rarely be used outside of that. Since this method is rather complex and differs per implementation, I will try and create a broad example.

```php
// 1) AJAX pushes a file upload to /files/upload?name=file.jpg

// 2) FilesController::upload() handles the import by setting the file name for the image
public function upload() {
    $this->request->data['Upload']['image'] = $this->request->query['name'];
    
    if ($this->Upload->save($this->request->data)) {
        // File uploaded and record saved
    }
}
```

The primary difference between regular uploading and importing is that stream importing requires the destination file name to be passed as the value. Once the value is set, it will attempt to copy the file from the stream and process the file with the attachment settings as if it was a regular upload.

## Modifying Settings With Callbacks ##

There are times when you need to modify the attachment settings dynamically before the upload occurs, for example changing the destination folder. You can achieve this by defining callbacks within the model. These callbacks are `beforeUpload()`, `beforeTransform()` and `beforeTransport()` &mdash; they are pretty self explanatory. You only need to define a callback when you want to modify something.

```php
 // Lets change some settings
public function beforeUpload($options) {
    $options['append'] = '-original';
    
    return $options;
}

// Or maybe place the files in files/uploads/resize/
public function beforeTransform($options) {
    $options['finalPath'] = '/files/uploads/' . $options['method'] . '/' 
    $options['uploadDir'] = WWW_ROOT . $options['finalPath'];
    
    return $options;
}

// And even change the S3 folder
public function beforeTransport($options) {
    $options['folder'] = 'img/' . $this->data[$this->alias]['slug'] . '/';
    
    return $options;
}
```

There are no restrictions on what you can modify, so have fun with it.

## Deleting Files ##

By default, files are automatically deleted any time a database record is deleted, or anytime a record update occurs and the previous file will be overwritten. However, there are times when you want to delete the files manually but not delete the associated record. You can achieve this by calling `deleteFiles()` through the respective model.

For example, say the Image model has 3 file fields: small, medium and large.

```php
// Delete all
$this->Image->deleteFiles($id);

// Delete only medium
$this->Image->deleteFiles($id, array('medium'));
```

## Saving Meta Data ##

Attachments also support the saving of meta data in the database. If you want to save the extension, or mime type or file size alongside the path, you can do so. The following meta fields (including Exif data) are available: basename (name with ext), ext, name (without ext), size, type (mime type), width, height, exif.make, exif.model, exif.exposure, exif.orientation, exif.fnumber, exif.date, exif.iso, and exif.focal. You can save these fields by defining the `metaColumns` settings.

```php
public $actsAs = array(
    'Uploader.Attachment' => array(
        'image' => array(
            'metaColumns' => array(
                'ext' => 'extension',
                'type' => 'mimeType',
                'size' => 'fileSize',
                'exif.model' => 'camera'
            )
        )
    )
);
```

Every key in the `metaColumns` setting should be one of the available meta fields, and the value should be the database column to save it to.

 Meta data is only derived from the original file, not the transformations. Transformed files do not have metaColumns support.

## Validating An Upload ##

Like any upload form, you want to validate the file before it is actually uploaded. We can do this by using the `FileValidationBehavior` and our models built in validation system. The Uploader validation can the following: width, height, minWidth, minHeight, maxWidth, maxHeight, filesize, extension, type, mimeType and required. All we need to do is set the validation rules by applying an `$actsAs` behavior, like so.

Here are a few examples of how to use the behavior to add form validation.

```php
public $actsAs = array(
    'Uploader.FileValidation' => array(
        'image' => array(
            'maxWidth' => 100,
            'minHeight' => 100,
            'extension' => array('gif', 'jpg', 'png', 'jpeg'),
            'type' => 'image',
            'mimeType' => array('image/gif'),
            'filesize' => 5242880,
            'required' => true
        )
    )
);
```

The code above would allow basic customization for validation. If you want to extend this validation even further and have custom error messages, you can do it like so. Additionally, you can validate more than one field.

```php
public $actsAs = array(
    'Uploader.FileValidation' => array(
        'image' => array(
            'extension' => array('gif', 'jpg', 'png', 'jpeg'),
            'required' => array(
                'value' => true,
                'error' => 'File required'
            )
        ),
        'thumbnail' => array(
            'required' => false
        )
    )
);
```

The validation rules also accept the same options as CakePHP's validation system. This allows us to use options like on and allowEmpty.

```php
public $actsAs = array(
    'Uploader.FileValidation' => array(
        'image' => array(
            'required' => array(
                'value' => false,
                'on' => 'update',
                'allowEmpty' => true
            )
        )
    )
);
```
