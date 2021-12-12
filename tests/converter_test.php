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
 * PHPUnit tests for Libre Lambda file converter.
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
use \core_files\conversion;

/**
 * PHPUnit tests for Libre Lambda file converter.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fileconverter_librelambda_converter_testcase extends advanced_testcase {

    /**
     * Test is_config_set method with missing configuration.
     */
    public function test_is_config_set_false() {
        $converter = new \fileconverter_librelambda\converter();

        // Reflection magic as we are directly testing a private method.
        $method = new ReflectionMethod('\fileconverter_librelambda\converter', 'is_config_set');
        $method->setAccessible(true); // Allow accessing of private method.
        $result = $method->invoke(new \fileconverter_librelambda\converter, $converter);

        $this->assertFalse($result);
    }

    /**
     * Test is_config_set method with missing configuration.
     */
    public function test_is_config_set_true() {
        $this->resetAfterTest();

        set_config('api_key', 'key', 'fileconverter_librelambda');
        set_config('api_secret', 'secret', 'fileconverter_librelambda');
        set_config('s3_input_bucket', 'bucket1', 'fileconverter_librelambda');
        set_config('s3_output_bucket', 'bucket2', 'fileconverter_librelambda');
        set_config('api_region', 'ap-southeast-2', 'fileconverter_librelambda');

        $converter = new \fileconverter_librelambda\converter();

        // Reflection magic as we are directly testing a private method.
        $method = new ReflectionMethod('\fileconverter_librelambda\converter', 'is_config_set');
        $method->setAccessible(true); // Allow accessing of private method.
        $result = $method->invoke(new \fileconverter_librelambda\converter, $converter);

        $this->assertTrue($result);
    }

    /**
     * Test the is bucket accessible method. Should return false.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_is_bucket_accessible_false() {
        // Set up the AWS mock.
        $mock = new MockHandler();
        $mock->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new S3Exception('Mock exception', $cmd);
        });

        $converter = new \fileconverter_librelambda\converter();
        $converter->create_client($mock);

        // Reflection magic as we are directly testing a private method.
        $method = new ReflectionMethod('\fileconverter_librelambda\converter', 'is_bucket_accessible');
        $method->setAccessible(true); // Allow accessing of private method.
        $result = $method->invoke($converter, 'input');

        $this->assertFalse($result->success);
    }

    /**
     * Test the is bucket accessible method. Should return false.
     * We mock out the S3 client response as we are not trying to connect to the live AWS API.
     */
    public function test_is_bucket_accessible_true() {
         // Set up the AWS mock.
         $mock = new MockHandler();
         $mock->append(new Result(array()));

         $converter = new \fileconverter_librelambda\converter();
         $converter->create_client($mock);

         // Reflection magic as we are directly testing a private method.
         $method = new ReflectionMethod('\fileconverter_librelambda\converter', 'is_bucket_accessible');
         $method->setAccessible(true); // Allow accessing of private method.
         $result = $method->invoke($converter, 'input');

         $this->assertTrue($result->success);
    }

    /**
     * Test bucket permissions method of converter class.
     */
    public function test_have_bucket_permissions_false() {
        // Set up the AWS mock.
        $mock = new MockHandler();
        $mock->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new S3Exception('Mock exception', $cmd);
        });
        $mock->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new S3Exception('Mock exception', $cmd);
        });
        $mock->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new S3Exception('Mock exception', $cmd);
        });

        $converter = new \fileconverter_librelambda\converter();
        $converter->create_client($mock);

        // Reflection magic as we are directly testing a private method.
        $method = new ReflectionMethod('\fileconverter_librelambda\converter', 'have_bucket_permissions');
        $method->setAccessible(true); // Allow accessing of private method.
        $result = $method->invoke($converter, 'bucket1');

        $this->assertFalse($result->success);
    }

    /**
     * Test bucket permissions method of converter class.
     */
    public function test_have_bucket_permissions_true() {
        // Set up the AWS mock.
        $mock = new MockHandler();
        $mock->append(new Result(array()));
        $mock->append(new Result(array()));
        $mock->append(new Result(array()));

        $converter = new \fileconverter_librelambda\converter();
        $converter->create_client($mock);

        // Reflection magic as we are directly testing a private method.
        $method = new ReflectionMethod('\fileconverter_librelambda\converter', 'have_bucket_permissions');
        $method->setAccessible(true); // Allow accessing of private method.
        $result = $method->invoke($converter, 'bucket1');

        $this->assertTrue($result->success);
    }

    /**
     * Test are requirements met method of converter class.
     */
    public function test_are_requirements_met_false() {
        $converter = new \fileconverter_librelambda\converter();

        $result = $converter::are_requirements_met();
        $debugging = $this->getDebuggingMessages();
        $this->resetDebugging();

        $this->assertCount(1, $debugging);
        $this->assertFalse($result);
    }

    /**
     * Test start document conversion method.
     */
    public function test_start_document_conversion() {
        global $CFG;
        $this->resetAfterTest();
        set_config('s3_input_bucket', 'bucket1', 'fileconverter_librelambda');

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $instance = $generator->create_instance(array('course' => $course->id));
        $context = context_module::instance($instance->cmid);

        // Create file to analyze.
        $fs = get_file_storage();
        $filerecord = array(
            'contextid' => $context->id,
            'component' => 'assignsubmission_file',
            'filearea' => 'submission_files',
            'itemid' => $instance->cmid,
            'filepath' => '/',
            'filename' => 'testsubmission.odt');
        $fileurl = $CFG->dirroot . '/files/converter/librelambda/tests/fixtures/testsubmission.odt';
        $file = $fs->create_file_from_pathname($filerecord, $fileurl);

        $conversion = new conversion(0, (object) [
            'sourcefileid' => $file->get_id(),
            'targetformat' => 'pdf',
        ]);
        $conversion->create();

        // Set up the AWS mock.
        $mock = new MockHandler();
        $mock->append(new Result(array('ObjectURL' => 's3://herpderp')));

        $converter = new \fileconverter_librelambda\converter();
        $converter->create_client($mock);

        $convert = $converter->start_document_conversion($conversion);

        $this->assertEquals(conversion::STATUS_IN_PROGRESS, $convert->status);
    }

    /**
     * Test poll document conversion method. For already complete status.
     */
    public function test_poll_document_conversion_already_complete() {
        global $CFG;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $instance = $generator->create_instance(array('course' => $course->id));
        $context = context_module::instance($instance->cmid);

        // Create file to analyze.
        $fs = get_file_storage();
        $filerecord = array(
            'contextid' => $context->id,
            'component' => 'assignsubmission_file',
            'filearea' => 'submission_files',
            'itemid' => $instance->cmid,
            'filepath' => '/',
            'filename' => 'testsubmission.odt');
        $fileurl = $CFG->dirroot . '/files/converter/librelambda/tests/fixtures/testsubmission.odt';
        $file = $fs->create_file_from_pathname($filerecord, $fileurl);

        $conversion = new conversion(0, (object) [
                'sourcefileid' => $file->get_id(),
                'targetformat' => 'pdf',
        ]);
        $conversion->create();

        // Set up the AWS mock.
        $mock = new MockHandler();
        $mock->append(new Result(array('ObjectURL' => 's3://herpderp')));

        $converter = new \fileconverter_librelambda\converter();
        $converter->create_client($mock);

        $converter->start_document_conversion($conversion);
        $conversion->set('status', conversion::STATUS_COMPLETE);
        $conversion->update();

        $convert = $converter->poll_conversion_status($conversion);

        $this->assertEquals(conversion::STATUS_COMPLETE, $conversion->get('status'));
    }


    /**
     * Test poll document conversion method. For already complete status.
     */
    public function test_poll_document_conversion_already_progress() {
        global $CFG;
        $this->resetAfterTest();
        set_config('s3_input_bucket', 'bucket1', 'fileconverter_librelambda');
        set_config('s3_output_bucket', 'bucket2', 'fileconverter_librelambda');

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $instance = $generator->create_instance(array('course' => $course->id));
        $context = context_module::instance($instance->cmid);

        // Create file to analyze.
        $fs = get_file_storage();
        $filerecord = array(
            'contextid' => $context->id,
            'component' => 'assignsubmission_file',
            'filearea' => 'submission_files',
            'itemid' => $instance->cmid,
            'filepath' => '/',
            'filename' => 'testsubmission.odt');
        $fileurl = $CFG->dirroot . '/files/converter/librelambda/tests/fixtures/testsubmission.odt';
        $file = $fs->create_file_from_pathname($filerecord, $fileurl);

        $conversion = new conversion(0, (object) [
            'sourcefileid' => $file->get_id(),
            'targetformat' => 'pdf',
        ]);
        $conversion->create();

        // Set up the AWS mock.
        $mock = new MockHandler();
        $mock->append(new Result(array('ObjectURL' => 's3://herpderp')));
        $mock->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new S3Exception('Mock exception', $cmd, array('code' => 'NoSuchKey'));
        });

        $converter = new \fileconverter_librelambda\converter();
        $converter->create_client($mock);

        $converter->start_document_conversion($conversion);

        $convert = $converter->poll_conversion_status($conversion);

        $this->assertEquals(conversion::STATUS_IN_PROGRESS, $conversion->get('status'));
    }

    /**
     * Test convesion schedlued tasks for inprogress conversions.
     *
     */
    public function test_execute_conversion_task_progress() {
        global $CFG;
        $this->resetAfterTest();
        set_config('s3_input_bucket', 'bucket1', 'fileconverter_librelambda');
        set_config('s3_output_bucket', 'bucket2', 'fileconverter_librelambda');

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $instance = $generator->create_instance(array('course' => $course->id));
        $context = context_module::instance($instance->cmid);

        // Create file to analyze.
        $fs = get_file_storage();
        $filerecord = array(
            'contextid' => $context->id,
            'component' => 'assignsubmission_file',
            'filearea' => 'submission_files',
            'itemid' => $instance->cmid,
            'filepath' => '/',
            'filename' => 'testsubmission.odt');
        $fileurl = $CFG->dirroot . '/files/converter/librelambda/tests/fixtures/testsubmission.odt';
        $file = $fs->create_file_from_pathname($filerecord, $fileurl);

        $conversion = new conversion(0, (object) [
            'sourcefileid' => $file->get_id(),
            'targetformat' => 'pdf',
            'converter' => '\fileconverter_librelambda\converter',
        ]);
        $conversion->create();

        // Set up the AWS mock.
        $mock = new MockHandler();
        $mock->append(new Result(array('ObjectURL' => 's3://herpderp')));
        $mock->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new S3Exception('Mock exception', $cmd, array('code' => 'NoSuchKey'));
        });

        $converter = new \fileconverter_librelambda\converter();
        $converter->create_client($mock);
        $converter->start_document_conversion($conversion);

        $this->expectOutputRegex("/Processing/"); // We expect trace output for this test.

        $convertertask = new \fileconverter_librelambda\task\convert_submissions();
        $convertertask->execute();

        $convert = $converter->poll_conversion_status($conversion);

        $this->assertEquals(conversion::STATUS_IN_PROGRESS, $conversion->get('status'));

    }

    /**
     * Test convesion schedlued tasks for failed conversions.
     *
     */
    public function test_execute_conversion_task_failed() {
        global $CFG;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $instance = $generator->create_instance(array('course' => $course->id));
        $context = context_module::instance($instance->cmid);

        // Create file to analyze.
        $fs = get_file_storage();
        $filerecord = array(
            'contextid' => $context->id,
            'component' => 'assignsubmission_file',
            'filearea' => 'submission_files',
            'itemid' => $instance->cmid,
            'filepath' => '/',
            'filename' => 'testsubmission.odt');
        $fileurl = $CFG->dirroot . '/files/converter/librelambda/tests/fixtures/testsubmission.odt';
        $file = $fs->create_file_from_pathname($filerecord, $fileurl);

        $conversion = new conversion(0, (object) [
            'sourcefileid' => $file->get_id(),
            'targetformat' => 'pdf',
            'converter' => '\fileconverter_librelambda\converter',
        ]);
        $conversion->create();

        // Set up the AWS mock.
        $mock = new MockHandler();
        $mock->append(new Result(array('ObjectURL' => 's3://herpderp')));
        $mock->append(function (CommandInterface $cmd, RequestInterface $req) {
            return new S3Exception('Mock exception', $cmd, array('code' => 'FAIL'));
        });

        $converter = new \fileconverter_librelambda\converter();
        $converter->create_client($mock);
        $converter->start_document_conversion($conversion);

        $this->expectOutputRegex("/Processing/"); // We expect trace output for this test.

        $convertertask = new \fileconverter_librelambda\task\convert_submissions();
        $convertertask->execute();

        $convert = $converter->poll_conversion_status($conversion);

        $this->assertEquals(conversion::STATUS_FAILED, $conversion->get('status'));

    }

    /**
     * Test the client still creates properly when using proxy settings.
     */
    public function test_client_uses_proxy() {
        global $CFG;
        $this->resetAfterTest();

        $converter1 = new \fileconverter_librelambda\converter();
        $client1 = $converter1->create_client();

        // Test the client creates correctly.
        $this->assertTrue($client1 instanceof \Aws\S3\S3Client);

        // Now set some configs.
        $CFG->proxyhost = '127.0.0.1';
        $CFG->proxyuser = 'user';
        $CFG->proxypassword = 'password';
        $CFG->proxyport = '1337';

        $converter2 = new \fileconverter_librelambda\converter();
        $client2 = $converter2->create_client();

        // Test the client creates correctly with proxy settings.
        $this->assertTrue($client2 instanceof \Aws\S3\S3Client);

        // Now change to SOCKS proxy.
        $CFG->proxytype = 'SOCKS5';
        $converter3 = new \fileconverter_librelambda\converter();
        $client3 = $converter3->create_client();

        // Test the client creates correctly with proxy settings.
        $this->assertTrue($client3 instanceof \Aws\S3\S3Client);
    }
}
