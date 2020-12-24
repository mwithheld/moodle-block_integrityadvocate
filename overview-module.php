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
    case (!FeatureControl::OVERVIEW_MODULE_LTI):
        throw new \Exception('This feature is disabled');
    case (empty($moduleid) || ($moduleid = \required_param('moduleid', \PARAM_INT)) < 1):
        // This is only an optional_param in overview.php.
        // The above line throws an error if $moduleid is not passed as an integer.
        // But we get here if $moduleid is zero or negative.
        throw new \InvalidArgumentException("Invalid moduleid={$moduleid}");
    case(!empty(\require_capability('block/integrityadvocate:overview', $coursecontext))):
        // This is not a required permission in the parent file - we only query has_capability().
        // Here, the above line throws an error if the current user is not a teacher, so we should never get here.
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

switch (true) {
    case(FeatureControl::OVERVIEW_MODULE_LTI):
        $debug && Logger::log(__FILE__ . '::Request is for OVERVIEW_MODULE_LTI');
        /**
         * Code here is adapted from https://gist.github.com/matthanger/1171921 .
         */
        $launch_url = INTEGRITYADVOCATE_BASEURL_LTI . INTEGRITYADVOCATE_LTI_PATH . '/Participants';

        $launch_data = [
            // Required for Moodle oauth_helper.
            'api_root' => $launch_url,
            // 2020Dec: launch_presentation_locale appears to be unused, LTIConsumer example was en-US.
            'launch_presentation_locale' => \current_language(),
            // 2020Dec: roles appears to be unused. 0 = admin; 3=learner.
            'roles' => 0,
            // This should always be 1.
            'resource_link_id' => '1',
            // Who is requesting this info?.
            'user_id' => $USER->id,
            'lis_person_contact_email_primary' => $USER->email,
            'lis_person_name_family' => $USER->lastname,
            'lis_person_name_full' => \fullname($USER),
            'lis_person_name_given' => $USER->firstname,
            'lis_person_sourcedid' => $USER->id,
            // Extra info to help identify this request to the remote side.  2020Dec: They appear to be unused.
            'tool_consumer_instance_description' => "site={$CFG->wwwroot}; course={$courseid}; blockinstanceid={$blockinstanceid}; moduleid={$moduleid}",
            'tool_consumer_instance_guid' => $blockinstanceid,
            'tool_consumer_blockversion' => \get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version'),
            // LTI setup.
            'lti_message_type' => 'basic-lti-launch-request',
            'lti_version' => 'LTI-1p0',
            // OAuth 1.0 setup.
            'oauth_callback' => 'about:blank',
            'oauth_consumer_key' => $blockinstance->config->appid,
            'oauth_consumer_secret' => $blockinstance->config->apikey,
            'oauth_nonce' => \uniqid('', true),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (new \DateTime())->getTimestamp(),
            'oauth_version' => '1.0',
            // Context info.
            'context_id' => $courseid,
            'context_label' => $COURSE->shortname,
            'context_title' => $COURSE->fullname,
        ];

        // Setup the LTI UI for one specific module.
        $m = null;
        foreach ($modules as $key => $thismodule) {
            if (intval($thismodule['id']) === intval($moduleid)) {
                $m = $modules[$key];
                break;
            }
        }
        if (ia_u::is_empty($m)) {
            $msg = 'This module is not an IA module';
            $debug && Logger::log(__FILE__ . "::{$msg}");
            throw new \InvalidArgumentException($msg);
        }

        $custom_activities = [(object) ['Id' => $m['id'], 'Name' => $m['modulename'] . ': ' . $m['name']]];
        $launch_data['custom_activities'] = json_encode($custom_activities, JSON_PARTIAL_OUTPUT_ON_ERROR);

        // We only need launch the LTI.
        // The request is signed using OAuth Core 1.0 spec: http://oauth.net/core/1.0/ .
        // Moodle's code does the same as the example at https://gist.github.com/matthanger/1171921 but with a bit more cleanup.
        require_once($CFG->libdir . '/oauthlib.php');
        $signature = (new \oauth_helper($launch_data))->sign('POST', $launch_url, $launch_data, urlencode($blockinstance->config->apikey) . '&');
        echo ia_output::get_lti_iframe_html($launch_url, $launch_data, $signature);
        break;

    default:
        throw new \Exception('This feature is disabled');
}
