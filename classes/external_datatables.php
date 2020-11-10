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
 * IntegrityAdvocate external functions - participants getter.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

defined('MOODLE_INTERNAL') || die;
require_once(dirname(__DIR__) . '/lib.php');
require_once($CFG->libdir . '/externallib.php');

use block_integrityadvocate\Api as ia_api;
use block_integrityadvocate\Logger as Logger;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

trait external_datatables {

    /**
     * Get participant-level info (i.e. no session info) for DataTables.
     *
     * @param string $appid The AppId of the attached block.
     * @param int $courseid The course id the user is working in.
     * @param <int> $userids The userids to get info for.
     * @return array Build result array that sent back as the AJAX result.
     */
    public static function get_datatables_participants(string $appid, int $courseid, int $draw, int $start, int $length, array $search, array $columns): array {
        global $USER;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = true || Logger::do_log_for_function($fxn);
        $debugvars = $fxn . "::Started with \$appid={$appid}; \$courseid={$courseid}; \$draw={$draw}; \$start={$start}; \$length={$length}; \$search=" . serialize($search) . ' \$columns=' . print_r($columns, true);
        $debug && Logger::log($debugvars);

        self::validate_parameters(self::get_datatables_participants_parameters(),
                [
                    'appid' => $appid,
                    'courseid' => $courseid,
                    'draw' => $draw,
                    'start' => $start,
                    'length' => $length,
                    'tblsearch' => $search,
                    'columns' => $columns,
                ]
        );
        $result = array(
            'submitted' => false,
            'success' => true,
            'warnings' => [],
        );
        $blockversion = get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version');
        $coursecontext = null;
        $debug && Logger::log($fxn . '::Got blockversion=' . $blockversion);

        // Check for things that should make this fail.
        switch (true) {
            case(!\confirm_sesskey()):
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => get_string('confirmsesskeybad'));
                break;
            case(!\block_integrityadvocate\FeatureControl::OVERVIEW_COURSE_V2) :
                error_log($fxn . '::This feature is disabled');
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
//            case(intval(ia_mu::get_courseid_from_cmid($moduleid)) !== intval($courseid)):
//                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Moduleid={$moduleid} is not in the course with id={$courseid}; \$get_courseid_from_cmid=" . ia_mu::get_courseid_from_cmid($moduleid));
//                break;
//            case(!($cm = \get_course_and_cm_from_cmid($moduleid, null, $courseid)[1]) || !($blockinstance = ia_mu::get_first_block($cm->context, INTEGRITYADVOCATE_SHORTNAME, false))):
//                // The above line also throws an error if $overrideuserid cannot access the module.
//                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'The target module must have an instance of ' . INTEGRITYADVOCATE_SHORTNAME . ' attached');
//                break;
//            case($blockinstance->config->appid !== $appid):
//                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "The input appid {$blockinstance->config->appid} does not match the block intance appid={$appid}");
//                break;
//            case(\has_capability('block/integrityadvocate:overview', $cm->context)):
//                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'Instructors do not get the proctoring UI so never need to open or close the session');
//                break;
            case(!\is_enrolled($coursecontext, $USER->id, 'block/integrityadvocate:view', true /* Only active users */)) :
                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "Course id={$courseid} does not have userid={$USER->id} enrolled");
                break;
//            case(!\is_enrolled(($modulecontext = $cm->context), $USER->id, 'block/integrityadvocate:view', true /* Only active users */)) :
//                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => "The userid={$USER->id} is not enrolled in the target module cmid={$moduleid}");
//                break;
//            case(intval($userid) !== intval($USER->id)):
//                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'The userid is not the current user');
//                break;
//            case(!($user = ia_mu::get_user_as_obj($userid))):
//                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'The userid is not a valid user');
//                break;
//            case($user->deleted || $user->suspended):
//                $result['warnings'][] = array('warningcode' => implode('-', $blockversion, __LINE__), 'message' => 'The user is suspended or deleted');
//                break;
        }
        $debug && Logger::log($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));
        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            Logger::log($fxn . '::' . serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }
        $debug && Logger::log($fxn . '::No warnings');
        return $result;
    }

    /**
     * Describes the parameters for the external function.
     *
     * @return external_function_parameters The parameters for the external function.
     */
    public static function get_datatables_participants_parameters(): \external_function_parameters {
        $search = new \external_single_structure([
            'value' => new \external_value(PARAM_RAW_TRIMMED, null, VALUE_OPTIONAL, null, true),
            'regex' => new \external_value(PARAM_BOOL, null, VALUE_OPTIONAL, false, true),
                ], null, VALUE_OPTIONAL, null, true);
        return new \external_function_parameters([
            'appid' => new \external_value(PARAM_ALPHANUMEXT),
            'courseid' => new \external_value(PARAM_INT),
            'draw' => new \external_value(PARAM_INT),
            'start' => new \external_value(PARAM_INT),
            'length' => new \external_value(PARAM_INT),
            'order' => new \external_multiple_structure(
                    new \external_single_structure([
                        'column' => new \external_value(PARAM_INT),
                        'dir' => new \external_value(PARAM_ALPHANUMEXT),
                            ]), null, VALUE_OPTIONAL, null, true
            ),
            'tblsearch' => $search,
            'columns' => new \external_multiple_structure(
                    new \external_single_structure([
                        'data' => new \external_value(PARAM_INT),
                        'name' => new \external_value(PARAM_ALPHAEXT),
                        'orderable' => new \external_value(PARAM_BOOL, null, VALUE_OPTIONAL, true, true),
                        'searchable' => new \external_value(PARAM_BOOL, null, VALUE_OPTIONAL, true, true),
                        'search' => new \external_single_structure([
                            'value' => new \external_value(PARAM_RAW_TRIMMED, null, VALUE_OPTIONAL, null, true),
                            'regex' => new \external_value(PARAM_BOOL, null, VALUE_OPTIONAL, false, true),
                                ], null, VALUE_OPTIONAL, null, true),
                            ])
            ),
        ]);
    }

    /**
     * Describes the external function return value.
     *
     * @return external_single_structure
     */
    public static function get_datatables_participants_returns(): \external_single_structure {
        return new \external_single_structure([
            'submitted' => new \external_value(PARAM_BOOL, 'submitted', true, false, false),
            'warnings' => new \external_warnings(),
            // Array of key-value.
            'values' => new \external_value(PARAM_ALPHANUMEXT, 'Some return gobbledygook', VALUE_OPTIONAL, 'returned value', true),
//            new \external_multiple_structure(
//                    new \external_single_structure(
//                            [
//                        'name' => new \external_value(PARAM_ALPHANUMEXT, 'The field to update'),
//                        'value' => new \external_value(PARAM_RAW, 'The value of the field'),
//                            ]
//                    )
//            )
                ]
        );
    }

}
