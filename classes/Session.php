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
 * IntegrityAdvocate class to represent a single IA participant session.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

defined('MOODLE_INTERNAL') || die;

/**
 * Class to represent a single IA participant session.
 */
class Session {

    /** @var int Moodle module ID this is attached to. */
    public $activityid;

    /** @var int Count of user clicks. */
    public $clickiamherecount;

    /** @var int Unix timestamp when this was ended. */
    public $end;

    /** @var int Count times user exited fullscreen mode. */
    public $exitfullscreencount;

    /** @var string Unique ID for this item. */
    public $id;

    /** @var int Unix timestamp when this was overridden. */
    public $overridedate = -1;

    /**  @var string Override user first name. */
    public $overridelmsuserfirstname;

    /** @var int Moodle user id of the overriding user. */
    public $overridelmsuserid;

    /** @var string Override user last name. */
    public $overridelmsuserlastname;

    /** @var string Reason for override. */
    public $overridereason;

    /** @var int Participant Status value applied by the overrider. */
    public $overridestatus;

    /** @var string Base64-encoded image. */
    public $participantphoto;

    /** @var string URL the user can re-submit their ID to (if ID check failed and IA-side allows it). */
    public $resubmiturl;

    /** @var int Unix timestamp when this was started. */
    public $start;

    /** @var int Participant Status value. */
    public $status;

    /** @var Participant parent of this session. */
    public $participant;

    /** @var Flag[] Array of Flag objects in this session */
    public $flags = [];

    /**
     * Return true if the session is overridden.
     *
     * @return bool true if the session is overridden.
     */
    public function has_override(): bool {
        return isset($this->overridestatus);
    }

    /**
     * Get the net session status, accounting for any overrides.
     */
    public function get_status(): int {
        if (isset($this->overridestatus)) {
            return $this->overridestatus;
        }
        return $this->status;
    }

    /**
     * Return a string representation of this object.
     * The (recursive) participant property is removed.
     *
     * @return string representation of this object.
     */
    public function __toString(): string {
        $self = clone($this);
        if (isset($self->participantphoto) && !empty($self->participantphoto)) {
            $self->participantphoto = preg_replace(INTEGRITYADVOCATE_REGEX_DATAURI, 'redacted_base64_image', $self->participantphoto);
        }
        unset($self->participant);
        return json_encode($self, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

}
