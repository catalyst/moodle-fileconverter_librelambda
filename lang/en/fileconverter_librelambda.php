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

$string['pluginname'] = 'LibreLambda document converter';
$string['privacy:metadata:fileconverter_librelambda:externalpurpose'] = 'This information is sent to AWS API in order the file to be converted to an alternative format. The file is temporarily kept in an AWS S3 bucket and gets deleted after the conversion is done.';
$string['privacy:metadata:fileconverter_librelambda:filecontent'] = 'The content of the file.';
$string['privacy:metadata:fileconverter_librelambda:filemimetype'] = 'The MIME type of the file.';
$string['privacy:metadata:fileconverter_librelambda:params'] = 'The query parameters passed to AWS API.';
$string['event:poll_conversion_status'] = 'Poll conversion status';
$string['event:start_document_conversion'] = 'Start document conversion';
$string['settings:generalheader'] = 'General Settings';
$string['settings:aws:header'] = 'Amazon S3 Settings';
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
$string['settings:aws:usesdkcreds'] = 'Use the default credential provider chain to find AWS credentials';
$string['settings:aws:sdkcredsok'] = 'AWS credentials found. This setting can be safely enabled.';
$string['settings:aws:sdkcredserror'] = 'Couldn\'t find AWS credentials. It\'s unsafe to enable this setting. Follow up <a href="https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html">AWS documentation</a>.';
$string['settings:connectionsuccess'] = 'Could establish connection to the external object storage.';
$string['settings:connectionfailure'] = 'Could not establish connection to the external object storage.';
$string['settings:writefailure'] = 'Could not write object to the external object storage. ';
$string['settings:readfailure'] = 'Could not read object from the external object storage. ';
$string['settings:deletesuccess'] = 'Could delete object from the external object storage - It is not recommended for the user to have delete permissions. ';
$string['settings:deleteerror'] = 'Could not delete object from the external object storage. ';
$string['settings:permissioncheckpassed'] = 'Permissions check passed.';
$string['settings:conversion_timeout'] = 'Conversion timeout';
$string['settings:conversion_timeout_help'] = 'How long should we wait for converted file.';
$string['settings:useproxy'] = 'Use proxy';
$string['settings:useproxy_help'] = 'Librelambda can use configured Moodle proxy to reach AWS API.';
$string['settings:confignotset'] = 'Configuration not set properly';
$string['preparesubmissionsforannotation'] = 'Check LibreLambda conversions.';
$string['provision:inputbucket'] = 'Input bucket: {$a}';
$string['provision:outputbucket'] = 'Output bucket: {$a}';
$string['provision:setconfig'] = 'Setting plugin configuration in Moodle, from returned settings.';
$string['provision:stack'] = 'Provisioning the Lambda function and stack resources';
$string['provision:lambdaparams'] = 'Converter params';
$string['provision:s3useraccesskey'] = 'S3 user access key: {$a}';
$string['provision:s3usersecretkey'] = 'S3 user secret key: {$a}';
$string['provision:stackcreated'] = 'Cloudformation stack created. Stack ID is: {$a}';
$string['provision:stackupdated'] = 'Cloudformation stack updated. Stack ID is: {$a}';
$string['test:bucketnotexists'] = 'The {$a} bucket does not exist.';
$string['test:conversioncheck'] = 'Checking conversion, please wait...';
$string['test:conversioncomplete'] = 'File conversion sucessful. ({$a})';
$string['test:fileuploaded'] = 'Test file uploaded';
$string['test:uploadfile'] = 'Uploading test file';
