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

use block_integrityadvocate\Utility as ia_u;
use block_integrityadvocate\Logger as Logger;
use block_integrityadvocate\MoodleUtility as ia_mu;

defined('MOODLE_INTERNAL') || die;

// Security check - this file must be included from overview.php.
defined('INTEGRITYADVOCATE_OVERVIEW_INTERNAL') || die();

$debug = false || Logger::do_log_for_function(INTEGRITYADVOCATE_BLOCK_NAME . '\\' . basename(__FILE__));
$debug && Logger::log(basename(__FILE__) . "::Started with courseid={$courseid}");

// Check all requirements.
switch (true) {
    case (!FeatureControl::OVERVIEW_COURSE && !FeatureControl::OVERVIEW_COURSE_V2):
        throw new \Exception('This feature is disabled');
    case (empty($blockinstanceid)):
        throw new \InvalidArgumentException('$blockinstanceid is required');
    case (empty($courseid) || ia_u::is_empty($course) || ia_u::is_empty($coursecontext)) :
        throw new \InvalidArgumentException('$courseid, $course and $coursecontext are required');
    case(!empty(\require_capability('block/integrityadvocate:overview', $coursecontext))):
        // The above line throws an error if the current user is not a teacher, so we should never get here.
        $debug && Logger::log(__FILE__ . '::Checked required capability: overview');
        break;
    default:
        $debug && Logger::log(__FILE__ . '::All requirements are met');
}

