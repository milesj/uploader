<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/uploader/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/uploader
 */

App::uses('Set', 'Utility');
App::uses('ModelBehavior', 'Model');

use Transit\Transit;
use Transit\File;
use Transit\Exception\ValidationException;
use Transit\Transformer\Image\CropTransformer;
use Transit\Transformer\Image\FlipTransformer;
use Transit\Transformer\Image\ResizeTransformer;
use Transit\Transformer\Image\ScaleTransformer;
use Transit\Transformer\Image\ExifTransformer;
use Transit\Transformer\Image\RotateTransformer;
use Transit\Transformer\Image\FitTransformer;
use Transit\Transporter\Aws\S3Transporter;
use Transit\Transporter\Aws\GlacierTransporter;
use Transit\Transporter\Rackspace\CloudFilesTransporter;

/**
 * A CakePHP Behavior that attaches a file to a model, uploads automatically,
 * and then stores a value in the database.
 */
class AttachmentBehavior extends ModelBehavior {

    /**
     * Transformation types.
     */
    const CROP = 'crop';
    const FLIP = 'flip';
    const RESIZE = 'resize';
    const SCALE = 'scale';
    const ROTATE = 'rotate';
    const EXIF = 'exif';
    const FIT = 'fit';

    /**
     * Transportation types.
     */
    const S3 = 's3';
    const GLACIER = 'glacier';
    const CLOUD_FILES = 'cloudfiles';

    /**
     * Transit instances indexed by model alias.
     *
     * @type \Transit\Transit[]
     */
    protected $_uploads = array();

    /**
     * Mapping of database columns to attachment fields.
     *
     * @type array
     */
    protected $_columns = array();

    /**
     * Default attachment settings.
     *
     * @type array {
     *      @type string $nameCallback  Method to format filename with
     *      @type string $append        What to append to the end of the filename
     *      @type string $prepend       What to prepend to the beginning of the filename
     *      @type string $tempDir       Directory to upload files to temporarily
     *      @type string $uploadDir     Directory to move file to after upload to make it publicly accessible
     *      @type string $transportDir  Directory to place files in after transporting
     *      @type string $finalPath     The final path to prepend to file names (like a domain)
     *      @type string $dbColumn      Database column to write file path to
     *      @type array $metaColumns    Database columns to write meta data to
     *      @type string $defaultPath   Default image if no file is uploaded
     *      @type bool $overwrite       Overwrite a file with the same name if it exists
     *      @type bool $stopSave        Stop save() if error exists during upload
     *      @type bool $allowEmpty      Allow an empty file upload to continue
     *      @type array $transforms     List of transforms to apply to the image
     *      @type array $transformers   List of custom transformers to class/namespaces
     *      @type array $transport      Settings for file transportation
     *      @type array $transporters   List of custom transporters to class/namespaces
     *      @type array $curl           List of cURL options to set for remote importing
     *      @type bool $cleanup         Remove old files when new files are being written
     * }
     */
    protected $_defaultSettings = array(
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
        'curl' => array(),
        'cleanup' => true
    );

    /**
     * Default transform settings.
     *
     * @type array {
     *      @type string $class         The transform method / class to use
     *      @type string $nameCallback  Method to format filename with
     *      @type string $append        What to append to the end of the filename
     *      @type string $prepend       What to prepend to the beginning of the filename
     *      @type string $uploadDir     Directory to move file to after upload to make it publicly accessible
     *      @type string $transportDir  Directory to place files in after transporting
     *      @type string $finalPath     The final path to prepend to file names (like a domain)
     *      @type string $dbColumn      Database column to write file path to
     *      @type string $defaultPath   Default image if no file is uploaded
     *      @type bool $overwrite       Overwrite a file with the same name if it exists
     *      @type bool $self            Should the transforms apply to the uploaded file instead of creating new images
     * }
     */
    protected $_transformSettings = array(
        'class' => '',
        'nameCallback' => '',
        'append' => '',
        'prepend' => '',
        'uploadDir' => '',
        'transportDir' => '',
        'finalPath' => '',
        'dbColumn' => '',
        'defaultPath' => '',
        'overwrite' => false,
        'self' => false
    );

