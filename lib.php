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
 * IntegrityAdvocate block common configuration and helper functions
 *
 * Some code in this file comes from block_completion_progress
 * https://moodle.org/plugins/block_completion_progress
 * with full credit and thanks due to Michael de Raadt.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use block_integrityadvocate\Api as ia_api;
use block_integrityadvocate\Logger as Logger;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

\defined('MOODLE_INTERNAL') || die;

$blockintegrityadvocatewwwroot = \dirname(__FILE__, 3);
require_once($blockintegrityadvocatewwwroot . '/user/lib.php');
require_once($blockintegrityadvocatewwwroot . '/lib/filelib.php');
require_once($blockintegrityadvocatewwwroot . '/lib/completionlib.php');
// Used for Monolog, which is caled in MoodleUtility.php::log().
require_once(__DIR__ . '/vendor/autoload.php');

require_once(__DIR__ . '/classes/polyfills.php');
require_once(__DIR__ . '/classes/Utility.php');
require_once(__DIR__ . '/classes/MoodleUtility.php');
require_once(__DIR__ . '/classes/HTTPException.php');
require_once(__DIR__ . '/classes/Output.php');
require_once(__DIR__ . '/classes/Status.php');
require_once(__DIR__ . '/classes/Api.php');
require_once(__DIR__ . '/classes/Flag.php');
require_once(__DIR__ . '/classes/Participant.php');
require_once(__DIR__ . '/classes/Session.php');

/** @var string Short name for this plugin. */
const INTEGRITYADVOCATE_SHORTNAME = 'integrityadvocate';

/** @var string Longer name for this plugin. */
const INTEGRITYADVOCATE_BLOCK_NAME = 'block_integrityadvocate';

/** @var string Base url for the LTI endpoint with no trailing slash. */
const INTEGRITYADVOCATE_BASEURL_API = 'https://ca.integrityadvocateserver.com';

/** @var string Path relative to baseurl of the API with no trailing slash. */
const INTEGRITYADVOCATE_API_PATH = '/api';

/** @var string Base url for the API with no trailing slash. */
const INTEGRITYADVOCATE_BASEURL_LTI = 'https://www.integrityadvocateserver.com';

/** @var string Path relative to baseurl of the LTI endpoint with no trailing slash. */
const INTEGRITYADVOCATE_LTI_PATH = '/lti';

/** @var string Email address for privacy api data cleanup requests */
const INTEGRITYADVOCATE_PRIVACY_EMAIL = 'admin@integrityadvocate.com';

/** @var string Regex to check a string is a Data URI ref ref https://css-tricks.com/data-uris/. */
const INTEGRITYADVOCATE_REGEX_DATAURI = '#data:image.?\/[a-zA-z-]*;base64,\s*[^"\s$]*#';

/** @var string String part to denote a session started key */
const INTEGRITYADVOCATE_SESSION_STARTED_KEY = 'session_started';

/**
 * Get participants in this block context.
 * Returns empty array if not a block context, if the block is missing APIKey/AppId, or if no participants found.
 *
 * @param \context $blockcontext Block context to get IA Participants data for.
 * @return array<block_integrityadvocate\Participant> Array of Participant objects.
 */
function block_integrityadvocate_get_participants_for_blockcontext(\context $blockcontext): array {
    $fxn = Logger::NONAMESPACE_FUNCTION_PREFIX . Logger::filepath_relative_to_plugin(__FILE__) . '::' . __FUNCTION__;
    $debug = false || Logger::do_log_for_function($fxn);
    $debug && Logger::log($fxn . '::Started with $context=' . ia_u::var_dump($blockcontext, true));

    // We only have user data where the block_integrityadvocate is added to a module.
    // In these cases we have existing code to get the user data from the blockinstance.
    if ($blockcontext->contextlevel !== CONTEXT_BLOCK) {
        return [];
    }

    $blockinstance = \block_instance_by_id($blockcontext->instanceid);

    // We cannot get data from the remote API without an APIKey and AppId.
    if (ia_u::is_empty($blockinstance) || !($blockinstance instanceof \block_integrityadvocate) || $blockinstance->get_apikey_appid_errors()) {
        return [];
    }

    $coursecontext = $blockcontext->get_course_context();

    // Get IA participant data from the remote API.
    $participants = ia_api::get_participants($blockinstance->config->apikey, $blockinstance->config->appid, $coursecontext->instanceid);
    $debug && Logger::log($fxn . '::Got count($participants)=' . ia_u::count_if_countable($participants));

    return $participants;
}

