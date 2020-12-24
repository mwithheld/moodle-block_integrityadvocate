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

class ParticipantsCache {

    /** @var int Course ID. */
    public $courseid;

    /** @var int Unix timestamp when this was modified. */
    public $modified = -1;

    /** @var array<Participant> Array of participant objects attached to this course. */
    public $participantsraw = [];

    public function __construct(int $courseid) {
        $this->courseid = $courseid;
    }

}

/**
 * Class to represent a single IA participant.
 */
class Participant {

    /** @var int Course ID. */
    public $courseid;

    /** @var int Unix timestamp when this was created. */
    public $created = -1;

    /** @var string The user email */
    public $email = '';

    /** @var string The user firstname */
    public $firstname = '';

    /** @var string The user lastname */
    public $lastname = '';

    /** @var int Unix timestamp when this was modified. */
    public $modified = -1;

    /** @var int Unix timestamp when this was overridden. */
    public $overridedate = -1;

    /**  @var string Override user first name. */
    public $overridelmsuserfirstname = '';

    /** @var int Moodle user id of the overriding user. */
    public $overridelmsuserid;

    /** @var string Override user last name. */
    public $overridelmsuserlastname = '';

    /** @var string Reason for override. */
    public $overridereason = '';

    /** @var int Status value applied by the overrider. */
    public $overridestatus;

    /** @var int Unique id (Moodle user id) assigned to this participant */
    public $participantidentifier;

    /** @var string Base64-encoded image. */
    public $participantphoto = '';

    /** @var string URL the user can re-submit their ID to (if ID check failed and IA-side allows it). */
    public $resubmiturl = '';

    /** @var array<Session> Array of session objects attached to this participant */
    public $sessions = [];

    /** @var int Status value. */
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
     * @return mixed Null if not found; else The newest Session matching the activity.
     */
    public function get_latest_module_session(int $activityid): ?Session {
        if (!is_array($this->sessions) || empty($this->sessions) || $activityid < 0) {
            return null;
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
            return null;
        }

        return $latestsession;
    }

    /**
     * Return a string representation of this object.
     * The (recursive) session>participant property is removed.
     *
     * @return string representation of this object.
     */
    public function __toString(): string {
        $self = clone($this);
        foreach ($self->sessions as $key => $s) {
            $self->sessions[$key] = $s->__toString();
        }
        if (isset($self->participantphoto) && !empty($self->participantphoto)) {
            $self->participantphoto = \preg_replace(INTEGRITYADVOCATE_REGEX_DATAURI, 'redacted_base64_image', $self->participantphoto);
        }
        return \json_encode($self, \JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

}
