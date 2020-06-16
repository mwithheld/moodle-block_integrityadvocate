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
require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/lib.php');

use block_integrityadvocate\Api as ia_api;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Status as ia_status;
use block_integrityadvocate\Utility as ia_u;

class block_integrityadvocate_external extends \external_api {

    /**
     * Describes the parameters for set_override.
     *
     * @return external_function_parameters
     */
    public static function set_override_parameters(): \external_function_parameters {
        return new \external_function_parameters(
                [
            'status' => new \external_value(PARAM_INT, 'Status'),
            'reason' => new \external_value(PARAM_TEXT, 'Reason for override'),
            'targetuserid' => new \external_value(PARAM_INT, 'Target user id'),
            'overrideuserid' => new \external_value(PARAM_INT, 'Overriding user id'),
            'blockinstanceid' => new \external_value(PARAM_INT, 'Block instance id'),
                ]
        );
    }

    /**
     * Set the override status and reason.
     *
     * @param int $status The integer status.
     * @param string $reason The reason for override.
     * @param int $targetuserid Target user id.  Must be enrolled in the course.
     * @param int $overrideuserid Overriding user id.  Must be active in the course with this block's ovderride priv.
     * @param int $blockinstanceid Block instance id. Must be an instance of this block.
     */
    public static function set_override(int $status, string $reason, int $targetuserid, int $overrideuserid, int $blockinstanceid): array {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . "::Started with \$status={$status}; \$reason={$reason}; \$targetuserid={$targetuserid}; \$overrideuserid={$overrideuserid}, \$blockinstanceid={$blockinstanceid}");

        self::validate_parameters(self::set_override_parameters(),
                [
                    'status' => $status,
                    'reason' => $reason,
                    'targetuserid' => $targetuserid,
                    'overrideuserid' => $overrideuserid,
                    'blockinstanceid' => $blockinstanceid,
                ]
        );

        $result = array(
            'submitted' => false,
            'success' => true,
            'warnings' => array(),
        );
        $coursecontext = null;

        // Check for things that should make this fail.
        switch (true) {
            case(!ia_mu::nonce_validate(INTEGRITYADVOCATE_BLOCK_NAME . "_override_{$blockinstanceid}_{$targetuserid}")):
                $result['warnings'][] = array('warningcode' => __LINE__, 'message' => 'Nonce not found');
                break;
            case(!ia_status::is_overriddable($status)) :
                $result['warnings'][] = array('warningcode' => __LINE__, 'message' => "Status={$status} not an overridable value");
                break;
            case(ia_u::is_empty($blockinstance = \block_instance_by_id($blockinstanceid))) :
                $result['warnings'][] = array('warningcode' => __LINE__, 'message' => "Blockinstance not found for blockinstanceid={$blockinstanceid}");
                break;
            case(!($blockinstance instanceof block_integrityadvocate)) :
                $result['warnings'][] = array('warningcode' => __LINE__, 'message' => "Blockinstanceid={$blockinstanceid} is not an instance of block_integrityadvocate");
                break;
            case($configerrors = $blockinstance->get_config_errors()) :
                $result['warnings'][] = array('warningcode' => __LINE__, 'message' => "Blockinstanceid={$blockinstanceid} has config errors: <br />\n" . implode("<br />\n", $configerrors));
                break;
            case(!$blockinstance->is_visible()) :
                $result['warnings'][] = array('warningcode' => __LINE__, 'message' => "Blockinstanceid={$blockinstanceid} is hidden");
                break;
            case(!($coursecontext = $blockinstance->context->get_course_context())) :
                $result['warnings'][] = array('warningcode' => __LINE__, 'message' => "Course context not found for blockinstanceid={$blockinstanceid}");
                break;
            case(!($courseid = $coursecontext->instanceid)) :
                $result['warnings'][] = array('warningcode' => __LINE__, 'message' => "Course not found from contextid={$coursecontext->id}");
                break;
            case(!($targetuser = ia_mu::get_user_as_obj($targetuserid))) :
                $result['warnings'][] = array('warningcode' => __LINE__, 'message' => "Target user not found for targetuserid={$targetuserid}");
                break;
            case(!\is_enrolled($coursecontext, $targetuser /* Include inactive courses. */)) :
                $result['warnings'][] = array('warningcode' => __LINE__, 'message' => "Course id={$courseid} does not have targetuserid={$targetuserid} enrolled");
                break;
            case(!($overrideuser = ia_mu::get_user_as_obj($overrideuserid))) :
                $result['warnings'][] = array('warningcode' => __LINE__, 'message' => "Overriding user not found for overrideuserid={$overrideuserid}");
                break;
            case(!\is_enrolled($coursecontext, $overrideuser, $overridepermission = 'block/integrityadvocate:override', true)) :
                $result['warnings'][] = array('warningcode' => __LINE__, 'message' => "Course id={$courseid} does not have overrideuserid={$overrideuserid} active with the permission {$overridepermission}");
                break;
        }
        $debug && ia_mu::log($fxn . '::After checking failure conditions, warnings=' . var_export($result['warnings'], true));

        // Sanitize inputs.
        $reasoncleaned = substr(preg_replace('/[^a-zA-Z0-9\ .,_-]/', '', clean_param($reason, PARAM_TEXT)), 0, 32);

        // Makes sure user may execute functions in this context.
        self::validate_context($coursecontext);

        $result['submitted'] = true;
        $result['success'] = ia_api::set_override($blockinstance->config->apikey, $blockinstance->config->appid, $status, $reasoncleaned, $targetuserid, $overrideuser, $courseid);

        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
        }

        return $result;
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
