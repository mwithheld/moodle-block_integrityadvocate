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
 * IntegrityAdvocate external functions.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/externallib.php');

use block_integrityadvocate\Api as ia_api;
use block_integrityadvocate\Logger as Logger;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Status as ia_status;
use block_integrityadvocate\Utility as ia_u;

/**
 * Answers AJAX calls for this block.
 */
class block_integrityadvocate_external extends \external_api {

    /**
     * Describes the parameters for session_* functions.
     *
     * @return external_function_parameters The parameters for session_*() functions.
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

    private static function session_function_returns(): \external_single_structure {
        return new \external_single_structure(
                [
            'submitted' => new \external_value(PARAM_BOOL, 'submitted', true, false, false),
            'warnings' => new \external_warnings()
                ]
        );
    }

    private static function session_function_validate_params(string $appid, int $courseid, int $moduleid, int $userid): array {
        global $USER;
        $debug = false || Logger::doLogForFunction(__CLASS__ . '::' . __FUNCTION__);
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$appid={$appid}; \$courseid={$courseid}; \$moduleid={$moduleid}; \$userid={$userid}";
        $debug && Logger::log($debugvars);

        self::validate_parameters(self::session_function_params(),
                [
                    'appid' => $appid,
                    'courseid' => $courseid,
                    'moduleid' => $moduleid,
                    'userid' => $userid,
                ]
        );

        $result = array(
            'submitted' => false,
            'success' => true,
            'warnings' => [],
        );
        $blockversion = get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version');
        $coursecontext = null;

        // Check for things that should make this fail.
        switch (true) {
            case(!\confirm_sesskey()):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => get_string('confirmsesskeybad'));
                break;
            case(!block_integrityadvocate\FeatureControl::SESSION_STARTED_TRACKING) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'This feature is disabled');
                break;
            case(!ia_u::is_guid($appid)):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'The input appid is an invalid GUID');
                break;
            case(!($course = ia_mu::get_course_as_obj($courseid))):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'The input courseid is an invalid course id');
                break;
            case(!($coursecontext = \context_course::instance($courseid))):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'The course context is invalid');
                break;
            case(!\is_enrolled($coursecontext, $userid, 'block/integrityadvocate:view', true /* Only active users */)) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Course id={$courseid} does not have targetuserid={$targetuserid} enrolled");
                break;
            case(intval(ia_mu::get_courseid_from_cmid($moduleid)) !== intval($courseid)):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Moduleid={$moduleid} is not in the course with id={$courseid}; \$get_courseid_from_cmid=" . ia_mu::get_courseid_from_cmid($moduleid));
                break;
            case(!($cm = \get_course_and_cm_from_cmid($moduleid, null, $courseid, $userid)[1]) || !($blockinstance = ia_mu::get_first_block($cm->context, INTEGRITYADVOCATE_SHORTNAME, false))):
                // The above line also throws an error if $overrideuserid cannot access the module.
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'The target module must have an instance of ' . INTEGRITYADVOCATE_SHORTNAME . ' attached');
                break;
            case($blockinstance->config->appid !== $appid):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "The input appid {$blockinstance->config->appid} does not match the block intance appid={$appid}");
                break;
            case(intval($userid) !== intval($USER->id)):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'The userid is not the current user');
                break;
            case(!($user = ia_mu::get_user_as_obj($userid))):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'The userid is not a valid user');
                break;
            case($user->deleted || $user->suspended):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'The user is suspended or deleted');
                break;
            case(!\is_enrolled(($modulecontext = $cm->context), $userid, 'block/integrityadvocate:view', true /* Only active users */)) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "The userid={$userid} is not enrolled in the target module cmid={$moduleid}");
                break;
            case(\has_capability('block/integrityadvocate:overview', $cm->context)):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'Instructors do not get the proctoring UI so never need to open or close the session');
                break;
        }
        $debug && Logger::log($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));
        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            Logger::log($fxn . '::' . serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }
        $debug && Logger::log($fxn . '::No warnings');

        // Makes sure the current user may execute functions in this context.
        self::validate_context($cm->context);

        return $result;
    }

    /**
     * Describes the parameters for session_close.
     *
     * @return external_function_parameters The parameters for session_open.
     */
    public static function session_close_parameters(): \external_function_parameters {
        return self::session_function_params();
    }

    public static function session_close(string $appid, int $courseid, int $moduleid, int $userid): array {
        $debug = false || Logger::doLogForFunction(__CLASS__ . '::' . __FUNCTION__);
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$appid={$appid}; \$courseid={$courseid}; \$moduleid={$moduleid}; \$userid={$userid}";
        $debug && Logger::log($debugvars);

        $result = array_merge(['submitted' => false, 'success' => true, 'warnings' => []], self::session_function_validate_params($appid, $courseid, $moduleid, $userid));
        $debug && Logger::log($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));

        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            Logger::log($fxn . '::' . serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }
        $debug && Logger::log($fxn . '::No warnings');

        $result['success'] = ia_api::close_remote_session($appid, $courseid, $moduleid, $userid);
        if (!$result['success']) {
            $msg = 'Failed to save the session start flag to the remote IA server';
            $result['warnings'] = array('warningcode' => get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version') . __LINE__, 'message' => $msg);
            Logger::log($fxn . "::$msg; \$debugvars={$debugvars}");
        }
        $result['submitted'] = true;

        $debug && Logger::log($fxn . '::About to return result=' . ia_u::var_dump($result, true));
        return $result;
    }

    /**
     * Describes the session_open return value.
     *
     * @return external_single_structure
     */
    public static function session_close_returns(): \external_single_structure {
        return self::session_function_returns();
    }

    /**
     * Describes the parameters for session_open.
     *
     * @return external_function_parameters The parameters for session_open.
     */
    public static function session_open_parameters(): \external_function_parameters {
        return self::session_function_params();
    }

    public static function session_open(string $appid, int $courseid, int $moduleid, int $userid): array {
        global $USER;
        $debug = false || Logger::doLogForFunction(__CLASS__ . '::' . __FUNCTION__);
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$appid={$appid}; \$courseid={$courseid}; \$moduleid={$moduleid}; \$userid={$userid}";
        $debug && Logger::log($debugvars);

        $result = array_merge(['submitted' => false, 'success' => true, 'warnings' => []], self::session_function_validate_params($appid, $courseid, $moduleid, $userid));
        $debug && Logger::log($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));

        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            Logger::log($fxn . '::' . serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }
        $debug && Logger::log($fxn . '::No warnings');

        $result['success'] = ia_mu::nonce_set(implode('_', array(INTEGRITYADVOCATE_SESSION_STARTED_KEY, $appid, $courseid, $moduleid, $userid)));
        if (!$result['success']) {
            $msg = 'Failed to save the session start flag to the remote IA server';
            $result['warnings'] = array('warningcode' => get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version') . __LINE__, 'message' => $msg);
            Logger::log($fxn . "::$msg; \$debugvars={$debugvars}");
        }
        $result['submitted'] = true;

        $debug && Logger::log($fxn . '::About to return result=' . ia_u::var_dump($result, true));
        return $result;
    }

    /**
     * Describes the session_open return value.
     *
     * @return external_single_structure
     */
    public static function session_open_returns(): \external_single_structure {
        return self::session_function_returns();
    }

    /**
     * Set the override status and reason.
     *
     * @param int $status The integer status.
     * @param string $reason The reason for override.
     * @param int $targetuserid Target user id.  Must be enrolled in the course.
     * @param int $overrideuserid Overriding user id.  Must be active in the course with this block's override priv.
     * @param int $blockinstance_requesting_id Block instance id. Must be an instance of this block.  Because the overview is for the whole course, the moduleid from this blockinstance may not contain the correct moduleid.
     * @param int $moduleid CMID for the module.
     * @return array Build result array that sent back as the AJAX result.
     */
    public static function set_override(int $status, string $reason, int $targetuserid, int $overrideuserid, int $blockinstance_requesting_id, int $moduleid): array {
        $debug = false || Logger::doLogForFunction(__CLASS__ . '::' . __FUNCTION__);
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$status={$status}; \$reason={$reason}; \$targetuserid={$targetuserid}; \$overrideuserid={$overrideuserid}, \$blockinstance_requesting_id={$blockinstance_requesting_id}, \$moduleid={$moduleid}";
        $debug && Logger::log($debugvars);

        self::validate_parameters(self::set_override_parameters(),
                [
                    'status' => $status,
                    'reason' => $reason,
                    'targetuserid' => $targetuserid,
                    'overrideuserid' => $overrideuserid,
                    'blockinstanceid' => $blockinstance_requesting_id,
                    'moduleid' => $moduleid,
                ]
        );

        $result = array(
            'submitted' => false,
            'success' => true,
            'warnings' => [],
        );
        $blockversion = get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version');
        $coursecontext = null;

        // Check for things that should make this fail.
        switch (true) {
            case(!\confirm_sesskey()):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => get_string('confirmsesskeybad'));
                break;
            case(!block_integrityadvocate\FeatureControl::SESSION_STATUS_OVERRIDE) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'This feature is disabled');
                break;
            case(!ia_mu::nonce_validate(INTEGRITYADVOCATE_BLOCK_NAME . "_override_{$blockinstance_requesting_id}_{$targetuserid}")):
                // This nonce should be put into the server-side user session (ia_mu::nonce_set($noncekey)) when the form is generated.
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'Nonce not found');
                break;
            case(!ia_status::is_override_status($status)) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Status={$status} not an value that can be set as an override");
                break;
            case(ia_u::is_empty($blockinstance_requesting = \block_instance_by_id($blockinstance_requesting_id))) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Blockinstance not found for blockinstanceid={$blockinstance_requesting_id}");
                break;
            case(!($blockinstance_requesting instanceof block_integrityadvocate)) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Blockinstanceid={$blockinstance_requesting_id} is not an instance of block_integrityadvocate");
                break;
            case($configerrors = $blockinstance_requesting->get_config_errors()) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Blockinstanceid={$blockinstance_requesting_id} has config errors: <br />\n" . implode("<br />\n", $configerrors));
                break;
            case(!$blockinstance_requesting->is_visible()) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Blockinstanceid={$blockinstance_requesting_id} is hidden");
                break;
            case(!($coursecontext = $blockinstance_requesting->context->get_course_context())) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Course context not found for blockinstanceid={$blockinstance_requesting_id}");
                break;
            case(!($courseid = $coursecontext->instanceid)) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Course not found from contextid={$coursecontext->id}");
                break;
            case(!($targetuser = ia_mu::get_user_as_obj($targetuserid))) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Target user not found for targetuserid={$targetuserid}");
                break;
            case(!\is_enrolled($coursecontext, $targetuser /* Include inactive enrolments. */)) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Course id={$courseid} does not have targetuserid={$targetuserid} enrolled");
                break;
            case(!($overrideuser = ia_mu::get_user_as_obj($overrideuserid))) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Overriding user not found for overrideuserid={$overrideuserid}");
                break;
            case(intval(ia_mu::get_courseid_from_cmid($moduleid)) !== intval($courseid)):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Moduleid={$moduleid} is not in the course with id={$courseid}; \$get_courseid_from_cmid=" . ia_mu::get_courseid_from_cmid($moduleid));
                break;
            case(!($cm = \get_course_and_cm_from_cmid($moduleid, null, $courseid, $overrideuserid)[1]) || !($blockinstance_target = ia_mu::get_first_block($cm->context, INTEGRITYADVOCATE_SHORTNAME, false))):
                // The above line also throws an error if $overrideuserid cannot access the module.
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'The target module must have an instance of ' . INTEGRITYADVOCATE_SHORTNAME . ' attached');
                break;
            case(!\is_enrolled(($modulecontext = $cm->context), $targetuser /* Include inactive enrolments. */)) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "The targetuserid={$targetuserid} is not enrolled in the target module cmid={$moduleid}");
                break;
            case(!($hascapability_override = \has_capability(($overridepermission = 'block/integrityadvocate:override'), $modulecontext, $overrideuser))):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Course id={$courseid} does not have overrideuserid={$overrideuserid} active with the permission {$overridepermission}");
                break;
        }
        $debug && Logger::log($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));
        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            Logger::log($fxn . '::' . serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }
        $debug && Logger::log($fxn . '::No warnings');

        // Makes sure the current user may execute functions in this context.
        self::validate_context($cm->context);

        // Sanitize inputs.
        $reasoncleaned = substr(trim(preg_replace('/[^a-zA-Z0-9\ .,_-]/', '', clean_param($reason, PARAM_TEXT))), 0, 32);
        if (empty($reasoncleaned)) {
            $reasoncleaned = \get_string('override_reason_none', INTEGRITYADVOCATE_BLOCK_NAME);
        }

        // Do the call to the IA API.
        $result['success'] = ia_api::set_override_session($blockinstance_requesting->config->apikey, $blockinstance_requesting->config->appid, $status, $reasoncleaned, $targetuserid, $overrideuser, $courseid, $moduleid);
        if (!$result['success']) {
            $msg = 'Failed to save the override status to the remote IA server';
            $result['warnings'] = array('warningcode' => get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version') . __LINE__, 'message' => $msg);
            Logger::log($fxn . "::$msg; \$debugvars={$debugvars}");
        }
        $result['submitted'] = true;

        $debug && Logger::log($fxn . '::About to return result=' . ia_u::var_dump($result, true));
        return $result;
    }

    /**
     * Describes the parameters for set_override.
     *
     * @return external_function_parameters The parameters for set_override.
     */
    public static function set_override_parameters(): \external_function_parameters {
        return new \external_function_parameters(
                [
            'status' => new \external_value(PARAM_INT, 'Status'),
            'reason' => new \external_value(PARAM_TEXT, 'Reason for override'),
            'targetuserid' => new \external_value(PARAM_INT, 'Target user id'),
            'overrideuserid' => new \external_value(PARAM_INT, 'Overriding user id'),
            'blockinstanceid' => new \external_value(PARAM_INT, 'Block instance id'),
            'moduleid' => new \external_value(PARAM_INT, 'Module cmid'),
                ]
        );
    }

    /**
     * Describes the set_override return value.
     *
     * @return external_single_structure
     */
    public static function set_override_returns(): \external_single_structure {
        return new \external_single_structure(
                [
            'submitted' => new \external_value(PARAM_BOOL, 'submitted', true, false, false),
            'warnings' => new \external_warnings()
                ]
        );
    }

}
