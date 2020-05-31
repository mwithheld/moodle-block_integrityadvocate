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
 * ATM with IA APIv2 we cannot label and get back proctoring results per module,
 * so we are just getting results for all students associated with the API key and displaying them.
 */
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

// Include required files.
require_once(dirname(__FILE__, 3) . '/config.php');
require_once($CFG->dirroot . '/blocks/integrityadvocate/lib.php');
require_once($CFG->dirroot . '/notes/lib.php');
require_once($CFG->libdir . '/tablelib.php');

/** @var int How many users per page to show by default. */
const DEFAULT_PAGE_SIZE = 20;

/** @var int Flag to tell the overview-course.php and overview-user.php pages the include is legit. */
const INTEGRITYADVOCATE_OVERVIEW_INTERNAL = true;

$debug = false;

\require_login();

// Gather form data.
$blockinstanceid = required_param('instanceid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
// If userid is specified, show info only for that user.
$userid = optional_param('userid', 0, PARAM_INT); // Which user to show.
$debug && ia_mu::log(__FILE__ . '::Got param $userid=' . $userid);

// These are only used in the course view.
$groupid = optional_param('group', 0, PARAM_ALPHANUMEXT); // Group selected.
$perpage = optional_param('perpage', DEFAULT_PAGE_SIZE, PARAM_INT); // How many per page.
// Determine course and context.
$course = get_course($courseid);
if (ia_u::is_empty($course)) {
    throw new InvalidArgumentException('Invalid $courseid specified');
}
$coursecontext = CONTEXT_COURSE::instance($courseid, MUST_EXIST);

// Check user is logged in and capable of accessing the overview.
\require_login($course, false);
confirm_sesskey();

// Find the role to display, defaulting to students.
$roleid = optional_param('role', ia_mu::get_default_course_role($coursecontext), PARAM_INT);

// Set up page parameters.
$PAGE->set_course($course);
$PAGE->requires->css('/blocks/' . INTEGRITYADVOCATE_SHORTNAME . '/styles.css');
$baseurl = new \moodle_url('/blocks/' . INTEGRITYADVOCATE_SHORTNAME . '/overview.php',
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
$title = get_string('overview', INTEGRITYADVOCATE_BLOCK_NAME);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($title);
$PAGE->set_pagelayout('report');

if (!$userid) {
    $PAGE->add_body_class(INTEGRITYADVOCATE_BLOCK_NAME . '-overview-course');

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
    $PAGE->add_body_class(INTEGRITYADVOCATE_BLOCK_NAME . '-overview-user');
}

// Start page output.
echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2);
echo $OUTPUT->container_start(INTEGRITYADVOCATE_BLOCK_NAME);

// If there is an error, stops further processing, but still displays the page footer.
$continue = true;

// Both overview pages require the blockinstance, so get it here.
$blockinstance = \block_instance_by_id($blockinstanceid);

// If the block instance is not configured yet, simply return empty result.
if (ia_u::is_empty($blockinstance) || ($configerrors = $blockinstance->get_config_errors())) {
    if (!isset($blockinstance->context)) {
        echo 'No block context found - has the block instance been deleted?';
    } else {
        // No visible IA block found with valid config, so skip any output.
        if (\has_capability('block/integrityadvocate:overview', $blockinstance->context)) {
            echo implode("<br />\n", $configerrors);
        }
    }
    $continue = false;
}
$continue && $debug && ia_mu::log(__FILE__ . "::Got \$blockinstance with apikey={$blockinstance->config->apikey}; appid={$blockinstance->config->appid}");

// Check site and course completion are set up.
$setuperrors = ia_mu::get_completion_setup_errors($course);
if ($setuperrors) {
    foreach ($setuperrors as $err) {
        echo get_string($err, INTEGRITYADVOCATE_BLOCK_NAME) . "<br/>\n";
    }
    $continue = false;
}

if ($continue) {
    // Check if module have been selected in config.
    $modules = block_integrityadvocate_get_course_ia_modules($courseid);
    if (is_string($modules)) {
        echo get_string($modules, INTEGRITYADVOCATE_BLOCK_NAME) . "<br/>\n";
        $continue = false;
    }
    $debug && ia_mu::log(basename(__FILE__)
                    . '::Got module count=' . (is_countable($modules) ? count($modules) : 0));
}

if ($continue) {
    if ($userid) {
        $debug && ia_mu::log(basename(__FILE__) . "::Got a \$userid={$userid} so show the single user IA results");
        require_once('overview-user.php');
    } else {
        $debug && ia_mu::log(basename(__FILE__) . '::Got no userid so show the course users IA results');
        require_once('overview-course.php');
    }
}

// Clean up vars no longer needed instead of polluting the global namespace.
unset($title, $debug);

echo $OUTPUT->container_end();
echo $OUTPUT->footer();