if (FeatureControl::OVERVIEW_COURSE_V2) {
    $debug && Logger::log(__FILE__ . '::Request is for overview_course_v2');
    $prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_overviewcourse';

    // Output roles selector.
    echo $OUTPUT->container_start(INTEGRITYADVOCATE_BLOCK_NAME . '_roleid_select', "{$prefix}_roleid");
    echo $OUTPUT->single_select($PAGE->url, 'role', ia_mu::get_roles_for_select($coursecontext), $roleid);
    echo $OUTPUT->container_end();

    // Get list of students in the course.
    // Usage: get_role_users($roleid, $context, $parent, $fields, $sort, $all, $group, $limitfrom, $limitnum).
    $debug && Logger::log(__FILE__ . "::About to get_role_users(\$roleid={$roleid}, \$context={$coursecontext->id}, \$parent=false, \$fields='ra.id, u.id, u.email, u.lastaccess, u.picture, u.imagealt, ' . get_all_user_name_fields(true, 'u'), \$sort=null, \$all=true, \$group=$groupid, \$limitfrom=($currpage * $perpage), \$limitnum=$perpage)");
    $enrolledusers = get_role_users($roleid, $coursecontext, false, 'ra.id, u.id, u.email, u.lastaccess, u.picture, u.imagealt, ' . get_all_user_name_fields(true, 'u'), null, true, $groupid, ($currpage * $perpage), $perpage);
    $debug && Logger::log(__FILE__ . '::Got count($enrolledusers)=' . ia_u::count_if_countable($enrolledusers));

    if (!$enrolledusers) {
        echo get_string('nousersfound');
    } else {
        // The classes here are for DataTables styling ref https://datatables.net/examples/styling/index.html .
        echo '<table id="' . $prefix . '_table" class="stripe order-column hover display">';
        $tr = '<tr>';
        $tr_end = '</tr>';
        echo '<thead>';

        $tr_header = [$tr];
        $tr_header[] = \html_writer::tag('th', 'id', ['class' => "{$prefix}_id"]);
        $tr_header[] = \html_writer::tag('th', \get_string('user'), ['class' => "{$prefix}_user"]);
        $tr_header[] = \html_writer::tag('th', \get_string('email'), ['class' => "{$prefix}_email"]);
        $tr_header[] = \html_writer::tag('th', \get_string('lastcourseaccess'), ['class' => "{$prefix}_lastcourseaccess"]);
        $tr_header[] = \html_writer::tag('th', \get_string('column_latestparticipantleveldata', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_column_iadata"]);
        $tr_header[] = \html_writer::tag('th', \get_string('column_iaphoto', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_column_iaphoto"]);
        $tr_header[] = $tr_end;
        echo implode('', $tr_header) . '</thead><tbody>';

        echo $tr;
        $pictureparams = ['size' => 35, 'courseid' => $courseid, 'includefullname' => true];
        foreach ($enrolledusers as $user) {
            $debuginfo = "userid={$user->id}";
            //echo '<PRE>' . ia_u::var_dump($user) . '</PRE><hr>' . ia_output::BRNL;
            // Column=User.
            echo \html_writer::tag('td', $user->id, ['class' => "{$prefix}_id"]);
            echo \html_writer::tag('td', ia_mu::get_user_picture($user, $pictureparams), ['data-id' => $user->id, 'data-sort' => fullname($user), 'class' => "{$prefix}_user"]);

            echo \html_writer::tag('td', $user->email, ['class' => "{$prefix}_email"]);
            echo \html_writer::tag('td', ($user->lastaccess ? \userdate($user->lastaccess) : get_string('never')), ['class' => "{$prefix}_lastcourseaccess"]);
            echo \html_writer::tag('td', '', ['class' => "{$prefix}_column_iadata"]);
            echo \html_writer::tag('td', '', ['class' => "{$prefix}_column_iaphoto"]);
            echo $tr_end;
        }

        echo '</tbody>';
        echo "<tfoot>{$tr_header}</tfoot>";
        echo '</table>';
        // Used as a JQueryUI popup to show the user picture.
        echo '<div id="dialog"></div>';
    }

    // Get IA data for this first set.
    //$participants = ia_api::get_participants($blockinstance->config->apikey, $blockinstance->config->appid, $courseid);
    // Display the first 10.\
    // For each student, get their IA data.
} else if (FeatureControl::OVERVIEW_COURSE && !FeatureControl::OVERVIEW_COURSE_V2) {
    // OLD Participants table UI.
    // Moodle core: Notes, messages and bulk operations.
    $notesallowed = !empty($CFG->enablenotes) && \has_capability('moodle/notes:manage', $coursecontext);
    $messagingallowed = !empty($CFG->messaging) && \has_capability('moodle/site:sendmessage', $coursecontext);
    $bulkoperations = \has_capability('moodle/course:bulkmessaging', $coursecontext) && ($notesallowed || $messagingallowed);

    // Setup the ParticipantsTable instance.
    require_once(__DIR__ . '/classes/ParticipantsTable.php');
    $roleid = \optional_param('role', 0, PARAM_INT);
    $participanttable = new ParticipantsTable(
            $courseid, $groupid, $lastaccess = 0, $roleid, $enrolid = 0, $status = -1, $searchkeywords = [], $bulkoperations,
            $selectall = \optional_param('selectall', false, \PARAM_BOOL)
    );
    $participanttable->define_baseurl($baseurl);

    // Populate the ParticipantsTable instance with user rows from Moodle core info.
    $participanttable->setup_and_populate($perpage);

    $debug && Logger::log(basename(__FILE__) . '::About to populate_from_blockinstance()');
    // Populate the ParticipantsTable instance user rows with blockinstance-specific IA participant info.
    // No return value.
    $participanttable->populate_from_blockinstance($blockinstance);

    // Output the table.
    $participanttable->out_end();

    if ($bulkoperations) {
        echo '<br />';
        echo \html_writer::start_tag('div', array('class' => 'buttons'));

        echo \html_writer::start_tag('div', array('class' => 'btn-group'));
        echo \html_writer::tag('input', '', array('type' => 'button', 'id' => 'checkallonpage', 'class' => 'btn btn-secondary',
            'value' => \get_string('selectall')));
        echo \html_writer::tag('input', '', array('type' => 'button', 'id' => 'checknone', 'class' => 'btn btn-secondary',
            'value' => \get_string('deselectall')));
        echo \html_writer::end_tag('div');
        $displaylist = [];
        if ($messagingallowed) {
            $displaylist['#messageselect'] = \get_string('messageselectadd');
        }

        echo \html_writer::tag('label', \get_string('withselectedusers'), array('for' => 'formactionid'));
        echo \html_writer::select($displaylist, 'formaction', '', array('' => 'choosedots'), array('id' => 'formactionid'));

        echo '<input type="hidden" name="id" value="' . $courseid . '" />';
        echo '<noscript style="display:inline">';
        echo '<div><input type="submit" value="' . get_string('ok') . '" /></div>';
        echo '</noscript>';
        echo \html_writer::end_tag('div');

        $options = new \stdClass();
        $options->courseid = $courseid;
        $options->noteStateNames = \note_get_state_names();
        $options->stateHelpIcon = $OUTPUT->help_icon('publishstate', 'notes');
        $PAGE->requires->js_call_amd('core_user/participants', 'init', array($options));
    }
}

echo \html_writer::end_tag('form');
