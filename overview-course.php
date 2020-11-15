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
 * IntegrityAdvocate block Overview page showing course participants with a summary of their IntegrityAdvocate data.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

use block_integrityadvocate\Api as ia_api;
use block_integrityadvocate\Logger as Logger;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Output as ia_output;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

// Security check - this file must be included from overview.php.
defined('INTEGRITYADVOCATE_OVERVIEW_INTERNAL') || die();

// Sanity checks.
if (empty($blockinstanceid)) {
    throw new \InvalidArgumentException('$blockinstanceid is required');
}
if (empty($courseid) || ia_u::is_empty($course)) {
    throw new \InvalidArgumentException('$courseid and $course are required');
}

$debug = false || Logger::doLogForFunction(__CLASS__ . '::' . __FUNCTION__);
$debug && Logger::log(basename(__FILE__) . '::Started');

// Must be teacher to see this page.
\require_capability('block/integrityadvocate:overview', $coursecontext);

$notesallowed = !empty($CFG->enablenotes) && \has_capability('moodle/notes:manage', $coursecontext);
$messagingallowed = !empty($CFG->messaging) && \has_capability('moodle/site:sendmessage', $coursecontext);
$bulkoperations = \has_capability('moodle/course:bulkmessaging', $coursecontext) && ($notesallowed || $messagingallowed);

// The classes here are for DataTables styling ref https://datatables.net/examples/styling/index.html .
$prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_overview-course';

echo '<table id="' . $prefix . '" class="stripe order-column hover display">';
$tr = '<tr>';
$tr_end = '</tr>';
echo '<thead>';
$tr_header = $tr;
$tr_header .= \html_writer::tag('th', \get_string('fullnameuser'), ['class' => "{$prefix}_user"]);
$tr_header .= \html_writer::tag('th', \get_string('email'), ['class' => "{$prefix}_email"]);
$tr_header .= \html_writer::tag('th', \get_string('lastaccess'), ['class' => "{$prefix}_lastaccess"]);
$tr_header .= \html_writer::tag('th', \get_string('column_iadata', \INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_ia-data"]);
$tr_header .= \html_writer::tag('th', \get_string('column_iaphoto', \INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_ia-photo"]);
$tr_header .= $tr_end;
echo "{$tr_header}</thead><tbody>";

global $DB;
$lastaccess_course = $DB->get_records('user_lastaccess', ['courseid' => $courseid], 'userid', implode(',', ['userid', 'timeaccess']));
$debug && Logger::log('Got $lastaccess_course=' . ia_u::var_dump($lastaccess_course, true));
$users = \get_enrolled_users(\context_course::instance($courseid), 'moodle/grade:view', $groupid, 'u.*', 'u.lastname', 0, 0, false);
$debug && Logger::log('Got $users=get_enrolled_users=' . ia_u::var_dump($users, true));
if (ia_u::is_empty($users)) {
    echo \get_string('error_nousers', \INTEGRITYADVOCATE_BLOCK_NAME);
    exit;
}

// Check if there is any errors.
if ($configerrors = $blockinstance->get_config_errors()) {
    Logger::log(__CLASS__ . '::' . __FUNCTION__ . '::Error: ' . ia_u::var_dump($configerrors, true));
    echo implode(ia_output::BRNL, $configerrors);
    exit;
}

$participants = ia_api::get_participants($blockinstance->config->apikey, $blockinstance->config->appid, $courseid);
$debug && Logger::log('Got $participants=' . ia_u::var_dump($participants, true));

$pictureparams = ['size' => 35, 'courseid' => $courseid, 'includefullname' => true];

foreach ($users as $user) {
    error_log('Looking at userid=' . $user->id);
    echo $tr;
    // Column=User.
    $user = ia_mu::get_user_as_obj($user->id);
    echo \html_writer::tag('td', ia_mu::get_user_picture($user, $pictureparams), ['data-sort' => fullname($user), 'class' => "{$prefix}_user"]);

    // Column=email
    echo \html_writer::tag('td', $user->email, ['class' => "{$prefix}_email"]);

    // Column=lastaccess
    if (isset($lastaccess_course[$user->id]->timeaccess) && !empty($lastaccess_course[$user->id]->timeaccess)) {
        $userdate = \userdate($lastaccess_course[$user->id]->timeaccess);
        $debug && Logger::log('userid=' . $user->id . '; userdate=' . $userdate);
        echo \html_writer::tag('td', $userdate, ['class' => "{$prefix}_lastaccess"]);
    } else {
        echo \html_writer::tag('td', '', ['class' => "{$prefix}_lastaccess"]);
    }

    // Column=iadata and Column=iaphoto
    if (isset($participants[$user->id])) {
        echo \html_writer::tag('td', ia_output::get_participant_summary_output($blockinstance, $participants[$user->id], false, true, false), ['class' => "{$prefix}_iadata"]);
        echo \html_writer::tag('td', ia_output::get_participant_photo_output($user->id, $participants[$user->id]->participantphoto, $participants[$user->id]->status, $participants[$user->id]->email), ['class' => "{$prefix}_iaphoto"]);
    } else {
        echo \html_writer::tag('td', '', ['class' => "{$prefix}_iadata"]);
        echo \html_writer::tag('td', '', ['class' => "{$prefix}_iaphoto"]);
    }

    echo $tr_end;
}
echo '</tbody>';
echo "<tfoot>{$tr_header}</tfoot>";
echo '</table>';
// Used as a JQueryUI popup to show the user picture.
echo '<div id="dialog"></div>';
