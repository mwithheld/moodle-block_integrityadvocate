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
 * Integrity Advocate kind of event.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate\event;

defined('MOODLE_INTERNAL') || die();
/**
 * An IA remote request failed event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *      - string method: HTTP method, usually GET or POST.
 *      - string url: The URL for the request that failed.
 *      - int responsecode: The http response code.
 * }
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class ia_request_failed extends \core\event\base {
    /**
     * Initialize the event data.
     */
    protected function init() {
        $this->data['crud'] = 'r'; // Options= c(reate), r(ead), u(pdate), d(elete).
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Get the description of the event.
     *
     * @return string The event description.
     */
    public function get_description() {
        return "The user with id '$this->userid' got a failed remote IA request: {$this->other['responsecode']} {$this->other['method']} {$this->other['url']}. " .
            " This logs only once per user session.";
    }

    /**
     * Get the localized name of the event.
     *
     * @return string The localized event name.
     */
    public static function get_name() {
        return \get_string('eventia_request_failed', 'block_integrityadvocate');
    }

    /**
     * Custom validation.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!(isset($this->relateduserid) && is_numeric($this->relateduserid))) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        if (!(isset($this->other['responsecode']) && is_numeric($this->other['responsecode']))) {
            throw new \coding_exception('The \'responsecode\' must be set.');
        }
        if (!(isset($this->other['method']) && is_string($this->other['method']))) {
            throw new \coding_exception('The \'method\' must be set.');
        }
        if (!(isset($this->other['url']) && is_string($this->other['url']))) {
            throw new \coding_exception('The \'url\' must be set.');
        }
    }
}
