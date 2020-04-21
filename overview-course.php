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
defined('MOODLE_INTERNAL') || die;

// Security check - this file must be included from overview.php.
defined('INTEGRITYADVOCATE_OVERVIEW_INTERNAL') or die();

// Sanity checks.
if (empty($blockinstanceid)) {
    throw new InvalidArgumentException('$blockinstanceid is required');
}
if (empty($courseid) || empty($course)) {
    throw new InvalidArgumentException('$courseid and $course are required');
}

$debug = true;

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

// Z==============================================================================.
/**
 * Use a modified Course Participants table to show IntegrityAdvocate summary data.
 */
class integrityadvocate_overview_participants extends \core_user\participants_table {

    /**
     * Set up the table object from the provided data.
     * The goal here was to use parent logic but change it for our purposes.
     *
     * @param int $courseid
     * @param int|false $currentgroup False if groups not used, int if groups used, 0 all groups, USERSWITHOUTGROUP for no group
     * @param int $accesssince The time the user last accessed the site
     * @param int $roleid The role we are including, 0 means all enrolled users
     * @param int $enrolid The applied filter for the user enrolment ID.
     * @param int $status The applied filter for the user's enrolment status.
     * @param string|array $search The search string(s)
     * @param bool $bulkoperations Is the user allowed to perform bulk operations?
     * @param bool $selectall Has the user selected all users on the page?
     */
    public function __construct($courseid, $currentgroup, $accesssince, $roleid, $enrolid, $status, $search, $bulkoperations,
            $selectall) {
        parent::__construct($courseid, $currentgroup, $accesssince, $roleid, $enrolid, $status, $search, $bulkoperations, $selectall);

        $this->attributes['class'] .= ' datatable';

        // Add the custom IAData column.
        $columnsflipped = array_flip($this->columns);
        $columnsflipped[] = 'iadata';
        $columnsflipped[] = 'iaphoto';
        // Why does this not need flipping back?  Dunno, but it borks otherwise.
        $this->columns = $columnsflipped;

        // Do not strip tags from these colums (i.e. do not pass through the s() function).
        $this->column_nostrip = array('iadata');

        $this->headers[] = get_string('column_iadata', INTEGRITYADVOCATE_BLOCKNAME);
        $this->headers[] = get_string('column_iaphoto', INTEGRITYADVOCATE_BLOCKNAME);

        $this->prefs['collapse']['status'] = true;
        $this->define_columns($this->columns);

        // Prevent this columns from getting squished.
        $this->column_style('iadata', 'min-width', '20%');

        // The email field was dominating the display, so calm it down.
        $this->column_style('email', 'max-width', '200px');
        $this->column_style('email', 'word-wrap', 'break-word');

        // Hide columns we won't use.
        $this->column_style('roles', 'display', 'none');
        $this->column_style('groups', 'display', 'none');
        $this->column_style('status', 'display', 'none');
    }

    /**
     * Generate this column.
     *
     * @param \stdClass $data
     * @return string The IA photo else empty string
     */
    public function col_iaphoto($data) {
        return isset($data->iaphoto) ? $data->iaphoto : '';
    }

    /**
     * Generate this column.
     *
     * @param \stdClass $data
     * @return string The IA data else empty string
     */
    public function col_iadata($data) {
        return isset($data->iadata) ? $data->iadata : '';
    }

    /**
     * This is the beginning half of the parent class out() function.
     * So that we can populate data into the class structure and work with it
     * before the table is output to the end-user.
     *
     * @param int $perpage How many items per page to show.
     */
    public function setup_and_populate($perpage) {
        $this->setup();
        $this->query_db($perpage, $useinitialsbar = true);
    }

    /**
     * This is the ending half of the parent class out() function.
     * It outputs the table HTML.
     */
    public function out_end() {
        $this->build_table();
        $this->close_recordset();
        $this->finish_output();
    }

}

// Z==============================================================================.

$participanttable = new integrityadvocate_overview_participants(
        $courseid, $groupid = false, $lastaccess = 0, $roleid, $enrolid = 0, $status = -1, $searchkeywords = array(),
        $bulkoperations, $selectall = optional_param('selectall', false, PARAM_BOOL)
);
$participanttable->define_baseurl($baseurl);

