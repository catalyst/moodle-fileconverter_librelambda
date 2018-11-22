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

use core_files\conversion;
use core_files\converter;

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

        // Reflection magic as we are directly testing a private method.
        $method = new ReflectionMethod('\fileconverter_librelambda\converter', 'is_config_set');
        $method->setAccessible(true); // Allow accessing of private method.
        $result = $method->invoke(new \fileconverter_librelambda\converter);

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

        // Reflection magic as we are directly testing a private method.
        $method = new ReflectionMethod('\fileconverter_librelambda\converter', 'is_config_set');
        $method->setAccessible(true); // Allow accessing of private method.
        $result = $method->invoke(new \fileconverter_librelambda\converter);

        $this->assertTrue($result);
    }


    /**
     * Test are requirements met method of converter class.
     */
    public function test_are_requirements_met_false() {
        $converter = new \fileconverter_librelambda\converter();

        $result = $converter::are_requirements_met();

        $this->assertFalse($result);
    }

}
