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

use stored_file;
use moodle_exception;
use moodle_url;
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
        'html' => 'text/html'
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
     * Class constructor
     */
    public function __construct(){
        $this->config = get_config('fileconverter_librelambda');
    }

    /**
     *
     * @return \Aws\S3\S3Client
     */
    public function create_client(){
        $this->client = new S3Client ( [
                'version' => 'latest',
                'region' => $this->config->api_region,
                'credentials' => [
                        'key' => $this->config->api_key,
                        'secret' => $this->config->api_secret
                ]
        ]);

        return $this->client;
    }

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
     * @return boolean true on success, false on failure.
     */
    private static function is_bucket_accessible(\fileconverter_librelambda\converter $converter) {
        $connection = new \stdClass();
        $connection->success = true;
        $connection->message = '';

        try {
            $result = $converter->client->headBucket(array(
                'Bucket' => $converter->config->s3_input_bucket));

            $connection->message = get_string('settings:connectionsuccess', 'tool_objectfs');
        } catch (S3Exception $e) {
            $connection->success = false;
            $details = $converter->get_exception_details($e);
            $connection->message = get_string('settings:connectionfailure', 'tool_objectfs') . $details;
        }
        return $connection;
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

        // Check input bucket write capability.
        // Check output bucket read capability.
        // Check output bucket delete capability.

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

        return $this;
    }

    /**
     * Poll an existing conversion for status update.
     *
     * @param   conversion $conversion The file to be converted
     * @return  $this;
     */
    public function poll_conversion_status(conversion $conversion) {
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
        return implode(', ', ['rtf', 'doc', 'xls', 'docx', 'xlsx', 'ppt', 'pptx', 'pdf', 'html']);
    }
}
