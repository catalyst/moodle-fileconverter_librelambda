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
 * PHPUnit tests for Libre Lambda AWS provision.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace fileconverter_librelambda;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

use Aws\MockHandler;
use Aws\Result;
use Aws\CommandInterface;
use Psr\Http\Message\RequestInterface;
use Aws\S3\Exception\S3Exception;
use Aws\CloudFormation\Exception\CloudFormationException;

/**
 * Mock class to test for Libre Lambda AWS provision.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provision_mock extends provision {
    /**
     *
     * @var MockHandler
     */
    public $mocks3handler;

    /**
     *
     * @var MockHandler
     */
    public $mockcloudformationhandler;

    /**
     *
     * @var int
     */
    protected static $sleepbeforecheck = 0;

    /**
     *
     * @var string
     */
    public $resourcebucket;

    /**
     * The constructor for the class
     *
     * @param string $stack The stack name
     */
    public function __construct($stack=null) {
        $keyid = 'AAAAAAAAAAAA';
        $secret = 'aaaaaaaaaaaaaaaaaa';
        $region = 'ap-southeast-2';

        parent::__construct($keyid, $secret, $region, $stack);

        // Set up the AWS mocks.
        $this->mocks3handler = new MockHandler();
        $this->s3client = $this->create_s3_client($this->mocks3handler);
        $this->mockcloudformationhandler = new MockHandler();
        $this->cloudformationclient = $this->create_cloudformation_client($this->mockcloudformationhandler);
    }

    /**
     * Check if the bucket already exists in AWS.
     * Upgrade to public.
     *
     * @param string $bucketname The name of the bucket to check.
     * @return bool $bucketexists The result of the check.
     */
    public function check_bucket_exists($bucketname) {
        return parent::check_bucket_exists($bucketname);
    }

    /**
     * Create an S3 Bucket in AWS.
     * Upgrade to public.
     *
     * @return \stdClass $result The result of the bucket creation.
     */
    public function create_resource_bucket() {
        return parent::create_resource_bucket();
    }
}


