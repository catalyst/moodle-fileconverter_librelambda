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

namespace fileconverter_librelambda;

use Aws\MockHandler;

/**
 * Mock class to test for Libre Lambda AWS provision.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provision_mock extends provision {
    /**
     *
     * @var MockHandler
     */
    public $mocks3handler;

    /**
     *
     * @var MockHandler
     */
    public $mockcloudformationhandler;

    /**
     *
     * @var int
     */
    protected static $sleepbeforecheck = 0;

    /**
     *
     * @var string
     */
    public $resourcebucket;

    /**
     * The constructor for the class
     *
     * @param string $stack The stack name
     */
    public function __construct($stack = null) {
        $keyid = 'AAAAAAAAAAAA';
        $secret = 'aaaaaaaaaaaaaaaaaa';
        $region = 'ap-southeast-2';

        parent::__construct($keyid, $secret, $region, $stack);

        // Set up the AWS mocks.
        $this->mocks3handler = new MockHandler();
        $this->s3client = $this->create_s3_client($this->mocks3handler);
        $this->mockcloudformationhandler = new MockHandler();
        $this->cloudformationclient = $this->create_cloudformation_client($this->mockcloudformationhandler);
    }

    /**
     * Check if the bucket already exists in AWS.
     * Upgrade to public.
     *
     * @param string $bucketname The name of the bucket to check.
     * @return bool $bucketexists The result of the check.
     */
    public function check_bucket_exists($bucketname) {
        return parent::check_bucket_exists($bucketname);
    }

    /**
     * Create an S3 Bucket in AWS.
     * Upgrade to public.
     *
     * @return \stdClass $result The result of the bucket creation.
     */
    public function create_resource_bucket() {
        return parent::create_resource_bucket();
    }
}
