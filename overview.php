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
 * IntegrityAdvocate block overview page
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * This code is adapted from block_completion_progress::lib.php::block_completion_progress_bar
 * ATM with IA APIv2 we cannot label and get back proctoring results per activity,
 * so we are just getting results for all students associated with the API key and displaying them.
 */
// Include required files.
require_once(dirname(__FILE__, 3) . '/config.php');
require_once($CFG->dirroot . '/blocks/integrityadvocate/lib.php');
require_once($CFG->dirroot . '/notes/lib.php');
require_once($CFG->libdir . '/tablelib.php');

/** @var int How many users per page to show by default. */
const DEFAULT_PAGE_SIZE = 20;

/** @var int Flag to tell the overview-course.php and overview-user.php pages the include is legit. */
const INTEGRITYADVOCATE_OVERVIEW_INTERNAL = true;

$debug = true;

// Gather form data.
$blockinstanceid = required_param('instanceid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
// If userid is specified, show info only for that user.
$userid = optional_param('userid', 0, PARAM_INT); // Which user to show.
// These are only used in the course view.
$groupid = optional_param('group', 0, PARAM_ALPHANUMEXT); // Group selected.
$perpage = optional_param('perpage', DEFAULT_PAGE_SIZE, PARAM_INT); // How many per page.
// Determine course and context.
$course = get_course($courseid);
if (empty($course)) {
    throw new InvalidArgumentException('Invalid $courseid specified');
}
$coursecontext = CONTEXT_COURSE::instance($courseid, MUST_EXIST);

// Check user is logged in and capable of accessing the overview.
require_login($course, false);
require_capability('block/integrityadvocate:overview', $coursecontext);
confirm_sesskey();

// Find the role to display, defaulting to students.
$roleid = optional_param('role', block_integrityadvocate_overview_get_default_role($coursecontext), PARAM_INT);

// Set up page parameters.
$PAGE->set_course($course);
$PAGE->requires->css('/blocks/' . INTEGRITYADVOCATE_SHORTNAME . '/styles.css');
$baseurl = new moodle_url('/blocks/' . INTEGRITYADVOCATE_SHORTNAME . '/overview.php',
        array(
    'instanceid' => $blockinstanceid,
    'courseid' => $courseid,
    // These are only used in the course view.
    'group' => $groupid,
    'perpage' => $perpage,
    'sesskey' => sesskey(),
    'role' => $roleid,
    'userid' => $userid,
        ));
$PAGE->set_url($baseurl);
$PAGE->set_context($coursecontext);
$title = get_string('overview', INTEGRITYADVOCATE_BLOCKNAME);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($title);
$PAGE->set_pagelayout('report');


if (!$userid) {
    $PAGE->add_body_class(INTEGRITYADVOCATE_BLOCKNAME . '-overview-course');

    /*
     * We didn't need the style at this time, so leaving it here disabled.
     * I. $url = new moodle_url('https://cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css');.
     * I. $PAGE->requires->css($url);.
     */
    // Override options set in amd/build/init.js.
    $PAGE->requires->js_call_amd('block_integrityadvocate/init', 'init',
            // DataTable options ref https://datatables.net/reference/option/.
            array('.datatable', array(
                    'autoWidth' => false,
                    'info' => false,
                    'ordering' => false,
                    'paging' => false,
                    'searching' => true,
                    'language' => array(
                        // Language options ref https://datatables.net/reference/option/language.
                        'search' => get_string('filter') . '&nbsp;'
                    )
                )
            )
    );
} else {
    $PAGE->add_body_class(INTEGRITYADVOCATE_BLOCKNAME . '-overview-user');
}

// Start page output.
echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2);
echo $OUTPUT->container_start(INTEGRITYADVOCATE_BLOCKNAME);

$setuperrors = block_integrityadvocate_completion_setup_errors($course);
$continue = true;
if ($setuperrors) {
    foreach ($setuperrors as $err) {
        echo get_string($err, INTEGRITYADVOCATE_BLOCKNAME) . "<br/>\n";
    }
    $continue = false;
}

if ($continue) {
    // Check if activities/resources have been selected in config.
    $activities = block_integrityadvocate_get_course_ia_activities($courseid);
    if (is_string($activities)) {
        echo get_string($activities, INTEGRITYADVOCATE_BLOCKNAME) . "<br/>\n";
        $continue = false;
    }
    $debug && block_integrityadvocate_log(basename(__FILE__) . '::Got activities count=' . count($activities));
}


if ($continue) {
    if ($userid) {
        $debug && block_integrityadvocate_log(basename(__FILE__) . '::Got a userid so show the single user IA results');
        require_once('overview-user.php');
    } else {
        $debug && block_integrityadvocate_log(basename(__FILE__) . '::Got no userid so show the course users IA results');
        require_once('overview-course.php');
    }
}

// Clean up vars no longer needed instead of polluting the global namespace.
unset($title, $debug);

echo $OUTPUT->container_end();
echo $OUTPUT->footer();
