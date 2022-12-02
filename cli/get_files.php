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
 * The command line script.
 *
 * Will get all document files from S3 in a site that
 * uses the object fs plugin and download them locally
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
        'keyid'             => false,
        'secret'            => false,
        'help'              => false,
        'region'            => false,
        'bucket'            => '',
    ),
    array(
        'h' => 'help'
    )
    );

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || !$options['keyid'] || !$options['secret'] || !$options['region']
    || !$options['bucket']) {
        $help = "This command line script will get all document files from S3 in a site that
uses the object fs plugin and download them locally.

Options:
--keyid=STRING            AWS API Access Key ID.
--secret=STRING           AWS API Secret Access Key.
--region=STRING           The AWS region the bucket is in.
                          e.g. ap-southeast-2
--bucket=STRING           The input AWS S3 bucket to use.
                          Must exist.
-h, --help                Print out this help

Example:
\$sudo -u www-data php files/converter/librelambda/cli/test.php \
--keyid=QKIAIVYPO6FXJESSW4HQ \
--secret=CzI0r0FvPf/TqPwCoiPOdhztEkvkyULbWike1WqA \
--region=ap-southeast-2 \
--input-bucket=librelambda_input \
--output-bucket=librelambda_output \
--file='\\tmp\\test.odt'
";

        echo $help;
        die;
}

// Get all document files from database as recordset.



exit(0); // 0 means success.
