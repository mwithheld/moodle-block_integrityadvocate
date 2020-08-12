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
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate\privacy;

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\approved_userlist;
use \core_privacy\local\request\contextlist;
use \core_privacy\local\request\userlist;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/integrityadvocate/lib.php');

/**
 * Privacy Subsystem for block_integrityadvocate.
 */
class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /** @var string Re-usable name for this medatadata */
    const PRIVACYMETADATA_STR = 'privacy:metadata';

    /** @var str HTML linebreak */
    const BRNL = "<br>\n";

    /**
     * Get information about the user data stored by this plugin.
     * This does not include data that is set on the remote API side.
     *
     * @param  collection $collection An object for storing metadata.
     * @return collection The metadata.
     */
    public static function get_metadata(collection $collection): collection {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $collection=' . var_export($collection, true), INTEGRITYADVOCATE_LOGDEST_HTML);

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
            // This is not sent from Moodle - it is set on the remote side: 'session_status'.
            'exit_fullscreen_count',
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
                self::PRIVACYMETADATA_STR . ':' . INTEGRITYADVOCATE_BLOCK_NAME . ':tableexplanation');
        $debug && ia_mu::log('About to return $collection=' . var_export($collection, true), INTEGRITYADVOCATE_LOGDEST_HTML);

        return $collection;
    }

    /**
     * Get the list of users who have data within a context.
     * This will include users who are no longer enrolled in the context if they still have remote IA participant data.
     *
     * @param   \userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $userlist=' . var_export($userlist, true));

        if (empty($userlist->count())) {
            return;
        }

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $userlist->add_users(self::get_participants_from_blockcontext($context));
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(\core_privacy\local\request\approved_userlist $userlist) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $userlist=' . var_export($userlist, true));

        if (empty($userlist->count())) {
            return;
        }

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        // Get IA participant data from the remote API.
        $participants = \block_integrityadvocate_get_participants_for_blockcontext($context);
        $debug && ia_mu::log($fxn . '::Got count($participants)=' . ia_u::count_if_countable($participants));
        if (ia_u::is_empty($participants) || ia_u::is_empty($userlist) || ia_u::is_empty($userids = $userlist->get_userids())) {
            return;
        }

        // If we got participants, we are in the block context and the parent is a module.
        self::delete_participants($context, $participants, $userids);
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

        if (!($context instanceof \context_module)) {
            return;
        }

        // Get IA participant data from the remote API.
        $participants = \block_integrityadvocate_get_participants_for_blockcontext($context);
        $debug && ia_mu::log($fxn . '::Got count($participants)=' . ia_u::count_if_countable($participants));
        if (ia_u::is_empty($participants)) {
            return;
        }

        // If we got participants, we are in the block context and the parent is a module.
        $coursecontext = $context->get_course_context(true);
        $modulecontext = $context->get_parent_context();
        self::send_delete_request($modulecontext, 'Please remove all IA participant and overrider data for ' . self::BRNL .
                '&nbsp;&nbsp;&bull;&nbsp;courseid=' . $coursecontext->instanceid . self::BRNL .
                '&nbsp;&nbsp;&bull;&nbsp;activityid=' . $modulecontext->instanceid . self::BRNL
        );
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $contextlist=' . var_export($contextlist, true));

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        if (!isset($user->id)) {
            return;
        }

        foreach ($contextlist->get_contexts() as $context) {
            // Get IA participant data from the remote API.
            $participants = \block_integrityadvocate_get_participants_for_blockcontext($context);
            $debug && ia_mu::log($fxn . '::Got count($participants)=' . ia_u::count_if_countable($participants));
            if (ia_u::is_empty($participants)) {
                continue;
            }

            // If we got participants, we are in the block context and the parent is a module.
            self::delete_participants($context, $participants, array($user->id));
        }
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int           $userid       The user to search.
     * @return  contextlist   $contextlist  The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        // Gets all IA blocks in the site.
        $blockinstances = ia_mu::get_all_blocks(\INTEGRITYADVOCATE_SHORTNAME, false);

        $contextlist = new contextlist();
        if (empty($blockinstances)) {
            return $contextlist;
        }

        // For each visible IA block instance, get the context id.
        $contextids = array();
        foreach ($blockinstances as $b) {
            $blockcontext = $b->context;
            $parentcontext = $blockcontext->get_parent_context();
            // We only have data for IA blocks in modules.
            if (intval($parentcontext->contextlevel) !== intval(CONTEXT_MODULE)) {
                continue;
            }
            if (\is_enrolled($parentcontext, $userid)) {
                $contextids[] = $b->context->id;
            }
        }

        if (empty($contextids)) {
            return $contextlist;
        }
        $contextlist->add_user_contexts($contextids);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        if (empty($contextlist->count())) {
            return;
        }
        $user = $contextlist->get_user();
        if (!isset($user->id)) {
            return;
        }

        foreach ($contextlist->get_contexts() as $context) {
            // Get IA participant data from the remote API.
            $participants = \block_integrityadvocate_get_participants_for_blockcontext($context);
            if (ia_u::is_empty($participants)) {
                continue;
            }

            // If we got participants, we are in the block context and the parent is a module.
            if (isset($participants[$user->id]) && !empty($p = $participants[$user->id])) {
                $data = (object) self::get_participant_info_for_export($p);
                \core_privacy\local\request\writer::with_context($context)->export_data([INTEGRITYADVOCATE_BLOCK_NAME], $data);
            }
        }
    }

    // Z==========================================================================.
    // IA functions below this line.
    // Z==========================================================================.

    /**
     * Get list of unique IA participant and overrider IDs from the remote API.
     *
     * @param \context $blockcontext The IA block context.
     * @return [int] Array of unique IA participant Ids and overrider Ids from the remote API.
     */
    public static function get_participants_from_blockcontext(\context_block $blockcontext): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $userlist=' . var_export($userlist, true));

        $participants = \block_integrityadvocate_get_participants_for_blockcontext($blockcontext);
        $debug && ia_mu::log($fxn . '::Got count($participants)=' . ia_u::count_if_countable($participants));
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
     * @param context_block $blockcontext The block context.
     * @param [Participant] $participants IA Participants associated with the block.
     * @param [int] $useridstodelete List of userid to send deletion requests for. If empty, requests are sent for all participants.
     * @return bool True on success.
     */
    public static function delete_participants(\context_block $blockcontext, array $participants, array $useridstodelete = array()): bool {
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
                            self::get_participant_info_for_deletion($p));
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
                            self::get_override_info_for_deletion($p));
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
     * @return stdClass Participant info to for export to the user on request.
     */
    private static function get_participant_info_for_export(Participant $participant): stdClass {
        $info = $participant;
        // Protect privacy of the overrider.
        unset($info['overridelmsuserfirstname'],
                $info['overridelmsuserlastname'],
                $info['overridelmsuserid']
        );

        // Remove info set on the remote API side that is not really needed or useful.
        unset($info['resubmiturl']);

        // Translate data into user-readable strings - first at the participant level.
        $info->status = \block_integrityadvocate\Status::get_status_lang($participant->status);
        $info->overridestatus = \block_integrityadvocate\Status::get_status_lang($participant->overridestatus);

        // Translate data into user-readable strings - in each session.
        foreach ($participant->session as $s) {
            $s->status = \block_integrityadvocate\Status::get_status_lang($s->status);
            $s->overridestatus = \block_integrityadvocate\Status::get_status_lang($s->overridestatus);

            // Protect privacy of the overrider.
            unset($info['overridelmsuserfirstname'],
                    $info['overridelmsuserlastname'],
                    $info['overridelmsuserid']
            );
        }

        return (object) (array) $info;
    }

    /**
     * Gather IA participant info to send in the delete request.
     *
     * @param \block_integrityadvocate\Participant $participant
     * @return string HTML Participant info to uniquely identify the entry to IntegrityAdvocate.
     */
    private static function get_participant_info_for_deletion(Participant $participant): string {
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
            if ($property == 'status') {
                $val = \block_integrityadvocate\Status::get_status_lang($participant->$property);
            } else {
                $val = $participant->$property;
            }
            $info[] = "&nbsp;&nbsp;&bull;&nbsp;{$property}={$val}";
        }

        return implode(self::BRNL, $info);
    }

    /**
     * Gather IA override user info to send in the delete request.
     *
     * @param \block_integrityadvocate\Participant $participant
     * @return string HTML Participant and override info to uniquely identify the entry to IntegrityAdvocate.
     */
    private static function get_override_info_for_deletion(Participant $participant): string {
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
            if ($property == 'status') {
                $val = \block_integrityadvocate\Status::get_status_lang($participant->$property);
            } else {
                $val = $participant->$property;
            }
            $info[] = "&nbsp;&nbsp;&bull;&nbsp;{$property}={$val}";
        }

        return implode(self::BRNL, $info) . self::BRNL;
    }

    /**
     * Email the user data delete request to INTEGRITYADVOCATE_PRIVACY_EMAIL
     *
     * @param context_block $blockcontext The block context.
     * @param string $msg Text to add to the email.
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
