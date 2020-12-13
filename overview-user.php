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
use block_integrityadvocate\Status as ia_status;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

// Security check - this file must be included from overview.php.
defined('INTEGRITYADVOCATE_OVERVIEW_INTERNAL') || die();

// Check all requirements.
switch (true) {
    case (!FeatureControl::OVERVIEW_USER_ORIGINAL && !FeatureControl::OVERVIEW_USER_LTI):
        throw new \Exception('This feature is disabled');
    default:
        $debug && Logger::log(__FILE__ . '::All requirements are met');
}
// This is only optional_param() in overview.php.
$userid = \required_param('userid', PARAM_INT);

$debug = false || Logger::do_log_for_function(INTEGRITYADVOCATE_BLOCK_NAME . '\\' . basename(__FILE__));
$debug && Logger::log(__FILE__ . '::Started with $userid=' . $userid);

$parentcontext = $blockcontext->get_parent_context();

// Note this capability check is on the parent, not the block instance.
if (\has_capability('block/integrityadvocate:overview', $parentcontext)) {
    // For teachers, allow access to any enrolled course user, even if not active.
    if (!\is_enrolled($parentcontext, $userid)) {
        throw new \Exception('That user is not in this course');
    }
} else if (\is_enrolled($parentcontext, $userid, 'block/integrityadvocate:selfview', true)) {
    // For Students to view their own stuff.
    if (intval($USER->id) !== $userid) {
        throw new \Exception("You cannot view other users: \$USER->id={$USER->id}; \$userid={$userid}");
    }
} else {
    throw new \Exception('No capabilities to view this course user');
}

// Control whether to carry on displaying output.
$continue = true;

// Get list of modules that use IA block so we can omit displaying those without.
$coursemodules = \block_integrityadvocate_get_course_ia_modules($course, array('configured' => 1));
if (!is_array($coursemodules)) {
    $continue = false;
}

$user = ia_mu::get_user_as_obj($userid);
if (ia_u::is_empty($user)) {
    $msg = "Failed to find a Moodle user with id={$userid}";
    Logger::log($fxn . '::' . $msg);
    throw new \Exception($msg);
}

