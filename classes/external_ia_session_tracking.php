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
 * IntegrityAdvocate external functions - track IA sessions so we can close the remote session only once.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

defined('MOODLE_INTERNAL') || die;
require_once(\dirname(__DIR__) . '/lib.php');
require_once($CFG->libdir . '/externallib.php');

use block_integrityadvocate\Api as ia_api;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

trait external_ia_session_tracking {

    /**
     * Describes the parameters for session_* functions.
     *
     * @return \external_function_parameters The parameters for session_*() functions.
     */
    private static function session_function_params(): \external_function_parameters {
        return new \external_function_parameters(
                [
            'appid' => new \external_value(PARAM_ALPHANUMEXT, 'appid'),
            'courseid' => new \external_value(PARAM_INT, 'courseid'),
            'moduleid' => new \external_value(PARAM_INT, 'moduleid'),
            'userid' => new \external_value(PARAM_INT, 'userid'),
                ]
        );
    }

    /**
     * Calls self::validate_params() and check for things that should make a session_* request fail.
     *
     * @param string $appid The AppId of the attached block.
     * @param int $courseid The courseid the user is working in.
     * @param int $moduleid The moduleid the user is working in.
     * @param int $userid The user's userid.
     * @return array Build result array that sent back as the AJAX result.
     */
    private static function session_function_validate_params(string $appid, int $courseid, int $moduleid, int $userid): array {
        global $USER;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debugvars = $fxn . "::Started with \$appid={$appid}; \$courseid={$courseid}; \$moduleid={$moduleid}; \$userid={$userid}";
        $debug && debugging($debugvars);

        self::validate_parameters(self::session_function_params(),
                [
                    'appid' => $appid,
                    'courseid' => $courseid,
                    'moduleid' => $moduleid,
                    'userid' => $userid,
                ]
        );

        $result = ['submitted' => false,
            'success' => true,
            'warnings' => [],
        ];
        $blockversion = \get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version');
        $coursecontext = null;

        // Check for things that should make this fail.
        switch (true) {
            case(!\confirm_sesskey()):
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => \get_string('confirmsesskeybad'),
                ];
                break;
            case(!\block_integrityadvocate\FeatureControl::SESSION_STARTED_TRACKING) :
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => 'This feature is disabled',
                ];
                break;
            case(!ia_u::is_guid($appid)):
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => 'The input appid is an invalid GUID',
                ];
                break;
            case(!(ia_mu::get_course_as_obj($courseid))):
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => 'The input courseid is an invalid course id',
                ];
                break;
            case(!($coursecontext = \context_course::instance($courseid))):
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => 'The course context is invalid',
                ];
                break;
            case(!\is_enrolled($coursecontext, $userid, 'block/integrityadvocate:view', true /* Only active users */)) :
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => "Course id={$courseid} does not have targetuserid={$userid} enrolled",
                ];
                break;
            case((int) (ia_mu::get_courseid_from_cmid($moduleid)) !== (int) $courseid):
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => "Moduleid={$moduleid} is not in the course with id={$courseid}; \$get_courseid_from_cmid=" . ia_mu::get_courseid_from_cmid($moduleid),
                ];
                break;
            case(!($cm = \get_course_and_cm_from_cmid($moduleid, null, $courseid, $userid)[1]) ||
            !($blockinstance = ia_mu::get_first_block($cm->context, INTEGRITYADVOCATE_SHORTNAME, false))):
                // The above line also throws an error if $overrideuserid cannot access the module.
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => 'The target module must have an instance of ' . \INTEGRITYADVOCATE_SHORTNAME . ' attached',
                ];
                break;
            case($blockinstance->config->appid !== $appid):
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => "The input appid {$blockinstance->config->appid} does not match the block intance appid={$appid}",
                ];
                break;
            case((int) $userid !== (int) ($USER->id)):
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => 'The userid is not the current user',
                ];
                break;
            case(!($user = ia_mu::get_user_as_obj($userid))):
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => 'The userid is not a valid user',
                ];
                break;
            case($user->deleted || $user->suspended):
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => 'The user is suspended or deleted',
                ];
                break;
            case(!\is_enrolled(($cm->context), $userid, 'block/integrityadvocate:view', true /* Only active users */)) :
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => "The userid={$userid} is not enrolled in the target module cmid={$moduleid}",
                ];
                break;
            case(\has_capability('block/integrityadvocate:overview', $cm->context)):
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]), 
                    'message' => 'Instructors do not get the proctoring UI so never need to open or close the session',
                ];
                break;
        }
        $debug && debugging($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));
        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            debugging($fxn . '::' . \serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }
        $debug && debugging($fxn . '::No warnings');

        // Makes sure the current user may execute functions in this context.
        self::validate_context($cm->context);

        return $result;
    }

    /**
     * Describes the parameters for session_close.
     *
     * @return \external_function_parameters The parameters for session_open.
     */
    public static function session_close_parameters(): \external_function_parameters {
        return self::session_function_params();
    }

    /**
     * Close the remote IA session and clear the session flag that tracks that it was opened.
     *
     * @param string $appid The AppId of the attached block.
     * @param int $courseid The courseid the user is working in.
     * @param int $moduleid The moduleid the user is working in.
     * @param int $userid The user's userid.
     * @return array Build result array that sent back as the AJAX result.
     */
    public static function session_close(string $appid, int $courseid, int $moduleid, int $userid): array {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debugvars = $fxn . "::Started with \$appid={$appid}; \$courseid={$courseid}; \$moduleid={$moduleid}; \$userid={$userid}";
        $debug && debugging($debugvars);

        $result = \array_merge(['submitted' => false, 'success' => true, 'warnings' => []],
                self::session_function_validate_params($appid, $courseid, $moduleid, $userid));
        $debug && debugging($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));

        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            debugging($fxn . '::' . \serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }
        $debug && debugging($fxn . '::No warnings');

        $result['success'] = ia_api::close_remote_session($appid, $courseid, $moduleid, $userid);
        if (!$result['success']) {
            $msg = 'Failed to save the session start flag to the remote IA server';
            $result['warnings'] = [
                'warningcode' => \get_config(\INTEGRITYADVOCATE_BLOCK_NAME, 'version') . __LINE__,
                'message' => $msg,
            ];
            debugging($fxn . "::{$msg}; \$debugvars={$debugvars}");
        }
        $result['submitted'] = true;

        $debug && debugging($fxn . '::About to return result=' . ia_u::var_dump($result, true));
        return $result;
    }

    /**
     * Describes the session_open return value.
     *
     * @return \external_single_structure
     */
    public static function session_close_returns(): \external_single_structure {
        return self::returns_boolean_submitted();
    }

    /**
     * Describes the parameters for session_open.
     *
     * @return \external_function_parameters The parameters for session_open.
     */
    public static function session_open_parameters(): \external_function_parameters {
        return self::session_function_params();
    }

    /**
     * Remember that we have started an IA session by storing a flag in the user session.
     *
     * @param string $appid The AppId of the attached block.
     * @param int $courseid The course id the user is working in.
     * @param int $moduleid The module id the user is working in.
     * @param int $userid The user's userid.
     * @return array Build result array that sent back as the AJAX result.
     */
    public static function session_open(string $appid, int $courseid, int $moduleid, int $userid): array {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debugvars = $fxn . "::Started with \$appid={$appid}; \$courseid={$courseid}; \$moduleid={$moduleid}; \$userid={$userid}";
        $debug && debugging($debugvars);

        $result = \array_merge(['submitted' => false, 'success' => true, 'warnings' => []],
                self::session_function_validate_params($appid, $courseid, $moduleid, $userid));
        $debug && debugging($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));

        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            debugging($fxn . '::' . \serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }
        $debug && debugging($fxn . '::No warnings');

        $result['success'] = ia_mu::nonce_set(\implode('_', [INTEGRITYADVOCATE_SESSION_STARTED_KEY,
                    $appid, $courseid, $moduleid, $userid]));
        if (!$result['success']) {
            $msg = 'Failed to save the session start flag to the remote IA server';
            $result['warnings'] = [
                'warningcode' => \get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version') . __LINE__,
                'message' => $msg,
            ];
            debugging($fxn . "::{$msg}; \$debugvars={$debugvars}");
        }
        $result['submitted'] = true;

        $debug && debugging($fxn . '::About to return result=' . ia_u::var_dump($result, true));
        return $result;
    }

    /**
     * Describes the session_open return value.
     *
     * @return \external_single_structure
     */
    public static function session_open_returns(): \external_single_structure {
        return self::returns_boolean_submitted();
    }
}
