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
 * IntegrityAdvocate block capability setup
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate\task;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__, 3) . '/lib.php');
require_once(dirname(__FILE__, 5) . '/lib/completionlib.php');

/**
 * IntegrityAdvocate block capability setup
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_integrityadvocate extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('process_integrityadvocate', INTEGRITYADVOCATE_BLOCKNAME);
    }

    /**
     * Do the job.

     * @throws Exception The job will be retried.
     */
    public function execute() {
        global $SITE;
        $debug = true;

        /*
         * To send logs to the screen instead of PHP error log:
         * --
         * global $block_integrityadvocate_log_dest;
         * $block_integrityadvocate_log_dest = INTEGRITYADVOCATE_LOGDEST_MLOG;
         */
        $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . '::Started');

        // Check if completion is setup at the site level.
        if (\IntegrityAdvocate_Moodle_Utility::get_completion_setup_errors()) {
            $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . '::Completion is not set up at the site level, so skip this task.');
            return true;
        }

        $scheduledtask = \core\task\manager::get_scheduled_task(INTEGRITYADVOCATE_TASKNAME);
        $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . '::For $taskname=' . INTEGRITYADVOCATE_TASKNAME . ' got $scheduled_task=' . print_r($scheduledtask,
                                true));

        // Workaround: block_integrityadvocate_to_apitimezone() returns the string for unix zero time if passed $scheduledtask->get_last_run_time() directly.
        $lastruntime = $scheduledtask->get_last_run_time();
        $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . '::For $lastruntime=' . $lastruntime);

        // We have to use the API's timezone for the field lastmodified.
        // It must be converted to the API timezone, but this is done in the foreach loop below.
        $params = array('lastmodified' => max($lastruntime, $SITE->timecreated));
        $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . '::Built params=' . print_r($params, true));

        // Gets visible blocks.
        $blockinstances = \IntegrityAdvocate_Moodle_Utility::get_all_blocks(INTEGRITYADVOCATE_SHORTNAME);
        if (empty($blockinstances)) {
            $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . '::No integrityadvocate block instances found, so skip this task.');
            return true;
        }
        $msg = 'Found ' . count($blockinstances) . ' blockinstances total; will process those that are configured and added to an activity';
        mtrace($msg);
        if ($debug) {
            \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . '::' . $msg);
            // Disabled on purpose: \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . '::blockinstances=' . print_r($blockinstances, true));.
        }

        // Used to skip courses not modified since this many seconds.
        // Seven days.
        $courselastmodifiedmax = 60 * 60 * 24 * 7;

        // For each IA block instance, process IA data and update the activity completion status accordingly.
        foreach ($blockinstances as $b) {
            $blockcontext = $b->context;
            $modulecontext = $blockcontext->get_parent_context();
            $coursecontext = $blockcontext->get_course_context();
            $debuglooplevel1 = 'blockinstance';
            $courseid = $coursecontext->instanceid;
            $debugblockidentifier = 'courseid=' . $courseid . '; moduleid=' . $modulecontext->instanceid . '; blockinstanceid=' . $b->instance->id;
            $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::{$debuglooplevel1}:Looking at {$debugblockidentifier}");

            $courselastmodified = \IntegrityAdvocate_Moodle_Utility::get_course_lastaccess($courseid);
            if ($courselastmodified < (time() - $courselastmodifiedmax)) {
                $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::{$debuglooplevel1}:{$debugblockidentifier}: This course has not been modified in 7 days, so skip it");
                continue;
            }

            // Check if completion is setup at the course level.
            if (\IntegrityAdvocate_Moodle_Utility::get_completion_setup_errors($courseid)) {
                $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::{$debuglooplevel1}:{$debugblockidentifier}: This courses completion is not setup at the course level, so skip it");
                continue;
            }

            // Only process module-level blocks.
            if ($modulecontext->contextlevel != CONTEXT_MODULE) {
                // The context may also be CONTEXT_SITE or CONTEXT_COURSE, for example.
                $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::{$debuglooplevel1}:{$debugblockidentifier}: This is not a module-level block, so skip it");
                continue;
            }

            // Check the block has apikey and appid.
            if (!isset($b->config) || (!isset($b->config->apikey) && !isset($b->config->appid))) {
                $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::{$debuglooplevel1}:{$debugblockidentifier}: This block has no apikey and appid, so skip it");
                continue;
            }

            // Get course-module object so we can...
            // (a) check activity completion is enabled and...
            // (b) update its completion state for the user.
            $modinfo = get_fast_modinfo($courseid);
            // Note the cmid = $modulecontext->instance.
            $cm = $modinfo->get_cm($modulecontext->instanceid);

            if ($cm->completion == COMPLETION_TRACKING_NONE) {
                $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::{$debuglooplevel1}:{$debugblockidentifier}: Completion is disabled at the module level, so skip it");
                continue;
            }

            // Call the API with this block's API key and AppId - this returns cleaned participant data.
            $msg = 'About to get remote IA data for ' . $debugblockidentifier . ' since ' . $lastruntime;
            mtrace($msg);
            $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::$msg");

            // Get the course object so we can get timecreated and pass it to block_integrityadvocate_cron_single_user.
            $course = get_course($courseid);

            $params['lastmodified'] = \IntegrityAdvocate_Api::convert_to_apitimezone(max($params['lastmodified'],
                                    $b->instance->timecreated, $course->timecreated));
            $participants = \IntegrityAdvocate_Api::get_participant_data($b->config->apikey, $b->config->appid, $params);
            $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::{$debuglooplevel1}:{$debugblockidentifier}: Got participants=" . print_r($participants,
                                    true));
            if (empty($participants)) {
                $msg = 'No remote IA participant data returned';
                mtrace($msg);
                $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::{$debuglooplevel1}:{$debugblockidentifier}: {$msg}, so skip it");
                continue;
            }

            $msg = 'About to get process IA results for ' . count($participants) . ' participants';
            mtrace($msg);
            $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::$msg");

            $usersupdatedcount = 0;
            foreach ($participants as $p) {
                $usersupdatedcount += \block_integrityadvocate_cron_single_user($course, $modulecontext, $b, $p,
                        $debugblockidentifier);
                if ($debug && $usersupdatedcount > 0) {
                    \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::{$debuglooplevel1}:{$debugblockidentifier}: Updated {$usersupdatedcount} completion items");
                    $msg = "For IA participant {$p->ParticipantIdentifier} updated completion item";
                    mtrace($msg);
                    $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::$msg");
                }
            }

            $msg = "For course {$course->id} updated {$usersupdatedcount} completion items";
            mtrace($msg);
            $debug && \IntegrityAdvocate_Moodle_Utility::log(__FILE__ . '::' . __FUNCTION__ . "::$msg");

            // Reset the log output destination to default.
            // Disabled on purpose: $block_integrityadvocate_log_dest = INTEGRITYADVOCATE_LOGDEST_ERRORLOG;.
        }
    }

}
