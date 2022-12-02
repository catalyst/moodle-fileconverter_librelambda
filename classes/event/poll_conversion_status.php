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
 * The LibreLambda poll conversion status event.
 *
 * @package     fileconverter_librelambda
 * @copyright   2018 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace fileconverter_librelambda\event;

/**
 * The LibreLambda poll conversion status event.
 *
 * @package     fileconverter_librelambda
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class poll_conversion_status extends \core\event\base {

    /**
     * Init function.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;

    }

    /**
     * Get event name.
     */
    public static function get_name() {
        return get_string('event:poll_conversion_status', 'fileconverter_librelambda');
    }

    /**
     * Get event description.
     */
    public function get_description() {
        return "The conversion with id '{$this->other['id']}' has been polled and returned the status '{$this->other['status']}'.";
    }
}
