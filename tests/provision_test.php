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
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

use Aws\Result;
use Aws\MockHandler;
use Aws\CommandInterface;
use Psr\Http\Message\RequestInterface;
use Aws\S3\Exception\S3Exception;
use Aws\Iam\Exception\IamException;

/**
 * PHPUnit tests for Libre Lambda AWS provision.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fileconverter_librelambda_provision_testcase extends advanced_testcase {

    /**
     * Test the does bucket exist method. Should return false.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_check_bucket_exists_false() {
        // Set up the AWS mock.
        $mock = new MockHandler();
        $mock->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new S3Exception('Mock exception', $cmd);
        });

        $keyid = 'AAAAAAAAAAAA';
        $secret = 'aaaaaaaaaaaaaaaaaa';
        $region = 'ap-southeast-2';
        $bucketprefix = '';

        $bucketname = 'foobar';

        $provisioner = new \fileconverter_librelambda\provision($keyid, $secret, $region, $bucketprefix);
        $provisioner->create_s3_client($mock);

        // Reflection magic as we are directly testing a private method.
        $method = new ReflectionMethod('\fileconverter_librelambda\provision', 'check_bucket_exists');
        $method->setAccessible(true); // Allow accessing of private method.
        $result = $method->invoke($provisioner, $bucketname);

        $this->assertFalse($result);
    }

    /**
     * Test the does bucket exist method. Should return true.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_check_bucket_exists_true() {
         // Set up the AWS mock.
         $mock = new MockHandler();
         $mock->append(new Result(array()));

         $keyid = 'AAAAAAAAAAAA';
         $secret = 'aaaaaaaaaaaaaaaaaa';
         $region = 'ap-southeast-2';
         $bucketprefix = '';

         $bucketname = 'foobar';

         $provisioner = new \fileconverter_librelambda\provision($keyid, $secret, $region, $bucketprefix);
         $provisioner->create_s3_client($mock);

         // Reflection magic as we are directly testing a private method.
         $method = new ReflectionMethod('\fileconverter_librelambda\provision', 'check_bucket_exists');
         $method->setAccessible(true); // Allow accessing of private method.
         $result = $method->invoke($provisioner, $bucketname);

         $this->assertTrue($result);
    }

    /**
     * Test the does bucket exist method. Should return true when bucket exists but we can't access.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_check_bucket_exists_forbidden() {
        // Set up the AWS mock.
        $mock = new MockHandler();
        $mock->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new S3Exception('Mock exception', $cmd, array('code' => 403));
        });

            $keyid = 'AAAAAAAAAAAA';
            $secret = 'aaaaaaaaaaaaaaaaaa';
            $region = 'ap-southeast-2';
            $bucketprefix = '';

            $bucketname = 'foobar';

            $provisioner = new \fileconverter_librelambda\provision($keyid, $secret, $region, $bucketprefix);
            $provisioner->create_s3_client($mock);

            // Reflection magic as we are directly testing a private method.
            $method = new ReflectionMethod('\fileconverter_librelambda\provision', 'check_bucket_exists');
            $method->setAccessible(true); // Allow accessing of private method.
            $result = $method->invoke($provisioner, $bucketname);

            $this->assertTrue($result);
    }

    /**
     * Test creating the S3 bucket. Should return false.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_create_s3_bucket_false() {
        // Set up the AWS mock.
        $mock = new MockHandler();
        $response = array(
                'code' => 'BucketAlreadyOwnedByYou',
                'message' => 'Your previous request to create the named bucket succeeded and you already own it.'
        );

        $mock->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new S3Exception('Mock exception', $cmd,  array(
                    'code' => 'BucketAlreadyOwnedByYou',
                    'message' => 'Your previous request to create the named bucket succeeded and you already own it.'
            ));
        });

            $keyid = 'AAAAAAAAAAAA';
            $secret = 'aaaaaaaaaaaaaaaaaa';
            $region = 'ap-southeast-2';
            $bucketprefix = '';

            $bucketname = 'foobar.bah.joo.bar';

            $provisioner = new \fileconverter_librelambda\provision($keyid, $secret, $region, $bucketprefix);
            $provisioner->create_s3_client($mock);

            // Reflection magic as we are directly testing a private method.
            $method = new ReflectionMethod('\fileconverter_librelambda\provision', 'create_s3_bucket');
            $method->setAccessible(true); // Allow accessing of private method.
            $result = $method->invoke($provisioner, $bucketname);

            $this->assertFalse($result->status);
            $this->assertEquals($response['code'], $result->code);
            $this->assertEquals($response['message'], $result->message);
    }

    /**
     * Test creating the S3 bucket. Should return true.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_create_s3_bucket_true() {
        // Set up the AWS mock.
        $mock = new MockHandler();
        $mock->append(new Result(array('Location' => 'http://foobar.bah.joo.bar.s3.amazonaws.com/')));

            $keyid = 'AAAAAAAAAAAA';
            $secret = 'aaaaaaaaaaaaaaaaaa';
            $region = 'ap-southeast-2';
            $bucketprefix = '';

            $bucketname = 'foobar.bah.joo.bar';

            $provisioner = new \fileconverter_librelambda\provision($keyid, $secret, $region, $bucketprefix);
            $provisioner->create_s3_client($mock);

            // Reflection magic as we are directly testing a private method.
            $method = new ReflectionMethod('\fileconverter_librelambda\provision', 'create_s3_bucket');
            $method->setAccessible(true); // Allow accessing of private method.
            $result = $method->invoke($provisioner, $bucketname);

            $this->assertTrue($result->status);
            $this->assertEquals(0, $result->code);
            $this->assertEquals('http://foobar.bah.joo.bar.s3.amazonaws.com/', $result->message);
    }


}