// Do this so we can get the participant rawdata values and iterate through them.
$participanttable->setup_and_populate($perpage);

// Start gathring IA info here.
$participantdatacache = array();
foreach ($activities as $a) {
    $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . "::Started with activity id={$a['id']}; name={$a['name']}");

    // See if this activity has an associated IA block.
    $modulecontext = $a['context'];
    list($unused, $blockinstance) = \IntegrityAdvocate_Moodle_Utility::get_first_block($modulecontext, INTEGRITYADVOCATE_SHORTNAME);
    if (empty($blockinstance) || !isset($blockinstance->config) || (!isset($blockinstance->config->apikey) && !isset($blockinstance->config->appid))) {
        // No visible IA block found with valid config, so go on to the next activity.
        continue;
    }
    $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . "::Got \$blockinstance with apikey={$blockinstance->config->apikey}; appid={$blockinstance->config->appid}");

    // This activity-block has its own API key.
    // Use that to get IA-side data for all participants matching that API key.
    // Note the use of static caching here.
    if (!isset($participantdatacache[$blockinstance->config->apikey]) | empty($participantdatacache[$blockinstance->config->apikey])) {
        $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::No cached data found; About to call block_integrityadvocate_get_ia_participant_data()');
        $participantdatacache[$blockinstance->config->apikey] = \IntegrityAdvocate_Api::get_participant_data($blockinstance->config->apikey,
                        $blockinstance->config->appid);
    }

    // Check again if it is set.
    if (empty($participantdatacache[$blockinstance->config->apikey])) {
        $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . '::No API key set, so it cannot retrieve data from the API.  Go on to the next activity');
        continue;
    }
    $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . '::Got $participantdata[apikey]=' . print_r($participantdatacache[$blockinstance->config->apikey],
                            true));


    // Check each participant table row to see if they have a match in the $blockinstance participant data from IA.
    foreach ($participanttable->rawdata as $thisuserid => $thisuser) {
        $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . '::Looking at $thisuserid=' . $thisuserid);

        // See if our current user has values in $participantdata: 2 parts:
        // I.(1) Get the useridentifier.
        $useridentifier = block_integrityadvocate_encode_useridentifier($modulecontext, $thisuserid);
        $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . "::The Moodle user with userid={$thisuserid} has \$useridentifier={$useridentifier}");
        // I.(2) Look in the $participantdata for a match.
        $singleuserapidata = block_integrityadvocate_parse_user_data($participantdatacache[$blockinstance->config->apikey],
                $useridentifier);
        $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . '::Got $ia_single_user_data=' . print_r($singleuserapidata,
                                true));
        if (!$singleuserapidata) {
            $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . '::No single-user api data found. Skip to the next user');
            continue;
        }

        // Gather formatted data for this user.
        $participanttable->rawdata[$thisuserid]->iadata = \IntegrityAdvocate_Output::get_participant_summary_output(
                        $singleuserapidata, $blockinstanceid, $courseid, $thisuserid, true, false);
        $participanttable->rawdata[$thisuserid]->iaphoto = \IntegrityAdvocate_Output::get_summary_photo_html(
                        $singleuserapidata, block_integrityadvocate_filter_var_status($singleuserapidata));
    }
}

// Output the table.
$participanttable->out_end();


if ($bulkoperations) {
    echo '<br />';
    echo html_writer::start_tag('div', array('class' => 'buttons'));

    echo html_writer::start_tag('div', array('class' => 'btn-group'));
    echo html_writer::tag('input', '',
            array('type' => 'button', 'id' => 'checkallonpage', 'class' => 'btn btn-secondary',
                'value' => get_string('selectall')));
    echo html_writer::tag('input', '',
            array('type' => 'button', 'id' => 'checknone', 'class' => 'btn btn-secondary',
                'value' => get_string('deselectall')));
    echo html_writer::end_tag('div');
    $displaylist = array();
    if ($messagingallowed) {
        $displaylist['#messageselect'] = get_string('messageselectadd');
    }

    echo html_writer::tag('label', get_string("withselectedusers"), array('for' => 'formactionid'));
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
