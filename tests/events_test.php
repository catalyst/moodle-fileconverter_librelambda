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
class fileconverter_librelambda_events_testcase extends advanced_testcase {

    /**
     * Test start document conversion method.
     */
    public function test_start_document_conversion_event() {
        global $CFG;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $instance = $generator->create_instance(array('course' => $course->id));
        $context = context_module::instance($instance->cmid);

        // Create file to analyze.
        $fs = get_file_storage();
        $filerecord = array(
            'contextid' => $instance->cmid,
            'component' => 'assignsubmission_file',
            'filearea' => 'submission_files',
            'itemid' => 8,
            'filepath' => '/',
            'filename' => 'testsubmission.odt');
        $fileurl = $CFG->dirroot . '/files/converter/librelambda/tests/fixtures/testsubmission.odt';
        $file = $fs->create_file_from_pathname($filerecord, $fileurl);

        $conversion = new conversion(0, (object) [
            'sourcefileid' => $file->get_id(),
            'targetformat' => 'pdf',
        ]);
        $conversion->create();

        // Standard Event parameters.
        $eventinfo = array(
            'context' => $context,
            'courseid' => $course->id,
            'other' => array(
                'sourcefileid' => $conversion->get('sourcefileid'),
                'bucket' => 'input bucket',
                'key' => $file->get_pathnamehash(),
                'targetformat' => $conversion->get('targetformat'),
                'id' => $conversion->get('id'),
                'sourcefileid' => $conversion->get('sourcefileid'),
                'status' => conversion::STATUS_IN_PROGRESS
            ));

        $sink = $this->redirectEvents();
        $event = \fileconverter_librelambda\event\start_document_conversion::create($eventinfo);
        $event->trigger();
        $result = $sink->get_events();
        $event = reset($result);
        $sink->close();

        $this->assertEquals(conversion::STATUS_IN_PROGRESS, $event->other['status']);
        $this->assertEquals('conversion', $event->action);
    }


    /**
     * Test poll document conversion method. For already complete status.
     */
    public function test_poll_conversion_status_event() {
        global $CFG;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $instance = $generator->create_instance(array('course' => $course->id));
        $context = context_module::instance($instance->cmid);

        // Create file to analyze.
        $fs = get_file_storage();
        $filerecord = array(
            'contextid' => $instance->cmid,
            'component' => 'assignsubmission_file',
            'filearea' => 'submission_files',
            'itemid' => 8,
            'filepath' => '/',
            'filename' => 'testsubmission.odt');
        $fileurl = $CFG->dirroot . '/files/converter/librelambda/tests/fixtures/testsubmission.odt';
        $file = $fs->create_file_from_pathname($filerecord, $fileurl);

        $conversion = new conversion(0, (object) [
            'sourcefileid' => $file->get_id(),
            'targetformat' => 'pdf',
        ]);
        $conversion->create();

        // Standard Event parameters.
        $eventinfo = array(
            'context' => $context,
            'courseid' => $course->id,
            'other' => array(
                'sourcefileid' => $conversion->get('sourcefileid'),
                'bucket' => 'output bucket',
                'key' => $file->get_pathnamehash(),
                'targetformat' => $conversion->get('targetformat'),
                'id' => $conversion->get('id'),
                'sourcefileid' => $conversion->get('sourcefileid'),
                'status' => conversion::STATUS_COMPLETE
            ));

        $sink = $this->redirectEvents();
        $event = \fileconverter_librelambda\event\poll_conversion_status::create($eventinfo);
        $event->trigger();
        $result = $sink->get_events();
        $event = reset($result);
        $sink->close();

        $this->assertEquals(conversion::STATUS_COMPLETE, $event->other['status']);
        $this->assertEquals('status', $event->action);
    }

}
