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
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

$blockintegrityadvocatewwwroot = dirname(__FILE__, 3);
require_once($blockintegrityadvocatewwwroot . '/user/lib.php');
require_once($blockintegrityadvocatewwwroot . '/lib/filelib.php');
require_once($blockintegrityadvocatewwwroot . '/lib/completionlib.php');
// Used for Monolog, which is caled in MoodleUtility.php::log().
require_once(__DIR__ . '/vendor/autoload.php');

require_once(__DIR__ . '/classes/polyfills.php');
require_once(__DIR__ . '/classes/Utility.php');
require_once(__DIR__ . '/classes/MoodleUtility.php');
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

/** @var string Base url for the API with no trailing slash. */
const INTEGRITYADVOCATE_BASEURL = 'https://ca.integrityadvocateserver.com';

/** @var string Path relative to baseurl of the API with no trailing slash. */
const INTEGRITYADVOCATE_API_PATH = '/api';

/** @var string Email address for privacy api data cleanup requests */
const INTEGRITYADVOCATE_PRIVACY_EMAIL = 'admin@integrityadvocate.com';

/** @var string Regex to check a string is a Data URI ref ref https://css-tricks.com/data-uris/. */
const INTEGRITYADVOCATE_REGEX_DATAURI = '#data:image\/[a-zA-z-]*;base64,\s*[^"\s$]*#';

/** @var string String part to denote a session started key */
const INTEGRITYADVOCATE_SESSION_STARTED_KEY = 'session_started';

/**
 * Get participants in this block context.
 * Returns empty array if not a block context, if the block is missing APIKey/AppId, or if no participants found.
 *
 * @param \context $blockcontext Block context to get IA Participants data for.
 * @return array<\Participant> Array of Participant objects.
 */
function block_integrityadvocate_get_participants_for_blockcontext(\context $blockcontext): array {
    $debug = false;
    $fxn = __CLASS__ . '::' . __FUNCTION__;
    $debug && ia_mu::log($fxn . '::Started with $context=' . ia_u::var_dump($blockcontext, true));

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
    $debug && ia_mu::log($fxn . '::Got count($participants)=' . ia_u::count_if_countable($participants));

    return $participants;
}

/**
 * Get the modules in this course that have a configured IA block attached
 * optionally filtered to IA blocks having a matching apikey and appid or visible
 *
 * @param \stdClass|int $course The course to get modules from; if int the course object will be looked up
 * @param array<key=val> $filter e.g. array('visible'=>1, 'appid'=>'blah', 'apikey'=>'bloo')
 * @return string|array Array of modules that match; else string error identifier
 */
function block_integrityadvocate_get_course_ia_modules($course, $filter = []) {
    $debug = false;

    // Massage the course input if needed.
    $course = ia_mu::get_course_as_obj($course);
    if (!$course) {
        return 'no_course';
    }
    $debug && ia_mu::log(__FILE__ . '::' . __FUNCTION__ . '::Started with courseid=' . $course->id . '; $filter=' . (empty($filter) ? '' : ia_u::var_dump($filter, true)));

    // Get modules in this course.
    $modules = ia_mu::get_modules_with_completion($course->id);
    if (empty($modules)) {
        return 'no_modules_message';
    }
    $debug && ia_mu::log(__FILE__ . '::' . __FUNCTION__ . '::Found ' . ia_u::count_if_countable($modules) . ' modules in this course');

    // Filter for modules that use an IA block.
    $modules = block_integrityadvocate_filter_modules_use_ia_block($modules, $filter);
    $debug && ia_mu::log(__FILE__ . '::' . __FUNCTION__ . '::Found ' . ia_u::count_if_countable($modules) . ' modules that use IA');

    if (!$modules) {
        return 'no_modules_config_message';
    }

    return $modules;
}

/**
 * Filter the input Moodle modules array for ones that use an IA block.
 *
 * @param array<\stdClass> $modules Course modules to check
 * @param array<key=val> $filter e.g. array('visible'=>1, 'appid'=>'blah', 'apikey'=>'bloo')
 * @return array<\stdClass> of course modules.
 */
