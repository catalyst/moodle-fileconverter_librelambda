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
 * Plugin strings are defined here.
 *
 * @package     fileconverter_librelambda
 * @category    string
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Libre Lambda Document Converter';
$string['privacy:metadata:fileconverter_librelambda:externalpurpose'] = 'This information is sent to AWS API in order the file to be converted to an alternative format. The file is temporarily kept in an AWS S3 bucket and gets deleted after the conversion is done.';
$string['privacy:metadata:fileconverter_librelambda:filecontent'] = 'The content of the file.';
$string['privacy:metadata:fileconverter_librelambda:filemimetype'] = 'The MIME type of the file.';
$string['privacy:metadata:fileconverter_librelambda:params'] = 'The query parameters passed to AWS API.';
$string['settings:aws:header'] = 'AWS Settings';
$string['settings:aws:key'] = 'Key';
$string['settings:aws:key_help'] = 'Amazon API key credential.';
$string['settings:aws:secret'] = 'Secret';
$string['settings:aws:secret_help'] = 'Amazon API secret credential.';
$string['settings:aws:input_bucket'] = 'Input bucket';
$string['settings:aws:input_bucket_help'] = 'Amazon S3 bucket to upload assignment submissions.';
$string['settings:aws:output_bucket'] = 'Output bucket';
$string['settings:aws:output_bucket_help'] = 'Amazon S3 bucket to fetch converted assignment submissions.';
$string['settings:aws:region'] = 'Region';
$string['settings:aws:region_help'] = 'Amazon API gateway region.';
$string['settings:connectionsuccess'] = 'Could establish connection to the external object storage.';
$string['settings:connectionfailure'] = 'Could not establish connection to the external object storage.';
$string['settings:writefailure'] = 'Could not write object to the external object storage. ';
$string['settings:readfailure'] = 'Could not read object from the external object storage. ';
$string['settings:deletesuccess'] = 'Could delete object from the external object storage - It is not recommended for the user to have delete permissions. ';
$string['settings:deleteerror'] = 'Could not delete object from the external object storage. ';
$string['settings:permissioncheckpassed'] = 'Permissions check passed.';
