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

namespace block_integrityadvocate;

use block_integrityadvocate\Api as ia_api;
use block_integrityadvocate\Logger as Logger;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Output as ia_output;
use block_integrityadvocate\Status as ia_status;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

// Security check - this file must be included from overview.php.
defined('INTEGRITYADVOCATE_OVERVIEW_INTERNAL') || die();

$debug = false || Logger::do_log_for_function(INTEGRITYADVOCATE_BLOCK_NAME . '\\' . basename(__FILE__));
$debug && Logger::log(__FILE__ . '::Started with $moduleid=' . $moduleid);

// The "user" here is always the current $USER;
$userid = $USER->id;

// Check all requirements.
switch (true) {
    case (!FeatureControl::OVERVIEW_MODULE):
        throw new Exception('This feature is disabled');
    case (empty($blockinstanceid)):
        throw new \InvalidArgumentException('$blockinstanceid is required');
    case (empty($courseid) || ia_u::is_empty($course) || ia_u::is_empty($coursecontext)) :
        throw new \InvalidArgumentException('$courseid, $course and $coursecontext are required');
    case (($moduleid = \required_param('moduleid', PARAM_INT)) < 1):
        // This is only an optional param in overview.php.
        // The above line throws an error if $moduleid is not passed as an integer.
        // But we get here if $moduleid is zero or negative.
        throw new \InvalidArgumentException("Invalid moduleid={$moduleid}");
    case(!empty(\require_capability('block/integrityadvocate:overview', $coursecontext))):
        // The above line throws an error if the current user is not a teacher, so we should never get here.
        $debug && Logger::log(__FILE__ . '::Checked required capability: overview');
        break;
    case(intval(ia_mu::get_courseid_from_cmid($moduleid)) !== intval($courseid)):
        throw new \InvalidArgumentException("Moduleid={$moduleid} is not in the course with id={$courseid}; \$get_courseid_from_cmid=" . ia_mu::get_courseid_from_cmid($moduleid));
    case(!($cm = \get_course_and_cm_from_cmid($moduleid, null, $courseid, $userid)[1])):
        // The above line throws an error if $overrideuserid cannot access the module.
        // But we get here if $cm is empty.
        throw new \InvalidArgumentException('Invalid $cm found');
    case(empty($modulecontext = $blockinstance->context->get_parent_context())):
        throw new \InvalidArgumentException('Failed to find a valid parent context');
    case($modulecontext->contextlevel != \CONTEXT_MODULE):
        // Must be enrolled in the module to see this page.
        throw new \InvalidArgumentException("The passed-in moduleid={$moduleid} is not at the module context");
    case(!empty(\require_capability('block/integrityadvocate:overview', $modulecontext))):
        // The above line throws an error if the current user is not enrolled as an instructor in the module.
        // Note this capability check is on the parent, not the block instance.
        break;
    default:
        $debug && Logger::log(__FILE__ . '::All requirements are met');
}

// Show basic module info at the top.  Adapted from course/classes/output/course_module_name.php:export_for_template().
echo \html_writer::start_tag('div', ['class' => \INTEGRITYADVOCATE_BLOCK_NAME . '_overview_module_moduleinfo']);
echo $PAGE->get_renderer('core', 'course')->course_section_cm_name_title($cm);
echo \html_writer::end_tag('div');

// Wraps the main content for this page.  The div must be closed at the end of this script.
echo \html_writer::start_tag('div', ['class' => \INTEGRITYADVOCATE_BLOCK_NAME . '_overview_participant_container']);

// Get IA sessions associated with this course module for all participants.
$participantsessions = ia_api::get_participantsessions($blockinstance->config->apikey, $blockinstance->config->appid, $courseid, $moduleid);
$debug && Logger::log(__FILE__ . '::Got count($participantsessions)=' . ia_u::count_if_countable($participantsessions));
// Disabled on purpose: echo 'Done the API call; participantsessions=<PRE>' . ia_u::var_dump($participantsessions, true) . '</PRE>';.

