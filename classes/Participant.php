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

}
