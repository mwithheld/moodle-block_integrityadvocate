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
 * Privacy Subsystem for block_integrityadvocate.
 *
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request;
use block_integrityadvocate\Api as ia_api;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/integrityadvocate/lib.php');

class provider implements \core_privacy\local\metadata\provider \core_userlist_provider {

    const PRIVACYMETADATA_STR = 'privacy:metadata';
    const BRNL = "<br>\n";

    /**
     * Get information about the user data stored by this plugin.
     *
     * @param  collection $collection An object for storing metadata.
     * @return collection The metadata.
     */
    public static function get_metadata(collection $collection):
    collection {
        $privacyitems = array(
            // Course info.
            'cmid',
            'courseid',
            // Moodle user info.
            'email',
            'fullname',
            'userid',
            // Video session info.
            'identification_card',
            'session_end',
            'session_start',
            'user_video',
            // Override info.
            'override_date',
            'override_fullname',
            'override_reason',
            'override_status',
        );

        // Combine the above keys with corresponding values into a new key-value array.
        $privacyitemsarr = array();
        foreach ($privacyitems as $key) {
            $privacyitemsarr[$key] = self::PRIVACYMETADATA_STR . ':' . INTEGRITYADVOCATE_BLOCK_NAME . ':' . $key;
        }

        $collection->add_external_location_link(INTEGRITYADVOCATE_BLOCK_NAME, $privacyitemsarr,
                self::PRIVACYMETADATA_STR . ':' . INTEGRITYADVOCATE_BLOCK_NAME);

        return $collection;
    }

    /**
     * Get the list of users who have data within a context.
     * This will include users who are no longer enrolled in the context if they still have remote IA participant data.
     *
     * @param   \userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(\userlist $userlist) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $userlist=' . var_export($userlist, true));

        if (empty($userlist->count())) {
            return;
        }

        $userlist->add_users(self::get_participants_from_blockcontext($userlist->get_context()));
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(\approved_userlist $userlist, \context_block $blockcontext) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $userlist=' . var_export($userlist, true));

        if (empty($userlist->count())) {
            return;
        }

        // Get IA participant data from the remote API.
        $participants = \block_integrityadvocate_get_participants_for_blockcontext($userlist->get_context());
        $debug && ia_mu::log($fxn . '::Got count($participants)=' . (is_countable($participants) ? count($participants) : 0));
        if (ia_u::is_empty($participants) || ia_u::is_empty($userlist) || ia_u::is_empty($userids = $userlist->get_userids())) {
            return;
        }

        // If we got participants, we are in the block context and the parent is a module.
        self::delete_participants($blockcontext, $participants, $userids);
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $context=' . var_export($context, true));

        // Get IA participant data from the remote API.
        $participants = \block_integrityadvocate_get_participants_for_blockcontext($context);
        $debug && ia_mu::log($fxn . '::Got count($participants)=' . (is_countable($participants) ? count($participants) : 0));
        if (ia_u::is_empty($participants)) {
            return;
        }

        // If we got participants, we are in the block context and the parent is a module.
        $coursecontext = $context->get_course_context(true);
        $modulecontext = $context->get_parent_context();
        self::send_delete_request($moduulecontext, 'Please remove all IA participant and overrider data for ' . self::BRNL .
                '&nbsp;&nbsp;&bull;&nbsp;courseid=' . $coursecontext->instanceid . self::BRNL .
                '&nbsp;&nbsp;&bull;&nbsp;activityid=' . $modulecontext->instanceid . self::BRNL
        );
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(\approved_contextlist $contextlist) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $contextlist=' . var_export($contextlist, true));

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            // Get IA participant data from the remote API.
            $participants = \block_integrityadvocate_get_participants_for_blockcontext($context);
            $debug && ia_mu::log($fxn . '::Got count($participants)=' . (is_countable($participants) ? count($participants) : 0));
            if (ia_u::is_empty($participants)) {
                continue;
            }

            // If we got participants, we are in the block context and the parent is a module.
            self::delete_participants($context, $participants, array($user->id));
        }
    }

    // Z==========================================================================.
    // IA functions below this line.
    // Z==========================================================================.

    /**
     * Get list of unique IA participant and overrider IDs from the remote API.
     *
     * @param \context $blockcontext The IA block context.
     * @return array List of unique IA participant and overrider IDs from the remote API.
     */
    public static function get_participants_from_blockcontext(\context_block $blockcontext): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $userlist=' . var_export($userlist, true));

        $participants = \block_integrityadvocate_get_participants_for_blockcontext($blockcontext);
        $debug && ia_mu::log($fxn . '::Got count($participants)=' . (is_countable($participants) ? count($participants) : 0));
        if (ia_u::is_empty($participants)) {
            return array();
        }

        // If we got participants, we are in the block context and the parent is a module.
        //
        // Populate this list with user ids who have IA data in this context.
        // This lets us use add_users() to minimize DB calls rather than add_user() in the below loop.
        $userids = array();
        foreach ($participants as $p) {
            // Populate if is a participant.
            if (isset($p->participantidentifier) && !empty($p->participantidentifier)) {
                $userids[] = $p->participantidentifier;
            }

            // Populate if is an override instructor.
            if (isset($p->overridelmsuserid) && !empty($p->overridelmsuserid)) {
                $userids[] = $p->overridelmsuserid;
            }
        }

