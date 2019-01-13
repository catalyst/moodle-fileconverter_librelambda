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
     */
    public function __construct($keyid, $secret, $region, $inputbucket, $outputbucket) {
        global $CFG;

        $this->keyid = $keyid;
        $this->secret = $secret;
        $this->region = $region;
        $this->inputbucket = $inputbucket;
        $this->outputbucket = $outputbucket;

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
     *
     * @return \Aws\S3\S3Client
     */
    public function create_s3_client($handler=null){
        $connectionoptions = array(
                'version' => 'latest',
                'region' => $this->region,
                'credentials' => [
                        'key' => $this->keyid,
                        'secret' => $this->secret
                ]);

        // Allow handler overriding for testing.
        if ($handler!=null) {
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
    private function bucket_put_object($filepath){
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        $client = $this->s3client;
        $fileinfo = pathinfo($filepath);

        $uploadparams = array(
            'Bucket' => $this->inputbucket, // REQUIRED.
            'Key' => $fileinfo['filename'], // REQUIRED.
            'SourceFile' => $filepath, // REQUIRED.
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
        if($bucketexists) {
            // If we have bucket, upload file
            $result = $this->bucket_put_object($filepath);
        } else {
            $result->status = false;
            $result->code = 1;
            $result->message= get_string('test:bucketnotexists', 'fileconverter_librelambda', 'input');
        }

        return $result;

    }
}
