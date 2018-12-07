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
 * Class for provisioning AWS resources.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace fileconverter_librelambda;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Iam;

/**
 * Class for provisioning AWS resources.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provision {

    /**
     * AWS API Access Key ID.
     *
     * @var string
     */
    protected $keyid;

    /**
     * AWS API Secret Access Key.
     *
     * @var string
     */
    protected $secret;

    /**
     * The AWS region to create the environment in.
     *
     * @var string
     */
    protected $region;

    /**
     * The prefix to use for the created AWS S3 buckets.
     *
     * @var string
     */
    protected $bucketprefix;

    /**
     *
     * @var \Aws\S3\S3Client S3 client.
     */
    private $s3client;


    /**
     * The constructor for the class
     *
     * @param string $keyid AWS API Access Key ID.
     * @param string $secret AWS API Secret Access Key.
     * @param string $region The AWS region to create the environment in.
     * @param string $bucketprefix The prefix to use for the created AWS S3 buckets.
     */
    public function __construct($keyid, $secret, $region, $bucketprefix) {
        global $CFG;

        $this->keyid = $keyid;
        $this->secret = $secret;
        $this->region = $region;

        if ($this->bucketprefix == '') {
            $this->bucketprefix = $CFG->siteidentifier;
        } else {
            $this->bucketprefix = $bucketprefix;
        }

    }

    private function check_bucket_exists($bucketname, $client) {

    }

    /**
     * Creates a S3 bucket in AWS.
     *
     * @param string $suffix The bucket suffix to use.
     *
     */
    public function create_bucket($suffix, $handler=null) {
        // Setup the S3 client.
        $connectionoptions = array(
                'version' => 'latest',
                'region' => $this->region,
                'credentials' => [
                        'key' => $this->keyid,
                        'secret' => $this->secret
                ]);

        // Allow handler overriding for testing.
        if ($handler!=null) {
            $connectionoptions['handler'] = $handler;
        }

        // Only create client if it hasn't already been done.
        if ($this->s3client == null) {
            $this->s3client = new S3Client($connectionoptions);
        }

    }
}
