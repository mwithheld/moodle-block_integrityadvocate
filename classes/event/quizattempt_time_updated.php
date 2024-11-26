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
 * The quizattempt_time_updated event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int quizid: the id of the quiz.
 * }
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class quizattempt_time_updated extends \core\event\base {
    /**
     * Initialize the event data.
     */
    protected function init() {
        $this->data['crud'] = 'u'; // Options= c(reate), r(ead), u(pdate), d(elete).
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'quiz_attempts';
    }

    /**
     * Get the description of the event.
     *
     * @return string The event description.
     */
    public function get_description() {
        return "The user with id '$this->userid' has updated the starttime for quiz attempt id '$this->objectid' belonging " .
            "to the user with id '$this->relateduserid' for the quiz with course module id '$this->contextinstanceid'.";
    }

    /**
     * Get the localized name of the event.
     *
     * @return string The localized event name.
     */
    public static function get_name() {
        return \get_string('eventquizattempt_time_updated', 'block_integrityadvocate');
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/quiz/review.php', ['attempt' => $this->objectid]);
    }

    /**
     * Custom validation.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        if (!(isset($this->quizid) && is_numeric($this->quizid))) {
            throw new \coding_exception('The \'quizid\' must be set.');
        }
    }

    /**
     * Map objectid = quiz attemptid.
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'quiz_attempts', 'restore' => 'quiz_attempt'];
    }

    /**
     * Get other mappings for this event.
     *
     * @return array
     */
    public static function get_other_mapping() {
        $othermapped = [];
        $othermapped['quizid'] = ['db' => 'quiz', 'restore' => 'quiz'];

        return $othermapped;
    }
}
