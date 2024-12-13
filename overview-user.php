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
 * AS a student
 * ON the coursemodule page (e.g. quiz page)
 * IF the IA block is visible
 * AND the user has some remote IA data for this coursemodule
 * THEN the "User overview" button links to this current code file you are looking at.
 * This user-overview page has url like: /blocks/integrityadvocate/overview.php?instanceid=12&courseid=2&moduleid=2&userid=3
 * This page shows a single user's Integrity Advocate sessions for this coursemodule.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Output as ia_output;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

// Security check - this file must be included from overview.php.
\defined('INTEGRITYADVOCATE_OVERVIEW_INTERNAL') || die();

// Make sure we have this blocks constants defined.
require_once(__DIR__ . '/lib.php');

// This is only optional_param() in overview.php.
$userid = \required_param('userid', \PARAM_INT);

$debug = false;
$fxn = \basename(__FILE__);
$debug && \debugging($fxn . '::Started with $userid=' . $userid);

$parentcontext = $blockcontext->get_parent_context();

// This capability check is on the parent, not the block instance.
if (\has_capability('block/integrityadvocate:overview', $parentcontext)) {
    // For teachers, allow access to any enrolled course user, even if not active.
    if (!\is_enrolled($parentcontext, $userid)) {
        throw new \Exception('That user is not in this course');
    }
} else if (\is_enrolled($parentcontext, $userid, 'block/integrityadvocate:selfview', true)) {
    // For Students to view their own stuff.
    if ((int) ($USER->id) !== $userid) {
        throw new \Exception("You cannot view other users: \$USER->id={$USER->id}; \$userid={$userid}");
    }
} else {
    throw new \Exception('No capabilities to view this course user');
}

// Control whether to carry on displaying output.
$continue = true;

// Get list of modules that use IA block so we can omit displaying those without.
$coursemodules = \block_integrityadvocate_get_course_ia_modules($course, ['configured' => 1]);
if (!\is_array($coursemodules)) {
    $continue = false;
}

$user = ia_mu::get_user_as_obj($userid);
if (ia_u::is_empty($user)) {
    $msg = "Failed to find a Moodle user with id={$userid}";
    \debugging($fxn . '::' . $msg);
    throw new \Exception($msg);
}

if ($continue) {
    // Show basic user info at the top.  Adapted from user/view.php.
    echo \html_writer::start_tag('div', ['class' => \INTEGRITYADVOCATE_BLOCK_NAME . '_overview_user_userinfo']);
    echo $OUTPUT->user_picture($user, ['size' => 35, 'courseid' => $courseid, 'includefullname' => true]);
    echo \html_writer::end_tag('div');

    /*
     * Code here is adapted from https://gist.github.com/matthanger/1171921 .
     */
    $launchurl = INTEGRITYADVOCATE_BASEURL_LTI . INTEGRITYADVOCATE_LTI_PATH . '/Participant';

    // Avoid PHPStan warning: Variable $hascapabilityoverview might not be defined.
    if (!isset($hascapabilityoverview)) {
        $hascapabilityoverview = false;
    }

    $launchdata = [
        // Required for Moodle oauth_helper.
        'api_root' => $launchurl,
        // 2020Dec: launch_presentation_locale appears to be unused, LTIConsumer example was en-US.
        'launch_presentation_locale' => \current_language(),
        // 2020Dec: roles appears to be unused. 0 = admin; 3=learner.
        'roles' => ($hascapabilityoverview ? 0 : 3),
        // This should always be 1.
        'resource_link_id' => '1',
        // Who is requesting this info?.
        'user_id' => $userid,
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

    // Setup the LTI UI for one specific user.
    $launchdata['UserId'] = $userid;

    // We only need launch the LTI.
    // The request is signed using OAuth Core 1.0 spec: http://oauth.net/core/1.0/ .
    // Moodle's code does the same as the example at https://gist.github.com/matthanger/1171921 but with a bit more cleanup.
    require_once($CFG->libdir . '/oauthlib.php');
    $signature = (new \oauth_helper($launchdata))->sign('POST', $launchurl, $launchdata, \urlencode($blockinstance->config->apikey) . '&');
    echo ia_output::get_lti_iframe_html($launchurl, $launchdata, $signature);
}