if ($continue) {
    // Show basic user info at the top.  Adapted from user/view.php.
    echo \html_writer::start_tag('div', ['class' => \INTEGRITYADVOCATE_BLOCK_NAME . '_overview_user_userinfo']);
    echo $OUTPUT->user_picture($user, ['size' => 35, 'courseid' => $courseid, 'includefullname' => true]);
    echo \html_writer::end_tag('div');

    switch (true) {
        case(FeatureControl::OVERVIEW_USER_LTI):
            $debug && Logger::log(__FILE__ . '::Request is for OVERVIEW_USER_LTI');

            /**
             * Code here is adapted from https://gist.github.com/matthanger/1171921 .
             */
            $launch_url = INTEGRITYADVOCATE_BASEURL_LTI . INTEGRITYADVOCATE_LTI_PATH . '/Participant';

            $launch_data = [
                // Required for Moodle oauth_helper.
                'api_root' => $launch_url,
                // 2020Dec: launch_presentation_locale appears to be unused, LTIConsumer example was en-US.
                'launch_presentation_locale' => \current_language(),
                // 2020Dec: roles appears to be unused. 0 = admin; 3=learner.
                'roles' => ($hascapability_overview ? 0 : 3),
                // This should always be 1.
                'resource_link_id' => '1',
                // Who is requesting this info?.
                'user_id' => $USER->id,
                // Extra info to help identify this request to the remote side.  2020Dec: They appear to be unused.
                'tool_consumer_instance_description' => "site={$CFG->wwwroot}; course={$courseid}; blockinstanceid={$blockinstanceid}; moduleid={$moduleid}",
                'tool_consumer_instance_guid' => $blockinstanceid,
                'tool_consumer_blockversion' => get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version'),
                // LTI setup.
                'lti_message_type' => 'basic-lti-launch-request',
                'lti_version' => 'LTI-1p0',
                // OAuth 1.0 setup.
                'oauth_callback' => 'about:blank',
                'oauth_consumer_key' => $blockinstance->config->appid,
                'oauth_consumer_secret' => $blockinstance->config->apikey,
                'oauth_nonce' => uniqid('', true),
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => (new \DateTime())->getTimestamp(),
                'oauth_version' => '1.0',
                // Context info.
                'context_id' => $courseid,
                'context_label' => $COURSE->shortname,
                'context_title' => $COURSE->fullname,
            ];

            // Setup the LTI UI for one specific user.
            $launch_data['UserId'] = $userid;

            // We only need launch the LTI.
            // The request is signed using OAuth Core 1.0 spec: http://oauth.net/core/1.0/ .
            // Moodle's code does the same as the example at https://gist.github.com/matthanger/1171921 but with a bit more cleanup.
            require_once($CFG->libdir . '/oauthlib.php');
            $signature = (new \oauth_helper($launch_data))->sign('POST', $launch_url, $launch_data, urlencode($blockinstance->config->apikey) . '&');
            ?>
            <form id="ltiLaunchForm" name="ltiLaunchForm" method="POST" target="iframelaunch" style="display:none" action="<?php echo $launch_url; ?>">
                <?php foreach ($launch_data as $k => $v) { ?>
                    <input type="hidden" name="<?php echo $k; ?>" value="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>">
                <?php } ?>
                <input type="hidden" name="oauth_signature" value="<?php echo $signature; ?>">
                <button type="submit">Launch</button>
            </form>
            <iframe id="iframelaunch" name="iframelaunch" src="" style="width:100%;height:800px"></iframe>
            <script>document.getElementById("ltiLaunchForm").submit();</script>
            <?php
            break;
        default:
            $debug && Logger::log(__FILE__ . '::Request is for OVERVIEW_USER_ORIGINAL');

            $participant = ia_api::get_participant($blockinstance->config->apikey, $blockinstance->config->appid, $courseid, $userid, $blockinstance->instance->id);
            $debug && Logger::log(__FILE__ . "::For \$blockinstanct->config->apikey={$blockinstance->config->apikey}; \$blockinstance->config->appid={$blockinstance->config->appid}; \$courseid={$courseid}; \$userid={$userid}, got participant=" . ia_u::var_dump($participant));

            if (ia_u::is_empty($participant)) {
                echo get_string('error_curlnoremoteinfo', INTEGRITYADVOCATE_BLOCK_NAME);
                $continue = false;
            }

            echo \html_writer::start_tag('div', ['class' => \INTEGRITYADVOCATE_BLOCK_NAME . '_overview_participant_container']);
            $continue = isset($participant->sessions) && is_array($participant->sessions) && !empty($sessions = array_values($participant->sessions));

            if ($continue) {
                // Should we show override stuff?
                $showoverride = FeatureControl::SESSION_STATUS_OVERRIDE && $hascapability_override;
                $debug && Logger::log(__FILE__ . "::Got \$showoverride={$showoverride}");

                // Set a nonce into the server-side user session.
                // This means you can only do one override per user at a time.
                // Ref https://codex.wordpress.org/WordPress_Nonces for why it is a good idea to use nonces here.
                if ($showoverride) {
                    $noncekey = INTEGRITYADVOCATE_BLOCK_NAME . "_override_{$blockinstanceid}_{$participant->participantidentifier}";
                    $debug && Logger::log(__FILE__ . "::About to nonce_set({$noncekey})");
                    ia_mu::nonce_set($noncekey);
                }

                usort($sessions, array('\\' . INTEGRITYADVOCATE_BLOCK_NAME . '\Utility', 'sort_by_start_desc'));
                $prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_participant';

                // Build the override UI hidden to the page so we can just swap it in on click.
                if ($showoverride) {
                    $prefix_overrideform = INTEGRITYADVOCATE_BLOCK_NAME . '_override';
                    // Create a form for the override UI.
                    $overrideform = [];
                    $overrideform[] = \html_writer::start_tag('form', array('class' => $prefix_overrideform . '_form', 'style' => 'display:none'));
                    // Add the override status dropdown.
                    $overrideform[] = \html_writer::select(
                                    ia_status::get_overrides(),
                                    ' ' . $prefix_overrideform . '_select ' . $prefix_overrideform . '_status_select',
                                    null,
                                    array('' => 'choosedots'),
                                    array('class' => $prefix_overrideform . '_status_select', 'required' => true)
                    );
                    // Add the override reason textbox.
                    $overrideform[] = \html_writer::tag('input', '',
                                    array('class' => $prefix_overrideform . '_reason',
                                        'name' => $prefix_overrideform . '_reason',
                                        'maxlength' => 32,
                                        'required' => true
                    ));
                    // Add hidden fields needed for the AJAX call.
                    $overrideform[] = \html_writer::tag('input', '', array('type' => 'hidden', 'class' => $prefix_overrideform . '_targetuserid', 'name' => $prefix_overrideform . '_targetuserid', 'value' => $participant->participantidentifier));
                    $overrideform[] = \html_writer::tag('input', '', array('type' => 'hidden', 'class' => $prefix_overrideform . '_overrideuserid', 'name' => $prefix_overrideform . '_overrideuserid', 'value' => $USER->id));
                    $overrideform[] = \html_writer::tag('input', '', array('type' => 'hidden', 'class' => $prefix_overrideform . '_sesskey', 'name' => 'sesskey', 'value' => sesskey()));
                    // Add icons.
                    $overrideform[] = Output::add_icon('e/save', $prefix_overrideform, 'save');
                    $overrideform[] = Output::add_icon('i/loading', $prefix_overrideform, 'loading');
                    $overrideform[] = Output::add_icon('e/cancel', $prefix_overrideform, 'cancel');
                    // Close the form.
                    $overrideform[] = \html_writer::end_tag('form');
                    // Output the form we just built.
                    echo implode('', $overrideform);
                }

                // The classes here are for DataTables styling ref https://datatables.net/examples/styling/index.html .
                echo '<table id="', $prefix, '_table" class="stripe order-column hover display">';
                $tr = '<tr>';
                $tr_end = '</tr>';
                echo '<thead>';
                $tr_header = [$tr];
                $tr_header[] = \html_writer::tag('th', \get_string('session_start', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_start"]);
                $tr_header[] = \html_writer::tag('th', \get_string('session_end', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_end"]);
                $tr_header[] = \html_writer::tag('th', \get_string('activitymodule'), ['class' => "{$prefix}_session_activitymodule"]);

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
                $tr_header = implode('', $tr_header);
                echo "{$tr_header}</thead><tbody>";
                echo $tr;

                foreach ($sessions as $session) {
                    switch (true) {
                        case(ia_u::is_empty($session) || !isset($session->flags)):
                            $debug && Logger::log(__FILE__ . '::This session is empty or has no flags, so skip it');
                            continue 2;
                        case(!isset($session->activityid) || !($cmid = $session->activityid)):
                            $debug && Logger::log(__FILE__ . 'This session has no activityid so skip it');
                            continue 2;
                        case(!($courseid = ia_mu::get_courseid_from_cmid($cmid)) || intval($courseid) !== intval($session->participant->courseid)):
                            $debug && Logger::log(__FILE__ . "::This session belongs to courseid={$courseid} not matching participant->courseid={$session->participant->courseid}");
                            continue 2;
//            case(!in_array(intval($cmid), array_keys($coursemodules), true)):
//                $debug && Logger::log(__FILE__ . "::This session module (cmid={$cmid}) does not have the IA block attached, so skip it");
//                continue 2;
                    }

                    // Column=session_start.
                    $sessionstart = ia_u::is_unixtime_past($session->start) ? $session->start : '';
                    echo \html_writer::tag('td', ($sessionstart ? \userdate($session->start) : ''), ['data-sort' => $session->start, 'class' => "{$prefix}_session_start"]);
                    // Column=session_end.
                    $sessionend = ia_u::is_unixtime_past($session->end) ? $session->end : '';
                    echo \html_writer::tag('td', ($sessionend ? \userdate($sessionend) : ''), ['data-sort' => $sessionend, 'class' => "{$prefix}_session_end"]);

                    // Column=activitymodule.
                    // We need the coursemodule so we can get info like the name from it.
                    // We already know $cmid is valid and in this course.
                    // This throws a moodle_exception if the item doesn't exist or is of wrong module name.
                    // We do *not* use this block name for parameter 2 since it's the activity the block is attached to that matters.
                    $cm = \get_course_and_cm_from_cmid($cmid, null, $courseid, $session->participant->participantidentifier)[1];
                    echo \html_writer::tag('td', \html_writer::tag('a', $cm->name, ['href' => $cm->url]), ['data-cmid' => $cmid, 'class' => "{$prefix}_activitymodule"]);

                    $debuginfo = "name={$cm->name}; cmid={$cmid}";
                    $hasoverride = $session->has_override();
                    $debug && Logger::log(__FILE__ . "::{$debuginfo}:Got \$hasoverride={$hasoverride}");

                    // Column=session_status.
                    $latestmodulesession = $participant->get_latest_module_session($cmid);
                    $canoverride = $showoverride && $latestmodulesession && ($session->id == $latestmodulesession->id);
                    $debug && Logger::log(__FILE__ . "::{$debuginfo}:Got \$canoverride={$canoverride}");
                    $overrideclass = $canoverride ? " {$prefix}_session_overrideui" : '';
                    // If overridden, show the overridden status.
                    if ($hasoverride) {
                        // If overridden as Valid, add text "(Overridden)".
                        echo \html_writer::tag('td', ia_status::get_status_lang($session->overridestatus) . ' ' . \get_string('overridden', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_session_status {$prefix}_session_overridden" . $overrideclass]);
                    } else {
                        echo \html_writer::tag('td', ia_status::get_status_lang($session->status), ['class' => "{$prefix}_session_status" . $overrideclass]);
                    }

                    // Column=session_photo.
                    echo \html_writer::tag('td', ($session->participantphoto ? \html_writer::img($session->participantphoto, fullname(ia_mu::get_user_as_obj($participant->participantidentifier)), ['width' => 85, 'class' => "{$prefix}_session_jquimodal"]) : ''), ['class' => "{$prefix}_session_photo"]);

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
                            $overrideusername = $OUTPUT->user_picture($overrideuser, ['size' => 35, 'courseid' => $courseid, 'includefullname' => true]);
                        } else {
                            $overrideusername = '';
                        }
                        // Column=session_overridename.
                        echo \html_writer::tag('td', ($hasoverride ? $overrideusername : ''), ['class' => "{$prefix}_session_overridename"]);
                        // Column=session_overridereason.
                        echo \html_writer::tag('td', ($hasoverride ? htmlspecialchars($session->overridereason, ENT_QUOTES, 'UTF-8') : ''), ['class' => "{$prefix}_session_overridereason"]);
                    }
                    echo $tr_end;
                }

                echo "</tbody><tfoot>{$tr_header}</tfoot></table>";
                // Used as a JQueryUI popup to show the user picture.
                echo '<div id="dialog"></div>';
            }
    }
    // Close the participant_container.
    echo \html_writer::end_tag('div');
}