        return array_unique($userids);
    }

    /**
     * Request delete for the IA participants and overriders from the remote API.
     * One request per userid is sent, even if someone is both participant and overrider.
     *
     * @param array $participants IA Participants associated with the block.
     * @param array $useridstodelete List of userid to send deletion requests for.
     * If empty, requests are sent for all participants.
     * @return bool True on success.
     */
    public static function delete_participants(\context_block $blockcontext, array $participants,
            array $useridstodelete = array()): bool {
        // Prevent multiple messages for the same user by tracking the IDs we have sent to.
        $participantmessagesent = array();
        $overridemessagesent = array();

        foreach ($participants as $p) {
            // Check the participant is one we should delete.
            if (isset($p->participantidentifier) && !empty($p->participantidentifier) &&
                    (ia_u::is_empty($useridstodelete) || in_array($p->participantidentifier, $useridstodelete))
            ) {
                // Request participant data delete.
                $useridentifier = $blockcontext->instanceid . '-' . $p->participantidentifier;
                if (!in_array($useridentifier, $participantmessagesent)) {
                    self::send_delete_request($blockcontext, 'Please remove IA participant data for ' . self::BRNL .
                            self::get_participant_info_to_send($p));
                    $participantmessagesent[] = $useridentifier;
                }
            }

            // Check the override user is one we should delete.
            if (isset($p->overridelmsuserid) && !empty($p->overridelmsuserid) &&
                    (ia_u::is_empty($useridstodelete) || in_array($p->overridelmsuserid, $useridstodelete))
            ) {
                $useridentifier = $blockcontext->instanceid . '-' . $p->overridelmsuserid;
                // Request override instructor data delete.
                if (!in_array($useridentifier, $overridemessagesent)) {
                    self::send_delete_request($blockcontext, 'Please remove IA *overrider* data for ' . self::BRNL .
                            self::get_override_info_to_send($p));
                    $overridemessagesent[] = $useridentifier;
                }
            }
        }

        return true;
    }

    /**
     * Gather IA participant info to send in the delete request.
     *
     * @param \block_integrityadvocate\Participant $participant
     * @return string HTML Participant info to uniquely identify the entry to IntegrityAdvocate.
     */
    private static function get_participant_info_to_send(Participant $participant): string {
        $usefulfields = array(
            'cmid',
            'courseid',
            'created',
            'modified',
            'email',
            'firstname',
            'lastname',
            'overridedate',
            'participantidentifier',
            'status',
        );

        $info = array();
        foreach ($usefulfields as $property) {
            $info[] = "&nbsp;&nbsp;&bull;&nbsp;{$property}={$participant->$property}";
        }

        return implode(self::BRNL, $info);
    }

    /**
     * Gather IA override user info to send in the delete request.
     *
     * @param \block_integrityadvocate\Participant $participant
     * @return string HTML Participant and override info to uniquely identify the entry to IntegrityAdvocate.
     */
    private static function get_override_info_to_send(Participant $participant): string {
        $usefulfields = array(
            'cmid',
            'courseid',
            'created',
            'modified',
            'email',
            'firstname',
            'lastname',
            'overridedate',
            'overridelmsuserfirstname',
            'overridelmsuserid',
            'overridelmsuserlastname',
            'overridestatus',
            'participantidentifier',
            'status',
        );

        $info = array();
        foreach ($usefulfields as $property) {
            $info[] = "&nbsp;&nbsp;&bull;&nbsp;{$property}={$participant->$property}";
        }

        return implode(self::BRNL, $info) . self::BRNL;
    }

    /**
     * Email the user data delete request to INTEGRITYADVOCATE_PRIVACY_EMAIL
     *
     * @return bool True on emailing success; else false.
     */
    private static function send_delete_request(\context_block $blockcontext, string $msg): bool {
        global $USER, $CFG, $SITE;

        // Throws an exception if email is invalid.
        $mailto = clean_param(INTEGRITYADVOCATE_PRIVACY_EMAIL, PARAM_EMAIL);

        // Try a few ways to get an email from address.
        $mailfrom = $USER->email;
        if (empty($mailfrom) && !empty($CFG->supportemail)) {
            $mailfrom = $CFG->supportemail;
        }
        if (empty($mailfrom) && !empty($siteadmin = \get_admin()) && !empty($siteadmin->email)) {
            $mailfrom = $siteadmin->email;
        }
        if (empty($mailfrom)) {
            $mailfrom = $mailto;
        }

        $coursecontext = $blockcontext->get_course_context(true);
        $modulecontext = $blockcontext->get_parent_context();

        // Assemble email params.
        $subject = 'Moodle privacy API data removal request from "' . $SITE->fullname . '" ' . $CFG->wwwroot;
        $message = $subject . self::BRNL;
        $message .= "Admin email={$siteadmin->email}" . self::BRNL;
        $message .= "Course_Id={$coursecontext->instanceid}" . self::BRNL;
        // The $modulecontext->instanceid is the CMID.
        $message .= "Activity_Id={$modulecontext->instanceid}" . self::BRNL;
        $message .= '--' . self::BRNL;
        $message .= $msg;

        return email_to_user($mailto, $mailfrom, $subject, html_to_text($message), $message);
    }

}
