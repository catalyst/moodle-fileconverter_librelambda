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
 * Class for provisioning AWS resources.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace fileconverter_librelambda;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Iam\Exception\IamException;

/**
 * Class for provisioning AWS resources.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tester {

    /**
     * AWS API Access Key ID.
     *
     * @var string
     */
    protected $keyid;

    /**
     * AWS API Secret Access Key.
     *
     * @var string
     */
    protected $secret;

    /**
     * The AWS region to create the environment in.
     *
     * @var string
     */
    protected $region;

    /**
     * The AWS S3 input bucket name.
     *
     * @var string
     */
    protected $inputbucket;

    /**
     * The AWS S3 output bucket name.
     *
     * @var string
     */
    protected $outputbucket;

    /**
     * Use AWS credentials from the environment.
     *
     * @var string
     */
    protected $usesdkcreds;

    /**
     *
     * @var \Aws\S3\S3Client S3 client.
     */
    private $s3client;

    /**
     * The constructor for the class
     *
     * @param string $keyid AWS API Access Key ID.
     * @param string $secret AWS API Secret Access Key.
     * @param string $region The AWS region to create the environment in.
     * @param string $inputbucket The AWS S3 input bucket name.
     * @param string $outputbucket The AWS S3 output bucket name.
     * @param string $usesdkcreds Use AWS credentials from the environment.
     */
    public function __construct($keyid, $secret, $region, $inputbucket, $outputbucket, $usesdkcreds) {
        global $CFG;

        $this->usesdkcreds = $usesdkcreds;
        $this->keyid = $keyid;
        $this->secret = $secret;
        $this->region = $region;
        $this->inputbucket = $inputbucket;
        $this->outputbucket = $outputbucket;

        $this->useproxy = get_config('fileconverter_librelambda', 'useproxy');

    }

    /**
     * Check if the bucket already exists in AWS.
     *
     * @param string $bucketname The name of the bucket to check.
     * @return boolean $bucketexists The result of the check.
     */
    private function check_bucket_exists($bucketname) {
        $bucketexists = true;

        try {
            $this->s3client->headBucket(array('Bucket' => $bucketname));
        } catch (S3Exception $e) {
            // Check the error code. If code = 403, this means the bucket
            // exists but we can't access it.  Need to know either way.
            $errorcode = $e->getAwsErrorCode();
            if ($errorcode != 403) {
                $bucketexists = false;
            }

        }
        return $bucketexists;
    }

    /**
     * Create AWS S3 API client.
     *
     * @param \GuzzleHttp\Handler $handler Optional handler.
     * @return \Aws\S3\S3Client
     */
    public function create_s3_client($handler=null) {
        $connectionoptions = array('version' => 'latest', 'region' => $this->region);

        if (!$this->usesdkcreds) {
            $connectionoptions['credentials'] = array('key' => $this->keyid, 'secret' => $this->secret);
        }

        // Check if we are using the Moodle proxy.
        if ($this->useproxy) {
            $connectionoptions['http'] = ['proxy' => \local_aws\local\aws_helper::get_proxy_string()];
        }

        // Allow handler overriding for testing.
        if ($handler != null) {
            $connectionoptions['handler'] = $handler;
        }

        // Only create client if it hasn't already been done.
        if ($this->s3client == null) {
            $this->s3client = new S3Client($connectionoptions);
        }

        return $this->s3client;
    }


    /**
     * Put file into S3 bucket
     *
     * @param string $filepath
     * @return \stdClass
     */
    private function bucket_put_object($filepath) {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = 'File not PDF';

        $client = $this->s3client;
        $fileinfo = pathinfo($filepath);

        $uploadparams = array(
            'Bucket' => $this->inputbucket, // Required.
            'Key' => $fileinfo['filename'], // Required.
            'SourceFile' => $filepath, // Required.
            'Metadata' => array(
                'targetformat' => 'pdf',
                'id' => 'abc123',
                'sourcefileid' => '123456',
            )
        );

        try {
            $putobject = $client->putObject($uploadparams);
            $result->message = $putobject['ObjectURL'];

        } catch (S3Exception $e) {
            $result->status = false;
            $result->code = $e->getAwsErrorCode();
            $result->message = $e->getAwsErrorMessage();
        }

        return $result;
    }

    /**
     * Check the output bucket for a successfully converted file.
     *
     * @param string $filepath
     * @return \stdClass $result Result of the check.
     */
    private function bucket_get_object($filepath) {
        $result = new \stdClass();
        $result->status = false;
        $result->code = 1;
        $result->message = '';

        $client = $this->s3client;
        $fileinfo = pathinfo($filepath);

        $downloadparams = array(
            'Bucket' => $this->outputbucket, // Required.
            'Key' => $fileinfo['filename'], // Required.
        );

        $timeout = time() + (60 * 10); // Ten minute timeout.
        $getobject = false;

        // Check for file until file available,
        // or we timeout.
        while (time() < $timeout) {
            try {
                $getobject = $client->getObject($downloadparams);
                break;  // We have object.

            } catch (S3Exception $e) {
                // No such key error is expected as object may not have been converted yet.
                // All other errors are bad.
                if ($e->getAwsErrorCode() != 'NoSuchKey') {
                    $result->code = $e->getAwsErrorCode();
                    $result->message = $e->getAwsErrorMessage();
                    break;  // Break on unexpected error.
                }
            }

            sleep(5);  // Sleep for a bit before rechecking.
        }

        // Check mime type of downloaded object.
        if ($getobject) {
            $tmpfile = tmpfile();
            fwrite($tmpfile, $getobject['Body']);
            $tmppath = stream_get_meta_data($tmpfile)['uri'];
            $mimetype = mime_content_type($tmppath);

            if ($mimetype == 'application/pdf') {
                $result->status = true;
                $result->code = 0;
                $result->message = 'PDF created.';
            }
            fclose($tmpfile);
        }

        // Delete output bucket file.
        if ($result->code == 0) {
            $client->deleteObject($downloadparams);
        }

        return $result;
    }

    /**
     * Uploads a file to the S3 input bucket.
     *
     * @param string $filepath The path to the file to upload.
     *
     */
    public function upload_file($filepath) {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        $bucketname = $this->inputbucket;

        // Setup the S3 client.
        $this->create_s3_client();

        // Check input bucket exists.
        $bucketexists = $this->check_bucket_exists($bucketname);
        if ($bucketexists) {
            // If we have bucket, upload file.
            $result = $this->bucket_put_object($filepath);
        } else {
            $result->status = false;
            $result->code = 1;
            $result->message = get_string('test:bucketnotexists', 'fileconverter_librelambda', 'input');
        }

        return $result;

    }

    /**
     * Check if the document conversion was successful.
     *
     * @param unknown $filepath
     * @return \stdClass $result The result of the check.
     */
    public function conversion_check($filepath) {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        $bucketname = $this->outputbucket;

        // Setup the S3 client.
        $this->create_s3_client();

        // Check output bucket exists.
        $bucketexists = $this->check_bucket_exists($bucketname);
        if ($bucketexists) {
            // If we have bucket, try to download file.
            $result = $this->bucket_get_object($filepath);
        } else {
            $result->status = false;
            $result->code = 1;
            $result->message = get_string('test:bucketnotexists', 'fileconverter_librelambda', 'input');
        }

        return $result;
    }
}
