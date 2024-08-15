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

namespace block_integrityadvocate;

use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Output as ia_output;
use block_integrityadvocate\Utility as ia_u;

// Include required files.
require_once(\dirname(__FILE__, 3) . '/config.php');
// Make sure we have this blocks constants defined.
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/notes/lib.php');
require_once($CFG->libdir . '/tablelib.php');

/** @var int How many users per page to show by default. */
const INTEGRITYADVOCATE_DEFAULT_PAGE_SIZE = 10;

// Bool flag to tell the overview-course.php and overview-user.php pages the include is legit.
\define('INTEGRITYADVOCATE_OVERVIEW_INTERNAL', true);

$debug = false;

// Suppress debug notice that we have not done PAGE->set_url().
$debug && ($debugbackup = $CFG->debug);
$debug && ($CFG->debug = null);
$debug && \debugging(\basename(__FILE__) . "::Started with \$PAGE->url={$PAGE->url}");
$debug && ($CFG->debug = $debugbackup);

\require_login();

// Gather form data.
// Used for the APIkey and AppId.
$blockinstanceid = \required_param('instanceid', PARAM_INT);
// Used for all overview pages.
$courseid = \required_param('courseid', PARAM_INT);
// Used for overview-user page.
$userid = \optional_param('userid', 0, PARAM_INT);
// Used for overview-module page.
$moduleid = \optional_param('moduleid', 0, PARAM_INT);

// Params are used to build the current page URL.  These params are used for all overview pages.
$params = [
    'instanceid' => $blockinstanceid,
    'courseid' => $courseid,
];

// Determine course and course context.
if (empty($courseid) || ia_u::is_empty($course = \get_course($courseid)) || ia_u::is_empty($coursecontext = \CONTEXT_COURSE::instance($courseid, MUST_EXIST))) {
    throw new \InvalidArgumentException('Invalid $courseid specified');
}
$debug && \debugging("Got courseid={$course->id}");

// Check the current USER is logged in *to the course*.
\require_login($course, false);

// Set up which overview page we should produce: -user, -module, or -course.
// Specific sanity/security checks for each one are included in each file.
switch (true) {
    case ($userid):
        $debug && \debugging(__FILE__ . '::Request is for overview_user page. Got $userid=' . $userid);
        $requestedpage = 'overview-user';
        $params += [
            'userid' => $userid,
        ];
        break;
    case ($courseid && $moduleid):
        $debug && \debugging(__FILE__ . '::Request is for OVERVIEW_MODULE v1 page. Got $moduleid=' . $moduleid);
        $requestedpage = 'overview-module';

        // For now, assume the moduleid is valid. We will check it in overview-module.
        $context = \context_module::instance($moduleid);
        $PAGE->set_context($context);

        // Note this operation does not replace existing values ref https://stackoverflow.com/a/7059731.
        $params += [
            'moduleid' => $moduleid,
        ];
        break;
    case ($courseid):
        $debug && \debugging(__FILE__ . '::Request is for overview_course (any version) page. Got $moduleid=' . $moduleid);
        $requestedpage = 'overview-course';

        $PAGE->set_context($coursecontext);

        // The Moodle Participants table wants lots of params.
        $groupid = \optional_param('group', 0, PARAM_ALPHANUMEXT);
        $currpage = \optional_param('currpage', 0, PARAM_INT);
        $perpage = \optional_param('perpage', INTEGRITYADVOCATE_DEFAULT_PAGE_SIZE, PARAM_INT);

        // To prevent two role= params in the querystring, only set it if not specified.
        // Find the role to display, defaulting to students.  0 means all enrolled users.
        // To use the default student role, use second param=ia_mu::get_default_course_role($coursecontext).
        $roleid = \optional_param('role', -1, PARAM_INT);
        if ($roleid < 0) {
            $roleid = ia_mu::get_default_course_role($coursecontext);
            $params += [
                'role' => $roleid,
            ];
        }

        // We will add these params to the URL later.
        $params += [
            'group' => $groupid,
            'perpage' => $perpage,
            'currpage' => $currpage,
        ];
        break;
    default:
        throw new \InvalidArgumentException('Failed to figure out which overview to show');
}
$debug && \debugging('Build params=' . ia_u::var_dump($params));