if ($participantsessions) {
    // Group data by participant.
    $participants = [];
    foreach ($participantsessions as $session) {
        $participantidentifier = $session->participant->participantidentifier;
        if (!isset($participants[$participantidentifier])) {
            $participants[$participantidentifier] = $session->participant;
            $participants[$participantidentifier]->sessions = array();
        }
        $participants[$participantidentifier]->sessions[$session->id] = $session;
    }

    // Should we show override stuff?
    $showoverride = FeatureControl::SESSION_STATUS_OVERRIDE && $hascapability_override;
    $debug && Logger::log(__FILE__ . "::Got \$showoverride={$showoverride}");

    $prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_participant';

    // The classes here are for DataTables styling ref https://datatables.net/examples/styling/index.html .
    echo '<table id="' . $prefix . '_table" class="stripe order-column hover display">';
    $tr = '<tr>';
    $tr_end = '</tr>';
    echo '<thead>';
    $tr_header = [$tr];
    $tr_header[] = \html_writer::tag('th', \get_string('user'), ['class' => "{$prefix}_session_activitymodule"]);
    $tr_header[] = \html_writer::tag('th', \get_string('session_start', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_start"]);
    $tr_header[] = \html_writer::tag('th', \get_string('session_end', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_end"]);

    $tr_header[] = \html_writer::tag('th', \get_string('session_status', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_status"]);
    $tr_header[] = \html_writer::tag('th', \get_string('photo', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_photo"]);
    $tr_header[] = \html_writer::tag('th', \get_string('flags', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_flags"]);

    if ($showoverride) {
        $tr_header[] = \html_writer::tag('th', \get_string('session_overridedate', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_overridedate"]);
        $tr_header[] = \html_writer::tag('th', \get_string('session_overridestatus', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_overridestatus"]);

        $tr_header[] = \html_writer::tag('th', \get_string('session_overridename', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_overridename"]);
        $tr_header[] = \html_writer::tag('th', \get_string('session_overridereason', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_overridereason"]);
    }
    $tr_header[] = $tr_end;
    echo implode('', $tr_header) . '</thead><tbody>';
    echo $tr;

    $pictureparams = ['size' => 35, 'courseid' => $courseid, 'includefullname' => true];
    foreach ($participants as $p) {
        $debuginfo = "participantidentifier={$p->participantidentifier}";
        //echo '<PRE>' . ia_u::var_dump($p) . '</PRE><hr>' . ia_output::BRNL;
        // Column=User.
        $user = ia_mu::get_user_as_obj($p->participantidentifier);
        echo \html_writer::tag('td', ia_mu::get_user_picture($user, $pictureparams), ['data-sort' => fullname($user), 'class' => "{$prefix}_user"]);

        $session = $p->get_latest_module_session($moduleid);

        // Column=session_start.
        $sessionstart = ia_u::is_unixtime_past($session->start) ? $session->start : '';
        echo \html_writer::tag('td', ($sessionstart ? \userdate($session->start) : ''), ['data-sort' => $session->start, 'class' => "{$prefix}_session_start"]);

        // Column=session_end.
        $sessionend = ia_u::is_unixtime_past($session->end) ? $session->end : '';
        echo \html_writer::tag('td', ($sessionend ? \userdate($sessionend) : ''), ['data-sort' => $sessionend, 'class' => "{$prefix}_session_end"]);

        $hasoverride = $session->has_override();
        $debug && Logger::log(__FILE__ . "::{$debuginfo}:Got \$hasoverride={$hasoverride}");

        // Column=session_status.
        $latestmodulesession = $p->get_latest_module_session($moduleid);
        $canoverride = false;
        $debug && Logger::log(__FILE__ . "::{$debuginfo}:Got \$canoverride={$canoverride}");
        $overrideclass = $canoverride ? " {$prefix}_session_overrideui" : '';
        // If overridden, show the overridden status.
        if ($hasoverride) {
            // If overridden as Valid, add text "(Overridden)".
            echo \html_writer::tag('td', ia_status::get_status_lang($session->overridestatus) . ' ' . \get_string('overridden', INTEGRITYADVOCATE_BLOCK_NAME) . ia_output::get_button_overview($blockinstance, $p->participantidentifier), ['class' => "{$prefix}_session_status {$prefix}_session_overridden" . $overrideclass]);
        } else {
            echo \html_writer::tag('td', ia_status::get_status_lang($session->status) . ia_output::get_button_overview($blockinstance, $p->participantidentifier), ['class' => "{$prefix}_session_status" . $overrideclass]);
        }

        // Column=session_photo.
        echo \html_writer::tag('td', ($session->participantphoto ? \html_writer::img($session->participantphoto, fullname(ia_mu::get_user_as_obj($p->participantidentifier)), ['width' => 85, 'class' => "{$prefix}_session_jquimodal"]) : ''), ['class' => "{$prefix}_session_photo"]);

        // Column=session_flags.
        if (empty($session->flags)) {
            echo \html_writer::tag('td', '', ['data-sort' => $sessionend, 'class' => "{$prefix}_session_flags"]);
        } else {
            $flags = array_values($session->flags);
            usort($flags, array('\\' . INTEGRITYADVOCATE_BLOCK_NAME . '\Utility', 'sort_by_created_desc'));
            $flagoutput = [];
            foreach ($session->flags as $f) {
                // Omit b/c this is not very useful: $flagoutput .= htmlentities($f->flagtypename) . Output::BRNL;.
                $flagoutput[] = htmlentities($f->comment, ENT_QUOTES, 'UTF-8') . Output::BRNL;
                $capturedate = (isset($f->capturedate) ?: '');
                if (isset($f->capturedata) && ($f->capturedata != $session->participantphoto)) {
                    $flagoutput[] = \html_writer::img($f->capturedata, $capturedate, ['width' => 85, 'class' => "{$prefix}_session_jquimodal"]);
                }
            }
            // Column=session_flags.
            echo \html_writer::tag('td', implode('', $flagoutput), ['class' => "{$prefix}_session_flags"]);
        }

        // Instructor: If overridden, show the override info.
        if ($showoverride) {
            $overridedate = ia_u::is_unixtime_past($session->overridedate) ? $session->overridedate : '';

            // Column=session_overridedate.
            echo \html_writer::tag('td', ($hasoverride ? \userdate($overridedate) : ''), ['class' => "{$prefix}_session_overridedate"]);
            // Column=session_overridestatus - show the *original* status.
            echo \html_writer::tag('td', ($hasoverride ? ia_status::get_status_lang($session->status) : ''), ['class' => "{$prefix}_session_overridestatus"]);
            // Column=session_overridename.
            if (isset($session->overridelmsuserid) && ($overrideuser = ia_mu::get_user_as_obj($session->overridelmsuserid))) {
                $overrideusername = ia_mu::get_user_picture($overrideuser, ['size' => 35, 'courseid' => $courseid, 'includefullname' => true]);
            } else {
                $overrideusername = '';
            }
            // Column=session_overridename.
            echo \html_writer::tag('td', ($hasoverride ? $overrideusername : ''), ['class' => "{$prefix}_session_overridename"]);
            // Column=session_overridereason.
            echo \html_writer::tag('td', ($hasoverride ? htmlspecialchars($session->overridereason, ENT_QUOTES, 'UTF-8') : ''), ['class' => "{$prefix}_session_overridereason"]);

            echo $tr_end;
        }
    }
    echo '</tbody>';
    echo "<tfoot>{$tr_header}</tfoot>";
    echo '</table>';
    // Used as a JQueryUI popup to show the user picture.
    echo '<div id="dialog"></div>';
}
// Close the participant_container.
echo \html_writer::end_tag('div');
