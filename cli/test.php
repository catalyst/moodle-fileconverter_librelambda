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
 * This command line script will test a provisioned Librelambda environment in AWS.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);

require(__DIR__.'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'keyid'              => false,
        'secret'             => false,
        'help'               => false,
        'region'             => false,
        'input-bucket'       => '',
        'output-bucket'      => '',
        'file'               => '',
        'use-sdk-creds'      => 0
    ),
    array(
        'h' => 'help'
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}
$help = "Command line Librelmbda provision.
This command line script will provision the Librelambda environment in AWS.
It will setup the input and output buckets as well as the Lambda function in S3.
If you set use-sdk-creds=1, It will use credential set in AWS Credentials File.
(https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials_profiles.html)

Options:
--keyid=STRING            AWS API Access Key ID.
                          The API user for this key, will need permissions to:
                          Create S3 buckets, IAM roles, Lambda functions.
--secret=STRING           AWS API Secret Access Key.
--region=STRING           The AWS region to create the environment in.
                          e.g. ap-southeast-2
--input-bucket=STRING     The input AWS S3 bucket to use.
                          Must exist.
--output-bucket=STRING    The output AWS S3 bucket to use.
                          Must exist.
--file=STRING             The file (with path) to convert.
--use-sdk-creds           1:Use sdk credentials,
                          0:Use provided credentials.

-h, --help                Print out this help
";

$sdkexample = "
Example:
\$sudo -u www-data php files/converter/librelambda/cli/test.php \
--region=ap-southeast-2 \
--input-bucket=librelambda_input \
--output-bucket=librelambda_output \
--file='\\tmp\\test.odt' \
--use-sdk-creds=1
";
$example = "
Example:
\$sudo -u www-data php files/converter/librelambda/cli/test.php \
--keyid=QKIAIVYPO6FXJESSW4HQ \
--secret=CzI0r0FvPf/TqPwCoiPOdhztEkvkyULbWike1WqA \
--region=ap-southeast-2 \
--input-bucket=librelambda_input \
--output-bucket=librelambda_output \
--file='\\tmp\\test.odt' \
--use-sdk-creds=0
";

if ($options['help']) {
    echo $help.$sdkexample.$example;
    die;
} else if ($options['use-sdk-creds']) {
    if (!$options['region'] || !$options['input-bucket'] || !$options['output-bucket']) {
        echo $help.$sdkexample;
        die;
    }
} else if (!$options['keyid'] || !$options['secret'] || !$options['region']
|| !$options['input-bucket'] || !$options['output-bucket']) {
    echo $help.$example;
    die;
}

$tester = new \fileconverter_librelambda\tester(
    $options['keyid'],
    $options['secret'],
    $options['region'],
    $options['input-bucket'],
    $options['output-bucket'],
    $options['use-sdk-creds']);

// Upload file to input S3 bucket.
cli_heading(get_string('test:uploadfile', 'fileconverter_librelambda'));

$uploadresposnse = $tester->upload_file($options['file']);
if ($uploadresposnse->code != 0 ) {
    $errormsg = $uploadresposnse->code . ': ' . $uploadresposnse->message;
    throw new \moodle_exception($errormsg);
    exit(1);
} else {
    echo get_string('test:fileuploaded', 'fileconverter_librelambda') . PHP_EOL . PHP_EOL;
}

echo get_string('test:conversioncheck', 'fileconverter_librelambda') . PHP_EOL . PHP_EOL;
sleep(60); // No way it's going to be ready in less than a minute.
$conversionresposnse = $tester->conversion_check($options['file']);
if ($conversionresposnse->code != 0 ) {
    $errormsg = $conversionresposnse->code . ': ' . $conversionresposnse->message;
    throw new \moodle_exception($errormsg);
    exit(1);
} else {
    echo get_string('test:conversioncomplete', 'fileconverter_librelambda', $conversionresposnse->message) . PHP_EOL . PHP_EOL;
}


exit(0); // 0 means success.