function block_integrityadvocate_filter_modules_use_ia_block(array $modules, $filter = []): array {
    $debug = false;
    $debug && ia_mu::log(__FILE__ . '::' . __FUNCTION__ . '::Started with ' . ia_u::count_if_countable($modules) . ' modules; $filter=' . ($filter ? ia_u::var_dump($filter, true) : ''));

    foreach ($modules as $key => $m) {
        // Disabled on purpose: $debug &&ia_mu::log(__FILE__ . '::' . __FUNCTION__ . '::Looking at module with url=' . $a->url);.
        $modulecontext = $m['context'];
        $blockinstance = ia_mu::get_first_block($modulecontext, INTEGRITYADVOCATE_SHORTNAME, isset($filter['visible']) && (bool) $filter['visible']);

        // No block instances found for this module, so remove it.
        if (ia_u::is_empty($blockinstance)) {
            unset($modules[$key]);
            continue;
        }

        $blockinstanceid = $blockinstance->instance->id;
        $debug && ia_mu::log(__FILE__ . '::' . __FUNCTION__ . '::After block_integrityadvocate_get_ia_block() got $blockinstanceid=' . $blockinstanceid . '; $blockinstance->instance->id=' . (ia_u::is_empty($blockinstance) ? '' : $blockinstance->instance->id));

        // Init the result to false.
        if (isset($filter['configured']) && $filter['configured'] && $blockinstance->get_config_errors()) {
            $debug && ia_mu::log(__FILE__ . '::' . __FUNCTION__ . '::This blockinstance is not fully configured');
            unset($modules[$key]);
            continue;
        }

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
            $debug && ia_mu::log(__FILE__ . '::' . __FUNCTION__ . '::Looking to filter for apikey and appid');

            if ($requireapikey && ($blockinstance->config->apikey !== $requireapikey)) {
                $debug && ia_mu::log(__FILE__ . '::' . __FUNCTION__ . '::Found $blockinstance->config->apikey=' . $blockinstance->config->apikey . ' does not match requested apikey=' . $apikey);
                unset($modules[$key]);
                continue;
            }
            if ($requireappid && ($blockinstance->config->appid !== $requireappid)) {
                $debug && ia_mu::log(__FILE__ . '::' . __FUNCTION__ . '::Found $blockinstance->config->apikey=' . $blockinstance->config->apikey . ' does not match requested appid=' . $appid);
                unset($modules[$key]);
                continue;
            }
            $debug && ia_mu::log(__FILE__ . '::' . __FUNCTION__ . '::After filtering for apikey/appid, count($modules)=' . ia_u::count_if_countable($modules));
        }

        // Add the blockinstance data to the $amodules array to be returned.
        $modules[$key]['block_integrityadvocate_instance']['id'] = $blockinstanceid;
        $modules[$key]['block_integrityadvocate_instance']['instance'] = $blockinstance;
    }

    return $modules;
}

/**
 * Compares two table row elements for ordering.
 *
 * @param  mixed $a element containing name, online time and progress info
 * @param  mixed $b element containing name, online time and progress info
 * @return order of pair expressed as -1, 0, or 1
 */
function block_integrityadvocate_compare_rows($a, $b): int {
    global $sort;

    // Process each of the one or two orders.
    $orders = explode(', ', $sort);
    foreach ($orders as $order) {

        // Extract the order information.
        $orderelements = explode(' ', trim($order));
        $aspect = $orderelements[0];
        $ascdesc = $orderelements[1];

        // Compensate for presented vs actual.
        switch ($aspect) {
            case 'name':
                $aspect = 'lastname';
                break;
            case 'lastaccess':
                $aspect = 'lastaccesstime';
                break;
            case 'progress':
                $aspect = 'progressvalue';
                break;
        }

        // Check of order can be established.
        if (is_array($a)) {
            $first = $a[$aspect];
            $second = $b[$aspect];
        } else {
            $first = $a->$aspect;
            $second = $b->$aspect;
        }

        if ($first < $second) {
            return $ascdesc == 'ASC' ? 1 : -1;
        }
        if ($first > $second) {
            return $ascdesc == 'ASC' ? -1 : 1;
        }
    }

    // If previous ordering fails, consider values equal.
    return 0;
}
