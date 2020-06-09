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
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'txt' => 'text/plain',
        'gif' => 'image/gif',
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
        $connectionoptions = array('version' => 'latest',
                            'region' => isset($this->config->api_region) ? $this->config->api_region : 'ap-southeast-2');

        if (isset($this->config->usesdkcreds)) {
            $connectionoptions['credentials'] = array('key' => $this->config->api_key, 'secret' => $this->config->api_secret);
        }

        // Check if we are using the Moodle proxy.
        if (isset($this->config->useproxy)) {
            $connectionoptions['http'] = ['proxy' => \local_aws\local\aws_helper::get_proxy_string()];
        }

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
            return "Not a S3 exception: $message";
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
        if ($converter->get_usesdkcreds()) {
            if (empty($converter->config->s3_input_bucket) ||
                empty($converter->config->s3_output_bucket) ||
                empty($converter->config->api_region)) {
                return false;
            }
        } else {
            if (empty($converter->config->api_key) ||
                empty($converter->config->api_secret) ||
                empty($converter->config->s3_input_bucket) ||
                empty($converter->config->s3_output_bucket) ||
                empty($converter->config->api_region)) {
                return false;
            }
        }
        return $isset;
    }

    /**
     * Tests connection to S3 and bucket.
     * There is no check connection in the AWS API.
     * We use list buckets instead and check the bucket is in the list.
     *
     * @param \fileconverter_librelambda\converter $converter
     * @param string $bucket Name of buket to check.
     * @return boolean true on success, false on failure.
     */
    private static function is_bucket_accessible(\fileconverter_librelambda\converter $converter, $bucket) {
        $connection = new \stdClass();
        $connection->success = true;
        $connection->message = '';

        try {
            $result = $converter->client->headBucket(array(
                'Bucket' => $bucket));

            $connection->message = get_string('settings:connectionsuccess', 'fileconverter_librelambda');
        } catch (S3Exception $e) {
            $connection->success = false;
            $details = $converter->get_exception_details($e);
            $connection->message = get_string('settings:connectionfailure', 'fileconverter_librelambda') .' '. $details;
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
            $permissions->messages[] = get_string('settings:writefailure', 'fileconverter_librelambda') .' '. $details;
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
                $permissions->messages[] = get_string('settings:readfailure', 'fileconverter_librelambda') .' '. $details;
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
                $permissions->messages[] = get_string('settings:deleteerror', 'fileconverter_librelambda') .' '. $details;
            }
        }

        if ($permissions->success) {
            $permissions->messages[] = get_string('settings:permissioncheckpassed', 'fileconverter_librelambda');
        }
        return $permissions;
    }

    /**
     * Delete the converted file from the output bucket in S3.
     *
     * @param string $objectkey The key of the object to delete.
     */
    private function delete_converted_file($objectkey) {
        $deleteparams = array(
            'Bucket' => $this->config->s3_output_bucket, // Required.
            'Key' => $objectkey, // Required.
        );

        $s3client = $this->create_client();
        $s3client->deleteObject($deleteparams);

    }

    /**
     * Whether the plugin is configured and requirements are met.
     * @param string $rettype BASIC|SDK|CONNECTION.
     * @return  bool
     */
    public static function are_requirements_met($rettype = 'BASIC') {
        global $OUTPUT;
        $converter = new \fileconverter_librelambda\converter();

        // First check that we have the basic configuration settings set.
        if (!self::is_config_set($converter)) {
            if ($rettype == 'BASIC') {
                debugging('fileconverter_librelambda configuration not set');
                return false;
            } else if ($rettype == 'SDK') {
                return false;
            } else if ($rettype == 'CONNECTION') {
                return $OUTPUT->notification('fileconverter_librelambda configuration not set', 'notifyproblem');
            }
        }

        $converter->create_client();

        // Check that we can access the input S3 Bucket.
        $connection = self::is_bucket_accessible($converter, $converter->config->s3_input_bucket);
        if (!$connection->success) {
            if ($rettype == 'BASIC') {
                debugging('fileconverter_librelambda cannot connect to input bucket');
                return false;
            } else if ($rettype == 'SDK') {
                return false;
            } else if ($rettype == 'CONNECTION') {
                $output = get_string('settings:aws:input_bucket', 'fileconverter_librelambda') .': '. $connection->message;
                return $OUTPUT->notification($output, 'notifyproblem');
            }
        }

        // Check that we can access the output S3 Bucket.
        $connection = self::is_bucket_accessible($converter, $converter->config->s3_output_bucket);
        if (!$connection->success) {
            if ($rettype == 'BASIC') {
                debugging('fileconverter_librelambda cannot connect to output bucket');
                return false;
            } else if ($rettype == 'SDK') {
                return false;
            } else if ($rettype == 'CONNECTION') {
                $output = get_string('settings:aws:output_bucket', 'fileconverter_librelambda') .': '.$connection->message;
                return $OUTPUT->notification($output, 'notifyproblem');
            }
        }

        // Check input bucket permissions.
        $bucket = $converter->config->s3_input_bucket;
        $permissions = self::have_bucket_permissions($converter, $bucket);
        if (!$permissions->success) {
            if ($rettype == 'BASIC') {
                debugging('fileconverter_librelambda permissions failure on input bucket');
                return false;
            } else if ($rettype == 'SDK') {
                return false;
            } else if ($rettype == 'CONNECTION') {
                $output = get_string('settings:aws:input_bucket', 'fileconverter_librelambda') .': '. $connection->message;
                return $OUTPUT->notification($output, 'notifyproblem');
            }
        }

        // Check output bucket permissions.
        $bucket = $converter->config->s3_output_bucket;
        $permissions = self::have_bucket_permissions($converter, $bucket);
        if (!$permissions->success) {
            if ($rettype == 'BASIC') {
                debugging('fileconverter_librelambda permissions failure on output bucket');
                return false;
            } else if ($rettype == 'SDK') {
                return false;
            } else if ($rettype == 'CONNECTION') {
                $output = get_string('settings:aws:output_bucket', 'fileconverter_librelambda') .': '.$connection->message;
                return $OUTPUT->notification($output, 'notifyproblem');
            }
        }
        if ($rettype == 'CONNECTION') {
            return $OUTPUT->notification(get_string('settings:connectionsuccess', 'fileconverter_librelambda'), 'notifysuccess');
        }
        return true;
    }

    /**
     * Convert a document to a new format and return a conversion object relating to the conversion in progress.
     *
     * @param   \core_files\conversion $conversion The file to be converted
     * @return  $this
     */
    public function start_document_conversion(\core_files\conversion $conversion) {
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
        $conversion->update();

        // Trigger event.
        list($context, $course, $cm) = get_context_info_array($file->get_contextid());
        $eventinfo = array(
            'context' => $context,
            'courseid' => $course->id,
            'other' => array(
                'sourcefileid' => $conversion->get('sourcefileid'),
                'bucket' => $this->config->s3_input_bucket,
                'key' => $file->get_pathnamehash(),
                'targetformat' => $conversion->get('targetformat'),
                'id' => $conversion->get('id'),
                'status' => $this->status
            ));
        $event = \fileconverter_librelambda\event\start_document_conversion::create($eventinfo);
        $event->trigger();

        return $this;
    }

    /**
     * Poll an existing conversion for status update.
     *
     * @param   conversion $conversion The file to be converted
     * @return  $this;
     */
    public function poll_conversion_status(conversion $conversion) {

        // If conversion is complete or failed return early.
        if ($conversion->get('status') == conversion::STATUS_COMPLETE
            || $conversion->get('status') == conversion::STATUS_FAILED) {
            return $this;
        }

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
            $this->delete_converted_file($file->get_pathnamehash());
            $this->status = conversion::STATUS_COMPLETE;
        } catch (S3Exception $e) {
            $errorcode = $e->getAwsErrorCode();
            $timeinprogress = $conversion->get('timemodified') - $conversion->get('timecreated');
            if ($errorcode == 'NoSuchKey' && ($timeinprogress < $this->config->conversion_timeout)) {
                $conversion->set('status', conversion::STATUS_IN_PROGRESS);
                $this->status = conversion::STATUS_IN_PROGRESS;
            } else {
                $conversion->set('status', conversion::STATUS_FAILED);
                $this->status = conversion::STATUS_FAILED;
            }

        }
        $conversion->update();

        // Trigger event.
        list($context, $course, $cm) = get_context_info_array($file->get_contextid());
        $eventinfo = array(
            'context' => $context,
            'courseid' => $course->id,
            'other' => array(
                'sourcefileid' => $conversion->get('sourcefileid'),
                'bucket' => $this->config->s3_output_bucket,
                'key' => $file->get_pathnamehash(),
                'targetformat' => $conversion->get('targetformat'),
                'id' => $conversion->get('id'),
                'status' => $this->status
            ));
        $event = \fileconverter_librelambda\event\poll_conversion_status::create($eventinfo);
        $event->trigger();

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
        $conversions = array(
            'doc', 'docx', 'rtf', 'xls', 'xlsx', 'ppt', 'pptx', 'html', 'odt', 'ods', 'txt', 'png', 'jpg', 'gif', 'pdf'
            );
        return implode(', ', $conversions);
    }

    /**
     * Getter for usesdkcreds setting.
     *
     * @return string
     */
    public function get_usesdkcreds() {
        if (isset($this->config->usesdkcreds)) {
            return $this->config->usesdkcreds;
        }
        return false;
    }

    /**
     * Setter for usesdkcreds setting.
     *
     * @param boolean $value Value to be set.
     */
    public function set_usesdkcreds($value) {
        if ($value) {
            $this->config->usesdkcreds = true;
        } else {
            $this->config->usesdkcreds = false;
        }
    }
    /**
     * Perform test connection and permission check using
     * the default credential provider chain to find AWS credentials.
     *
     * @return string HTML string holding notification messages
     * @throws /coding_exception
     */
    public function define_client_check_sdk() {
        global $OUTPUT;
        $text = '';
        $converter = new \fileconverter_librelambda\converter();
        if (!$converter->get_usesdkcreds()) {
            $converter->set_usesdkcreds(true);
            if ($converter::are_requirements_met('SDK')) {
                $text = $OUTPUT->notification(get_string('settings:aws:sdkcredsok', 'fileconverter_librelambda'), 'notifysuccess');
            } else {
                $text = $OUTPUT->notification(get_string('settings:aws:sdkcredserror', 'fileconverter_librelambda'), 'warning');
            }
            $converter->set_usesdkcreds(false);
        }
        return $text;
    }

    /**
     * Moodle admin settings form to display connection details for the client service.
     *
     * @return string
     * @throws /coding_exception
     */
    public function define_client_check() {
        $converter = new \fileconverter_librelambda\converter();
        return $converter::are_requirements_met('CONNECTION');
    }

}
