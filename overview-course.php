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
 * IntegrityAdvocate block Overview page showing course participants with a summary of their IntegrityAdvocate data.
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
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

// Security check - this file must be included from overview.php.
defined('INTEGRITYADVOCATE_OVERVIEW_INTERNAL') || die();

$debug = false || Logger::do_log_for_function(INTEGRITYADVOCATE_BLOCK_NAME . '\\' . basename(__FILE__));
$debug && Logger::log(basename(__FILE__) . "::Started with courseid={$courseid}");

// Check all requirements.
switch (true) {
    case (!FeatureControl::OVERVIEW_COURSE && !FeatureControl::OVERVIEW_COURSE_V2):
        throw new \Exception('This feature is disabled');
    case (empty($blockinstanceid)):
        throw new \InvalidArgumentException('$blockinstanceid is required');
    case (empty($courseid) || ia_u::is_empty($course) || ia_u::is_empty($coursecontext)):
        throw new \InvalidArgumentException('$courseid, $course and $coursecontext are required');
    case (!empty(\require_capability('block/integrityadvocate:overview', $coursecontext))):
        // The above line throws an error if the current user is not a teacher, so we should never get here.
        $debug && Logger::log(__FILE__ . '::Checked required capability: overview');
        break;
    default:
        $debug && Logger::log(__FILE__ . '::All requirements are met');
}
switch (true) {
    case (FeatureControl::OVERVIEW_COURSE_V3):
        /**
         * Code here is adapted from https://gist.github.com/matthanger/1171921 .
         */
        $launch_url = INTEGRITYADVOCATE_BASEURL_LTI . INTEGRITYADVOCATE_LTI_PATH . '/Participants';

        $launch_data = [
            'api_root' => $launch_url,
            'launch_presentation_locale' => \current_language(),
            'user_id' => $USER->id,
            // 2020Dec: This appears to be unused.
            'roles' => '*',
            // Z--.
            // This should always be 1.
            'resource_link_id' => '1',
            // 2020Dec: These appear to be unused.
            //'resource_link_title' => 'Weekly Blog',
            //'resource_link_description' => 'A weekly blog.',
            // Z--.
            'lis_person_name_full' => \fullname($USER),
            'lis_person_contact_email_primary' => $USER->email,
            'lis_person_name_family' => $USER->lastname,
            'lis_person_name_given' => $USER->firstname,
//        'lis_person_sourcedid' => 'school.edu:user',
            //
//        'tool_consumer_instance_guid' => 'lmsng.school.edu',
//        'tool_consumer_instance_description' => 'University of School (LMSng)',
//        'ConsumerKey' => $blockinstance->config->apikey,
//        'ContextId' => $courseid,
//        'ResourceLinkId' => '1',
//        'Url' => INTEGRITYADVOCATE_BASEURL . '/lti/Participants',
            // --.
            // LTI setup.
            'lti_message_type' => 'basic-lti-launch-request',
            'lti_version' => 'LTI-1p0',
            // --.
            // OAuth setup.
            // Basic LTI uses OAuth to sign requests.
            // OAuth Core 1.0 spec: http://oauth.net/core/1.0/ .
            'oauth_callback' => 'about:blank',
            // This s/b the IA appid.
            'oauth_consumer_key' => $blockinstance->config->appid,
            'oauth_consumer_secret' => $blockinstance->config->apikey,
            'oauth_nonce' => uniqid('', true),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (new \DateTime())->getTimestamp(),
            'oauth_version' => '1.0',
            // Course id.
            'context_id' => $courseid,
            'context_title' => $COURSE->fullname,
            'context_label' => $COURSE->shortname,
            // IA activities in this course.
            'custom_activities' => json_encode([
                ((object) ['Id' => "2", 'Name' => 'Activity 1']),
                ((object) ['Id' => "3", 'Name' => 'Activity 2']),
                ((object) ['Id' => "8", 'Name' => 'Activity 3']),
                ((object) ['Id' => "9", 'Name' => 'Activity 4']),
                ((object) ['Id' => "11", 'Name' => 'Activity 5']),
                    ], JSON_PARTIAL_OUTPUT_ON_ERROR),
        ];

        // We only need OAUTH 1.0, and only to launch the LTI.
        // Moodle's code does the same as the example but with a bit more cleanup.
        require_once($CFG->libdir . '/oauthlib.php');
        $signature = (new \oauth_helper($launch_data))->sign('POST', $launch_url, $launch_data, urlencode($blockinstance->config->apikey) . '&');
        ?>
        <form id="form" name="ltiLaunchForm" method="POST" target="iframelaunch" style="display:none" action="<?php echo $launch_url; ?>">
            <?php foreach ($launch_data as $k => $v) { ?>
                <input type="hidden" name="<?php echo $k; ?>" value="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>">
            <?php } ?>
            <input type="hidden" name="oauth_signature" value="<?php echo $signature; ?>">
            <button type="submit">Launch</button>
        </form>
        <iframe id="iframelaunch" name="iframelaunch" src="" style="width: 100%;height: 800px;"></iframe>
        <script>document.getElementById("form").submit();</script>
        <?php
        break;
    case (FeatureControl::OVERVIEW_COURSE_V2):
        $debug && Logger::log(__FILE__ . '::Request is for overview_course_v2');
        $prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_overviewcourse';

        // Output roles selector.
//    echo $OUTPUT->container_start(INTEGRITYADVOCATE_BLOCK_NAME . '_roleid_select', "{$prefix}_roleid");
//    echo $OUTPUT->single_select($PAGE->url, 'role', ia_mu::get_roles_for_select($coursecontext), $roleid);
//    echo $OUTPUT->container_end();
//
        // Get list of students in the course.
        // Usage: get_role_users($roleid, $context, $parent, $fields, $sort, $all, $group, $limitfrom, $limitnum).
        $debug && Logger::log(__FILE__ . "::About to get_role_users(\$roleid={$roleid}, \$context={$coursecontext->id}, \$parent=false, \$fields='ra.id, u.id, u.email, u.lastaccess, u.picture, u.imagealt, ' . get_all_user_name_fields(true, 'u'), \$sort=null, \$all=true, \$group=$groupid, \$limitfrom=($currpage * $perpage), \$limitnum=$perpage)");
        $enrolledusers = get_role_users($roleid, $coursecontext, false, 'ra.id, u.id, u.email, u.lastaccess, u.picture, u.imagealt, ' . get_all_user_name_fields(true, 'u'), null, true, $groupid, ($currpage * $perpage), $perpage);
        $debug && Logger::log(__FILE__ . '::Got count($enrolledusers)=' . ia_u::count_if_countable($enrolledusers));

        if (!$enrolledusers) {
            echo get_string('nousersfound');
        } else {
            // The classes here are for DataTables styling ref https://datatables.net/examples/styling/index.html .
            echo '<table id="', $prefix, '_table" class="stripe order-column hover display">';
            $tr = '<tr>';
            $tr_end = '</tr>';
            echo '<thead>';

            $tr_header = [$tr];
            $tr_header[] = \html_writer::tag('th', 'id', ['class' => "{$prefix}_id"]);
            $tr_header[] = \html_writer::tag('th', \get_string('user'), ['class' => "{$prefix}_user"]);
            $tr_header[] = \html_writer::tag('th', \get_string('email'), ['class' => "{$prefix}_email"]);
            $tr_header[] = \html_writer::tag('th', \get_string('lastcourseaccess'), ['class' => "{$prefix}_lastcourseaccess"]);
            $tr_header[] = \html_writer::tag('th', \get_string('column_latestparticipantleveldata', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_column_iadata"]);
            $tr_header[] = \html_writer::tag('th', \get_string('column_iaphoto', INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_column_iaphoto"]);
            $tr_header[] = $tr_end;
            $tr_header = implode('', $tr_header);
            echo "{$tr_header}</thead><tbody>";

//        echo $tr;
//        $pictureparams = ['size' => 35, 'courseid' => $courseid, 'includefullname' => true];
//        foreach ($enrolledusers as $user) {
//            $debuginfo = "userid={$user->id}";
//            //echo '<PRE>' . ia_u::var_dump($user) . '</PRE><hr>' . ia_output::BRNL;
//            // Column=User.
//            echo \html_writer::tag('td', $user->id, ['class' => "{$prefix}_id"]);
//            echo \html_writer::tag('td', ia_mu::get_user_picture($user, $pictureparams), ['data-id' => $user->id, 'data-sort' => fullname($user), 'class' => "{$prefix}_user"]);
//            echo \html_writer::tag('td', $user->email, ['class' => "{$prefix}_email"]);
//            echo \html_writer::tag('td', ($user->lastaccess ? \userdate($user->lastaccess) : get_string('never')), ['class' => "{$prefix}_lastcourseaccess"]);
//            echo \html_writer::tag('td', '', ['class' => "{$prefix}_column_iadata"]);
//            echo \html_writer::tag('td', '', ['class' => "{$prefix}_column_iaphoto"]);
//            echo $tr_end;
//        }

            echo "</tbody><tfoot>{$tr_header}</tfoot></table>";
            // Used as a JQueryUI popup to show the user picture.
            echo '<div id="dialog"></div>';
        }

        // Get IA data for this first set.  We want participant-level data b/c we want the latest photo and status.
//    $participants = ia_api::get_participants($blockinstance->config->apikey, $blockinstance->config->appid, $courseid);
//    $debug && Logger::log(__FILE__ . '::Got participants=' . ia_u::var_dump($participants));
        // Display the first 10.
        // ??For each student, get their IA data??
        break;
    default:
        // OLD Participants table UI.
        // The classes here are for DataTables styling ref https://datatables.net/examples/styling/index.html .
        $prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_overview-course';

        echo '<table id="' . $prefix . '" class="stripe order-column hover display">';
        $tr = '<tr>';
        $tr_end = '</tr>';
        echo '<thead>';
        $tr_header = $tr;
        $tr_header .= \html_writer::tag('th', \get_string('fullnameuser'), ['class' => "{$prefix}_user"]);
        $tr_header .= \html_writer::tag('th', \get_string('email'), ['class' => "{$prefix}_email"]);
        $tr_header .= \html_writer::tag('th', \get_string('lastaccess'), ['class' => "{$prefix}_lastaccess"]);
        $tr_header .= \html_writer::tag('th', \get_string('column_iadata', \INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_ia-data"]);
        $tr_header .= \html_writer::tag('th', \get_string('column_iaphoto', \INTEGRITYADVOCATE_BLOCK_NAME), ['class' => "{$prefix}_ia-photo"]);
        $tr_header .= $tr_end;
        echo "{$tr_header}</thead><tbody>";

        global $DB;
        $lastaccess_course = $DB->get_records('user_lastaccess', ['courseid' => $courseid], 'userid', implode(',', ['userid', 'timeaccess']));
        $debug && Logger::log('Got $lastaccess_course=' . ia_u::var_dump($lastaccess_course, true));
        $users = \get_enrolled_users(\context_course::instance($courseid), 'moodle/grade:view', $groupid, 'u.*', 'u.lastname', 0, 0, false);
        $debug && Logger::log('Got $users=get_enrolled_users=' . ia_u::var_dump($users, true));
        if (ia_u::is_empty($users)) {
            echo \get_string('error_nousers', \INTEGRITYADVOCATE_BLOCK_NAME);
            exit;
        }

        // Check if there is any errors.
        if ($configerrors = $blockinstance->get_config_errors()) {
            Logger::log(__CLASS__ . '::' . __FUNCTION__ . '::Error: ' . ia_u::var_dump($configerrors, true));
            echo implode(ia_output::BRNL, $configerrors);
            exit;
        }

        $participants = ia_api::get_participants($blockinstance->config->apikey, $blockinstance->config->appid, $courseid);
        $debug && Logger::log('Got $participants=' . ia_u::var_dump($participants, true));

        $pictureparams = ['size' => 35, 'courseid' => $courseid, 'includefullname' => true, 'link' => false];
        global $OUTPUT;
        foreach ($users as $user) {
            error_log('Looking at userid=' . $user->id);
            echo $tr;
            // Column=User.
            $user = ia_mu::get_user_as_obj($user->id);
            echo \html_writer::tag(
                    'td',
                    '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $user->id . '&amp;course=' . $courseid . '" title="' . fullname($user) . '">' .
                    $OUTPUT->user_picture($user, $pictureparams) . '</a>',
                    ['data-sort' => fullname($user), 'class' => "{$prefix}_user"]
            );

            // Column=email
            echo \html_writer::tag('td', $user->email, ['class' => "{$prefix}_email"]);

            // Column=lastaccess
            if (isset($lastaccess_course[$user->id]->timeaccess) && !empty($lastaccess_course[$user->id]->timeaccess)) {
                $userdate = \userdate($lastaccess_course[$user->id]->timeaccess);
                $debug && Logger::log('userid=' . $user->id . '; userdate=' . $userdate);
                echo \html_writer::tag('td', $userdate, ['class' => "{$prefix}_lastaccess"]);
            } else {
                echo \html_writer::tag('td', '', ['class' => "{$prefix}_lastaccess"]);
            }

            // Column=iadata and Column=iaphoto
            if (isset($participants[$user->id])) {
                echo \html_writer::tag('td', ia_output::get_participant_summary_output($blockinstance, $participants[$user->id], false, true, false), ['class' => "{$prefix}_iadata"]);
                echo \html_writer::tag('td', ia_output::get_participant_photo_output($user->id, $participants[$user->id]->participantphoto, $participants[$user->id]->status, $participants[$user->id]->email), ['class' => "{$prefix}_iaphoto"]);
            } else {
                echo \html_writer::tag('td', '', ['class' => "{$prefix}_iadata"]);
                echo \html_writer::tag('td', '', ['class' => "{$prefix}_iaphoto"]);
            }

            echo $tr_end;
        }
        echo '</tbody>';
        echo "<tfoot>{$tr_header}</tfoot>";
        echo '</table>';
        // Used as a JQueryUI popup to show the user picture.
        echo '<div id="dialog"></div>';
}
