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

use block_integrityadvocate\MoodleUtility as ia_mu;

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
            'reason' => new \external_value(PARAM_RAW, 'Reason for override'),
            'targetuserid' => new \external_value(PARAM_INT, 'Target user id'),
            'overrideuserid' => new \external_value(PARAM_INT, 'Overriding user id'),
//            'cmid' => new \external_value(PARAM_INT, 'Course module id'),
                ]
        );
    }

    /**
     * Set the override status and reason.
     *
     * @param int $status The integer status.
     * @param string $reason The reason for override.
     * @param int $targetuserid Target user id.
     * @param int $overrideuserid Overriding user id.
     * @param int $cmid Course module id.
     */
    public static function set_override(int $status, string $reason, int $targetuserid, int $overrideuserid/* , int $cmid */): array {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . "::Started with \$status={$status}; \$reason={$reason}; \$targetuserid=$targetuserid; \$overrideuserid=$overrideuserid/*; \$cmid=$cmid*/");

        self::validate_parameters(self::set_override_parameters(),
                [
                    'status' => $status,
                    'reason' => $reason,
                    'targetuserid' => $targetuserid,
                    'overrideuserid' => $overrideuserid,
//                    'cmid' => $cmid,
                ]
        );

//        list($cm, $course, $questionnaire) = questionnaire_get_standard_page_items($cmid);
//        $questionnaire = new \questionnaire(0, $questionnaire, $course, $cm);
//
//        $context = \context_module::instance($cm->id);
//        self::validate_context($context);
//
//        require_capability('block/integrityadvocate:override', $context);
//
//        $result = $questionnaire->save_mobile_data($userid, $sec, $completed, $rid, $submit, $action, $responses);
        $result = array();
        $result['submitted'] = true;
        if (isset($result['warnings']) && !empty($result['warnings'])) {
            unset($result['responses']);
            $result['submitted'] = false;
        }
        $result['warnings'] = [];
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
