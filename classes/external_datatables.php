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
use block_integrityadvocate\Output as ia_output;
use block_integrityadvocate\Utility as ia_u;

trait external_datatables {

    /**
     * Get participant-level info (i.e. no session info) for DataTables.
     * Params for DataTables: ref https://datatables.net/manual/server-side
     *
     * @param string $appid The AppId of the attached block.
     * @param int $courseid The course id the user is working in.
     * @param int $draw Draw sequence counter.
     * @param int $start Paging first record indicator (0-indexed).
     * @param int $length Number of records that the table can display in the current draw.  Note -1 indicates that all records should be returned, but is NOT accepted here!
     * @param string $tblsearch Global search value. To be applied to all columns which have searchable as true.  In a departure from the DataTables docs, I've modded the JS to submit the filter field as only the search.value string here (not an array, and no regex).  Regex search is not supported.
     * @param array $order Ordering that should be applied.
     * @param array $columns Column data sources, searchable, orderable etc.  Per-column search is not supported.  Regex search is not supported.
     * @return array Build result array that sent back as the AJAX result.
     */
    public static function get_datatables_participants(string $appid, int $courseid, int $draw, int $start, int $length, string $tblsearch, array $order, array $columns): array {
        global $USER;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debugvars = $fxn . "::Started with \$appid={$appid}; \$courseid={$courseid}; \$draw={$draw}; \$start={$start}; \$length={$length}; \$tblsearch=" . $tblsearch . '; $order=' . ia_u::var_dump($order) . '; $columns=' . ia_u::var_dump($columns, true);
        $debug && Logger::log($debugvars);

        self::validate_parameters(self::get_datatables_participants_parameters(),
                [
                    'appid' => $appid,
                    'courseid' => $courseid,
                    'draw' => $draw,
                    'start' => $start,
                    'length' => $length,
                    'tblsearch' => $tblsearch,
                    'order' => $order,
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
                $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => get_string('confirmsesskeybad'));
                break;
            case(!\block_integrityadvocate\FeatureControl::OVERVIEW_COURSE_V2) :
                error_log($fxn . '::This feature is disabled');
                $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => 'This feature is disabled');
                break;
            case(!ia_u::is_guid($appid)):
                $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => 'The input appid is an invalid GUID');
                break;
            case(!($course = ia_mu::get_course_as_obj($courseid))):
                $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => 'The input courseid is an invalid course id');
                break;
            case(!($coursecontext = \context_course::instance($courseid))):
                $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => 'The course context is invalid');
                break;
            case(!\is_enrolled($coursecontext, $USER->id, 'block/integrityadvocate:overview', true /* Only active users */)) :
                $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => "Course id={$courseid} does not have userid={$USER->id} enrolled with overview privs");
                break;
            case(!$modinfo = \get_fast_modinfo($course)) :
                $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => "Course id={$courseid} has no modinfo");
                break;
            case($draw < 1) :
                $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => "Invalid draw {$draw} requested");
                break;
            case($start < 0) :
                $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => "Invalid start {$start} requested");
                break;
            case($length < 1) :
                $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => "Invalid length {$length} requested");
                break;
            case(ia_u::is_empty($activities = \block_integrityadvocate_get_course_ia_modules($course, array('visible' => 1, 'configured' => 1, 'appid' => $appid))) || !is_array($activities)):
                $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => "No activities in this course (id={$courseid}) use the specified appid={$appid}");
                break;
        }

        $debug && Logger::log($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));
        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            Logger::log($fxn . '::' . serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }

        // Security: Does the $USER have block/integrityadvocate:overview privs in that module context?
        {
            // Makes sure the current user may execute functions in this context.
            self::validate_context($coursecontext);

            $hascapability_overview = false;
            // If the user has overview privs, they can access overview info for all activities in the course.
            // So check we have at least one properly ia-activated activity the user can access using the tests in this for loop.
            // Remember this activity, and for later access to the IA API, we assume that its apikey is the same used everywhere in this course.
            $activity = null;
            foreach ($activities as $cm) {
                $debug && Logger::log($fxn . '::Looking at cm type=' . gettype($cm) . '; block instanceid=' . $cm['block_integrityadvocate_instance']['id']);
                $blockinstance = $cm['block_integrityadvocate_instance']['instance'];

                if (gettype($cm) !== 'cm_info') {
                    $cm = $modinfo->get_cm($cm['context']->instanceid);
                }
                if ($cm->deletioninprogress) {
                    continue;
                }
                // Only allow properly configured instances.
                if (ia_u::is_empty($blockinstance) || !ia_u::is_empty($blockinstance->get_apikey_appid_errors())) {
                    continue;
                }

                if (!$hascapability_overview = \has_capability('block/integrityadvocate:overview', $cm->context)) {
                    continue;
                }

                // Save the coursemodule for later use.
                $activity = $cm;
            }
        }
        if (!$hascapability_overview || ia_u::is_empty($activity)) {
            $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => "Module id={$cm->id} does not have userid={$USER->id} enrolled with overview privs");
            $result['success'] = false;
            Logger::log($fxn . '::' . serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }

        // Sanitize.
        {
            // Slightly modified from PARAM_FILE ("all dangerous chars are stripped, protects against XSS, SQL injections and directory traversals").
            // Modified to accept : as a character.
            $tblsearch_cleaned = preg_replace('~[[:cntrl:]]|[&<>"`\|\'\\\\/]~u', '', $tblsearch);
            $debug && Logger::log($fxn . '::Built $tblsearch_cleaned=' . ia_u::var_dump($tblsearch_cleaned));

            $order_cleaned = [];
            if (!ia_u::is_empty($order)) {
                $debug && Logger::log($fxn . '::About to clean $order=' . ia_u::var_dump($order, true));
                foreach ($order as $val) {
                    $debug && Logger::log($fxn . '::Looking at order val=' . ia_u::var_dump($val, true));
                    if (empty($val) ||
                            !isset($val['column']) || empty($val['column']) || !is_int($val['column']) ||
                            !isset($val['dir']) || empty($val['dir'])
                    ) {
                        Logger::log($fxn . '::Skipping invalid order=' . ia_u::var_dump($order, true));
                        continue;
                    }
                    $order_cleaned[$val['column']] = [
                        'column' => $val['column'],
                        'dir' => ($val['dir'] === 'desc') ? 'desc' : 'asc'
                    ];
                }
            }
            $debug && Logger::log($fxn . '::Built $order_cleaned=' . ia_u::var_dump($order_cleaned));

            $columns_cleaned = [];
            $valid_columns = array('rownum', 'userid', 'email', 'lastcourseaccess', 'ia-data', 'ia-photo');
            foreach ($columns as $colitem) {
                // Disabled on purpose: $debug && Logger::log($fxn . '::Looking at $col=' . ia_u::var_dump($colitem)); .

                $colitem_cleaned = [];
                $colitem_cleaned['data'] = $colitem['data'];
                if (empty($colitem['name']) || !in_array($colitem['name'], $valid_columns)) {
                    $debug && Logger::log($fxn . '::Skipping invalid column name=' . ia_u::var_dump($colitem['name']));
                    $result['warnings'][] = array('warningcode' => implode('-', [$blockversion, __LINE__]), 'message' => "Invalid column {$colitem['name']}");
                    // Don't bother processing any more - we will just return an error.
                    break;
                }
                $colitem_cleaned['name'] = $colitem['name'];
                // The ia-photo column is not orderable or searchable.
                if ($colitem_cleaned['name'] === 'ia-photo') {
                    $colitem_cleaned['orderable'] = false;
                    $colitem_cleaned['searchable'] = false;
                } else {
                    $colitem_cleaned['orderable'] = isset($colitem['orderable']) && (bool) $colitem['orderable'];
                    $colitem_cleaned['searchable'] = isset($colitem['searchable']) && (bool) $colitem['searchable'];
                }
                // We do not support per-columns searches, so this is always empty.
                $colitem_cleaned['search'] = null;
                $columns_cleaned[$colitem['data']] = $colitem_cleaned;
            }
            $debug && Logger::log($fxn . '::Built $columns_cleaned=' . ia_u::var_dump($columns_cleaned));

            if (isset($result['warnings']) && !empty($result['warnings'])) {
                $result['success'] = false;
                Logger::log($fxn . '::' . serialize($result['warnings']) . "; \$debugvars={$debugvars}");
                return $result;
            }
        }
        $result['submitted'] = true;

        // Set default to to empty data.
        // Return values in the data param are described at https://datatables.net/manual/server-side#Returned-data .
        $dataToReturn = [
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ];
        $result['values'] = json_encode($dataToReturn, JSON_INVALID_UTF8_IGNORE);

        // Set up the column sorting variable that is passed to get_role_users().
        // We will still need to sort by fullanme and IA status data later, but this at least defers some of the sorting work to the DB.
        $sort = [];
        foreach ($columns_cleaned as $key => $col) {
            if (!$col['orderable'] || isset($order_cleaned[$key]['dir'])) {
                $debug && Logger::log($fxn . "::Column {$col} is not orderable or a dir is not set");
                continue;
            }
            switch ($key) {
                case 0:
                    // Column=rownum: Do not sort by this column.
                    continue;
                case 1:
                    // Column=userid: Sort by fullname later if permitted, but by firstname and lastname for now.
                    $sort[] = "u.firstname {$order_cleaned[$key]['dir']}, u.lastname {$order_cleaned[$key]['dir']}";
                    continue;
                case 2:
                    $sort[] = "u.email {$order_cleaned[$key]['dir']}";
                    break;
                case 3:
                    $sort[] = "u.lastaccess {$order_cleaned[$key]['dir']}";
                    break;
                default:
                    // Do not sort by other columns.
                    continue;
            }
        }
        $debug && Logger::log($fxn . '::Built $sort=' . ia_u::var_dump($sort));

        // Get list of Moodle course participants.
        $roleid = ia_mu::get_default_course_role($coursecontext);
        $groupid = 0;
        // We can't apply search filters here b/c we need to include results from fullname and IA info.
        $enrolledusers = \get_role_users($roleid, $coursecontext, false, 'ra.id, u.id, u.email, u.lastaccess, u.picture, u.imagealt, ' . get_all_user_name_fields(true, 'u'), implode(', ', $sort), true, $groupid, null, null);
        $debug && Logger::log($fxn . '::Got count($enrolledusers)=' . ia_u::count_if_countable($enrolledusers));
        if (ia_u::is_empty($enrolledusers)) {
            $debug && Logger::log($fxn . "::No users enrolled in this courseid={$courseid}");
            return $result;
        }
        $dataToReturn['recordsTotal'] = ia_u::count_if_countable($enrolledusers);

        // Get list of IA participants.
        $debug && Logger::log($fxn . '::About to fetch participants with apikey=' . $blockinstance->config->apikey . '; appid=' . $appid . '; courseid=' . $courseid);
        $participants = ia_api::get_participants($blockinstance->config->apikey, $appid, $courseid);
        $debug && Logger::log($fxn . '::Got $participants=' . ia_u::var_dump($participants));
        if (ia_u::is_empty($participants)) {
            $debug && Logger::log($fxn . "::No IA participants found for this courseid={$courseid}");
            return $result;
        }

        $pictureparams = ['size' => 35, 'courseid' => $courseid, 'includefullname' => true];
        //$prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_overviewcourse';
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $cm->context);
        $rows = [];
        foreach ($enrolledusers as $user) {
            $rows[$user->id] = [
                $user->id,
                array('picture' => ia_mu::get_user_picture($user, $pictureparams), 'name' => fullname($user, $canviewfullnames)),
                $user->email,
                ($user->lastaccess ? \userdate($user->lastaccess) : get_string('never')),
                // IA info - this is set later.
                '',
                // IA photo - this is set later.
                '',
            ];
        }

        // Populate IA data into the list of Moodle users, and apply the incoming filter.
        // Any IA participants no longer enrolled will not be shown.
        foreach ($participants as $p) {
            $rows[$p->participantidentifier][4] = ia_output::get_participant_summary_output($blockinstance, $p, false, true, true);
            $rows[$p->participantidentifier][5] = $p->participantphoto;
        }

        // Apply the incoming filter.
        if ($tblsearch_cleaned) {
            $debug && Logger::log($fxn . '::About to filter row data for $tblsearch_cleaned=' . $tblsearch_cleaned);
            foreach ($rows as $key => $row) {
                $debug && Logger::log($fxn . '::Looking at row with $key=' . $key);
                $searchmatch = false;
                foreach ($row as $colnumber => $colvalue) {
                    $debug && Logger::log($fxn . '::Looking at column with $colnumber=' . $colnumber);
                    // Skip columns DataTables has marked as non-searchable.
                    if (!$columns_cleaned[$key]['searchable']) {
                        continue;
                    }
                    switch ($colnumber) {
                        case 0:
                            // Do not search the userid column.
                            continue;
                        case 1:
                            // Search the fullname plaintext only.
                            $plaintext = \clean_param($colvalue['name'], \PARAM_TEXT);
                            $debug && Logger::log($fxn . '::Searching in column value $plaintext=' . $plaintext);
                            if (stripos($plaintext, $tblsearch_cleaned) !== false) {
                                $searchmatch = true;
                                break 2;
                            }
                            break;
                        case 2:
                        case 3:
                        case 4:
                            // Search the plaintext.
                            $plaintext = \clean_param($colvalue, \PARAM_TEXT);
                            $debug && Logger::log($fxn . '::Searching in column value $plaintext=' . $plaintext);
                            if (stripos($plaintext, $tblsearch_cleaned) !== false) {
                                $debug && Logger::log($fxn . '::Search found a match');
                                $searchmatch = true;
                                // Break out of the select and the foreach(column).
                                break 2;
                            }
                            $debug && Logger::log($fxn . '::Search found no match');
                            break;
                        case 5:
                            // Do not search the base64 IA participant photo.
                            continue;
                    }
                    if ($searchmatch) {
                        $debug && Logger::log($fxn . '::We have a match, so go to the next row');
                        break;
                    }
                }
                // Remove rows with no match.
                if (!$searchmatch) {
                    unset($rows[$key]);
                }
            }
        }

        $dataToReturn['recordsFiltered'] = ia_u::count_if_countable($rows);
        $dataToReturn['data'] = array_values($rows);
        $debug && Logger::log($fxn . "::Built \$dataToReturn=" . ia_u::var_dump($dataToReturn));

        // This holds the DataTables return data.
        $result['values'] = json_encode($dataToReturn, JSON_INVALID_UTF8_IGNORE);

        $debug && Logger::log($fxn . '::No warnings; About to return $result=' . ia_u::var_dump($result));
        return $result;
    }

    /**
     * Describes the parameters for the external function.
     *
     * @return external_function_parameters The parameters for the external function.
     */
    public static function get_datatables_participants_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'appid' => new \external_value(\PARAM_ALPHANUMEXT),
            'courseid' => new \external_value(\PARAM_INT),
            'draw' => new \external_value(\PARAM_INT),
            'start' => new \external_value(\PARAM_INT),
            'length' => new \external_value(\PARAM_INT),
            // In a departure from the DataTables docs, I've modded the JS to submit the filter field as only the search.value string here (not an array, and no regex).
            // This value should be further sanitized before use.
            'tblsearch' => new \external_value(\PARAM_TEXT, null, null, '', true),
            'order' => new \external_multiple_structure(
                    new \external_single_structure([
                        'column' => new \external_value(\PARAM_INT),
                        'dir' => new \external_value(\PARAM_ALPHANUMEXT),
                            ])
            ),
            'columns' => new \external_multiple_structure(
                    new \external_single_structure([
                        'data' => new \external_value(\PARAM_INT),
                        'name' => new \external_value(\PARAM_ALPHAEXT),
                        'orderable' => new \external_value(\PARAM_BOOL),
                        'searchable' => new \external_value(\PARAM_BOOL)
                            /* The 'search' param is totally ignored b/c we do not support per-column searches. */
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
            'submitted' => new \external_value(\PARAM_BOOL, 'submitted', true, false, false),
            'warnings' => new \external_warnings(),
            // Array of key-value.
            'values' => new \external_value(\PARAM_RAW_TRIMMED, 'Moodle structure including DataTables data', null, 'returned value', true),
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
