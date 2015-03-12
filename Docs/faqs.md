# FAQs #

## 1) How do I use the database ID as the file name? ##

Since the record hasn't been saved yet, you will need to query the database for the last ID and increment it. You can accomplish this using the `nameCallback` setting.

```php
public function nameCallback($name, $file) {
    $data = $this->find('first', array(
        'order' => array($this->primaryKey => 'DESC'),
        'limit' => 1
    ));

    if ($data) {
        return $data[$this->alias][$this->primaryKey]++;
    }

    return $name;
}
```

 If any records get deleted, this example will fail as the next ID will not always be the next incremented ID. To get the actual increment ID, you would need to [query the table status for the value](http://stackoverflow.com/questions/6761403/how-to-get-the-next-auto-increment-id-in-mysql).

## 2) How do I remove the "resize-100x100" from transformations file names? ##

As a side-effect of the image transformation, all transformed images will have their file names appended with strings like "-resize-100x100" and "-crop-250x100". This happens so that files are not overwritten on accident while the image is being created. This string can *not* be removed with an append or prepend setting, you will simply end up with a file name like "prepend_name-resize-100x100_append". To completely remove it, you will need to apply a `nameCallback` to each transform. This callback will return the original files name &mdash; *but be sure that it will not overwrite the original*!

```php
public function transformNameCallback($name, $file) {
    return $this->getUploadedFile()->name();
}
```

## 3) How do I define an absolute path for tempDir and uploadDir? ##

These two settings require an absolute path or else you will end up some weirdness when the relative path resolves to the wrong folder. Since you can't append strings and constants within class property definitions, you can define a constant outside of the model.

```php
define('UPLOAD_DIR', WWW_ROOT . '/img/uploads/');

class Upload {
    public $actsAs = array(
        'Uploader.Attachment' => array(
            'image' => array(
                'uploadDir' => UPLOAD_DIR,
                'finalPath' => '/img/uploads/'
            )
        )
    );
}
```

Or you can modify the settings in a callback.

```php
public function beforeUpload($options) {
    $options['finalPath'] = '/img/uploads/' 
    $options['uploadDir'] = WWW_ROOT . $options['finalPath'];
     
    return $options;
}
```

## 4) How to upload through a has many? ##

The easiest way to upload multiple files is to upload through a has many association. As an example, I'll use an Image model that belongs to Product and has the `AttachmentBehavior` defined for the `path` field.

* Product has many Image
* Image belongs to Product

The first step is to create the view (there's no limit to the amount of images that can be listed).

```php
echo $this->Form->create('Product', array('type' => 'file'));
echo $this->Form->input('title');
echo $this->Form->input('Image.0.path', array('type' => 'file'));
echo $this->Form->input('Image.1.path', array('type' => 'file'));
echo $this->Form->end('Create');
```

The only other step is to change `save()` to `saveAssociated()` in the controller when saving the Product record.

```php
if ($this->Product->saveAssociated($this->request->data)) {
    // Do something
}
```

Be sure to set `dependent` to true in the settings or the associated records (and files) will not be deleted when the primary record is deleted.

## 5) How to upload multiple files? ##

Uploading multiple files not through an association can be quite tricky, but is possible. As an example, I'll use an Image model that has the `AttachmentBehavior` defined for the `path` field. To begin, we must convert our single file view to support multiple files. We can achieve this by numerically indexing the path fields.

```php
echo $this->Form->create('Image', array('type' => 'file'));
echo $this->Form->input('Image.0.path', array('type' => 'file'));
echo $this->Form->input('Image.0.caption');
echo $this->Form->input('Image.1.path', array('type' => 'file'));
echo $this->Form->input('Image.1.caption');
echo $this->Form->end('Upload');
```

Then to upload the files, call `saveMany()` in the controller. The only gotcha is that the Image array index needs to be passed; this is simply a problem with CakePHP's data structure expectancy.

```php
if ($this->Image->saveMany($this->request->data['Image'])) {
    // Do something
}
```

## 6) How do I upload files larger than 2MB? ##

When uploading really large files, the dreaded white screen can appear, or the memory exhausted error, both of which cause the request to break with no errors in the logs. This white screen is caused by PHP's inability to handle large file uploads with basic settings.

The first change that needs to be made is upping the max upload size in `php.ini`. Change these values to whatever you please. [Be sure to read the notes on each setting also.](http://www.php.net/manual/en/ini.core.php#ini.post-max-size)

```
; Maximum allowed size for uploaded files.
upload_max_filesize = 40M
; Must be greater than or equal to upload_max_filesize
post_max_size = 40M
```

The second change is to increase the memory limit from the default 16MB in `php.ini`.

```
memory_limit = 100M
```

If neither of these changes solve the white screen, you can disable the memory and time limit so the script can continue without exiting early. Place the following code at the top of the controller (or in bootstrap) that handles uploading.

```php
ini_set('memory_limit', '-1');
set_time_limit(0);
```

## 7) Will the uploader work on Heroku? ##

Yes it will... with a few changes. Since Heroku restricts file system writing outside of the `/tmp` folder, files must be transported to S3 (or another storage system), as well as having all file uploads point to the tmp folder. The Heroku build pack must also have the required PHP extensions enabled: gd, fileinfo, curl and exif (if you need it).

The following options need to be applied for all uploads and transforms.

```php
'tempDir' => '/tmp',
'uploadDir' => '/tmp',
'finalPath' => ''
```
