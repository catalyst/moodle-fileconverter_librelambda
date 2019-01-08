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
use Aws\Lambda\LambdaClient;
use Aws\Lambda\Exception\LambdaException;
use GuzzleHttp\Psr7;

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
     *
     * @var \Aws\Lambda\LambdaClient Lambda client.
     */
    private $lambdaclient;


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
            $this->bucketprefix = md5($CFG->siteidentifier);
        } else {
            $this->bucketprefix = $bucketprefix;
        }

    }

    public function get_bucket_prefix() {
        return $this->bucketprefix;
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
     *
     * @param unknown $handler
     * @return \Aws\Iam\IamClient
     */
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
        $bucketname = $this->bucketprefix . '-' . $suffix;

        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';
        $result->bucketname = $bucketname;

        // Setup the S3 client.
        $this->create_s3_client();

        // Check bucket exists.
        // If not create it.
        $bucketexists = $this->check_bucket_exists($bucketname);
        if(!$bucketexists) {
            $result = $this->create_s3_bucket($bucketname);
            $result->bucketname = $bucketname;
        } else {
            $result->status = false;
            $result->code = 1;
            $result->message= get_string('provision:bucketexists', 'fileconverter_librelambda');
        }

        return $result;

    }

    /**
     *
     * @return \stdClass
     */
    private function create_iam_role() {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        try {
            $document = '{"Version":"2012-10-17","Statement":[{"Sid": "","Effect":"Allow","Principal":{"Service":"lambda.amazonaws.com"},"Action":"sts:AssumeRole"}]}';
            $iamresult = $this->iamclient->createRole(array(
                    'AssumeRolePolicyDocument' => $document, // REQUIRED.
                    'Description' => 'Lambda Converter Role',
                    'RoleName' => 'lambda-convert', // REQUIRED.
            ));
            $result->message = $iamresult['Role']['Arn'];
        } catch (IamException $e) {
            $result->status = false;
            $result->code = $e->getAwsErrorCode();
            $result->message = $e->getAwsErrorMessage();
        }

        return $result;
    }

    /**
     *
     * @return \stdClass
     */
    private function attach_policy() {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        try {
            $document = '{"Version": "2012-10-17",'.
            '"Statement":[{"Effect": "Allow", "Action": ["logs:*"], "Resource": "arn:aws:logs:*:*:*"},'.
            '{"Effect": "Allow", "Action":["s3:GetObject", "s3:PutObject", "s3:DeleteObject"],"Resource": "arn:aws:s3:::*"}]}';
            $iamresult = $this->iamclient->putRolePolicy(array(
                'PolicyDocument' => $document,  // REQUIRED.
                'PolicyName' => 'lambda-convert-policy', // REQUIRED.
                'RoleName' => 'lambda-convert', // REQUIRED.
            ));
        } catch (IamException $e) {
            $result->status = false;
            $result->code = $e->getAwsErrorCode();
            $result->message = $e->getAwsErrorMessage();
        }

        return $result;
    }

    /**
     *
     */
    public function create_and_attach_iam() {
        $policyresult= new \stdClass();

        // Setup the Iam client.
        $this->create_iam_client();

        // Create IAM role.
        $roleresult = $this->create_iam_role();

        if ($roleresult->status) {
            // Attach policy.
            $policyresult = $this->attach_policy();
        }

        if (isset($policyresult->status) && !$policyresult->status) {
            $result = $policyresult;
        } else {
            $result = $roleresult;
        }

        return $result;

    }

    /**
     * Put file into S3 bucket
     *
     * @param string $filepath
     * @return \stdClass
     */
    private function bucket_put_object($filepath, $bucketname){
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        $client = $this->s3client;
        $fileinfo = pathinfo($filepath);

        $uploadparams = array(
            'Bucket' => $bucketname, // REQUIRED.
            'Key' => $fileinfo['basename'], // REQUIRED.
            'SourceFile' => $filepath, // REQUIRED.
            'Metadata' => array(
                'description' => 'This is the Libreoffice archive.',
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
    public function upload_file($filepath, $bucketname) {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        // Setup the S3 client.
        $this->create_s3_client();

        // Check input bucket exists.
        $bucketexists = $this->check_bucket_exists($bucketname);
        if($bucketexists) {
            // If we have bucket, upload file
            $result = $this->bucket_put_object($filepath, $bucketname);
        } else {
            $result->status = false;
            $result->code = 1;
            $result->message= get_string('test:bucketnotexists', 'fileconverter_librelambda', 'input');
        }

        return $result;

    }
    /**
     *
     * @param unknown $handler
     * @return \Aws\Iam\IamClient
     */
    public function create_lambda_client($handler=null){
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
        if ($this->lambdaclient== null) {
            $this->lambdaclient= new LambdaClient($connectionoptions);
        }

        return $this->iamclient;
    }

    public function lambda_create($params){
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        // Setup the Lambda client.
        $this->create_lambda_client();

        // Create Lambda function
        $handle = fopen($params['lambdarchive'] , 'r');
        $stream = Psr7\stream_for($handle);

        $lambdaparams = array(
            'Code' => array( // REQUIRED
                'ZipFile' => $stream,
            ),
            'Description' => 'Libre Office document converter function',
            'Environment' => array(
                'Variables' => array(
                    'LibreLocation' => $params['librearchive'],
                    'OutputBucket' => $params['outputbucket']
                ),
            ),
            'FunctionName' => 'lambdaconvert', // REQUIRED
            'Handler' => 'lambdaconvert.lambda_handler', // REQUIRED
            'MemorySize' => 256,
            'Publish' => true,
            'Role' => $params['iamrole'], // REQUIRED
            'Runtime' => 'python3.6', // REQUIRED
            'Timeout' => 360
        );

        $client = $this->lambdaclient;

        try {
            $createfunction = $client->createFunction($lambdaparams);
            $result->message = $createfunction['FunctionName'];

        } catch (LambdaException $e) {
            $result->status = false;
            $result->code = $e->getAwsErrorCode();
            $result->message = $e->getAwsErrorMessage();
        }
        fclose($handle);
        // Create event source mapping
        $permissionparams = array(
            'Action' => 'lambda:InvokeFunction',
            'FunctionName' =>  $createfunction['FunctionName'], // REQUIRED,
            'Principal' => 'events.amazonaws.com',
            'SourceArn' => 'arn:aws:s3:::'. $params['inputbucket'], // REQUIRED
            'StatementId' => 'ID-1',
        );

        if ($result->code == 0) {
            try {
                $addpermission = $client->addPermission($permissionparams);
                $result->message = $addpermission['Statement'];

            } catch (LambdaException $e) {
                $result->status = false;
                $result->code = $e->getAwsErrorCode();
                $result->message = $e->getAwsErrorMessage();
            }
        }

        return $result;
    }
}
