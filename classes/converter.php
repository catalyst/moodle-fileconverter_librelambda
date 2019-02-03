<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class for converting files between different file formats using AWS.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace fileconverter_librelambda;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

use \core_files\conversion;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 * Class for converting files between different formats using unoconv.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class converter implements \core_files\converter_interface {
    /** @var array $imports List of supported import file formats */
    private static $imports = [
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.ms-powerpoint',
        'html' => 'text/html',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    ];

    /** @var array $export List of supported export file formats */
    private static $exports = [
        'pdf' => 'application/pdf'
    ];

    /**
     *
     * @var object Plugin confiuration.
     */
    private $config;

    /**
     *
     * @var \Aws\S3\S3Client S3 client.
     */
    private $client;


    /**
     *
     * @var integer status of current conversion.
     */
    public $status;

    /**
     * Class constructor
     */
    public function __construct() {
        $this->config = get_config('fileconverter_librelambda');
    }

    /**
     * Create AWS S3 API client.
     *
     * @param \GuzzleHttp\Handler $handler Optional handler.
     * @return \Aws\S3\S3Client
     */
    public function create_client($handler=null) {
        $connectionoptions = array(
            'version' => 'latest',
            'region' => $this->config->api_region,
            'credentials' => [
                'key' => $this->config->api_key,
                'secret' => $this->config->api_secret
            ]);

        // Allow handler overriding for testing.
        if ($handler != null) {
            $connectionoptions['handler'] = $handler;
        }

        // Only create client if it hasn't already been done.
        if ($this->client == null) {
            $this->client = new S3Client($connectionoptions);
        }

        return $this->client;
    }

    /**
     * When an exception occurs get and return
     * the exception details.
     *
     * @param \Aws\Exception $exception The thrown exception.
     * @return string $details The details of the exception.
     */
    private function get_exception_details($exception) {
        $message = $exception->getMessage();

        if (get_class($exception) !== 'S3Exception') {
            return "Not a S3 exception : $message";
        }

        $errorcode = $exception->getAwsErrorCode();

        $details = ' ';

        if ($message) {
            $details .= "ERROR MSG: " . $message . "\n";
        }

        if ($errorcode) {
            $details .= "ERROR CODE: " . $errorcode . "\n";
        }

        return $details;
    }

    /**
     * Check if the plugin has the required configuration set.
     *
     * @param \fileconverter_librelambda\converter $converter
     * @return boolean $isset Is all configuration options set.
     */
    private static function is_config_set(\fileconverter_librelambda\converter $converter) {
        $isset = true;

        if (empty($converter->config->api_key) ||
            empty($converter->config->api_secret) ||
            empty($converter->config->s3_input_bucket) ||
            empty($converter->config->s3_output_bucket) ||
            empty($converter->config->api_region)) {
                $isset = false;
        }
        return $isset;
    }

    /**
     * Tests connection to S3 and bucket.
     * There is no check connection in the AWS API.
     * We use list buckets instead and check the bucket is in the list.
     *
     * @param \fileconverter_librelambda\converter $converter
     * @return boolean true on success, false on failure.
     */
    private static function is_bucket_accessible(\fileconverter_librelambda\converter $converter) {
        $connection = new \stdClass();
        $connection->success = true;
        $connection->message = '';

        try {
            $result = $converter->client->headBucket(array(
                'Bucket' => $converter->config->s3_input_bucket));

            $connection->message = get_string('settings:connectionsuccess', 'fileconverter_librelambda');
        } catch (S3Exception $e) {
            $connection->success = false;
            $details = $converter->get_exception_details($e);
            $connection->message = get_string('settings:connectionfailure', 'fileconverter_librelambda') . $details;
        }
        return $connection;
    }

    /**
     * Tests connection to S3 and bucket.
     * There is no check connection in the AWS API.
     * We use list buckets instead and check the bucket is in the list.
     *
     * @param \fileconverter_librelambda\converter $converter
     * @param string $bucket The bucket to check.
     * @return boolean true on success, false on failure.
     */
    private static function have_bucket_permissions(\fileconverter_librelambda\converter $converter, $bucket) {
        $permissions = new \stdClass();
        $permissions->success = true;
        $permissions->messages = array();

        try {
            $result = $converter->client->putObject(array(
                'Bucket' => $bucket,
                'Key' => 'permissions_check_file',
                'Body' => 'test content'));
        } catch (S3Exception $e) {
            $details = $converter->get_exception_details($e);
            $permissions->messages[] = get_string('settings:writefailure', 'fileconverter_librelambda') . $details;
            $permissions->success = false;
        }

        try {
            $result = $converter->client->getObject(array(
                'Bucket' => $bucket,
                'Key' => 'permissions_check_file'));
        } catch (S3Exception $e) {
            $errorcode = $e->getAwsErrorCode();
            // Write could have failed.
            if ($errorcode !== 'NoSuchKey') {
                $details = $converter->get_exception_details($e);
                $permissions->messages[] = get_string('settings:readfailure', 'fileconverter_librelambda') . $details;
                $permissions->success = false;
            }
        }

        try {
            $result = $converter->client->deleteObject(array(
                'Bucket' => $bucket,
                'Key' => 'permissions_check_file'));
            $permissions->messages[] = get_string('settings:deletesuccess', 'fileconverter_librelambda');
        } catch (S3Exception $e) {
            $errorcode = $e->getAwsErrorCode();
            // Something else went wrong.
            if ($errorcode !== 'AccessDenied') {
                $details = $converter->get_exception_details($e);
                $permissions->messages[] = get_string('settings:deleteerror', 'fileconverter_librelambda') . $details;
            }
        }

        if ($permissions->success) {
            $permissions->messages[] = get_string('settings:permissioncheckpassed', 'fileconverter_librelambda');
        }
        return $permissions;
    }


    /**
     * Whether the plugin is configured and requirements are met.
     *
     * @return  bool
     */
    public static function are_requirements_met() {
        $converter = new \fileconverter_librelambda\converter();

        // First check that we have the basic configuration settings set.
        if (!self::is_config_set($converter)) {
            return false;
        }

        $converter->create_client();

        // Check that we can access the S3 Buckets.
        $connection = self::is_bucket_accessible($converter);
        if (!$connection->success) {
            return false;
        }

        // Check input bucket permissions.
        $bucket = $converter->config->s3_input_bucket;
        $permissions = self::have_bucket_permissions($converter, $bucket);
        if (!$permissions->success) {
            return false;
        }

        // Check output bucket permissions.
        $bucket = $converter->config->s3_output_bucket;
        $permissions = self::have_bucket_permissions($converter, $bucket);
        if (!$permissions->success) {
            return false;
        }

        return true;
    }

    /**
     * Convert a document to a new format and return a conversion object relating to the conversion in progress.
     *
     * @param   \core_files\conversion $conversion The file to be converted
     * @return  this
     */
    public function start_document_conversion(\core_files\conversion $conversion) {
        global $CFG;
        $file = $conversion->get_sourcefile();

        $uploadparams = array(
            'Bucket' => $this->config->s3_input_bucket, // Required.
            'Key' => $file->get_pathnamehash(), // Required.
            'Body' => $file->get_content_file_handle(), // Required.
            'Metadata' => array(
                'targetformat' => $conversion->get('targetformat'),
                'id' => $conversion->get('id'),
                'sourcefileid' => $conversion->get('sourcefileid'),
            )
        );

        // Upload to S3 input bucket and set status to in progress, or failed if not good upload.
        $s3client = $this->create_client();
        try {
            $result = $s3client->putObject($uploadparams);
            $conversion->set('status', conversion::STATUS_IN_PROGRESS);
            $this->status = conversion::STATUS_IN_PROGRESS;
        } catch (S3Exception $e) {
            $conversion->set('status', conversion::STATUS_FAILED);
            $this->status = conversion::STATUS_FAILED;
        }

        return $this;
    }

    /**
     * Poll an existing conversion for status update.
     *
     * @param   conversion $conversion The file to be converted
     * @return  $this;
     */
    public function poll_conversion_status(conversion $conversion) {

        $file = $conversion->get_sourcefile();
        $tmpdir = make_request_directory();
        $saveas = $tmpdir . '/' . $file->get_pathnamehash();

        $downloadparams = array(
            'Bucket' => $this->config->s3_output_bucket, // Required.
            'Key' => $file->get_pathnamehash(), // Required.
            'SaveAs' => $saveas
        );

        // Check output bucket for file.
        $s3client = $this->create_client();
        try {
            $result = $s3client->getObject($downloadparams);
            $conversion->store_destfile_from_path($saveas);
            $conversion->set('status', conversion::STATUS_COMPLETE);
            $conversion->update();
        } catch (S3Exception $e) {
            $errorcode = $e->getAwsErrorCode();
            if ($errorcode == 'NoSuchKey') {
                $conversion->set('status', conversion::STATUS_IN_PROGRESS);
            } else {
                $conversion->set('status', conversion::STATUS_FAILED);
            }
        }

        return $this;
    }

    /**
     * Whether a file conversion can be completed using this converter.
     *
     * @param   string $from The source type
     * @param   string $to The destination type
     * @return  bool
     */
    public static function supports($from, $to) {
        // This is not a one-liner because of php 5.6.
        $imports = self::$imports;
        $exports = self::$exports;
        return isset($imports[$from]) && isset($exports[$to]);
    }

    /**
     * A list of the supported conversions.
     *
     * @return  string
     */
    public function get_supported_conversions() {
        return implode(', ', ['doc', 'docx', 'rtf', 'xls', 'xlsx', 'ppt', 'pptx', 'html', 'odt', 'ods', 'pdf']);
    }
}
