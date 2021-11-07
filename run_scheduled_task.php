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
 * CLI task execution.
 * Adapted for web use instead of CLI use from Moodle 3.10 admin/tool/task/scheduled_task.php.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once("$CFG->libdir/cronlib.php");
require_once("$CFG->libdir/classes/task/manager.php");
require_once("$CFG->libdir/classes/task/logmanager.php");

require_admin();

ini_set('log_errors', '1');
date_default_timezone_set('America/Vancouver');
@error_reporting(E_ALL | E_STRICT); // NOT FOR PRODUCTION SERVERS!
$CFG->debug = (E_ALL | E_STRICT);   // === DEBUG_DEVELOPER - NOT FOR PRODUCTION SERVERS!
@ini_set('display_errors', '1');    // NOT FOR PRODUCTION SERVERS!
$CFG->debugdisplay = 1;             // NOT FOR PRODUCTION SERVERS!
$execute = preg_replace('/[^0-9a-zA-Z_\-\\]/', '', clean_param($_GET['execute'], PARAM_TEXT));
echo '<PRE>';
echo "Started\n";

if ($execute) {
    if (!$task = \core\task\manager::get_scheduled_task($execute)) {
        die("Task '{$execute}' not found");
    }

    if (moodle_needs_upgrading()) {
        echo('Moodle upgrade pending, cannot execute tasks.');
        exit(1);
    }

//    \core\task\manager::scheduled_task_starting($task);
    // Increase memory limit.
    raise_memory_limit(MEMORY_EXTRA);

    // Emulate normal session - we use admin account by default.
    //cron_setup_user();
    // Execute the task.
    //\core\local\cli\shutdown::script_supports_graceful_exit();
    $cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
    if (!$cronlock = $cronlockfactory->get_lock('core_cron', 10)) {
        die('Cannot obtain cron lock');
    }
    if (!$lock = $cronlockfactory->get_lock('\\' . get_class($task), 10, 60)) {
        $cronlock->release();
        die('Cannot obtain task lock');
    }

    $task->set_lock($lock);
    if (!$task->is_blocking()) {
        $cronlock->release();
    } else {
        $task->set_cron_lock($cronlock);
    }

    //cron_run_inner_scheduled_task($task);
//    \core\task\manager::scheduled_task_starting($task);
//    \core\task\logmanager::start_logging($task);

    $fullname = $task->get_name() . ' (' . get_class($task) . ')';
    mtrace('Execute scheduled task: ' . $fullname);
//    cron_set_process_title('Scheduled task: ' . get_class($task));
//    cron_trace_time_and_memory();
//    $predbqueries = null;
//    $predbqueries = $DB->perf_get_queries();
    $pretime = microtime(true);
    try {
        get_mailer('buffer');
        cron_prepare_core_renderer();
        $task->execute();
        if ($DB->is_transaction_started()) {
            throw new coding_exception('Task left transaction open');
        }
        if (isset($predbqueries)) {
            mtrace("... used " . ($DB->perf_get_queries() - $predbqueries) . ' dbqueries');
            mtrace("... used " . (microtime(true) - $pretime) . ' seconds');
        }
        mtrace('Scheduled task complete: ' . $fullname);
        \core\task\manager::scheduled_task_complete($task);
    } catch (Exception $e) {
        if ($DB && $DB->is_transaction_started()) {
            error_log('Database transaction aborted automatically in ' . get_class($task));
            $DB->force_transaction_rollback();
        }
        if (isset($predbqueries)) {
            mtrace("... used " . ($DB->perf_get_queries() - $predbqueries) . ' dbqueries');
            mtrace("... used " . (microtime(true) - $pretime) . ' seconds');
        }
        mtrace('Scheduled task failed: ' . $fullname . ',' . $e->getMessage());
        if ($CFG->debugdeveloper) {
            if (!empty($e->debuginfo)) {
                mtrace('Debug info:');
                mtrace($e->debuginfo);
            }
            mtrace('Backtrace:');
            mtrace(format_backtrace($e->getTrace(), true));
        }
        \core\task\manager::scheduled_task_failed($task);
    } finally {
        // Reset back to the standard admin user.
        //cron_setup_user();
        //cron_set_process_title('Waiting for next scheduled task');
        cron_prepare_core_renderer(true);
    }
    get_mailer('close');
}
echo 'done';
echo '</PRE>';