    /**
     * Save attachment settings.
     *
     * @param Model $model
     * @param array $settings
     */
    public function setup(Model $model, $settings = array()) {
        if (!$settings) {
            return;
        }

        if (!isset($this->_columns[$model->alias])) {
            $this->_columns[$model->alias] = array();
        }

        foreach ($settings as $field => $attachment) {
            $attachment = Set::merge($this->_defaultSettings, $attachment + array(
                'dbColumn' => $field
            ));

            // Fix dbColumn if they set it to empty
            if (!$attachment['dbColumn']) {
                $attachment['dbColumn'] = $field;
            }

            $columns = array($attachment['dbColumn'] => $field);

            // Set defaults if not defined
            if (!$attachment['tempDir']) {
                $attachment['tempDir'] = TMP;
            }

            if (!$attachment['uploadDir']) {
                $attachment['finalPath'] = $attachment['finalPath'] ?: '/files/uploads/';
                $attachment['uploadDir'] = WWW_ROOT . $attachment['finalPath'];
            }

            // Merge transform settings with defaults
            if ($attachment['transforms']) {
                foreach ($attachment['transforms'] as $dbColumn => $transform) {
                    $transform = Set::merge($this->_transformSettings, $transform + array(
                        'uploadDir' => $attachment['uploadDir'],
                        'finalPath' => $attachment['finalPath'],
                        'dbColumn' => $dbColumn
                    ));

                    if ($transform['self']) {
                        $transform['dbColumn'] = $attachment['dbColumn'];
                    }

                    $columns[$transform['dbColumn']] = $field;
                    $attachment['transforms'][$dbColumn] = $transform;
                }
            }

            $this->settings[$model->alias][$field] = $attachment;
            $this->_columns[$model->alias] += $columns;
        }
    }

    /**
     * Cleanup and reset the behavior when its detached.
     *
     * @param Model $model
     * @return void
     */
    public function cleanup(Model $model) {
        parent::cleanup($model);

        $this->_uploads = array();
        $this->_columns = array();
    }

