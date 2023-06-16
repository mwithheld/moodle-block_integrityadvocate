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
use block_integrityadvocate\Api as ia_api;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/integrityadvocate/lib.php');

/**
 * On module create/update events that correspond to finishing a module, close the remote IA session.
 *
 * @copyright IntegrityAdvocate.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_integrityadvocate_observer {

    /**
     * Parse the triggered event and decide if and how to act on it.
     * User logout and other course events lead to the user's remote IA sessions being closed.
     *
     * @param \core\event\base $event Event to maybe act on
     * @return bool True if attempted to close the remote IA session; else false.
     */
    public static function process_event(\core\event\base $event): bool {
        $debug = false;
        $debuginfo = "eventname={$event->eventname}; crud={$event->crud}; courseid={$event->courseid}; userid={$event->userid}";

        // Disabled on purpose: $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . '::Started with event=' . ia_u::var_dump($event, true));.
        $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . "::Started with \$debuginfo={$debuginfo}; event->crud={$event->crud}; "
                        . "is c/u=" . (\in_array($event->crud, ['c', 'u'], true)));
        $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . "::Started with event->contextlevel={$event->contextlevel}; "
                        . "is_contextlevelmatch=" . ($event->contextlevel === CONTEXT_MODULE));

        // No CLI events correspond to a user finishing an IA session.
        if (\defined('CLI_SCRIPT') && CLI_SCRIPT) {
            $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . "::Started with event->crud={$event->crud}; crud match=" . (\in_array($event->crud, ['c', 'u'], true)));
            return false;
        }

        // If there is no user attached to this event, we can't close the user's IA session, so skip.
        if (!\is_numeric($event->userid)) {
            $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . "::The event has no user info so skip it; debuginfo={$debuginfo}");
            return false;
        }

        // Make sure this is a module-level event.
        // Note \core\event\user_graded events are contextlevel=50, but there are other events that should close the IA session.
        $iscoursemodulechangeevent = ( $event->contextlevel === CONTEXT_MODULE && \is_numeric($event->courseid) && $event->courseid != SITEID );
        if (!$iscoursemodulechangeevent) {
            $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . "::This is not a module-level event so skip it; debuginfo={$debuginfo}");
            return false;
        }
        $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . '::This is a module-level event, so continue');

        return self::close_module_user_session($event);
    }

    /**
     * Parse out event info to get the related IA block, module, course, and user.
     * Then close the remote IA session.
     *
     * Assumes the event is a course-module event with a userid.
     *
     * @param \core\event\base $event Event to maybe act on
     * @return bool True if attempted to close the remote IA session; else false.
     */
    protected static function close_module_user_session(\core\event\base $event): bool {
        $debug = false;
        $debuginfo = "eventname={$event->eventname}; crud={$event->crud}; courseid={$event->courseid}; userid={$event->userid}";
        $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . "::Started with \$debuginfo={$debuginfo}");

        if (!($blockinstance = self::check_should_close_user_ia($event))) {
            return false;
        }

        $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . "::About to close_session() for \$debuginfo={$debuginfo}");
        return self::close_session($blockinstance, $event->userid);
    }

    /**
     * Figure out from the event info if we should close the remote IA session.
     * Checks the user is enrolled and the block is visible.
     *
     * @param \core\event\base $event Triggered event.
     * @return block_integrityadvocate Null if should not close the remote IA session; else returns the $blockinstance.
     */
    protected static function check_should_close_user_ia(\core\event\base $event): ?\block_integrityadvocate {
        $debug = false;

        $modulecontext = $event->get_context();
        if ($modulecontext->contextlevel != CONTEXT_MODULE) {
            $msg = 'The passed-in event is not from a module context level';
            debugging(__CLASS__ . '::' . __FUNCTION__ . "::{$msg}");
            throw new \InvalidArgumentException($msg);
        }

        if (!\is_enrolled($modulecontext, $event->userid, '', true)) {
            $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . '::The user has no active enrolment in this course-module so skip it');
            return null;
        }
        $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . '::The user has an active enrolment in this course-module so continue');

        // Make sure an IA block instance is present and visible.
        $blockinstance = ia_mu::get_first_block($modulecontext, \INTEGRITYADVOCATE_SHORTNAME);
        if (!$blockinstance || $blockinstance->get_config_errors()) {
            $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . '::The block is not present or not visible, or has config errors, so skip it');
            return null;
        }

        // Instructors do not get the proctoring UI so never need to close the session.
        $hascapabilityoverview = \has_capability('block/integrityadvocate:overview', $modulecontext);
        if ($hascapabilityoverview) {
            return null;
        }

        return $blockinstance;
    }

    /**
     * Actually close the remote IA session.
     * Assumes the user is enrolled in the block's parent context
     *
     * @param \block_integrityadvocate $blockinstance to close sessions for
     * @param int $userid The Moodle userid to close the session for
     * @return bool true if remote session is closed; else false.
     */
    protected static function close_session(\block_integrityadvocate $blockinstance, int $userid): bool {
        $debug = false;
        $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . '::Started');

        $appid = isset($blockinstance->config->appid) ? \trim($blockinstance->config->appid) : false;
        $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . "::Found appid={$appid}");
        if (!$appid) {
            $debug && debugging(__CLASS__ . '::' . __FUNCTION__ . '::The block instance has no appid configured, so skip it');
            return false;
        }

        $blockcontext = $blockinstance->context;
        $modulecontext = $blockcontext->get_parent_context();
        $coursecontext = $blockcontext->get_course_context();

        return ia_api::close_remote_session($appid, $coursecontext->instanceid, $modulecontext->instanceid, $userid);
    }
}
