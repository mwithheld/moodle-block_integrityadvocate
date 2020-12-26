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
 * block_integrityadvocate cache definitions.
 *
 * @package    block_integrityadvocate
 * @copyright IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

// Ref https://docs.moodle.org/dev/Cache_API_-_Quick_reference#Invalidating_keys_from_a_cache .
$definitions = array(
    'perrequest' => array(
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'canuselocalstore' => true,
    ),
    'persession' => array(
        'mode' => cache_store::MODE_SESSION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'canuselocalstore' => true,
    ),
//    'untilcourseenrolmentchanges' => array(
//        'mode' => cache_store::MODE_SESSION,
//        'simplekeys' => true,
//        'simpledata' => true,
//        'staticacceleration' => true,
//        'canuselocalstore' => true,
//        // Does not clear this cache: Suspending a user.
//        // Clears this cache: Unenrolling a user.
//        'invalidationevents' => ['\core\event\enrol_instance_updated', '\core\event\user_enrolment_updated'],
//    ),
);
