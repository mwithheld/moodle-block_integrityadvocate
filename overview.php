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

namespace block_integrityadvocate;

use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Output as ia_output;
use block_integrityadvocate\Utility as ia_u;

require_once(\dirname(__FILE__, 3) . '/config.php');
// Make sure we have this blocks constants defined.
require_once(__DIR__ . '/lib.php');

\require_login();

// Bool flag to tell child pages the include is legit.
\define('INTEGRITYADVOCATE_OVERVIEW_INTERNAL', true);

$debug = false;
$fxn = __FILE__;

// Gather form data.
// Used for the APIkey and AppId.
$blockinstanceid = \required_param('instanceid', \PARAM_INT);
// Used for all pages.
$courseid = \required_param('courseid', \PARAM_INT);
// Used for overview-user page.
$userid = \optional_param('userid', 0, \PARAM_INT);
// Used for overview-module page.
$moduleid = \optional_param('moduleid', 0, \PARAM_INT);

// Determine course and course context.
if (
    empty($courseid) || (int)$courseid === (int)\SITEID || ia_u::is_empty($course = \get_course($courseid))
    || ia_u::is_empty($coursecontext = \context_course::instance($courseid, MUST_EXIST))
) {
    throw new \InvalidArgumentException('Invalid $courseid specified');
}
$debug && \debugging($fxn . "::Got courseid={$course->id}");

// Check the current USER is logged in to the correct context.
switch (true) {
    case ($userid):
        // throw new \InvalidArgumentException('The overview-user page is deprecated');
    case ($userid):
        $debug && debugging(__FILE__ . '::Request is for overview_user page. Got $userid=' . $userid);
        break;
    case ($courseid && $moduleid):
        $debug && \debugging($fxn . '::Got a moduleid=' . ia_u::var_dump($moduleid, true));
        [$course, $cm] = \get_course_and_cm_from_cmid($moduleid);
        \require_login($courseid, false, $cm);
        // We will check the module is valid in overview-module.php.
        $modulecontext = \context_module::instance($moduleid);
        $PAGE->set_context($modulecontext);
        break;
    case ($courseid):
        \require_login($courseid, false);
        $coursecontext = \context_course::instance($courseid);
        $PAGE->set_context($coursecontext);
        break;
    default:
        throw new \InvalidArgumentException('Failed to figure out which overview to show:' . __LINE__);
}

// Params are used to build the current page URL.  These params are used for all overview pages.
$params = [
    'instanceid' => $blockinstanceid,
    'courseid' => $courseid,
];

// Set up which overview page we should produce: -user, -module, or -course.
// Specific sanity/security checks for each one are included in each file.
switch (true) {
    case ($userid):
        // throw new \InvalidArgumentException('The overview-user page is deprecated');
    case ($userid):
        $debug && debugging(__FILE__ . '::Request is for overview_user page. Got $userid=' . $userid);
        $pageslug = 'overview-user';
        $params += [
            'userid' => $userid,
        ];
        break;
    case ($courseid && $moduleid):
        $debug && \debugging($fxn . '::Request is for OVERVIEW_MODULE page. Got $moduleid=' . $moduleid);
        $pageslug = 'overview-module';

        // For now, assume the moduleid is valid. We will check it in overview-module.
        $context = \context_module::instance($moduleid);
        $PAGE->set_context($context);

        [$course, $cm] = \get_course_and_cm_from_cmid($moduleid);
        $PAGE->set_cm($cm, $COURSE);

        // Note this operation does not replace existing values ref https://stackoverflow.com/a/7059731.
        $params += [
            'moduleid' => $moduleid,
        ];
        break;
    case ($courseid):
        $debug && \debugging($fxn . '::Request is for overview_course (any version) page. Got $moduleid=' . $moduleid);
        $pageslug = 'overview-course';

        $PAGE->set_context($coursecontext);
        break;
    default:
        throw new \InvalidArgumentException('Failed to figure out which overview to show:' . __LINE__);
}
$debug && \debugging($fxn . '::Built params=' . ia_u::var_dump($params));

// All pages require the blockinstance.
$blockinstance = \block_instance_by_id($blockinstanceid);
// Sanity check that we got an IA block instance.
if (ia_u::is_empty($blockinstance) || !($blockinstance instanceof \block_integrityadvocate) || !isset($blockinstance->context) || empty($blockcontext = $blockinstance->context)) {
    $debug && \debugging($fxn . '::Got $blockinstance=' . \var_export($blockinstance, true) . '; context=' . \var_export($blockcontext ?? '', true));
    throw new \InvalidArgumentException("Blockinstanceid={$blockinstanceid} is not an instance of block_integrityadvocate");
}

