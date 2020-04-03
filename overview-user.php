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
 * IntegrityAdvocate block Overview showing a single user's Integrity Advocate detailed info.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Security check - this file must be included from overview.php.
defined('INTEGRITYADVOCATE_OVERVIEW_INTERNAL') or die();

// Sanity checks.
if (empty($blockinstanceid)) {
    throw new InvalidArgumentException('$blockinstanceid is required');
}
if (empty($courseid) || empty($course)) {
    throw new InvalidArgumentException('$courseid and $course are required');
}
$userid = required_param('userid', PARAM_INT);

// Note: block_integrityadvocate_get_course_user_ia_data() makes sure the user is enrolled in a course activity.
$useriaresults = block_integrityadvocate_get_course_user_ia_data($course, $userid);

$continue = true;

// If we get back a string we got an error, so display it and quit.
if (is_string($useriaresults)) {
    echo get_string($useriaresults, INTEGRITYADVOCATE_BLOCKNAME);
    $continue = false;
}

if ($continue) {
    // Show basic user info at the top.  Adapted from user/view.php.
    echo html_writer::start_tag('div', array('class' => 'block_integrityadvocate_overview_user_userinfo'));
    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
    echo $OUTPUT->user_picture($user, array('size' => 35, 'courseid' => $courseid, 'includefullname' => true));
    echo html_writer::end_tag('div');

    foreach ($useriaresults as $a) {
        $blockinstanceid = $a['activity']['block_integrityadvocate_instance']['id'];
        $participantdata = $a['ia_participant_data'];

        // Display summary.
        echo block_integrityadvocate_get_participant_summary_output($participantdata, $blockinstanceid, $courseid, $userid, false);

        // Display flag info.
        echo block_integrityadvocate_get_participant_flags_output($participantdata);
    }
}