/**
 * Get all IA sessions for all participants in the course.
 *
 * @param string $apikey
 * @param string $appid
 * @param int $courseid
 * @return array<Session>, e.g.
 * (
 *    [<session_id>=04fda967-25df-4ce0-945f-72a244b862de] => block_integrityadvocate\Session Object (
 *            [activityid] => 2
 *            [clickiamherecount] =>
 *            [end] => 1605913685
 *            [exitfullscreencount] =>
 *            [id] => 04fda967-25df-4ce0-945f-72a244b862de
 *            [overridedate] => -1
 *            [overridelmsuserfirstname] =>
 *            [overridelmsuserid] =>
 *            [overridelmsuserlastname] =>
 *            [overridereason] =>
 *            [overridestatus] =>
 *            [participantphoto] => redacted_base64_image
 *            [resubmiturl] =>
 *            [start] => 1605913620
 *            [status] => 0
 *            [participant] => block_integrityadvocate\Participant Object (
 *                    [courseid] => 2
 *                    [created] => -1
 *                    [email] => vhmark@gmail.com
 *                    [firstname] => Mark
 *                    [lastname] => van Hoek
 *                    [modified] => -1
 *                    [overridedate] => -1
 *                    [overridelmsuserfirstname] =>
 *                    [overridelmsuserid] =>
 *                    [overridelmsuserlastname] =>
 *                    [overridereason] =>
 *                    [overridestatus] =>
 *                    [participantidentifier] => 3
 *                    [participantphoto] =>
 *                    [resubmiturl] =>
 *                    [sessions] => Array()
 *                    [status] =>
 *                )
 *            [flags] => Array()
 *        ), ...
 * )
 */
function block_integrityadvocate_get_course_sessions(string $apikey, string $appid, int $courseid) {
    $debug = false || Logger::do_log_for_function(__FILE__ . '::' . __FUNCTION__);
    $fxn = __FILE__ . '::' . __FUNCTION__;
    $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid};";
    $debug && Logger::log($debugvars);

    $modules = block_integrityadvocate_get_course_ia_modules($courseid, ['configured' => 1, 'appid' => $appid]);
    $debug && Logger::log($fxn . '::Got $modules=' . ia_u::var_dump($modules));

    $participantsessions = [];
    foreach ($modules as $m) {
        $debug && Logger::log($fxn . '::Looking at moduleid=' . $m['id']);
        // Disabled on purpose: $debug && Logger::log($fxn . '::Looking at module=' . ia_u::var_dump($m));
        // Get participant sessions for all users.
        $modulesessions = ia_api::get_participantsessions($apikey, $appid, $courseid, $m['id']);
        $debug && Logger::log($fxn . '::Got $modulesessions=' . ia_u::var_dump($modulesessions));
        $participantsessions = \array_merge($participantsessions, $modulesessions);
    }

    $debug && Logger::log($fxn . '::About to return $participantsessions=' . ia_u::var_dump($participantsessions));
    return $participantsessions;
}

/**
 * Get the participants' latest sessions.  Note the participants are only stubs.
 *
 * @param string $apikey The API key.
 * @param string $appid The App ID.
 * @param int $courseid The course id.
 * @return array Array of Participants, each with the sessions attribute sorted by start date ascending.
 */