// All overview pages require the blockinstance.
$blockinstance = \block_instance_by_id($blockinstanceid);
// Sanity check that we got an IA block instance.
if (ia_u::is_empty($blockinstance) || !($blockinstance instanceof \block_integrityadvocate) || !isset($blockinstance->context) || empty($blockcontext = $blockinstance->context)) {
    throw new \InvalidArgumentException("Blockinstanceid={$blockinstanceid} is not an instance of block_integrityadvocate=" .
        \var_export($blockinstance, true) . '; context=' . \var_export($blockcontext, true));
}

// Set up page parameters.
$PAGE->set_pagelayout('report');
$PAGE->set_course($course);
$PAGE->add_body_class(INTEGRITYADVOCATE_BLOCK_NAME . '-' . $requestedpage);
$PAGE->requires->data_for_js('M.block_integrityadvocate', ['appid' => $blockinstance->config->appid, 'courseid' => $courseid, 'moduleid' => $moduleid], true);

// Used to build the page URL.
$baseurl = new \moodle_url('/blocks/' . INTEGRITYADVOCATE_SHORTNAME . '/overview.php', $params);
$PAGE->set_url($baseurl);

$page = \get_string(\str_replace('-', '_', $requestedpage), INTEGRITYADVOCATE_BLOCK_NAME);
$title = format_string($course->fullname) . ': ' . $page;
$courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add(format_string($course->fullname), $courseurl);
$PAGE->navbar->add($page);

// Start page output.
// All header parts like JS, CSS must be above this.
echo $OUTPUT->header();
$headingjs = 'document.getElementById("iframelaunch").src=document.getElementById("iframelaunch").src;e.preventDefault();return false';
echo $OUTPUT->heading($title . '&nbsp;' . $OUTPUT->image_icon('i/reload', \get_string('refresh'), 'moodle', ['onclick' => $headingjs]), 2);
echo $OUTPUT->container_start(INTEGRITYADVOCATE_BLOCK_NAME);

// Gather capabilities for later use.
$hascapabilityoverview = \has_capability('block/integrityadvocate:overview', $blockcontext);
$hascapabilityselfview = \has_capability('block/integrityadvocate:selfview', $blockcontext);

// Check for errors that mean we should not show any overview page.
switch (true) {
    case ($configerrors = $blockinstance->get_config_errors()):
        $debug && \debugging(__FILE__ . '::No visible IA block found with valid config; $configerrors=' . ia_u::var_dump($configerrors));
        // Instructors see the errors on-screen.
        if ($hascapabilityoverview) {
            \core\notification::error(\implode(ia_output::BRNL, $configerrors));
        }
        break;

    case($setuperrors = ia_mu::get_completion_setup_errors($course)):
        $debug && \debugging(__FILE__ . '::Got completion setup errors; $setuperrors=' . ia_u::var_dump($setuperrors));
        foreach ($setuperrors as $err) {
            echo get_string($err, INTEGRITYADVOCATE_BLOCK_NAME), ia_output::BRNL;
        }
        break;

    case(!$hascapabilityoverview && !$hascapabilityselfview):
        $msg = 'No permissions to see anything in the block';
        $debug && \debugging(__FILE__ . "::{$msg}");
        \core\notification::error($msg . ia_output::BRNL);
        break;

    case (\is_string($modules = block_integrityadvocate_get_course_ia_modules($courseid))):
        $msg = get_string($modules, INTEGRITYADVOCATE_BLOCK_NAME);
        $debug && \debugging(__FILE__ . "::{$msg}");
        \core\notification::error($msg . ia_output::BRNL);
        break;

    default:
        $debug && \debugging(__FILE__ . "::Got \$blockinstance with apikey={$blockinstance->config->apikey}; appid={$blockinstance->config->appid}");

        // Open the requested overview page.
        require_once($requestedpage . '.php');
}

echo $OUTPUT->container_end();
// On the module overview page, show the "Back to course" button.
if ($courseid && $moduleid) {
    echo ia_output::get_button_overview_course($blockinstance);
}
echo $OUTPUT->single_button($courseurl, get_string('btn_backto_course', INTEGRITYADVOCATE_BLOCK_NAME));
echo $OUTPUT->footer();
