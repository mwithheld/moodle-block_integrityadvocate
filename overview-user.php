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
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Output as ia_output;
use block_integrityadvocate\Status as ia_status;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

// Security check - this file must be included from overview.php.
defined('INTEGRITYADVOCATE_OVERVIEW_INTERNAL') or die();

// Sanity checks.
if (empty($blockinstanceid)) {
    throw new InvalidArgumentException('$blockinstanceid is required');
}
if (empty($courseid) || ia_u::is_empty($course) || ia_u::is_empty($coursecontext)) {
    throw new \InvalidArgumentException('$courseid, $course and $coursecontext are required');
}

$userid = \required_param('userid', PARAM_INT);
$debug = false;
$debug && ia_mu::log(__FILE__ . '::Got param $userid=' . $userid);

$parentcontext = $blockinstance->context->get_parent_context();

// Note this capability check is on the parent, not the block instance.
if (\has_capability('block/integrityadvocate:overview', $parentcontext)) {
    // For teachersm allow access to any enrolled course user, even if not active.
    if (!\is_enrolled($parentcontext, $userid)) {
        throw new \Exception('That user is not in this course');
    }
} else if (\is_enrolled($parentcontext, $userid, 'block/integrityadvocate:selfview', true)) {
    if (intval($USER->id) !== $userid) {
        throw new \Exception("You cannot view other users: \$USER->id={$USER->id}; \$userid={$userid}");
    }
} else {
    throw new \Exception('No capabilities to view this course user');
}

$user = $DB->get_record('user', array('id' => $userid), '*', \MUST_EXIST);
$participant = ia_api::get_participant($blockinstance->config->apikey, $blockinstance->config->appid, $courseid, $userid);

// Show basic user info at the top.  Adapted from user/view.php.
echo \html_writer::start_tag('div', ['class' => \INTEGRITYADVOCATE_BLOCK_NAME . '_overview_user_userinfo']);
echo $OUTPUT->user_picture($user, ['size' => 35, 'courseid' => $courseid, 'includefullname' => true]);
echo \html_writer::end_tag('div');

if (ia_u::is_empty($participant)) {
    $msg = 'No participant found';
    if ($hascapability_overview) {
        $msg .= ': Double-check the APIkey and AppId for this block instance are correct';
    }
    echo $msg;
    $continue = false;
}