// Set up page parameters. All $PAGE setup must be done before output.
$PAGE->set_pagelayout('report');
$PAGE->set_course($course);
$PAGE->add_body_class(INTEGRITYADVOCATE_BLOCK_NAME . '-' . $pageslug);
$PAGE->requires->data_for_js('M.block_integrityadvocate', ['appid' => $blockinstance->config->appid, 'courseid' => $courseid, 'moduleid' => $moduleid], true);

// Used to build the page URL.
$baseurl = new \moodle_url('/blocks/' . INTEGRITYADVOCATE_SHORTNAME . '/overview.php', $params);
$PAGE->set_url($baseurl);

$pagename = \get_string(\str_replace('-', '_', $pageslug), INTEGRITYADVOCATE_BLOCK_NAME);
$pageheading = \format_string($course->fullname) . ': ' . $pagename;
$courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

switch (true) {
    case ($userid):
        // throw new \InvalidArgumentException('The overview-user page is deprecated');
        $contentheading = \format_string($modname);
        break;
    case ($courseid && $moduleid):
        $debug && \debugging($fxn . '::Set content header to module name');
        // Add module breadcrumb.
        $modname = \format_string($cm->name);
        $contentheading = \format_string($modname);
        break;
    case ($courseid):
        $debug && \debugging($fxn . '::Set content header to course fullname');
        $contentheading = \format_string($course->fullname);
        break;
    default:
        throw new \InvalidArgumentException('Failed to figure out which overview to show:' . __LINE__);
}

$PAGE->set_title($pageheading);
$PAGE->set_heading($pageheading);

$PAGE->navbar->add(\format_string($course->fullname), $courseurl);
if (!$userid && $moduleid) {
    // Add module breadcrumb.
    $modname = \format_string($cm->name);
    $PAGE->navbar->add($modname, new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $moduleid]));
}
$PAGE->navbar->add($pagename);

// Output starts here.
// All header parts like JS, CSS must be above this.
echo $OUTPUT->header();
$headingjs = 'document.getElementById("iframelaunch").src=document.getElementById("iframelaunch").src;e.preventDefault();return false';
echo $OUTPUT->heading($contentheading . '&nbsp;' . $OUTPUT->image_icon('i/reload', \get_string('refresh'), 'moodle', ['onclick' => $headingjs]), 2);
echo $OUTPUT->container_start(INTEGRITYADVOCATE_BLOCK_NAME);

// Gather capabilities for later use.
$hascapabilityoverview = \has_capability('block/integrityadvocate:overview', $blockcontext);
$hascapabilityselfview = \has_capability('block/integrityadvocate:selfview', $blockcontext);

// Check for errors that mean we should not show any overview page.
switch (true) {
    case ($configerrors = $blockinstance->get_config_errors()):
        $debug && \debugging($fxn . '::No visible IA block found with valid config; $configerrors=' . ia_u::var_dump($configerrors));
        // Instructors see the errors on-screen.
        if ($hascapabilityoverview) {
            \core\notification::error(\implode(ia_output::BRNL, $configerrors ?? ['Something went wrong getting config errors']));
        }
        break;

    case ($setuperrors = ia_mu::get_completion_setup_errors($course)):
        $debug && \debugging($fxn . '::Got completion setup errors; $setuperrors=' . ia_u::var_dump($setuperrors));
        foreach ($setuperrors as $err) {
            echo \get_string($err, INTEGRITYADVOCATE_BLOCK_NAME), ia_output::BRNL;
        }
        break;

    case (!$hascapabilityoverview && !$hascapabilityselfview):
        $msg = 'No permissions to see anything in the block';
        $debug && \debugging($fxn . "::{$msg}");
        \core\notification::error($msg . ia_output::BRNL);
        break;

    case (\is_string($modules = block_integrityadvocate_get_course_ia_modules($courseid))):
        $msg = \get_string($modules, INTEGRITYADVOCATE_BLOCK_NAME);
        $debug && \debugging($fxn . "::{$msg}");
        \core\notification::error($msg . ia_output::BRNL);
        break;

    default:
        $debug && \debugging($fxn . "::Got \$blockinstance with apikey={$blockinstance->config->apikey}; appid={$blockinstance->config->appid}");

        // Open the requested overview page.
        require_once($pageslug . '.php');
}

echo $OUTPUT->container_end();

// Show version, appid, blockid.
echo $blockinstance->get_footer();

// On the module overview page, show the "Back to course" button.
if ($courseid && $moduleid) {
    echo ia_output::get_button_overview_course($blockinstance);
}
echo $OUTPUT->single_button($courseurl, \get_string('btn_backto_course', INTEGRITYADVOCATE_BLOCK_NAME));

// Moodle page footer.
echo $OUTPUT->footer();
