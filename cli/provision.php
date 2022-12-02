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
 *
 * It will setup the input and output buckets as well as the Lambda function in S3.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);

require(__DIR__.'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');

$defaultstack = \fileconverter_librelambda\provision::DEFAULT_STACK_NAME;
$help = "Command line Librelmbda provision.
This command line script will provision the Librelambda environment in AWS.
It will setup the input and output buckets as well as the Lambda function in S3.

Options:
--keyid=STRING          AWS API Access Key ID.
                        The API user for this key, will need permissions to:
                        Create S3 buckets, IAM roles, Lambda functions.
--secret=STRING         AWS API Secret Access Key.
--region=STRING         The AWS region to create the environment in.
                        e.g. ap-southeast-2
--stack-name=STRING     AWS stack name where lambda operates.
                        If this isn't provided the default $defaultstack is used.
                        Prefix must be unique and must not contain spaces.
--replace-stack         If existing stack is to be replaced.
                        If stack with the name exists, and this option is not specified,
                        an error will be thrown and lambda will not be deployed.
--set-config            Will update the plugin configuration with the resources
                        created by this script.
--remove-stack          If specified, existing stack is to be removed and NOT replaced

-h, --help              Print out this help

Example:
\$sudo -u www-data php files/converter/librelambda/cli/provision.php \
--keyid=QKIAIVYPO6FXJESSW4HQ \
--secret=CzI0r0FvPf/TqPwCoiPOdhztEkvkyULbWike1WqA \
--region=ap-southeast-2 \
--set-config
";
$stackexistsmsg = "Stack exsists and replacement not requested.
If you want to replace the stack use \"--replace-stack\" option
";
$stacknotexistsmsg = "Stack does not exsist.";

/** Abort function
 * @param string $msg
 */
function abort(string $msg) {
    echo "$msg\n";
    die;
}

echo PHP_EOL;

// Get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'keyid'           => null,
        'secret'          => null,
        'region'          => null,
        'stack-name'      => null,
        'replace-stack'   => false,
        'remove-stack'    => false,
        'set-config'      => false,
        'help'            => false,
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
    abort($help);
}

$provisioner = new \fileconverter_librelambda\provision(
    $options['keyid'],
    $options['secret'],
    $options['region'],
    $options['stack-name']
);

$stackexists = $provisioner->stack_status();

if ($options['remove-stack']) {
    if ($options['set-config']) {
        abort("Cannot set config when removing stack.\n\n$help");
    }

    if (!$stackexists) {
        abort($stacknotexistsmsg);
    }

    $stack = $provisioner->stack_name();
    echo "Do you really want to remove \"$stack\" stack? [Type \"yes\" to confirm]: ";
    $confirmation = trim( fgets(STDIN) );
    if (strtolower($confirmation) !== 'yes') {
         // The user did not say 'yes'.
         exit (1);
    }

    $removalresponse = $provisioner->remove_stack();
    if (!$removalresponse->status) {
        abort($removalresponse->message);
    } else {
        echo "Removed" . PHP_EOL . PHP_EOL;
    }
    exit (0);
}

if (!$options['replace-stack'] && $stackexists) {
    abort("$stackexistsmsg\n$help");
}

// First we make the Libre archive a zip file so it can be a Lambda layer.
$librepath = $CFG->dirroot . '/files/converter/librelambda/libre/lo.tar.xz';
$tmpfname = sys_get_temp_dir() . '/lo.zip';
$zip = new ZipArchive();
$zip->open($tmpfname, ZipArchive::CREATE);
$zip->addFile($librepath, 'lo.tar.xz');
$zip->close();

$cloudformationpath = $CFG->dirroot . '/files/converter/librelambda/lambda/stack.template';
$lambdapath = $CFG->dirroot . '/files/converter/librelambda/lambda/lambdaconvert.zip';

// Create Lambda function, IAM roles and the rest of the stack.
cli_heading(get_string('provision:stack', 'fileconverter_librelambda'));

$createstackresponse = $provisioner->provision_stack(
    $cloudformationpath,
    [$tmpfname, $lambdapath],
    $options['replace-stack']
);
unlink($tmpfname);  // Remove temp file.

if (!$createstackresponse->status) {
    abort($createstackresponse->message);
}

echo $createstackresponse->message . PHP_EOL . PHP_EOL;

// Print summary.
cli_heading(get_string('provision:lambdaparams', 'fileconverter_librelambda'));
echo get_string('provision:s3useraccesskey', 'fileconverter_librelambda', $createstackresponse->S3UserAccessKey) . PHP_EOL;
echo get_string('provision:s3usersecretkey', 'fileconverter_librelambda', $createstackresponse->S3UserSecretKey) . PHP_EOL;
echo get_string('provision:inputbucket', 'fileconverter_librelambda', $createstackresponse->InputBucket) . PHP_EOL;
echo get_string('provision:outputbucket', 'fileconverter_librelambda', $createstackresponse->OutputBucket) . PHP_EOL;

// Set config.
if ($options['set-config']) {
    cli_heading(get_string('provision:setconfig', 'fileconverter_librelambda'));
    set_config('api_key', $createstackresponse->S3UserAccessKey, 'fileconverter_librelambda');
    set_config('api_secret', $createstackresponse->S3UserSecretKey, 'fileconverter_librelambda');
    set_config('s3_input_bucket', $createstackresponse->InputBucket, 'fileconverter_librelambda');
    set_config('s3_output_bucket', $createstackresponse->OutputBucket, 'fileconverter_librelambda');
    set_config('api_region', $options['region'], 'fileconverter_librelambda');
    purge_all_caches();  // Purge caches to ensure UI updates with new settings.
}

exit(0); // 0 means success.