if (!INTEGRITYADVOCATE_FEATURE_OVERRIDE) {
    if ($continue) {
        // Display user basic info.
        if ($hascapability_override) {
            $noncekey = INTEGRITYADVOCATE_BLOCK_NAME . "_override_{$blockinstanceid}_{$participant->participantidentifier}";
            $debug && ia_mu::log(__FILE__ . "::About to nonce_set({$noncekey})");
            ia_mu::nonce_set($noncekey);
        }
        echo ia_output::get_participant_basic_output($blockinstance, $participant, true, false, $hascapability_override);

        // Display summary.
        echo ia_output::get_sessions_output($participant);
    }
} else {
    echo '<div id="overview_participant_container">';
    $continue = !empty($sessions = array_values($participant->sessions));

    if ($continue) {
        usort($sessions, array('\\' . INTEGRITYADVOCATE_BLOCK_NAME . '\Utility', 'sort_by_start_desc'));
        $modinfo = \get_fast_modinfo($courseid, -1);

        $prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_participant';

        // The classes here are for DataTables styling ref https://datatables.net/examples/styling/index.html .
        echo '<table id="' . $prefix . '_table" class="stripe order-column hover display">';
        $tr = '<tr>';
        $tr_end = '</tr>';
        echo '<thead>';
        $tr_header = $tr;
        $tr_header .= \html_writer::tag('td', \get_string('session_start', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_start"]);
        $tr_header .= \html_writer::tag('td', \get_string('session_end', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_end"]);

        $tr_header .= \html_writer::tag('td', \get_string('activitymodule'), ['class' => "{$prefix}_session_activitymodule"]);
        $tr_header .= \html_writer::tag('td', \get_string('session_status', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_status"]);
        $tr_header .= \html_writer::tag('td', \get_string('photo', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_photo"]);
        $tr_header .= \html_writer::tag('td', \get_string('flags', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_flags"]);
        if ($hascapability_override) {
            $tr_header .= \html_writer::tag('td', \get_string('override_view', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_viewdetails"]);
            $tr_header .= \html_writer::tag('td', \get_string('session_overridedate', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_overridedate"]);
            $tr_header .= \html_writer::tag('td', \get_string('session_overridestatus', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_overridestatus"]);
            $tr_header .= \html_writer::tag('td', \get_string('session_overridename', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_overridename"]);
            $tr_header .= \html_writer::tag('td', \get_string('session_overridereason', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_overridereason"]);
        }
        $tr_header .= $tr_end;
        echo "{$tr_header}</thead><tbody>";
        echo $tr;

        foreach ($sessions as $session) {
            switch (true) {
                case(ia_u::is_empty($session) || !isset($session->flags)):
                    $debug && ia_mu::log(__FILE__ . '::This session is empty or has no flags, so skip it');
                    continue;
                case(!isset($session->activityid) || !($cmid = $session->activityid)):
                    $debug && ia_mu::log(__FILE__ . 'This session has no activityid so skip it');
                    continue;
                case(!($courseid = ia_mu::get_courseid_from_cmid($cmid)) || intval($courseid) !== intval($session->participant->courseid)):
                    $debug && ia_mu::log(__FILE__ . "::This session belongs to courseid={$courseid} not matching participant->courseid={$session->participant->courseid}");
                    continue;
            }

            // Column=session_start.
            echo \html_writer::tag('td', ($session->start ? \userdate($session->start) : ''), ['data-sort' => $session->start, 'class' => "{$prefix}_session_start"]);
            // Column=session_end.
            $sessionend = ia_u::is_unixtime_past($session->end) ? $session->end : '';
            echo \html_writer::tag('td', \userdate($sessionend), ['data-sort' => $sessionend, 'class' => "{$prefix}_session_end"]);

            // Column=activitymodule.
            // We need the coursemodule so we can get info like the name from it.
            // We already know $cmid is valid and in this course.
            // This throws a moodle_exception if the item doesn't exist or is of wrong module name.
            // We do *not* use this block name for parameter 2 since it's the activity the block is attached to that matters.
            list($unused, $cm) = \get_course_and_cm_from_cmid($cmid, null, $courseid, $session->participant->participantidentifier);
            echo \html_writer::tag('td', \html_writer::tag('a', $cm->name, ['href' => $cm->url]), ['class' => "{$prefix}_activitymodule"]);

            $hasoverride = $session->has_override();
            // Temporary test data.
            if (true && $hasoverride = (bool) random_int(0, 1)) {
                $session->overridedate = random_int($session->end, time());
                $overrideints = array_keys(ia_status::get_overriddable());
                sort($overrideints);
                $session->overridestatus = random_int(min($overrideints), max($overrideints));
            }
            $overridedate = ia_u::is_unixtime_past($session->overridedate) ? $session->overridedate : '';

            // Column=session_status.
            $statushtml = '';
            // If overridden the overridden status.
            if ($hasoverride) {
                echo \html_writer::tag('td', ia_status::get_status_lang($session->overridestatus), ['class' => "{$prefix}_session_status overridden" . ($hascapability_override ? ' overrideui' : '')]);
            } else {
                echo \html_writer::tag('td', ia_status::get_status_lang($session->status), ['class' => "{$prefix}_session_status" . ($hascapability_override ? ' overrideui' : '')]);
            }

            // Column=session_photo.
            echo \html_writer::tag('td', ($session->participantphoto ? \html_writer::img($session->participantphoto, fullname(ia_mu::get_user_as_obj($participant->participantidentifier)), ['width' => 85, 'class' => "{$prefix}_session_jquimodal"]) : ''), ['class' => "{$prefix}_session_photo"]);

            // Column=session_flags.
            if (empty($session->flags)) {
                echo \html_writer::tag('td', '', ['data-sort' => $sessionend, 'class' => "{$prefix}_session_flags"]);
            } else {
                $flags = array_values($session->flags);
                usort($flags, array('\\' . INTEGRITYADVOCATE_BLOCK_NAME . '\Utility', 'sort_by_created_desc'));
                $flagoutput = '';
                foreach ($session->flags as $f) {
                    // This is not very useful: $flagoutput .= htmlentities($f->flagtypename) . Output::BRNL;.
                    $flagoutput .= htmlentities($f->comment) . Output::BRNL;
                    $capturedate = (isset($f->capturedate) ?: '');
                    if (isset($f->capturedata) && ($f->capturedata != $session->participantphoto)) {
                        $flagoutput .= \html_writer::img($f->capturedata, $capturedate, ['width' => 85, 'class' => "{$prefix}_session_jquimodal"]);
                    }
                }
                echo \html_writer::tag('td', $flagoutput, ['class' => "{$prefix}_session_flags"]);
            }

            // Instructor: If overridden, show the override info.
            if ($hascapability_override) {
                // Temporary test data.
                if (true && $hasoverride) {
                    $session->overridelmsuserid = 4;
                    $session->overridereason = ' Blah cuz I wanted to test this';
                }

                // Column=override_view.
                echo \html_writer::tag('td', '', ['data-sort' => $session->id, 'class' => "{$prefix}_session_overrideview" . ($hasoverride ? ' details-control' : '')]);
                // Column=session_overridedate.
                echo \html_writer::tag('td', ($hasoverride ? \userdate($overridedate) : ''), ['class' => "{$prefix}_session_overridedate"]);
                // Column=session_overridestatus - show the *original* status.
                echo \html_writer::tag('td', ($hasoverride ? ia_status::get_status_lang($session->status) : ''), ['class' => "{$prefix}_session_overridestatus"]);
                // Column=session_overridename.
                $overrideusername = isset($session->overridelmsuserid) ? $OUTPUT->user_picture(ia_mu::get_user_as_obj($session->overridelmsuserid), ['size' => 35, 'courseid' => $courseid, 'includefullname' => true]) : '';
                echo \html_writer::tag('td', ($hasoverride ? $overrideusername : ''), ['class' => "{$prefix}_session_overridename"]);
                // Column=session_overridereason.
                echo \html_writer::tag('td', ($hasoverride ? htmlspecialchars($session->overridereason) : ''), ['class' => "{$prefix}_session_overridereason"]);
            }
            echo $tr_end;
        }

        echo '</tbody>';
        echo "<tfoot>{$tr_header}</tfoot>";
        echo '</table>';
        echo '<div id="dialog"></div>';
    }
    echo '</div>';
}