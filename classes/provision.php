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
use Aws\CloudFormation\CloudFormationClient;
use Aws\CloudFormation\Exception\CloudFormationException;

/**
 * Class for provisioning AWS resources.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provision {
    const STACK_NAME = 'LambdaConvertStack';

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
     * @var \Aws\Lambda\LambdaClient Lambda client.
     */
    private $cloudformationclient;

    /**
     * @var bool whether we should use a proxy.
     */
    private $useproxy;

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

        if ($bucketprefix == '') {
            $this->bucketprefix = md5($CFG->siteidentifier);
        } else {
            $this->bucketprefix = $bucketprefix;
        }

        $this->useproxy = get_config('fileconverter_librelambda', 'useproxy');

    }

    /**
     * Get the S3 bucket prefix.
     *
     * @return string The bucket prefix.
     */
    public function get_bucket_prefix() {
        return $this->bucketprefix;
    }

    /**
     * Check if the bucket already exists in AWS.
     *
     * @param string $bucketname The name of the bucket to check.
     * @return bool $bucketexists The result of the check.
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
        $connectionoptions = array(
                'version' => 'latest',
                'region' => $this->region,
                'credentials' => [
                        'key' => $this->keyid,
                        'secret' => $this->secret
                ]);

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
     * Create an S3 Bucket in AWS.
     *
     * @param string $bucketname The name to use for the S3 bucket.
     * @return \stdClass $result The result of the bucket creation.
     */
    private function create_s3_bucket($bucketname) {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';
        try {
            $s3result = $this->s3client->createBucket(array(
                    'ACL' => 'private',
                    'Bucket' => $bucketname, // Required.
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
        if (!$bucketexists) {
            $result = $this->create_s3_bucket($bucketname);
            $result->message = get_string('provision:bucketcreated', 'fileconverter_librelambda', [
                'bucket' => $bucketname,
                'location' => $result->message,
            ]);
        } else {
            $result->message = get_string('provision:bucketexists', 'fileconverter_librelambda', [
                'bucket' => $bucketname,
            ]);
        }

        $result->bucketname = $bucketname;
        return $result;
    }


    /**
     * Put file into S3 bucket.
     *
     * @param string $filepath The path to the local file to Put.
     * @param string $bucketname Te name of the bucket to use.
     * @return \stdClass $result The result of the Put operation.
     */
    private function bucket_put_object($filepath, $bucketname) {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        $client = $this->s3client;
        $fileinfo = pathinfo($filepath);

        $uploadparams = array(
            'Bucket' => $bucketname, // Required.
            'Key' => $fileinfo['basename'], // Required.
            'SourceFile' => $filepath, // Required.
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
     * @param string $bucketname Te name of the bucket to use.
     * @return \stdClass $result The result of the Put operation.
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
        if ($bucketexists) {
            // If we have bucket, upload file.
            $result = $this->bucket_put_object($filepath, $bucketname);
        } else {
            $result->status = false;
            $result->code = 1;
            $result->message = get_string('test:bucketnotexists', 'fileconverter_librelambda', 'input');
        }

        return $result;

    }

    /**
     * Create and AWS Cloudformation API client.
     *
     * @param \GuzzleHttp\Handler $handler Optional handler.
     * @return \Aws\CloudFormation\CloudFormationClient The create Cloudformation client.
     */
    public function create_cloudformation_client($handler=null) {
        $connectionoptions = array(
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => $this->keyid,
                'secret' => $this->secret
            ]);

        // Check if we are using the Moodle proxy.
        if ($this->useproxy) {
            $connectionoptions['http'] = ['proxy' => \local_aws\local\aws_helper::get_proxy_string()];
        }

        // Allow handler overriding for testing.
        if ($handler != null) {
            $connectionoptions['handler'] = $handler;
        }

        // Only create client if it hasn't already been done.
        if ($this->cloudformationclient == null) {
            $this->cloudformationclient = new CloudFormationClient($connectionoptions);
        }

        return $this->cloudformationclient;
    }

    /**
     * Use cloudformation to create the "stack" in AWS.
     * The stack creation creates the input and output S3 buckets,
     * the required roles and user permisions, and the Lmabda function
     * to convert documents.
     *
     * @param array $params The params to create the stack with.
     * @return \stdClass $result The result of stack creation.
     */
    public function create_stack($params) {
        $result = new \stdClass();
        $result->status = true;
        $result->code = 0;
        $result->message = '';

        // Setup the Cloudformation client.
        $this->create_cloudformation_client();

        $template = file_get_contents($params['templatepath']);

        $stackparams = [
            'Capabilities' => ['CAPABILITY_NAMED_IAM'],
            'Parameters' => [
                [
                    'ParameterKey' => 'BucketPrefix',
                    'ParameterValue' => $params['bucketprefix'],
                ],
                [
                    'ParameterKey' => 'ResourceBucket',
                    'ParameterValue' => $params['resourcebucket'],
                ],
                [
                    'ParameterKey' => 'LambdaArchiveKey',
                    'ParameterValue' => $params['lambdaarchive'],
                ],
                [
                    'ParameterKey' => 'LambdaLayerKey',
                    'ParameterValue' => $params['lambdalayer'],
                ],
            ],
            'StackName' => self::STACK_NAME, // Required.
            'TemplateBody' => $template,
        ];

        $client = $this->cloudformationclient;
        $create = true;
        $check_retries = 10;

        // Check for stack.
        if ($this->stack_status()) {

            try {
                $stackparams['UsePreviousTemplate'] = false;
                $updatestack = $client->updateStack($stackparams);
                $result->message = get_string('provision:stackupdated', 'fileconverter_librelambda', $updatestack['StackId']);
                $create = false;
                $check_retries = 20;

            } catch (CloudFormationException $e) {
                $deleteparams = [
                    'StackName' => self::STACK_NAME,
                ];

                $client->deleteStack($deleteparams);
                if ($ready = $this->check_stack_ready(['DELETE_COMPLETE', 'DELETE_FAILED'])) {
                    list($stackstatus, $outputs) = $ready;
                    $deleted = ($stackstatus === 'DELETE_COMPLETE');
                } else {
                    $deleted = true; // We assume it is gone if there's no status.
                }
                if (!$deleted) {
                    $result->status = false;
                    $result->code = $stackstatus;
                    $result->message = 'Stack removal failed. Maybe the buckets are not empty?';
                    return $result;
                }
            }
        }

        if ($create) {
            // Create stack.
            try {
                $stackparams['OnFailure'] = 'DELETE';
                $createstack = $client->createStack($stackparams);
                $result->message = get_string('provision:stackcreated', 'fileconverter_librelambda', $createstack['StackId']);

            } catch (CloudFormationException $e) {
                $result->status = false;
                $result->code = $e->getAwsErrorCode();
                $result->message = $e->getAwsErrorMessage();
                return $result;
            }
        }

        // Stack creation can take several minutes.
        // Periodically check for stack updates.
        $exitcodes = [
            'CREATE_FAILED',
            'CREATE_COMPLETE',
            'UPDATE_COMPLETE',
            'DELETE_COMPLETE'
        ];
        if ($ready = $this->check_stack_ready($exitcodes, $check_retries)) {
            list($stackstatus, $outputs) = $ready;

            if ($stackstatus === 'CREATE_COMPLETE' || $stackstatus === 'UPDATE_COMPLETE') {
                if ($outputs) {
                    foreach ($outputs as $output) {
                        switch ($output['OutputKey']) {
                            case 'S3UserAccessKey':
                                $result->S3UserAccessKey = $output['OutputValue'];
                                break;

                            case 'S3UserSecretKey':
                                $result->S3UserSecretKey = $output['OutputValue'];
                                break;

                            case 'InputBucket':
                                $result->InputBucket = $output['OutputValue'];
                                break;

                            case 'OutputBucket':
                                $result->OutputBucket = $output['OutputValue'];
                                break;
                        }
                    }
                } else {
                    $result->status = false;
                    $result->code = $stackstatus;
                    $result->message = 'No stack details';
                    return $result;
                }
            } else {
                $ready = null;
            }
        }

        if (!$ready) {
            $result->status = false;
            $result->code = $stackstatus;
            $result->message = 'Stack creation failed';
        }

        return $result;
    }

    /**
     * Check stack ready
     *
     * @param array $statuses List of acceptable statuses
     * @return array|null [$status, $outputs]
     */
    private function check_stack_ready($statuses, $retries=10) {
        $client = $this->cloudformationclient;

        // Check stack status until acceptable code received,
        // or we timeout in 5 mins.
        for ($i = 0; $i < $retries; $i++) {
            $res = $this->check_stack();
            if ($res === null) {
                return;
            }

            list ($stackstatus, $outputs) = $res;

            // Exit under cetain conditions.
            if (in_array($stackstatus, $statuses, true)) {
                return $res;
            }

            sleep(30);  // Sleep for a bit before rechecking.
        }
    }

    /**
     * Stack status
     *
     * @return string|null
     */
    private function stack_status() {
        $res = $this->check_stack();
        if ($res === null) {
            return;
        }

        list ($stackstatus, $outputs) = $res;
        return $stackstatus;
    }

    /**
     * Check stack
     *
     * @return array|null [$status, $outputs]
     */
    private function check_stack() {
        $client = $this->cloudformationclient;

        $describeparams = [
            'StackName' => self::STACK_NAME,
        ];

        try {
            $stackdetails = $client->describeStacks($describeparams);
        } catch (CloudFormationException $e) {
            return;
        }

        $stacks = $stackdetails['Stacks'];
        if (count($stacks) != 1) {
            return;
        }
        $stackdetail = $stacks[0];
        $stackstatus = $stackdetail['StackStatus'];

        echo "Stack status: " . $stackstatus . PHP_EOL;
        return [$stackstatus, isset($stackdetail['Outputs']) ? $stackdetail['Outputs'] : null];
    }
}
