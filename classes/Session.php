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

    /**
     *
     * @var int
     */
    public $activityid;

    /**
     *
     * @var int
     */
    public $clickiamherecount;

    /**
     *
     * @var int
     */
    public $end;

    /**
     *
     * @var int
     */
    public $exitfullscreencount;

    /**
     *
     * @var int
     */
    public $id;

    /**
     *
     * @var string Base64-encoded image
     */
    public $participantphoto;

    /**
     *
     * @var int
     */
    public $start;

    /**
     *
     * @var int
     */
    public $status;

    /**
     *
     * @var Participant parent of this session.
     */
    public $participant;

    /**
     *
     * @var Flag[]
     */
    public $flags = array();

    public $overridedate = -1;
    public $overridelmsuserfirstname;
    public $overridelmsuserid;
    public $overridelmsuserlastname;
    public $overridereason;
    public $overridestatus;
    
    public function is_overridden(): bool {
        return isset($this->overridestatus);
    }

}
