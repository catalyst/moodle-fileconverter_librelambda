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
 * This command line script will provision the Librelambda environment in AWS.
 * It will setup the input and output buckets as well as the Lambda function in S3.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);

require(__DIR__.'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');         // cli only functions

// now get cli options
list($options, $unrecognized) = cli_get_params(
    array(
        'keyid'             => false,
        'secret'            => false,
        'help'              => false,
        'region'            => false,
        'bucket-prefix'     => '',
        'set-config'        => false,
    ),
    array(
        'h' => 'help'
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || !$options['keyid'] || !$options['secret'] || !$options['region']) {
    $help =
"Command line Librelmbda provision.
This command line script will provision the Librelambda environment in AWS.
It will setup the input and output buckets as well as the Lambda function in S3.

Options:
--keyid=STRING            AWS API Access Key ID.
                          The API user for this key, will need permissions to:
                          Create S3 buckets, IAM roles, Lambda functions.
--secret=STRING           AWS API Secret Access Key.
--region=STRING           The AWS region to create the environment in.
                          e.g. ap-southeast-2
--bucket-prefix=STRING    The prefix to use for the created AWS S3 buckets.
                          Bucket names need to be globally unique.
                          If this isn't provided the a random prefix will be generated.
--set-config              Will update the plugin configuration with the resources
                          created by this script.

-h, --help                Print out this help

Example:
\$sudo -u www-data php files/converter/librelambda/cli/provision.php \
--keyid=QKIAIVYPO6FXJESSW4HQ \
--secret=CzI0r0FvPf/TqPwCoiPOdhztEkvkyULbWike1WqA \
--region=ap-southeast-2
";

    echo $help;
    die;
}

$provisioner = new \fileconverter_librelambda\provision($options['keyid'], $options['secret'], $options['region'], $options['bucket-prefix']);

// Create S3 buckets.
cli_heading(get_string('provision:creatings3', 'fileconverter_librelambda'));

$resourcebucketresposnse = $provisioner->create_bucket('resource');
if ($resourcebucketresposnse->code != 0 ) {
    $errormsg = $resourcebucketresposnse->code . ': ' . $resourcebucketresposnse->message;
    throw new \moodle_exception($errormsg);
    exit(1);
} else {
    echo get_string('provision:bucketcreated', 'fileconverter_librelambda', array(
            'bucket' =>'input',
            'location' => $resourcebucketresposnse->message)) . PHP_EOL;
}

// Upload Libre Office archive to input bucket.
cli_heading(get_string('provision:uploadlibrearchive', 'fileconverter_librelambda'));

$librepath = $CFG->dirroot . '/files/converter/librelambda/libre/lo.tar.xz';
$libreuploadresponse = $provisioner->upload_file($librepath, $resourcebucketresposnse->bucketname);
if ($libreuploadresponse->code != 0 ) {
    $errormsg = $libreuploadresponse->code . ': ' . $libreuploadresponse->message;
    throw new \moodle_exception($errormsg);
    exit(1);
} else {
    echo get_string('provision:librearchiveuploaded', 'fileconverter_librelambda', $libreuploadresponse->message) . PHP_EOL;
}

// Upload Lambda funtion to input bucket.
cli_heading(get_string('provision:uploadlambdaarchive', 'fileconverter_librelambda'));
$lambdapath = $CFG->dirroot . '/files/converter/librelambda/lambda/lambdaconvert.zip';
$lambdauploadresponse = $provisioner->upload_file($lambdapath, $resourcebucketresposnse->bucketname);
if ($lambdauploadresponse->code != 0 ) {
    $errormsg = $lambdauploadresponse->code . ': ' . $lambdauploadresponse->message;
    throw new \moodle_exception($errormsg);
    exit(1);
} else {
    echo get_string('provision:lambdaarchiveuploaded', 'fileconverter_librelambda', $lambdauploadresponse->message) . PHP_EOL;
}

// Create Lambda function, IAM roles and the rest of the stack.
cli_heading(get_string('provision:stack', 'fileconverter_librelambda'));
$cloudformationpath = $CFG->dirroot . '/files/converter/librelambda/lambda/lambdaconvert.zip';

$params = array(
    'lambdarchive' => $lambdapath,
    'inputbucket' => $resourcebucketresposnse->bucketname,
    'outputbucket' =>$outputbucketresposnse->bucketname,
    'librearchive' => $libreuploadresponse->message,
    'iamrole' => $iamresposnse->message
);


// Print summary.


//  Test things.


exit(0); // 0 means success
