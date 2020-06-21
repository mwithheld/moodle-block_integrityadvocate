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
 * IntegrityAdvocate block Overview page showing course participants with
 * a summary of their IntegrityAdvocate data.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

// Security check - this file must be included from overview.php.
defined('INTEGRITYADVOCATE_OVERVIEW_INTERNAL') or die();

// Sanity checks.
if (empty($blockinstanceid)) {
    throw new \InvalidArgumentException('$blockinstanceid is required');
}
if (empty($courseid) || ia_u::is_empty($course)) {
    throw new \InvalidArgumentException('$courseid and $course are required');
}

$debug = true;

require_capability('block/integrityadvocate:overview', $coursecontext);

// Output roles selector.
echo $OUTPUT->container_start('progressoverviewmenus');

$sql = 'SELECT  DISTINCT r.id, r.name, r.shortname
            FROM    {role} r, {role_assignments} a
           WHERE    a.contextid = :contextid
             AND    r.id = a.roleid';
$params = array('contextid' => $coursecontext->id);
$roles = role_fix_names($DB->get_records_sql($sql, $params), $coursecontext);
$rolestodisplay = array(0 => get_string('allparticipants'));
foreach ($roles as $role) {
    $rolestodisplay[$role->id] = $role->localname;
}
echo '&nbsp;' . get_string('role') . '&nbsp;';
echo $OUTPUT->single_select($PAGE->url, 'role', $rolestodisplay, $roleid);
echo $OUTPUT->container_end();

$notesallowed = !empty($CFG->enablenotes) && has_capability('moodle/notes:manage', $coursecontext);
$messagingallowed = !empty($CFG->messaging) && has_capability('moodle/site:sendmessage', $coursecontext);
$bulkoperations = has_capability('moodle/course:bulkmessaging', $coursecontext) && ($notesallowed || $messagingallowed);

// Setup the ParticipantsTable instance.
require_once(__DIR__ . '/classes/ParticipantsTable.php');
$participanttable = new block_integrityadvocate\ParticipantsTable(
        $courseid, $groupid, $lastaccess = 0, $roleid, $enrolid = 0, $status = -1, $searchkeywords = array(), $bulkoperations,
        $selectall = optional_param('selectall', false, PARAM_BOOL)
);
$participanttable->define_baseurl($baseurl);

// Populate the ParticipantsTable instance with user rows from Moodle core info.
$participanttable->setup_and_populate($perpage);

// Populate the ParticipantsTable instance user rows with blockinstance-specific IA participant info.
// No return value.
$participanttable->populate_from_blockinstance($blockinstance, optional_param('page', 0, PARAM_INT));

// Output the table.
$participanttable->out_end();

if ($bulkoperations) {
    echo '<br />';
    echo html_writer::start_tag('div', array('class' => 'buttons'));

    echo html_writer::start_tag('div', array('class' => 'btn-group'));
    echo html_writer::tag('input', '', array('type' => 'button', 'id' => 'checkallonpage', 'class' => 'btn btn-secondary',
        'value' => get_string('selectall')));
    echo html_writer::tag('input', '', array('type' => 'button', 'id' => 'checknone', 'class' => 'btn btn-secondary',
        'value' => get_string('deselectall')));
    echo html_writer::end_tag('div');
    $displaylist = array();
    if ($messagingallowed) {
        $displaylist['#messageselect'] = get_string('messageselectadd');
    }

    echo html_writer::tag('label', get_string('withselectedusers'), array('for' => 'formactionid'));
    echo html_writer::select($displaylist, 'formaction', '', array('' => 'choosedots'), array('id' => 'formactionid'));

    echo '<input type="hidden" name="id" value="' . $courseid . '" />';
    echo '<noscript style="display:inline">';
    echo '<div><input type="submit" value="' . get_string('ok') . '" /></div>';
    echo '</noscript>';
    echo html_writer::end_tag('div');

    $options = new stdClass();
    $options->courseid = $courseid;
    $options->noteStateNames = note_get_state_names();
    $options->stateHelpIcon = $OUTPUT->help_icon('publishstate', 'notes');
    $PAGE->requires->js_call_amd('core_user/participants', 'init', array($options));
}
echo html_writer::end_tag('form');
