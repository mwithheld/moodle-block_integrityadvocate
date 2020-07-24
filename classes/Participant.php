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
 * IntegrityAdvocate class to represent a single IA participant.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

/**
 * Class to represent a single IA participant.
 */
class Participant {

    // Our minimun-supported PHP is 7.2.  PHP < 7.4 does not support typed properties.
    public $courseid;
    public $created = -1;
    public $email;
    public $firstname;
    public $lastname;
    public $modified = -1;
    public $overridedate = -1;
    public $overridelmsuserfirstname;
    public $overridelmsuserid;
    public $overridelmsuserlastname;
    public $overridereason;
    public $overridestatus;
    public $participantidentifier;
    public $participantphoto;
    public $resubmiturl;
    public $sessions = array();
    public $status;

    /**
     * Return true if this participant has a session override.
     *
     * @return bool True if this participant has a session override.
     */
    public function has_session_override(): bool {
        if (!is_array($this->sessions) || empty($this->sessions)) {
            return false;
        }

        foreach ($this->sessions as $s) {
            if ($s->has_override()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the most recent of sessions for the specified activityid (cmid).
     * The most recent is defined in order of [end || start time].
     *
     * @param int $activityid The activityid to search for.
     * @return mixed bool false if not found; else The newest session matching the activity.
     */
    public function get_latest_module_session(int $activityid) {
        if (!is_array($this->sessions) || empty($this->sessions) || $activityid < 0) {
            return false;
        }

        // Setup an empty object for comparing the start and end times.
        $latestsession = new Session();
        $latestsession->end = -1;
        $latestsession->start = -1;

        // Iterate over the sessions and compare only those matching the activityid.
        // Choose the one with the newest in order of [end || start time].
        foreach ($this->sessions as $s) {
            // Only match the module's activityid (cmid).
            if (intval($activityid) !== intval($s->activityid)) {
                continue;
            }
            if (($s->end > $latestsession->end) || ($s->start > $latestsession->start)) {
                $latestsession = $s;
            }
        }

        // If $latestsession is empty or is just the comparison object, we didn't find anything.
        if (ia_u::is_empty($latestsession) || !isset($latestsession->id)) {
            return false;
        }

        return $latestsession;
    }

}