    /**
     * After a find, replace any empty fields with the default path.
     *
     * @param Model $model
     * @param array $results
     * @param bool $primary
     * @return array
     */
    public function afterFind(Model $model, $results, $primary=false) {
        $alias = $model->alias;

        foreach ($results as $i => $data) {
            if (empty($data[$alias])) {
                continue;
            }

            foreach ($data[$alias] as $field => $value) {
                if (empty($this->settings[$alias][$field]) || !empty($value)) {
                    continue;
                }

                $attachment = $this->_settingsCallback($model, $this->settings[$alias][$field]);

                if ($attachment['defaultPath']) {
                    $results[$i][$alias][$attachment['dbColumn']] = $attachment['defaultPath'];
                }

                if ($attachment['transforms']) {
                    foreach ($attachment['transforms'] as $transform) {
                        if ($transform['defaultPath']) {
                            $results[$i][$alias][$transform['dbColumn']] = $transform['defaultPath'];
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Deletes any files that have been attached to this model.
     *
     * @param Model $model
     * @param bool $cascade
     * @return bool
     */
    public function beforeDelete(Model $model, $cascade = true) {
        if (empty($model->id)) {
            return false;
        }

        return $this->deleteFiles($model, $model->id, array(), true);
    }

    /**
     * Before saving the data, try uploading the file, if successful save to database.
     *
     * @uses Transit\Transit
     *
     * @param Model $model
     * @param array $options
     * @return bool
     */
    public function beforeSave(Model $model, $options = array()) {
        $alias = $model->alias;

        if (empty($model->data[$alias])) {
            return true;
        }

        // Loop through the data and upload the file
        foreach ($model->data[$alias] as $field => $file) {
            if (empty($this->settings[$alias][$field])) {
                continue;
            }

            // Gather attachment settings
            $attachment = $this->_settingsCallback($model, $this->settings[$alias][$field]);
            $data = array();

            // Initialize Transit
            $transit = new Transit($file);
            $transit->setDirectory($attachment['tempDir']);

            $this->_uploads[$alias] = $transit;

            // Set transformers and transporter
            $this->_addTransformers($model, $transit, $attachment);
            $this->_setTransporter($model, $transit, $attachment);

            // Attempt upload or import
            try {
                $overwrite = $attachment['overwrite'];
                $response = null;

                // File upload
                if (is_array($file)) {
                    $response = $transit->upload($overwrite);

                // Remote import
                } else if (preg_match('/^http/i', $file)) {
                    $response = $transit->importFromRemote($overwrite, $attachment['curl']);

                // Local import
                } else if (file_exists($file)) {
                    $response = $transit->importFromLocal($overwrite);

                // Stream import
                } else if (!empty($file)) {
                    $response = $transit->importFromStream($overwrite);
                }

                // Successful upload or import
                if ($response) {
                    $dbColumnMap = array($attachment['dbColumn']);
                    $transportConfig = array($this->_prepareTransport($attachment));

                    // Rename and move file
                    $data[$attachment['dbColumn']] = $this->_renameAndMove($model, $transit->getOriginalFile(), $attachment);

                    // Fetch exif data before transforming
                    $metaData = array();

                    foreach ($transit->getOriginalFile()->toArray() as $key => $value) {
                        if (substr($key, 0, 4) === 'exif' && $value) {
                            $metaData[$key] = $value;
                        }
                    }

                    // Transform the files and save their path
                    if ($attachment['transforms']) {
                        $transit->transform();

                        $transformedFiles = $transit->getTransformedFiles();
                        $count = 0;

                        foreach ($attachment['transforms'] as $transform) {
                            if ($transform['self']) {
                                $tempFile = $transit->getOriginalFile();
                                $dbColumnMap[0] = $transform['dbColumn'];

                            } else {
                                $tempFile = $transformedFiles[$count];
                                $dbColumnMap[] = $transform['dbColumn'];
                                $count++;

                                $transportConfig[] = $this->_prepareTransport($transform);
                            }

                            $data[$transform['dbColumn']] = $this->_renameAndMove($model, $tempFile, $transform);
                        }
                    }

                    $metaData = array_merge($transit->getOriginalFile()->toArray(), $metaData);

                    // Transport the files and save their remote path
                    if ($attachment['transport']) {
                        if ($transportedFiles = $transit->transport($transportConfig)) {
                            foreach ($transportedFiles as $i => $transportedFile) {
                                $data[$dbColumnMap[$i]] = $transportedFile;
                            }
                        }
                    }
                }

            // Trigger form errors if validation fails
            } catch (ValidationException $e) {
                $dbColumns = array_merge(array($attachment['dbColumn']), array_keys($attachment['transforms']));

                foreach ($dbColumns as $dbCol) {
                    unset($model->data[$alias][$dbCol]);
                }

                // Allow empty uploads
                if ($attachment['allowEmpty']) {
                    continue;
                }

                // Invalidate and stop
                $model->invalidate($field, __d('uploader', $e->getMessage()));

                if ($attachment['stopSave']) {
                    return false;
                }

            // Log exceptions that shouldn't be shown to the client
            } catch (Exception $e) {
                $model->invalidate($field, __d('uploader', $e->getMessage()));

                $this->log($e->getMessage(), LOG_DEBUG);

                // Rollback the files since it threw errors
                $transit->rollback();

                if ($attachment['stopSave']) {
                    return false;
                }
            }

            // Save file meta data
            $cleanup = $data;

            if ($attachment['metaColumns'] && $data && !empty($metaData)) {
                foreach ($attachment['metaColumns'] as $method => $column) {
                    if (isset($metaData[$method]) && $column) {
                        $data[$column] = $metaData[$method];
                    }
                }
            }

            // Merge upload data with model data
            if ($data) {
                $model->data[$alias] = $data + $model->data[$alias];
            }

            // Keep it in a loop, so it will delete all files
            // If we are doing an update, delete the previous files that are being replaced
            if ($model->id && $cleanup) {
                $this->_cleanupOldFiles($model, $cleanup);
            }
        }

        return true;
    }

    /**
     * Delete all files associated with a record but do not delete the record.
     *
     * @param Model $model
     * @param int $id
     * @param array $filter
     * @param bool $isDelete
     * @return bool
     */
    public function deleteFiles(Model $model, $id, array $filter = array(), $isDelete = false) {
        $columns = $this->_columns[$model->alias];
        $data = $this->_doFind($model, array($model->alias . '.' . $model->primaryKey => $id));

        if (empty($data[$model->alias])) {
            return false;
        }

        // Set data in case $this->data is used in callbacks
        $model->set($data);

        $save = array();

        foreach ($data[$model->alias] as $column => $value) {
            if (empty($columns[$column]) || empty($value)) {
                continue;
            } else if ($filter && !in_array($column, $filter)) {
                continue;
            }

            if ($this->_deleteFile($model, $columns[$column], $value, $column)) {
                $save[$column] = '';

                // Reset meta data also
                foreach ($this->settings[$model->alias][$columns[$column]]['metaColumns'] as $metaKey => $fieldKey) {
                    $save[$fieldKey] = '';
                }
            }
        }

        // Set the fields to empty
        if ($save && !$isDelete) {
            $model->id = $id;
            $model->save($save, array(
                'validate' => false,
                'callbacks' => false,
                'fieldList' => array_keys($save)
            ));
        }

        return true;
    }

    /**
     * Return the uploaded original File object.
     *
     * @param Model $model
     * @return \Transit\File
     */
    public function getUploadedFile(Model $model) {
        if (isset($this->_uploads[$model->alias])) {
            return $this->_uploads[$model->alias]->getOriginalFile();
        }

        return null;
    }

    /**
     * Return the transformed File objects.
     *
     * @param Model $model
     * @return \Transit\File[]
     */
    public function getTransformedFiles(Model $model) {
        if (isset($this->_uploads[$model->alias])) {
            return $this->_uploads[$model->alias]->getTransformedFiles();
        }

        return array();
    }

    /**
     * Trigger callback methods to modify attachment settings before uploading.
     *
     * @param Model $model
     * @param array $options
     * @return array
     */
    protected function _settingsCallback(Model $model, array $options) {
        if (method_exists($model, 'beforeUpload')) {
            $options = $model->beforeUpload($options);
        }

        if ($options['transforms'] && method_exists($model, 'beforeTransform')) {
            foreach ($options['transforms'] as $i => $transform) {
                $options['transforms'][$i] = $model->beforeTransform($transform);
            }
        }

        if ($options['transport'] && method_exists($model, 'beforeTransport')) {
            $options['transport'] = $model->beforeTransport($options['transport']);
        }

        return $options;
    }

    /**
     * Add Transit Transformers based on the attachment settings.
     *
     * @param Model $model
     * @param \Transit\Transit $transit
     * @param array $attachment
     */
    protected function _addTransformers(Model $model, Transit $transit, array $attachment) {
        if (empty($attachment['transforms'])) {
            return;
        }

        foreach ($attachment['transforms'] as $options) {
            $transformer = $this->_getTransformer($attachment, $options);

            if ($options['self']) {
                $transit->addSelfTransformer($transformer);
            } else {
                $transit->addTransformer($transformer);
            }
        }
    }

    /**
     * Set the Transit Transporter to use based on the attachment settings.
     *
     * @param Model $model
     * @param \Transit\Transit $transit
     * @param array $attachment
     */
    protected function _setTransporter(Model $model, Transit $transit, array $attachment) {
        if (empty($attachment['transport'])) {
            return;
        }

        $transit->setTransporter($this->_getTransporter($attachment, $attachment['transport']));
    }

    /**
     * Return a Transformer based on the options.
     *
     * @uses Transit\Transformer\Image\CropTransformer
     * @uses Transit\Transformer\Image\FlipTransformer
     * @uses Transit\Transformer\Image\ResizeTransformer
     * @uses Transit\Transformer\Image\ScaleTransformer
     * @uses Transit\Transformer\Image\RotateTransformer
     * @uses Transit\Transformer\Image\ExifTransformer
     * @uses Transit\Transformer\Image\FitTransformer
     *
     * @param array $attachment
     * @param array $options
     * @return \Transit\Transformer
     * @throws \InvalidArgumentException
     */
    protected function _getTransformer(array $attachment, array $options) {
        $class = isset($options['method']) ? $options['method'] : $options['class'];

        switch ($class) {
            case self::CROP:
                return new CropTransformer($options);
            break;
            case self::FLIP:
                return new FlipTransformer($options);
            break;
            case self::RESIZE:
                return new ResizeTransformer($options);
            break;
            case self::SCALE:
                return new ScaleTransformer($options);
            break;
            case self::ROTATE:
                return new RotateTransformer($options);
            break;
            case self::EXIF:
                return new ExifTransformer($options);
            break;
            case self::FIT:
                return new FitTransformer($options);
            break;
            default:
                if (isset($attachment['transformers'][$class])) {
                    return new $attachment['transformers'][$class]($options);
                }
            break;
        }

        throw new InvalidArgumentException(sprintf('Invalid transform class %s', $class));
    }

    /**
     * Return a Transporter based on the options.
     *
     * @uses Transit\Transporter\Aws\S3Transporter
     * @uses Transit\Transporter\Aws\GlacierTransporter
     *
     * @param array $attachment
     * @param array $options
     * @return \Transit\Transporter
     * @throws \InvalidArgumentException
     */
    protected function _getTransporter(array $attachment, array $options) {
        $class = $options['class'];

        switch ($class) {
            case self::S3:
                return new S3Transporter($options['accessKey'], $options['secretKey'], $options);
            break;
            case self::GLACIER:
                return new GlacierTransporter($options['accessKey'], $options['secretKey'], $options);
            break;
            case self::CLOUD_FILES:
                return new CloudFilesTransporter($options['username'], $options['apiKey'], $options);
            break;
            default:
                if (isset($attachment['transporters'][$class])) {
                    return new $attachment['transporters'][$class]($options);
                }
            break;
        }

        throw new InvalidArgumentException(sprintf('Invalid transport class %s', $class));
    }

    /**
     * Rename or move the file and return its relative path.
     *
     * @param Model $model
     * @param \Transit\File $file
     * @param array $options
     * @return string
     */
    protected function _renameAndMove(Model $model, File $file, array $options) {
        $nameCallback = null;

        if ($options['nameCallback'] && method_exists($model, $options['nameCallback'])) {
            $nameCallback = array($model, $options['nameCallback']);
        }

        if ($options['uploadDir']) {
            $file->move($options['uploadDir'], $options['overwrite']);
        }

        $file->rename($nameCallback, $options['append'], $options['prepend']);

        return (string) $options['finalPath'] . $file->basename();
    }

    /**
     * Trigger a find() call but disable virtual fields before doing so.
     *
     * @param Model $model
     * @param array $where
     * @param string $type
     * @return array
     */
    protected function _doFind(Model $model, array $where, $type = 'first') {
        $virtual = $model->virtualFields;
        $model->virtualFields = array();

        $results = $model->find($type, array(
            'conditions' => $where,
            'contain' => false,
            'recursive' => -1,
            'order' => ''
        ));

        $model->virtualFields = $virtual;

        return $results;
    }

    /**
     * Attempt to delete a file using the attachment settings.
     *
     * @uses Transit\File
     *
     * @param Model $model
     * @param string $field
     * @param string $path
     * @param string $column
     * @return bool
     */
    protected function _deleteFile(Model $model, $field, $path, $column) {
        if (empty($this->settings[$model->alias][$field])) {
            return false;
        }

        $attachment = $this->_settingsCallback($model, $this->settings[$model->alias][$field]);
        $basePath = $attachment['uploadDir'] ?: $attachment['tempDir'];

        // Get uploadDir from transform
        if ($attachment['transforms']) {
            foreach ($attachment['transforms'] as $transform) {
                if ($transform['dbColumn'] === $column) {
                    $basePath = $transform['uploadDir'];
                }
            }
        }

        try {
            // Delete remote file
            if ($attachment['transport']) {
                $transporter = $this->_getTransporter($attachment, $attachment['transport']);

                return $transporter->delete($path);

            // Delete local file
            } else {
                $file = new File($basePath . basename($path));

                return $file->delete();
            }

        } catch (Exception $e) {
            $this->log($e->getMessage(), LOG_DEBUG);
        }

        return false;
    }

    /**
     * Delete previous files if a record is being overwritten.
     *
     * @param Model $model
     * @param array $fields
     * @return void
     */
    protected function _cleanupOldFiles(Model $model, array $fields) {
        $columns = $this->_columns[$model->alias];
        $data = $this->_doFind($model, array($model->alias . '.' . $model->primaryKey => $model->id));

        if (empty($data[$model->alias])) {
            return;
        }

        foreach ($fields as $column => $value) {
            if (empty($data[$model->alias][$column])) {
                continue;
            }

            if (!empty($this->settings[$model->alias][$column])) {
                $attachment = $this->_settingsCallback($model, $this->settings[$model->alias][$column]);

                if (!$attachment['cleanup']) {
                    continue;
                }
            }

            // Delete if previous value doesn't match new value
            $previous = $data[$model->alias][$column];

            if ($previous !== $value) {
                $this->_deleteFile($model, $columns[$column], $previous, $column);
            }
        }
    }

    /**
     * Prepare transport configuration.
     *
     * @param array $settings
     * @return array
     */
    protected function _prepareTransport(array $settings) {
        $config = array();

        if (!empty($settings['transportDir'])) {
            $config['folder'] = $settings['transportDir'];
        }

        if (!empty($settings['returnUrl'])) {
            $config['returnUrl'] = $settings['returnUrl'];
        }

        return $config;
    }

}
