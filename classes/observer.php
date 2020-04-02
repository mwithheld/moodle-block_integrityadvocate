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
     * Get the user_enrolment.id value
     * using Moodle core queries where possible
     * Respects [enrolment status] and [enrolment active dates]
     * Excludes deleted and suspended users
     *
     * @global moodle_database $DB Moodle DB object
     * @param context $context Course or module context to look for user enrolment in
     * @param int $userid The userid to get the ueid for
     * @return int user-enrolment id; false if nothing found
     * @throws InvalidArgumentException
     */
    private static function get_ueid(context $context, $userid) {
        global $DB;

        $debug = false;
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Started with userid={$userid}");

        if (!is_numeric($userid)) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Param validation failed");
            throw new InvalidArgumentException('userid must be an int');
        }
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Param validation done");

        // --This section adapted from enrollib.php::get_enrolled_with_capabilities_join().
        // Initialize empty arrays to be filled later.
        $joins = array();
        $wheres = array();

        $enrolledjoin = get_enrolled_join($context, 'u.id;', true);
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::get_enrolled_join() returned=" . print_r($enrolledjoin, true));

        // Make the parts easier to use.
        $joins[] = $enrolledjoin->joins;
        $wheres[] = $enrolledjoin->wheres;
        $params = $enrolledjoin->params;

        // Clean up Moodle-provided joins.
        $joins = implode("\n", str_replace(';', ' ', $joins));
        // Add our critariae.
        $wheres[] = "u.suspended=0 AND u.deleted=0 AND u.id=" . intval($userid);
        $wheres = implode(' AND ', $wheres);

        // Figure out what prefix was used.
        $matches = array();
        preg_match('/ej[0-9]+_/', $joins, $matches);
        $prefix = $matches[0];
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Got prefix=$prefix");

        // Build the full join part of the sql.
        $sqljoin = new \core\dml\sql_join($joins, $wheres, $params);
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Built sqljoin=' . print_r($sqljoin, true));
        /*
         * The value of $sqljoin is something like this:
         * JOIN {user_enrolments} ej1_ue ON ej1_ue.userid = u.id;
         * JOIN {enrol} ej1_e ON (ej1_e.id = ej1_ue.enrolid AND ej1_e.courseid = :ej1_courseid)
         * [wheres] => 1 = 1
         *   AND ej1_ue.status = :ej1_active
         *   AND ej1_e.status = :ej1_enabled
         *   AND ej1_ue.timestart < :ej1_now1
         *   AND (ej1_ue.timeend = 0 OR ej1_ue.timeend > :ej1_now2)
         *
         * [params] => Array
         *       (
         *           [ej1_courseid] => 2
         *           [ej1_enabled] => 0
         *           [ej1_active] => 0
         *           [ej1_now1] => 1577401300
         *           [ej1_now2] => 1577401300
         *       )
         */
        //
        // --This section adapted from enrollib.php::get_enrolled_join()
        // Build the query including our select clause.
        // Use MAX and GROUP BY in case there are multiple user-enrolments.
        $sql = "
                SELECT  {$prefix}ue.id, max({$prefix}ue.timestart)
                FROM    {user} u
                {$sqljoin->joins}
                WHERE {$sqljoin->wheres}
                GROUP BY {$prefix}ue.id
                ";
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Built sql={$sql} with params=" . print_r($params, true));

        $enrolmentinfo = $DB->get_record_sql($sql, $sqljoin->params, IGNORE_MULTIPLE);
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got $userEnrolmentInfo=' . print_r($enrolmentinfo, true));

        if (!$enrolmentinfo) {
            block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Failed to find an active user_enrolment.id for userid={$userid} and context={$context->id} with SQL={$sql}");
            // Return a guaranteed-invalid userid.
            return -1;
        }

        return $enrolmentinfo->id;
    }

    /**
     * Parse the triggered event and decide if and how to act on it.
     * User logout and other course events lead to the user's remote IA sessions being closed
     *
     * @param \core\event\base $event Event to maybe act on
     * @return true if attempted to close the remote IA session; else false
     */
    public static function process_events(\core\event\base $event) {
        $debug = false;
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

        // On logout, close all IA sessions. Note: This is a read event not a create/update.
        if ($event->eventname == '\\core\\event\\user_loggedout') {
            self::close_all_user_sessions($event);
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
                '\\assignsubmission_onlinetext\\event\\submission_updated',
                '\\core\\event\\course_module_completion_updated', /* Course activity completion updated */
                '\\mod_assign\\event\\assessable_submitted',
                '\\mod_choice\\event\\answer_created',
                '\\mod_feedback\\event\\response_submitted',
                '\\mod_lesson\\event\\lesson_ended',
                '\\mod_quiz\\event\\attempt_abandoned',
                '\\mod_quiz\\event\\attempt_submitted',
            )):
                block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::This eventname is whitelisted so act on it; debuginfo={$debuginfo}");
                break;
            default:
                $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::This eventname is not in the whitelist to be acted on, so skip it; debuginfo={$debuginfo}");
                return false;
        }

        // If it's not in the whitelist, make sure
        // (a) this is a create or update event; and
        // (b) this is an activity-level event
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
         * Note \core\event\user_graded events are CONTEXT_MODULE=50
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
     *
     * Assumes the event is a course-activity event with a userid.
     *
     * @param \core\event\base $event Event to maybe act on
     * @return true if attempted to close the remote IA session; else false
     */
    static function close_all_user_sessions(\core\event\base $event) {
        $debug = false;
        $debuginfo = "eventname={$event->eventname}; crud={$event->crud}; courseid={$event->courseid}; userid={$event->userid}";
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Started with \$debuginfo={$debuginfo}");

        // Gets visible blocks.
        $blockinstances = block_integrityadvocate_get_all_blocks();
        if (empty($blockinstances)) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::No integrityadvocate block instances found, so skip this task.');
            return true;
        }

        $success = false;

        // For each IA block instance, process IA data and update the activity completion status accordingly.
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
        $debug = false;
        $debuginfo = "eventname={$event->eventname}; crud={$event->crud}; courseid={$event->courseid}; userid={$event->userid}";
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Started with \$debuginfo={$debuginfo}");

        $context = $event->get_context();
        if (!is_enrolled($context, null, null, true)) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::The user has no active enrolment in this course-activity so skip it; debuginfo={$debuginfo}");
            return false;
        }
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::The user has an active enrolment in this course-activity so continue');

        // Find the user-enrolment-id, which is what the IntegrityAdvocate API uses.
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::About to get_ueid');
        $ueid = self::get_ueid($context, $event->userid);
        if ($ueid < 0) {
            block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Failed to find an active user_enrolment.id for userid and context={$$event->contextid}; debuginfo={$debuginfo}");
            return false;
        }
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Found ueid={$ueid}");

        // Get the appid from plugin instance config: 3 parts:
        // ...(1) Get the associated course module contextid.
        $activitycontextid = $event->contextid;
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Found contextid={$activitycontextid}");

        // ...(2) Find the non-hidden proctor blocks associated with this module.
        // Abstracted $blockname to reduce errors in case the block name changes.
        // Remove the block_ prefix and _observer suffix.
        $blockname = implode('_', array_slice(explode('_', substr(__CLASS__, strrpos(__CLASS__, '\\') + 1)), 1, -1));
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Found blockname={$blockname}");
        // Summarize what we have figured out so far.
        $debuginfo .= "; ueid={$ueid}";
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Maybe we should ask the IA API to close the session; {$debuginfo}");

        // (3) See if an IA block instance is present and visible.
        list($unused, $blockinstance) = block_integrityadvocate_get_ia_block($context);
        if (!$blockinstance) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::The block is not present or not visible, so skip it");
            return false;
        }

        return self::close_session($blockinstance, $event->userid);
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
        $debug = false;
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
