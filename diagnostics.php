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
 * IntegrityAdvocate block diagnostics page
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

use block_integrityadvocate\Output as ia_output;
use block_integrityadvocate\Utility as ia_u;

// Include required files.
require_once(\dirname(__FILE__, 3) . '/config.php');
// Make sure we have this blocks constants defined.
require_once(__DIR__ . '/lib.php');

\require_login();

$debug = false;
$fxn = __FILE__;

// Gather form data.
// Used for the APIkey and AppId.
$blockinstanceid = \required_param('instanceid', \PARAM_INT);
// Used for all pages.
$courseid = \required_param('courseid', \PARAM_INT);

// Params are used to build the current page URL.  These params are used for all overview pages.
$params = [
    'instanceid' => $blockinstanceid,
    'courseid' => $courseid,
];

// Determine course and course context.
if (
    empty($courseid) || (int)$courseid === (int)\SITEID || ia_u::is_empty($course = \get_course($courseid))
    || ia_u::is_empty($coursecontext = \context_course::instance($courseid, MUST_EXIST))
) {
    throw new \InvalidArgumentException('Invalid $courseid specified');
}
$debug && \debugging($fxn . "::Got courseid={$course->id}");

// Check the current USER is logged in *to the course*.
\require_login($course, false);

$pageslug = 'diagnostics';

$PAGE->set_context($coursecontext);
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

// Used to build the page URL.
$baseurl = new \moodle_url('/blocks/' . INTEGRITYADVOCATE_SHORTNAME . '/diagnostics.php', $params);
$PAGE->set_url($baseurl);

$pagename = \get_string('pluginname', INTEGRITYADVOCATE_BLOCK_NAME) . ' ' . \get_string(\str_replace('-', '_', $pageslug), INTEGRITYADVOCATE_BLOCK_NAME);
$title = \format_string($course->fullname) . ': ' . $pagename;
$courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add(\format_string($course->fullname), $courseurl);
$PAGE->navbar->add($pagename);

// Start page output.
// All header parts like JS, CSS must be above this.
echo $OUTPUT->header();
echo $OUTPUT->container_start(INTEGRITYADVOCATE_BLOCK_NAME);

// Security: Check capability.
\require_capability('block/integrityadvocate:diagnostics', $blockcontext);

$manager = new diagnostics_manager();
$diagnosticresults = $manager->do_diagnostics($courseid);
$debug && \debugging($fxn . '::Got diagnosticresults=' . ia_u::var_dump($diagnosticresults));

$table = new \html_table();
$table->data = [];
$table->head  = [
    \get_string('status'),
    \get_string('check'),
    \get_string('summary'),
];
$table->colclasses = [
    'rightalign status',
    'leftalign check',
    'leftalign summary',
];
$table->id = INTEGRITYADVOCATE_BLOCK_NAME . '_diagnostics';
$table->attributes = ['class' => 'admintable generaltable'];
$table->data = [];

$senddisabled = false;
foreach ($diagnosticresults as $result) {
    $debug && \debugging($fxn . '::Looking at $result=' . ia_u::var_dump($result));
    $table->data[] = [$OUTPUT->check_result($result), $result->get_summary(), $result->get_details()];
}

echo \html_writer::table($table);

echo $OUTPUT->container_end();

// Show version, appid, blockid.
echo $blockinstance->get_footer();

echo ia_output::get_button_overview_course($blockinstance);

echo $OUTPUT->single_button($courseurl, \get_string('btn_backto_course', INTEGRITYADVOCATE_BLOCK_NAME));

// Moodle page footer.
echo $OUTPUT->footer();
