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
use Aws\Iam\IamClient;
use Aws\Iam\Exception\IamException;

/**
 * Class for provisioning AWS resources.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provision {

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
     * The prefix to use for the created AWS S3 buckets.
     *
     * @var string
     */
    protected $bucketprefix;

    /**
     *
     * @var \Aws\S3\S3Client S3 client.
     */
    private $s3client;

    /**
     *
     * @var \Aws\Iam\IamClient IAM client.
     */
    private $iamclient;


    /**
     * The constructor for the class
     *
     * @param string $keyid AWS API Access Key ID.
     * @param string $secret AWS API Secret Access Key.
     * @param string $region The AWS region to create the environment in.
     * @param string $bucketprefix The prefix to use for the created AWS S3 buckets.
     */
    public function __construct($keyid, $secret, $region, $bucketprefix) {
        global $CFG;

        $this->keyid = $keyid;
        $this->secret = $secret;
        $this->region = $region;

        if ($this->bucketprefix == '') {
            $this->bucketprefix = $CFG->siteidentifier;
        } else {
            $this->bucketprefix = $bucketprefix;
        }

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

    public function create_iam_client($handler=null){
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
        if ($this->iamclient== null) {
            $this->iamclient= new IamClient($connectionoptions);
        }

        return $this->iamclient;
    }

    /**
     *
     * @param string $bucketname
     * @return \stdClass
     */
    private function create_s3_bucket($bucketname) {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';
        try {
            $s3result = $this->s3client->createBucket(array(
                    'ACL' => 'private',
                    'Bucket' => $bucketname, // REQUIRED
                    'CreateBucketConfiguration' => array(
                            'LocationConstraint' => $this->region,
                    ),
            ));
            $result->message = $s3result['Location'];
        } catch (S3Exception $e) {
            $result->status = false;
            $result->code = $e->getAwsErrorCode();
            $result->message = $e->getAwsErrorMessage();
        }

        return $result;
    }

    /**
     * Creates a S3 bucket in AWS.
     *
     * @param string $suffix The bucket suffix to use.
     *
     */
    public function create_bucket($suffix) {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        $bucketname = $this->bucketprefix . $suffix;

        // Setup the S3 client.
        $this->create_s3_client();

        // Check bucket exists.
        // If not create it.
        $bucketexists = $this->check_bucket_exists($bucketname);
        if(!$bucketexists) {
            $result = $this->create_s3_bucket($bucketname);
        } else {
            $result->status = false;
            $result->code = 1;
            $result->message= get_string('provision:bucketexists', 'fileconverter_librelambda');
        }

        return $result;

    }

    private function create_iam_role() {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        try {
            $document = '{"Version":"2012-10-17","Statement":[{"Sid": "","Effect":"Allow","Principal":{"Service":"lambda.amazonaws.com"},"Action":"sts:AssumeRole"}]}';
            $iamresult = $this->iamclient->createRole(array(
                    'AssumeRolePolicyDocument' => $document, // REQUIRED
                    'Description' => 'Lambda PDF Role',
                    'RoleName' => 'lambda-pdf', // REQUIRED
            ));
            $result->message = $iamresult['Role']['ARN'];
        } catch (IamException $e) {
            $result->status = false;
            $result->code = $e->getAwsErrorCode();
            $result->message = $e->getAwsErrorMessage();
        }

        return $result;
    }

    private function attach_policy() {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        try {
            $iamresult = $this->iamclient->attachRolePolicy(array(
                    'PolicyArn' => 'arn:aws:iam::aws:policy/AWSLambdaExecute', // REQUIRED
                    'RoleName' => 'lambda-pdf', // REQUIRED
            ));
        } catch (IamException $e) {
            $result->status = false;
            $result->code = $e->getAwsErrorCode();
            $result->message = $e->getAwsErrorMessage();
        }

        return $result;
    }

    public function create_and_attach_iam() {

        // Setup the Iam client.
        $this->create_iam_client();

    }
}
