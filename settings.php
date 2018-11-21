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
 * Plugin administration pages are defined here.
 *
 * @package     fileconverter_librelambda
 * @category    admin
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $options = [];

    $settings->add(new admin_setting_configselect('fileconverter_googledrive/issuerid',
        get_string('issuer', 'fileconverter_googledrive'),
        get_string('issuer_help', 'fileconverter_googledrive'),
        '',
        $options));

    // Basic settings.
    $mform->addElement('header', 'settingheader', get_string('settings:aws:header', 'fileconverter_librelambda'));

    $regionoptions = array(
        'us-east-1'      => 'us-east-1 (N. Virginia)',
        'us-east-2'      => 'us-east-2 (Ohio)',
        'us-west-1'      => 'us-west-1 (N. California)',
        'us-west-2'      => 'us-west-2 (Oregon)',
        'ap-northeast-1' => 'ap-northeast-1 (Tokyo)',
        'ap-northeast-2' => 'ap-northeast-2 (Seoul)',
        'ap-northeast-3' => 'ap-northeast-3 (Osaka)',
        'ap-south-1'     => 'ap-south-1 (Mumbai)',
        'ap-southeast-1' => 'ap-southeast-1 (Singapore)',
        'ap-southeast-2' => 'ap-southeast-2 (Sydney)',
        'ca-central-1'   => 'ca-central-1 (Canda Central)',
        'cn-north-1'     => 'cn-north-1 (Beijing)',
        'cn-northwest-1' => 'cn-northwest-1 (Ningxia)',
        'eu-central-1'   => 'eu-central-1 (Frankfurt)',
        'eu-west-1'      => 'eu-west-1 (Ireland)',
        'eu-west-2'      => 'eu-west-2 (London)',
        'eu-west-3'      => 'eu-west-3 (Paris)',
        'sa-east-1'      => 'sa-east-1 (Sao Paulo)'
    );

    $mform->addElement('text', 'api_key', get_string('settings:aws:key', 'fileconverter_librelambda'));
    $mform->addHelpButton('api_key', 'settings:aws:key', 'fileconverter_librelambda');
    $mform->setType("api_key", PARAM_TEXT);

    $mform->addElement('passwordunmask', 'api_secret', get_string('settings:aws:secret', 'fileconverter_librelambda'), array('size' => 40));
    $mform->addHelpButton('api_secret', 'settings:aws:secret', 'fileconverter_librelambda');
    $mform->setType("api_secret", PARAM_TEXT);

    $mform->addElement('text', 's3_input_bucket', get_string('settings:aws:input_bucket', 'fileconverter_librelambda'));
    $mform->addHelpButton('s3_input_bucket', 'settings:aws:bucket', 'fileconverter_librelambda');
    $mform->setType("s3_input_bucket", PARAM_TEXT);

    $mform->addElement('text', 's3_output_bucket', get_string('settings:aws:output_bucket', 'fileconverter_librelambda'));
    $mform->addHelpButton('s3_output_bucket', 'settings:aws:bucket', 'fileconverter_librelambda');
    $mform->setType("s3_output_bucket", PARAM_TEXT);

    $mform->addElement('select', 'api_region', get_string('settings:aws:region', 'fileconverter_librelambda'), $regionoptions);
    $mform->addHelpButton('api_region', 'settings:aws:region', 'fileconverter_librelambda');

}