/**
 * PHPUnit tests for Libre Lambda AWS provision.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provision_test extends \advanced_testcase {

    /**
     * Test the does bucket exist method. Should return false.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_create_resource_bucket_exists_false() {
        $provisioner = new provision_mock();

        $provisioner->mocks3handler->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new S3Exception('Mock exception', $cmd, ['code' => 'NotFound']);
        });
        $provisioner->mocks3handler->append(new Result(
            ['Location' => "http://bucket.s3.amazonaws.com/"]
        ));

        $provisioner->create_resource_bucket();

        $this->assertEquals(
            $provisioner->resourcebucket,
            $provisioner->mocks3handler->getLastCommand()['Bucket']
        );
    }

    /**
     * Test the does bucket exist method. Should return true.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_create_resource_bucket_exists_true() {
        $provisioner = new provision_mock();

        $provisioner->mocks3handler->append(new Result([]));

        $provisioner->create_resource_bucket();

        $this->assertEquals(
            $provisioner->resourcebucket,
            $provisioner->mocks3handler->getLastCommand()['Bucket']
        );
    }

    /**
     * Test the does bucket exist method. Should return true when bucket exists but we can't access.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_create_resource_bucket_exists_forbidden() {
        $provisioner = new provision_mock();

        $bucketname = 'foobar';
        $provisioner->mocks3handler->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new S3Exception('Mock exception', $cmd, ['code' => 'Forbidden']);
        });

        $this->expectException("Aws\\S3\\Exception\\S3Exception");
        $provisioner->create_resource_bucket();
    }

    /**
     * Test creating AWS stack failure when it exists and replace is false.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_provision_stack_exists_no_replace() {
        global $CFG;

        $provisioner = new provision_mock();

        $provisioner->mocks3handler->append(new Result([]));
        $provisioner->mocks3handler->append(new Result(['ObjectURL' => "https://amazon/lambdaconvert.zip"]));
        $provisioner->mockcloudformationhandler->append(new Result([
            'Stacks' => [['StackStatus' => 'CREATE_COMPLETE']],
        ]));

        $result = $provisioner->provision_stack(
            $CFG->dirroot . '/files/converter/librelambda/lambda/stack.template',
            [$CFG->dirroot . '/files/converter/librelambda/lambda/lambdaconvert.zip'],
            false
        );

        $this->assertEquals(
            null,
            $provisioner->mocks3handler->getLastCommand()
        );

        $this->assertFalse($result->status);
        $this->assertEquals('Stack exsists and replacement not requested', $result->message);

        $this->expectOutputString("");
    }

    /**
     * Helper that checks whether the AWS stack files are checked out.
     *
     * @return string AWS stack files dir
     */
    private function check_aws_stack(): string {
        $repo = "https://github.com/catalyst/moodle-fileconverter_librelambda-aws_stack.git";
        $stackdir = sys_get_temp_dir() . '/fileconverter_librelambda-aws_stack';
        if (!is_dir($stackdir)) {
            $this->markTestSkipped(implode("\n", [
                "$stackdir not found.",
                "You can either:",
                "  Checkout $repo in $stackdir",
                "  or",
                "  Run php cli/provision.php that will do that for you."
            ]));
        }
        return $stackdir;
    }

    /**
     * Test creating AWS stack success when it exists and replace is true.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_provision_stack_exists_replace() {
        $stackdir = $this->check_aws_stack();
        $lambdazip = 'lambdaconvert.zip';
        $provisioner = new provision_mock();

        $provisioner->mocks3handler->append(new Result([]));
        $provisioner->mocks3handler->append(new Result(['ObjectURL' => "https://amazon/$lambdazip"]));
        $provisioner->mockcloudformationhandler->append(new Result([
            'Stacks' => [['StackStatus' => 'CREATE_COMPLETE']],
        ]));
        $provisioner->mockcloudformationhandler->append(new Result([
            'StackId' => 'StackId',
        ]));
        $provisioner->mockcloudformationhandler->append(new Result([
            'Stacks' => [[
                'StackStatus' => 'UPDATE_COMPLETE',
                'Outputs' => [
                    [
                        'OutputKey' => 'InputBucket',
                        'OutputValue' => 'InputBucket'
                    ],
                    [
                        'OutputKey' => 'OutputBucket',
                        'OutputValue' => 'OutputBucket'
                    ],
                ],
            ]],
        ]));

        $result = $provisioner->provision_stack(
            "$stackdir/lambda/stack.template",
            ["$stackdir/lambda/$lambdazip"],
            true
        );

        $lasts3 = $provisioner->mocks3handler->getLastCommand();
        $this->assertEquals($lambdazip, $lasts3['Key']);
        $this->assertEquals($provisioner->resourcebucket, $lasts3['Bucket']);

        $this->assertEquals(
            \fileconverter_librelambda\provision::DEFAULT_STACK_NAME,
            $provisioner->mockcloudformationhandler->getLastCommand()['StackName']
        );

        $this->assertTrue($result->status);
        $this->assertEquals('Cloudformation stack updated. Stack ID is: StackId', $result->message);

        $this->expectOutputString("");
    }

    /**
     * Test creating AWS stack success when it does not exist.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_provision_stack_not_exists() {
        $stackdir = $this->check_aws_stack();
        $stack = 'AnotherStack';
        $lambdazip = 'lambdaconvert.zip';
        $provisioner = new provision_mock($stack);

        $provisioner->mocks3handler->append(new Result([]));
        $provisioner->mocks3handler->append(new Result(['ObjectURL' => "https://amazon/$lambdazip"]));
        $provisioner->mockcloudformationhandler->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new CloudFormationException('Mock exception', $cmd);
        });
        $provisioner->mockcloudformationhandler->append(new Result([
            'StackId' => 'StackId',
        ]));
        $provisioner->mockcloudformationhandler->append(new Result([
            'Stacks' => [[
                'StackStatus' => 'CREATE_COMPLETE',
                'Outputs' => [
                    [
                        'OutputKey' => 'InputBucket',
                        'OutputValue' => 'InputBucket'
                    ],
                    [
                        'OutputKey' => 'OutputBucket',
                        'OutputValue' => 'OutputBucket'
                    ],
                ],
            ]],
        ]));

        $result = $provisioner->provision_stack(
            "$stackdir/lambda/stack.template",
            ["$stackdir/lambda/$lambdazip"],
            true
        );

        $lasts3 = $provisioner->mocks3handler->getLastCommand();
        $this->assertEquals($lambdazip, $lasts3['Key']);
        $this->assertEquals($provisioner->resourcebucket, $lasts3['Bucket']);

        $this->assertEquals(
            $stack,
            $provisioner->mockcloudformationhandler->getLastCommand()['StackName']
        );

        $this->assertTrue($result->status);
        $this->assertEquals('Cloudformation stack created. Stack ID is: StackId', $result->message);

        $this->expectOutputString("");
    }

    /**
     * Test creating AWS stack success when it does not exist.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_remove_stack_remove_objects_ok() {
        $stackdir = $this->check_aws_stack();
        $filename = 'some.file';
        $provisioner = new provision_mock();

        $cloudformationpath = "$stackdir/lambda/stack.template";
        $provisioner->mockcloudformationhandler->append(new Result([
            'Stacks' => [['StackStatus' => 'CREATE_COMPLETE']],
        ]));
        $provisioner->mockcloudformationhandler->append(new Result([]));
        $provisioner->mockcloudformationhandler->append(new Result([
            'Stacks' => [['StackStatus' => 'DELETE_COMPLETE']],
        ]));
        $provisioner->mocks3handler->append(new Result([ // listObjects.
            'Contents' => [
                ['Key' => $filename],
            ]
        ]));
        $provisioner->mocks3handler->append(new Result([ // deleteObjects.
            'Deleted' => [
                ['Key' => $filename],
            ],
            'Errors' => [],
        ]));
        $provisioner->mocks3handler->append(new Result([])); // deleteBucket.

        $result = $provisioner->remove_stack();

        $this->assertEquals(
            \fileconverter_librelambda\provision::DEFAULT_STACK_NAME,
            $provisioner->mockcloudformationhandler->getLastCommand()['StackName']
        );
        $this->assertEquals(
            $provisioner->resourcebucket,
            $provisioner->mocks3handler->getLastCommand()['Bucket']
        );

        $this->assertTrue($result->status);
        $this->assertEquals('', $result->message);

        $this->expectOutputString("");
    }
}
