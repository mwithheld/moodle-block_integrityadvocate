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
 * Event observer.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/integrityadvocate/lib.php');

/**
 * On activity create/update events that correspond to finishing an activity, close the remote IA session.
 *
 * @copyright IntegrityAdvocate.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_integrityadvocate_observer {

    /**
     * Parse the triggered event and decide if and how to act on it.
     * User logout and other course events lead to the user's remote IA sessions being closed
     *
     * @param \core\event\base $event Event to maybe act on
     * @return true if attempted to close the remote IA session; else false
     */
    public static function process_event(\core\event\base $event) {
        $debug = true;
        $debuginfo = "eventname={$event->eventname}; crud={$event->crud}; courseid={$event->courseid}; userid={$event->userid}";
        if ($debug) {
            // Disabled on purpose: block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started with event=' . print_r($event, true));.
            block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Started with \$debuginfo={$debuginfo}; event->crud={$event->crud}; is c/u=" . (in_array($event->crud, array('c', 'u'), true)));
            block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Started with event->contextlevel={$event->contextlevel}; is_contextlevelmatch=" . ($event->contextlevel === CONTEXT_MODULE));
        }

        // No CLI events correspond to a user finishing an IA session.
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Started with event->crud={$event->crud}; crud match=" . (in_array($event->crud, array('c', 'u'), true)));
            return false;
        }

        // If there is no user attached to this event, we can't close the user's IA session, so skip.
        if (is_numeric($event->userid)) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::The event has no user info so skip it; debuginfo={$debuginfo}");
            return false;
        }

        switch ($event->eventname) {
            case '\\core\\event\\user_loggedout':
                // On logout, close all IA sessions. Note: This is a read event not a create/update.
                $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::{$event->eventname}: close all remote IA sessions for userid={$event->userid}");
                self::close_all_user_sessions($event);
                return;
            case '\\core\\event\\course_module_completion_updated':
                // When activity completion updates...
                // (a) Close the IA session; and...
                // (b) Update the activity completion info from the IA status.
                $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::$event->eventname::Event name={$event->eventname}, so just close the IA session");
                self::close_activity_user_session($event);
                $useriaresults = block_integrityadvocate_get_course_user_ia_data($event->courseid, $event->userid, $event->contextid);
                if ($useriaresults) {
                    // If we get back a string we got an error, so skip it.
                    if (is_string($useriaresults)) {
                        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::$event->eventname::Skipped closing: " . print_r($useriaresults, true));
                    } elseif (is_array($participant = $useriaresults[0])) {
                        process_integrityadvocate::process_single_user($event->oourseid, $participant, __FUNCTION__);
                        return;
                    }
                }
        }

        /*
         * Blacklist events to *not* act on.
         * Ref event list /report/eventlist/index.php
         *   - Education level=Participating
         *   - Database query type=create or update
         */
        switch (true) {
            case block_integrityadvocate_strposa($event->eventname, array(
                '\\mod_chat\\event\\',
                '\\mod_data\\event\\',
                '\\mod_glossary\\event\\',
                '\\mod_lesson\\event\\',
                '\\mod_scorm\\event\\',
                '\\mod_wiki\\event\\',
            )):
            // None of the event names starting with these strings correspond to finishing an activity.
            case preg_match('/\\mod_forum\\event\\.*created$/i', $event->eventname):
            // None of the \mod_forum\*created events correspond to finishing an activity - they probably jost posted to the forum or added a discussion but that's not finishing with forums.
            case in_array($event->eventname, array(
                '\\assignsubmission_onlinetext\\event\\submission_updated',
                '\\mod_assign\\event\\submission_duplicated',
                '\\mod_quiz\\event\\attempt_becameoverdue',
                '\\mod_quiz\\event\\attempt_started',
                '\\mod_workshop\\event\\submission_reassessed',
            )):
                // None of these exact string matches on event names correspond to finishing an activity.
                $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::This eventname is blacklisted, so skip it; debuginfo={$debuginfo}");
                return false;
            default:
                // Do nothing.
                $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::This eventname is not blacklisted so continue");
        }

        /*
         * Whitelist events to act on.
         * Ref event list /report/eventlist/index.php
         *   - Education level=Participating
         *   - Database query type=create or update
         */
        switch (true) {
            case in_array($event->eventname, array(
                /* Create events */
                '\\mod_assign\\event\\assessable_submitted',
                '\\mod_choice\\event\\answer_created',
                '\\mod_feedback\\event\\response_submitted',
                // This is blacklisted in wildcards above: '\\mod_lesson\\event\\lesson_ended',.
                '\\mod_quiz\\event\\attempt_abandoned',
                '\\mod_quiz\\event\\attempt_submitted',
            )):
                block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::This eventname is whitelisted so act on it; debuginfo={$debuginfo}");
                break;
            default:
                $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::This eventname is not in the whitelist to be acted on, so skip it; debuginfo={$debuginfo}");
                return false;
        }

        // Make sure...
        // (a) this is a create or update event; and...
        // (b) this is an activity-level event.
        $iscreateorupdate = in_array($event->crud, array('c', 'u'), true);
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Found $is_create_or_update=' . $iscreateorupdate);
        if ($iscreateorupdate) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::This is not a create or update event, so skip it');
            return false;
        }

        $iscoursemodulechangeevent = (
                // CONTEXT_MODULE=70.
                $event->contextlevel === CONTEXT_MODULE &&
                is_numeric($event->courseid) && $event->courseid != SITEID
                );

        /*
         * Note \core\event\user_graded events are CONTEXT_MODULE=50...
         * but there are other events that should close the IA session.
         */
        if (!$iscoursemodulechangeevent) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::This is not a course activity module create or update so skip it; debuginfo={$debuginfo}");
            return false;
        }
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::This is a course activity module create or update so continue');

        return self::close_activity_user_session($event);
    }

    /**
     * Then close all remote IA sessions for the user attached to the passed-in event.
     * The event does not need an attached course or activity.
     *
     * Assumes the event is a course-activity event with a userid.
     *
     * @param \core\event\base $event Event to maybe act on
     * @return true if attempted to close the remote IA session; else false
     */
    static function close_all_user_sessions(\core\event\base $event) {
        $debug = true;
        $debuginfo = "eventname={$event->eventname}; crud={$event->crud}; userid={$event->userid}";
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Started with \$debuginfo={$debuginfo}");

        // Gets visible blocks.
        $blockinstances = block_integrityadvocate_get_all_blocks();
        if (empty($blockinstances)) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::No integrityadvocate block instances found, so skip this task.');
            return true;
        }

        $success = false;

        // For each visible IA block instance, process IA data and update the activity completion status accordingly.
        foreach ($blockinstances as $b) {
            $success &= self::close_session($b, $event->userid);
        }

        return $success;
    }

    /**
     * Parse out event info to get the related IA block, activity, course, and user.
     * Then close the remote IA session.
     *
     * Assumes the event is a course-activity event with a userid.
     *
     * @param \core\event\base $event Event to maybe act on
     * @return true if attempted to close the remote IA session; else false
     */
    static function close_activity_user_session(\core\event\base $event) {
        $debug = true;
        $debuginfo = "eventname={$event->eventname}; crud={$event->crud}; courseid={$event->courseid}; userid={$event->userid}";
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Started with \$debuginfo={$debuginfo}");

        if (!($blockinstance = check_should_close_user_ia($event))) {
            return false;
        }

        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::About to close_session() for \$debuginfo={$debuginfo}");
        return self::close_session($blockinstance, $event->userid);
    }

    static function check_should_close_user_ia(\core\event\base $event) {
        $debug = true;

        $modulecontext = $event->get_context();

        if ($modulecontext->contextlevel != CONTEXT_MODULE) {
            $msg = 'The passed-in event is not from a module context level';
            block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::$msg");
            throw new InvalidArgumentException($msg);
        }

        if (!is_enrolled($modulecontext, null, null, true)) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::The user has no active enrolment in this course-activity so skip it");
            return false;
        }
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::The user has an active enrolment in this course-activity so continue');

        // Check the user has a valid UEID in this context.
        // Disabled on purpose: $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::About to get_ueid');.
        $ueid = block_integrityadvocate_get_ueid($modulecontext, $event->userid);
        if ($ueid < 0) {
            block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Failed to find a UEID for userid and context={$event->contextid}; debuginfo={$debuginfo}");
            return false;
        }
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Found ueid={$ueid}");

        if ($debug) {
            // Abstracted $blockname to reduce errors in case the block name changes.
            // Remove the block_ prefix and _observer suffix.
            $blockname = implode('_', array_slice(explode('_', substr(__CLASS__, strrpos(__CLASS__, '\\') + 1)), 1, -1));
            block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Found blockname={$blockname}");
        }

        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Maybe we should ask the IA API to close the session");

        // Make sure an IA block instance is present and visible.
        list($unused, $blockinstance) = block_integrityadvocate_get_ia_block($modulecontext);
        if (!$blockinstance) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::The block is not present or not visible, so skip it");
            return false;
        }

        return $blockinstance;
    }

    /**
     * Actually close the remote IA session.
     * Assumes the user is enrolled in the block's parent context
     *
     * @param block_integrityadvocate $blockinstance to close sessions for
     * @param int $userid The Moodle userid to close the session for
     * @return boolean true if remote session is closed; else false
     */
    static function close_session(block_integrityadvocate $blockinstance, $userid) {
        $debug = true;
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Started");

        $appid = trim($blockinstance->config->appid);
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Found appid={$appid}");
        if (!$appid) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::The block instance has no appid configured, so skip it");
            return false;
        }

        $blockcontext = $blockinstance->context;
        $modulecontext = $blockcontext->get_parent_context();

        return block_integrityadvocate_close_api_session($appid, $modulecontext, $userid);
    }

}