function block_integrityadvocate_get_latest_participant_sessions(string $apikey, string $appid, int $courseid) {
    $debug = false || Logger::do_log_for_function(__FILE__ . '::' . __FUNCTION__);
    $fxn = __FILE__ . '::' . __FUNCTION__;
    $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid};";
    $debug && Logger::log($debugvars);

    $participantsessions = block_integrityadvocate_get_course_sessions($apikey, $appid, $courseid);
    $debug && Logger::log($fxn . '::Got $participantsessions=' . ia_u::var_dump($participantsessions));

    // Invert the array so sessions are collected for each participant.
    $participants = [];
    foreach ($participantsessions as $s) {
        Logger::log($fxn . '::Looking at $s=' . ia_u::var_dump($s));
        if (!isset($participants[$s->participant->participantidentifier]) || ia_u::is_empty($thisparticipant = $participants[$s->participant->participantidentifier])) {
            $thisparticipant = $s->participant;
            $participants[$s->participant->participantidentifier] = $thisparticipant;
        }

        if (isset($thisparticipant->sessions[$s->id])) {
            $msg = $fxn . "::Attempting to overwrite an existing session (id={$s->id}) -- this should not happen";
            Logger::log($fxn . "::{$msg}; \$participantsessions=" . ia_u::var_dump($participantsessions));
            throw new Exception($msg);
        }

        $thisparticipant->sessions[$s->id] = $s;
    }
    $debug && Logger::log($fxn . '::Built $participants=' . ia_u::var_dump($participants));

    // Sort each participant's sessions.
    foreach ($participants as &$p) {
        $debug && Logger::log($fxn . "::Find latest session: Looking at \$p->participantidentifier={$p->participantidentifier}");
        \usort($p->sessions, ['\\' . INTEGRITYADVOCATE_BLOCK_NAME . '\Utility', 'sort_by_start_desc']);
    }

    $debug && Logger::log($fxn . '::About to return $participants=' . ia_u::var_dump($participants));
    return $participants;
}

/**
 * Get the modules in this course that have a configured IA block attached...
 * Optionally filtered to IA blocks having a matching apikey and appid or visible.
 *
 * @param \stdClass|int $course The course to get modules from; if int the course object will be looked up.
 * @param array<key=val> $filter e.g. array('visible'=>1, 'appid'=>'blah', 'apikey'=>'bloo').
 * @return string|array Array of modules that match; else string error identifier.
 */
function block_integrityadvocate_get_course_ia_modules($course, $filter = []) {
    $fxn = Logger::NONAMESPACE_FUNCTION_PREFIX . Logger::filepath_relative_to_plugin(__FILE__) . '::' . __FUNCTION__;
    $debug = false || Logger::do_log_for_function($fxn);

    // Massage the course input if needed.
    $course = ia_mu::get_course_as_obj($course);
    if (!$course) {
        $debug && Logger::log($fxn . '::No $course specified');
        return 'no_course';
    }
    $debug && Logger::log($fxn . '::Started with courseid=' . $course->id . '; $filter=' . (empty($filter) ? '' : ia_u::var_dump($filter, true)));

    // Get modules in this course.
    $modules = ia_mu::get_modules_with_completion($course->id);
    if (empty($modules)) {
        $debug && Logger::log($fxn . '::No course modules found');
        return 'no_modules_message';
    }
    $debug && Logger::log($fxn . '::Found ' . ia_u::count_if_countable($modules) . ' modules in this course');

    // Filter for modules that use an IA block.
    $modules = block_integrityadvocate_filter_modules_use_ia_block($modules, $filter);
    $debug && Logger::log($fxn . '::Found ' . ia_u::count_if_countable($modules) . ' modules that use IA');

    if (!$modules) {
        return 'no_modules_config_message';
    }

    return $modules;
}

