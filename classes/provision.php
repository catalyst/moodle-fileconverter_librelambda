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
use Aws\Exception\AwsException;

/**
 * Class for provisioning AWS resources.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provision {
    const DEFAULT_STACK_NAME = 'LambdaConvert';
    const MAX_BUCKET_PREFIX_LEN = 52;

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
     * The prefix to use for the created AWS S3 buckets - only lower cases are allowed.
     *
     * @var string
     */
    protected $bucketprefix;

    /**
     * The AWS stack name
     *
     * @var string
     */
    protected $stack;

    /**
     * The AWS resource bucket name
     *
     * @var string
     */
    protected $resourcebucket;

    /**
     * S3 client.
     *
     * @var S3Client
     */
    protected $s3client;

    /**
     * Cloud client.
     *
     * @var CloudFormationClient
     */
    protected $cloudformationclient;

    /**
     *
     * @var int
     */
    protected static $sleepbeforecheck = 5;

    /**
     * @var bool whether we should use a proxy.
     */
    private $useproxy;

    /**
     * The constructor for the class
     *
     * @param string $keyid  AWS API Access Key ID.
     * @param string $secret AWS API Secret Access Key.
     * @param string $region AWS Region to create the environment in.
     * @param string $stack  AWS Stack name
     */
    public function __construct($keyid, $secret, $region, $stack=null) {
        global $CFG;

        $this->keyid = $keyid;
        $this->secret = $secret;
        $this->region = $region;

        $this->stack = $stack ? $stack : self::DEFAULT_STACK_NAME;
        $ps = '-' . md5($CFG->siteidentifier);
        $this->bucketprefix = substr(
            strtolower($this->stack),
            0,
            self::MAX_BUCKET_PREFIX_LEN - strlen($this->stack)
        ) . $ps;
        $this->resourcebucket = $this->bucketprefix . '-resource';

        $this->useproxy = get_config('fileconverter_librelambda', 'useproxy');

        // Setup the S3 client.
        $this->s3client = $this->create_s3_client();

        // Setup the Cloudformation client.
        $this->cloudformationclient = $this->create_cloudformation_client();
    }

    /**
     * Stack name.
     *
     * @return string $stack
     */
    public function stack_name() {
        return $this->stack;
    }

    /**
     * Create a AWS S3 API client.
     *
     * @param \GuzzleHttp\Handler $handler Optional handler.
     * @return \Aws\S3\S3Client
     */
    protected function create_s3_client($handler=null) {
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

        return new S3Client($connectionoptions);
    }


    /**
     * Create the S3 resource bucket.
     *
     * @return void
     * @throws S3Exception
     */
    protected function create_resource_bucket() {
        try {
            $this->s3client->headBucket(
                ['Bucket' => $this->resourcebucket]
            );
        } catch (S3Exception $e) {
            // Check the error code. If code = NotFound, this means the bucket
            // does not exists, so we create it.
            if ($e->getAwsErrorCode() == 'NotFound') {
                $this->s3client->createBucket([
                    'ACL' => 'private',
                    'Bucket' => $this->resourcebucket,
                    'CreateBucketConfiguration' => ['LocationConstraint' => $this->region],
                ]);

                return;
            }

            // Otherwise rethrow.
            throw $e;
        }
    }

    /**
     * Remove the S3 resource bucket.
     *
     * @return void
     * @throws S3Exception
     */
    protected function remove_resource_bucket() {
        $s3result = $this->s3client->listObjects([
            'Bucket' => $this->resourcebucket
        ]);
        // If the bucket is not empty - empty it.
        if ($contents = $s3result['Contents']) {
            $objects = array_map(function ($c) {
                return $c['Key'];
            }, $contents);
            $deleteobjects = array_map(function ($o) {
                return ['Key' => $o];
            }, $objects);
            $args = [
                'Bucket' => $this->resourcebucket,
                'Delete' => [
                    'Objects' => $deleteobjects,
                ],
            ];
            $s3result = $this->s3client->deleteObjects($args);
            if (count($s3result['Deleted']) < count($contents)) {
                $e = $s3result['Errors'][0];
                // Upgrade to exception.
                throw new S3Exception(
                    $e['Message'],
                    $this->s3client->getCommand('DeleteObjects', $args)
                );
            }
        }

        $this->s3client->deleteBucket([
            'Bucket' => $this->resourcebucket
        ]);
    }

    /**
     * Uploads a file to the S3 resource bucket.
     *
     * @param string $filepath The path to the file to upload.
     * @return string $url uploaded file url.
     * @throws S3Exception
     */
    protected function upload_resource($filepath) {
        $fileinfo = pathinfo($filepath);
        $uploadparams = array(
            'Bucket' => $this->resourcebucket,
            'Key' => $fileinfo['basename'],
            'SourceFile' => $filepath,
        );

        $putobject = $this->s3client->putObject($uploadparams);
        return $putobject['ObjectURL'];
    }

    /**
     * Create a AWS Cloudformation API client.
     *
     * @param \GuzzleHttp\Handler $handler Optional handler.
     * @return \Aws\CloudFormation\CloudFormationClient
     */
    protected function create_cloudformation_client($handler=null) {
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

        return new CloudFormationClient($connectionoptions);
    }

    /**
     * Provision lambda stack and the resources.
     *
     * @param string $templatepath Stack template path.
     * @param array  $resources    Files to upload to the rtesource bucket.
     * @param bool   $replace      If the stack already exists replace it.
     * @return \stdClass $result The result of the stack creation.
     */
    public function provision_stack($templatepath, $resources, $replace) {
        $result = new \stdClass();
        $result->status = true;
        $result->message = '';

        if ($exists = $this->stack_status()) {
            if (!$replace) {
                $result->status = false;
                $result->message = 'Stack exsists and replacement not requested';
                return $result;
            }
        }

        $template = str_replace(
            '__STACK__', $this->stack,
            file_get_contents($templatepath)
        );
        $template = str_replace(
            '__BUCKET_PREFIX__', $this->bucketprefix,
            $template
        );

        try {
            $this->create_resource_bucket();
            foreach ($resources as $r) {
                $this->upload_resource($r);
            }

            if ($exists) {
                list($stackid, $outputs) = $this->update_stack($template);
                $result->message = get_string('provision:stackupdated', 'fileconverter_librelambda', $stackid);
            } else {
                list($stackid, $outputs) = $this->create_stack($template);
                $result->message = get_string('provision:stackcreated', 'fileconverter_librelambda', $stackid);
            }

            return (object) array_merge((array) $result, $outputs);

        } catch (AwsException $e) {
            $result->status = false;
            $result->message = $e->getMessage() . ": " . $e->getAwsErrorMessage();
            return $result;
        }
    }

    /**
     * Use CloudFormation to create the stack in AWS.
     * The stack template specifies the input and output S3 buckets,
     * the required roles and user permisions, and the Lambda function
     * to convert documents.
     *
     * @param string $template Stack template
     * @return string $stackid
     * @throws CloudFormationException
     */
    protected function create_stack($template) {
        $stackparams = [
            'Capabilities' => ['CAPABILITY_NAMED_IAM'],
            'StackName' => $this->stack,
            'TemplateBody' => $template,
            'OnFailure' => 'DELETE',
        ];

        $createstack = $this->cloudformationclient->createStack($stackparams);
        sleep(static::$sleepbeforecheck);

        // Stack creation can take several minutes.
        // Periodically check for stack updates.
        $exitcodes = [
            'CREATE_FAILED',
            'CREATE_COMPLETE',
            'DELETE_COMPLETE',
            'ROLLBACK_COMPLETE'
        ];
        if ($ready = $this->check_stack_ready($exitcodes)) {
            list($stackstatus, $outputs) = $ready;

            if ($stackstatus === 'CREATE_COMPLETE') {
                return [$createstack['StackId'], $outputs];
            }
        }

        // Not COMPLETE - throw an exception.
        throw new CloudFormationException(
            "Stack creation failed",
            $this->cloudformationclient->getCommand('CreateStack', $stackparams)
        );
    }

    /**
     * Use CloudFormation to update the stack in AWS.
     * Sometimes AWS cannot see the change, and refuses to update. In that case
     * we delete/create.
     *
     * @param string $template Stack template
     * @return string $stackid
     * @throws CloudFormationException
     */
    protected function update_stack($template) {
        $stackparams = [
            'Capabilities' => ['CAPABILITY_NAMED_IAM'],
            'StackName' => $this->stack,
            'TemplateBody' => $template,
            'UsePreviousTemplate' => false,
        ];

        try {
            $updatestack = $this->cloudformationclient->updateStack($stackparams);
        } catch (CloudFormationException $e) {
            $this->delete_stack();
            return $this->create_stack($template);
        }
        sleep(static::$sleepbeforecheck);

        // Stack update can take several minutes.
        // Periodically check for stack updates.
        $exitcodes = [
            'UPDATE_FAILED',
            'UPDATE_COMPLETE',
            'ROLLBACK_COMPLETE'
        ];
        if ($ready = $this->check_stack_ready($exitcodes)) {
            list($stackstatus, $outputs) = $ready;

            if ($stackstatus === 'UPDATE_COMPLETE') {
                return [$updatestack['StackId'], $outputs];
            }
        }

        // Not COMPLETE - throw an exception.
        throw new CloudFormationException(
            "Stack update failed",
            $this->cloudformationclient->getCommand('UpdateStack', $stackparams)
        );
    }

    /**
     * Remove the stack and the resources from AWS.
     *
     * @return void
     * @return \stdClass $result The result of stack removal.
     */
    public function remove_stack() {
        $result = new \stdClass();
        $result->status = true;
        $result->message = '';

        try {
            if ($stackstatus = $this->stack_status()) {
                $this->delete_stack();
            }

            $this->remove_resource_bucket();
        } catch (AwsException $e) {
            $result->status = false;
            $result->message = $e->getMessage() . ": " . $e->getAwsErrorMessage();
        }

        return $result;
    }

    /**
     * Use CloudFormation to remove the stack from AWS.
     *
     * @return void
     * @throws CloudFormationException
     */
    protected function delete_stack() {
        $deleteparams = [
            'StackName' => $this->stack,
        ];

        $this->cloudformationclient->deleteStack($deleteparams);
        sleep(static::$sleepbeforecheck);

        if ($ready = $this->check_stack_ready(['DELETE_COMPLETE', 'DELETE_FAILED'])) {
            list($stackstatus, $outputs) = $ready;
            $deleted = ($stackstatus === 'DELETE_COMPLETE');
        } else {
            $deleted = true; // We assume it is gone if there's no status.
        }
        if (!$deleted) {
            // Not deleted - throw an exception.
            throw new CloudFormationException(
                "Stack removal failed. Maybe the buckets are not empty?",
                $this->cloudformationclient->getCommand('DeleteObjects', $deleteparams)
            );
        }
    }

    /**
     * Check stack ready
     *
     * @param array $statuses List of terminal statuses
     * @return array|null [$status, $outputs]
     */
    private function check_stack_ready($statuses) {
        // Check stack status until acceptable code received,
        // or we timeout in 5 mins. We shortcut the process after
        // so many "no stack" responses.
        $maxnull = 3;
        for ($i = 0, $n = 0; $i < 10; $i++) {
            $res = $this->check_stack();
            if ($res === null) {
                $n++;
                if ($n > $maxnull) {
                    return;
                }
                continue;
            }

            list ($stackstatus, $outputs) = $res;

            // Exit in case terminal status is reported.
            if (in_array($stackstatus, $statuses, true)) {
                return $res;
            }

            echo "Stack status: " . $stackstatus . PHP_EOL;
            sleep(30);  // Sleep for a bit before rechecking.
        }
    }

    /**
     * Stack status
     *
     * @return string|null
     */
    public function stack_status() {
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
        $describeparams = [
            'StackName' => $this->stack,
        ];

        try {
            $stackdetails = $this->cloudformationclient->describeStacks($describeparams);
        } catch (CloudFormationException $e) {
            return;
        }

        $stacks = $stackdetails['Stacks'];
        if (count($stacks) != 1) {
            return;
        }
        $stackdetail = $stacks[0];
        $stackstatus = $stackdetail['StackStatus'];

        $out = [];
        if (isset($stackdetail['Outputs'])) {
            foreach ($stackdetail['Outputs'] as $output) {
                $out[$output['OutputKey']] = $output['OutputValue'];
            }
        }

        return [$stackstatus, $out];
    }
}
