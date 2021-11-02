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
 * IntegrityAdvocate external functions - get course activity info.
 * This is a no-login-required API endpoint, secured by requiring
 * a matching [courseid, IA APIKey, and AppId] in a course block to yield any info.
 *
 * Example:
 * https://127.0.0.1/lib/ajax/service.php?info=block_integrityadvocate_course_activities
 * with valid post info
 * [{"index":0,"methodname":"block_integrityadvocate_course_activities","args":{"appid":"12345678-aeb1-4f3d-8ac0-1f3a12345678","courseid":2,"apikey":"12345678qaUuYX3Res78VnxS385tlm12345678="}}]
 * returns info like this
 * {
 * "CourseId": "2",
 * "CourseName": "Course 2",
 * "ActivityId": "837",
 * "ActivityName": "Activity 1"
 * }
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

\defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once(\dirname(__DIR__) . '/lib.php');
require_once(\dirname(__DIR__) . '/block_integrityadvocate.php');
require_once($CFG->libdir . '/externallib.php');

use block_integrityadvocate as ia;
use block_integrityadvocate\Logger as Logger;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

trait external_get_course_info
{

    /**
     * Describes the parameters for get_course_info_* functions.
     *
     * @return external_function_parameters The parameters for get_course_info_*() functions.
     */
    private static function get_course_info_params(): \external_function_parameters
    {
        return new \external_function_parameters(
            [
            'apikey' => new \external_value(\PARAM_BASE64, 'apikey'),
            'appid' => new \external_value(\PARAM_ALPHANUMEXT, 'appid'),
            'courseid' => new \external_value(\PARAM_INT, 'courseid'),
            ]
        );
    }

    /**
     * Calls self::validate_params() and check for things that should make a get_course_info_* request fail.
     *
     * @param string $apikey The APIKey serves as auth.
     * @param string $appid The AppId serves as auth.
     * @param int $courseid The courseid the to get info for.
     * @return array Build result array that sent back as the AJAX result.
     */
    private static function get_course_activities_validate_params(string $apikey, string $appid, int $courseid): array
    {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = true || Logger::do_log_for_function($fxn);
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid}";
        $debug && error_log($debugvars);

        self::validate_parameters(
            self::get_course_info_params(),
            [
                'apikey' => $apikey,
                'appid' => $appid,
                'courseid' => $courseid,
            ]
        );

        $result = [
            'submitted' => true,
            'success' => false,
            'warnings' => [],
        ];
        $blockversion = \get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version');
        $coursecontext = null;

        // Check for things that should make this fail.
        switch (true) {
            case (!FeatureControl::EXTERNAL_API_COURSE_INFO):
                $result['warnings'][] = ['warningcode' => \implode('a', [$blockversion, __LINE__]), 'message' => 'This feature is disabled'];
                break;
            case (!ia::is_valid_apikey($apikey)):
                $result['warnings'][] = ['warningcode' => \implode('a', [$blockversion, __LINE__]), 'message' => 'The input apikey is invalid'];
                break;
            case (!ia::is_valid_appid($appid)):
                $result['warnings'][] = ['warningcode' => \implode('a', [$blockversion, __LINE__]), 'message' => 'The input appid is invalid'];
                break;
            case ($courseid < 1 || $courseid == \SITEID || !(ia_mu::couse_exists($courseid))):
                $result['warnings'][] = ['warningcode' => \implode('a', [$blockversion, __LINE__]), 'message' => 'The input courseid is invalid'];
                break;
            case (!($coursecontext = \context_course::instance($courseid))):
                $result['warnings'][] = ['warningcode' => \implode('a', [$blockversion, __LINE__]), 'message' => 'The course context is invalid'];
                break;
            case (!$blockinstance = block_integrityadvocate_get_first_course_ia_block($courseid, $apikey, $appid)):
                $result['warnings'][] = ['warningcode' => \implode('a', [$blockversion, __LINE__]), 'message' => "No block in courseid={$courseid} matches the input apikey and appid"];
                break;
        }

        $debug && error_log($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));
        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            error_log($fxn . '::' . \serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }
        $debug && error_log($fxn . '::No warnings');

        return $result;
    }

    /**
     * Describes the parameters for get_course_activities.
     *
     * @return external_function_parameters The parameters for session_open.
     */
    public static function get_course_activities_parameters(): \external_function_parameters
    {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = true || Logger::do_log_for_function($fxn);
        $debugvars = $fxn . '::Started';
        $debug && error_log($debugvars);

        return self::get_course_info_params();
    }

    /**
     * Gets a list of course activity info.
     *
     * @param string $apikey The APIKey serves as auth.
     * @param string $appid The AppId serves as auth.
     * @param int $courseid The courseid the to get info for.
     * @return array Build result array that sent back as the AJAX result.
     */
    public static function get_course_activities(string $apikey, string $appid, int $courseid): array
    {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = true || Logger::do_log_for_function($fxn);
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid}";
        $debug && error_log($debugvars);

        $result = \array_merge(['submitted' => false, 'success' => false, 'warnings' => [], 'courseactivities'=>[]], self::get_course_activities_validate_params($apikey, $appid, $courseid));
        $debug && error_log($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));

        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            Logger::log($fxn . '::' . \serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }
        $debug && Logger::log($fxn . '::No warnings');
        
        $modinfo = \get_fast_modinfo($courseid, -1);
        $coursefullname = get_course($courseid)->fullname;
        
        $modules = [];
        foreach ($modinfo->instances as $module => $instances) {
            foreach ($instances as $cm) {
                //$debug && error_log($fxn . '::Looking at coursemodule=' . ia_u::var_dump($cm, true));
                $modules[] = [
                    'courseid' => $courseid,
                    'coursename' => $coursefullname,
                    'activityid' => $cm->id,
                    'activityname' => $cm->name,
                    'activitytype' => $module,
                ];
            }
        }

        $debug && error_log($fxn . '::Got modules=' . ia_u::var_dump($modules, true));

        $result['courseactivities'] = $modules;
        $result['success'] = true;

        $debug && error_log($fxn . '::About to return result=' . ia_u::var_dump($result, true));
        return $result;
    }

    /**
     * Describes the get_course_actviities() return value.
     *
     * @return external_single_structure
     */
    public static function get_course_activities_returns(): \external_single_structure
    {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = true || Logger::do_log_for_function($fxn);
        $debugvars = $fxn . '::Started';
        $debug && error_log($debugvars);

        return new \external_single_structure(
            [
            // Usage: external_value($type, $desc, $required, $default, $allownull).
            'submitted' => new \external_value(PARAM_BOOL, 'submitted', true, false, false),
            'warnings' => new \external_warnings(),
            'courseactivities' => new \external_multiple_structure(
                new \external_single_structure(
                    array(
                    'courseid' => new \external_value(PARAM_INT, 'Course ID'),
                    'coursename' => new \external_value(PARAM_NOTAGS, 'Course display name with no HTML tags'),
                    'activityid' => new \external_value(PARAM_INT, 'Activity ID'),
                    'activityname' => new \external_value(PARAM_NOTAGS, 'Module display name with no HTML tags e.g. Fancy Quiz number 1'),
                    'activitytype' => new \external_value(PARAM_PLUGIN, 'Plugin type e.g. quiz, forum, glossary'),
                    )
                ), VALUE_OPTIONAL
            )
            ]
        );
    }
}