/**
 * Filter the input Moodle modules array for ones that use an IA block.
 *
 * @param array $modules Array of \stdClass course modules to check.
 * @param array $filter e.g. array('visible'=>1, 'appid'=>'blah', 'apikey'=>'bloo').
 * @return array Array of course modules, each as an array. E.g. each entry is like this:
 *  (
 *   [type] => quiz
 *   [modulename] => Quiz
 *   [id] => 2
 *   [instance] => 1
 *   [name] => My Quiz
 *   [expected] => 0
 *   [section] => 0
 *   [position] => 1
 *   [url] => https://moodle_wwwroot/mod/quiz/view.php?id=2
 *   [context] => context_module Object
 *       (
 *           [_id:protected] => 29
 *           [_contextlevel:protected] => 70
 *           [_instanceid:protected] => 2
 *           [_path:protected] => /1/3/25/29
 *           [_depth:protected] => 4
 *           [_locked:protected] => 0
 *       )
 *   [available] =>
 *   [block_integrityadvocate_instance] => Array
 *       (
 *           [id] => 23
 *           [instance] => block_integrityadvocate Object
 *               (
 *                   [str] =>
 *                   [title] => Integrity Advocate
 *                   [arialabel] =>
 *                   [content_type] => 2
 *                   [content] =>
 *                   [instance] => stdClass Object
 *                       (
 *                           [id] => 23
 *                           [blockname] => integrityadvocate
 *                           [parentcontextid] => 29
 *                           [showinsubcontexts] => 0
 *                           [requiredbytheme] => 0
 *                           [pagetypepattern] => mod-quiz-*
 *                           [subpagepattern] =>
 *                           [defaultregion] => side-pre
 *                           [defaultweight] => 0
 *                           [configdata] => ...=
 *                           [timecreated] => 1605749308
 *                           [timemodified] => 1605749308
 *                       )
 *                   [page] => ...
 *  )
 */
function block_integrityadvocate_filter_modules_use_ia_block(array $modules, $filter = []): array {
    $fxn = Logger::NONAMESPACE_FUNCTION_PREFIX . Logger::filepath_relative_to_plugin(__FILE__) . '::' . __FUNCTION__;
    $debug = false || Logger::do_log_for_function($fxn);
    $debug && Logger::log($fxn . '::Started with ' . ia_u::count_if_countable($modules) . ' modules; $filter=' . ($filter ? ia_u::var_dump($filter, true) : ''));

    // Since we update the modules in this loop, the $m is purposely by reference.
    foreach ($modules as $key => &$m) {
        $debug && Logger::log($fxn . '::Looking at module=' . ia_u::var_dump($m));
        $modulecontext = $m['context'];
        $blockinstance = ia_mu::get_first_block($modulecontext, INTEGRITYADVOCATE_SHORTNAME, isset($filter['visible']) && (bool) $filter['visible']);

        // No block instances found for this module, so remove it.
        if (ia_u::is_empty($blockinstance)) {
            unset($modules[$key]);
            continue;
        }

        $blockinstanceid = $blockinstance->instance->id;
        $debug && Logger::log($fxn . '::After block_integrityadvocate_get_ia_block() got $blockinstanceid=' . $blockinstanceid . '; $blockinstance->instance->id=' . (ia_u::is_empty($blockinstance) ? '' : $blockinstance->instance->id));

        // Init the result to false.
        if (isset($filter['configured']) && $filter['configured'] && $blockinstance->get_config_errors()) {
            $debug && Logger::log($fxn . '::This blockinstance is not fully configured');
            unset($modules[$key]);
            continue;
        }
        $debug && Logger::log($fxn . '::The blockinstance is configured');

        $requireapikey = false;
        if (isset($filter['apikey']) && $filter['apikey']) {
            $requireapikey = $filter['apikey'];
        }

        $requireappid = false;
        if (isset($filter['appid']) && $filter['appid']) {
            $requireappid = $filter['appid'];
        }
        if ($requireapikey || $requireappid) {
            // Filter for modules with matching apikey and appid.
            $debug && Logger::log($fxn . '::Looking to filter for apikey and appid');

            if ($requireapikey && ($blockinstance->config->apikey !== $requireapikey)) {
                $debug && Logger::log($fxn . '::Found $blockinstance->config->apikey=' . $blockinstance->config->apikey . ' does not match requested apikey=' . $requireapikey);
                unset($modules[$key]);
                continue;
            }
            if ($requireappid && ($blockinstance->config->appid !== $requireappid)) {
                $debug && Logger::log($fxn . '::Found $blockinstance->config->apikey=' . $blockinstance->config->apikey . ' does not match requested appid=' . $requireappid);
                unset($modules[$key]);
                continue;
            }
            $debug && Logger::log($fxn . '::After filtering for apikey/appid, count($modules)=' . ia_u::count_if_countable($modules));
        }

        // Add the blockinstance data to the $amodules array to be returned.
        $m['block_integrityadvocate_instance']['id'] = $blockinstanceid;
        $m['block_integrityadvocate_instance']['instance'] = $blockinstance;
    }

    return $modules;
}
