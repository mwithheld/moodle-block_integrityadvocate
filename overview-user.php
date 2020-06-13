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

// Show basic user info at the top.  Adapted from user/view.php.
echo \html_writer::start_tag('div', array('class' => \INTEGRITYADVOCATE_BLOCK_NAME . '_overview_user_userinfo'));
$user = $DB->get_record('user', array('id' => $userid), '*', \MUST_EXIST);
echo $OUTPUT->user_picture($user, array('size' => 35, 'courseid' => $courseid, 'includefullname' => true));
echo \html_writer::end_tag('div');

$participant = ia_api::get_participant($blockinstance->config->apikey, $blockinstance->config->appid, $courseid, $userid);

if (ia_u::is_empty($participant)) {
    $msg = 'No participant found';
    if ($hascapability_overview) {
        $msg .= ': Double-check the APIkey and AppId for this block instance are correct';
    }
    echo $msg;
    $continue = false;
}

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