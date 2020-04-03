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
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/user/lib.php');

/** @var string Short name for this block */
const INTEGRITYADVOCATE_SHORTNAME = 'integrityadvocate';

/** @var string Longer name for this block. */
const INTEGRITYADVOCATE_BLOCKNAME = 'block_integrityadvocate';

/** @var string Scheduled cron task name. */
const INTEGRITYADVOCATE_TASKNAME = 'block_integrityadvocate\task\process_integrityadvocate';

/** @var string Base url for the API. */
const INTEGRITYADVOCATE_BASEURL = 'https://integrityadvocate.com';

/** @var string Version of the API to use. */
const INTEGRITYADVOCATE_API_PATH = 'api2';

/** @var string String the IA API uses for a proctor session that is complete and valid. */
const INTEGRITYADVOCATE_API_STATUS_VALID = 'Valid';

/** @var string String the IA API uses for a proctor session that is started but not yet complete. */
const INTEGRITYADVOCATE_API_STATUS_INPROGRESS = 'In Progress';

/** @var string String the IA API uses for a proctor session that is complete but the presented ID card is invalid. */
const INTEGRITYADVOCATE_API_STATUS_INVALID_ID = 'Invalid (ID)';

/** @var string String the IA API uses for a proctor session that is complete but in participating the user broke 1+ rules.  See IA flags for details. */
const INTEGRITYADVOCATE_API_STATUS_INVALID_RULES = 'Invalid (Rules)';

/** @var string The remote API's timezone so we can convert to/from unix and user time. */
const INTEGRITYADVOCATE_API_TIMEZONE = 'America/Edmonton';

/** @var string Store logged messaged to the standard PHP error log. */
const INTEGRITYADVOCATE_LOGDEST_ERRORLOG = 'ERRORLOG';

/** @var string Send logged messages to standard HTML output, adding a <br> tag and a newline. */
const INTEGRITYADVOCATE_LOGDEST_HTML = 'HTML';

/** @var string Store logged messaged to the moodle log handler plain-textified. */
const INTEGRITYADVOCATE_LOGDEST_MLOG = 'MLOG';

/** @var string Store logged messaged to STDOUT through htmlentities. */
const INTEGRITYADVOCATE_LOGDEST_STDOUT = 'STDOUT';

/** @var int Time out remote IA sessions after this many minutes. */
const INTEGRITYADVOCATE_SESS_TIMEOUT = 10;

static $block_integrityadvocate_log_dest = INTEGRITYADVOCATE_LOGDEST_ERRORLOG;

/**
 * Log $message to HTML output, mlog, stdout, or error log
 *
 * @global object $CFG Moodle CFG object
 * @global string $block_integrityadvocate_log_dest The current logging destination so it can be overridden globally.
 * @param string $message Message to log
 * @param string $dest One of the INTEGRITYADVOCATE_LOGDEST_* constants.
 */
function block_integrityadvocate_log($message, $dest = false) {
    global $CFG, $block_integrityadvocate_log_dest;
    $debug = false;
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started with $dest=' . $dest . "\n");

    // I did not use the PHP7.4 null coalesce b/c we want compat back to PHP5.6.
    $dest = $dest ?: $block_integrityadvocate_log_dest;
    $dest = $dest ?: INTEGRITYADVOCATE_LOGDEST_ERRORLOG;

    // If the file path is included, strip it.
    $cleanedmsg = str_replace($CFG->dirroot, '', $message);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::After cleanup, $dest=' . $dest . "\n");

    switch ($dest) {
        case INTEGRITYADVOCATE_LOGDEST_HTML:
            print($cleanedmsg) . "<br />\n";
            break;
        case INTEGRITYADVOCATE_LOGDEST_MLOG:
            // If the file path was included, remove it entirely by stripping up to the first :: inclusive.
            $posn = strpos($cleanedmsg, '::');
            if ($posn !== false && stripos($message, $CFG->dirroot) !== false) {
                $cleanedmsg = substr($cleanedmsg, $posn + 2);
            }
            mtrace(html_to_text($cleanedmsg, 0, false));
            break;
        case INTEGRITYADVOCATE_LOGDEST_STDOUT:
            print(htmlentities($cleanedmsg, 0, false)) . "\n";
            break;
        case INTEGRITYADVOCATE_LOGDEST_ERRORLOG:
        default:
            error_log($cleanedmsg);
            break;
    }
}

/**
 * Return if there are config errors
 *
 * @global moodle_database $DB Moodle DB object
 * @global stdClass $COURSE Moodle COURSE object
 * @param block_integrityadvocate $blockinstance to check
 * @throws Exception If error
 * @return array(field=>error message)
 */
function block_integrityadvocate_ia_config_errors(block_integrityadvocate $blockinstance) {
    $debug = false;
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started with $blockinstance=' . print_r($blockinstance, true));

    $errors = array();
    if (empty($blockinstance->config->apikey)) {
        $errors['config_apikey'] = get_string('error_noapikey', INTEGRITYADVOCATE_BLOCKNAME);
    }
    if (empty($blockinstance->config->appid)) {
        $errors['config_appid'] = get_string('error_noappid', INTEGRITYADVOCATE_BLOCKNAME);
    }

    /*
     * If this block is added to a a quiz, warn instructors if the block is hidden to students during quiz attempts.
     */
    $parentcontext = $blockinstance->context->get_parent_context();
    if (empty($parentcontext) || $parentcontext->contextlevel !== CONTEXT_MODULE) {
        // This is a course-level block and not a quiz/module-level block, so just return what errors we have so far.
        return $errors;
    }

    if (stripos($parentcontext->get_context_name(), 'quiz') === 0) {
        global $DB, $COURSE;
        if ($COURSE->id == SITEID) {
            throw new Exception('This block cannot exist on the site context');
        }
        $modinfo = get_fast_modinfo($COURSE);
        $cm = $modinfo->get_cm($parentcontext->instanceid);
        $record = $DB->get_record('quiz', array('id' => $cm->instance), 'id, showblocks', MUST_EXIST);
        if ($record->showblocks < 1) {
            $errors['config_quiz_showblocks'] = get_string('error_quiz_showblocks', INTEGRITYADVOCATE_BLOCKNAME);
        }
    }

    return $errors;
}

/**
 * Extract user data from the IA API participant data array
 * (This is the Participants part of the curl object from the API with properties ParticipantCount:int and Participants:array)
 *
 * @param object[] $participantdata All the IA data returned from the API for an AppID
 * @param string $useridentifier Identifier for the user to find info for
 * @return false if nothing found; else Object of IA participant data
 */
function block_integrityadvocate_parse_user_data(array $participantdata, $useridentifier) {
    $debug = false;
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Started with \$useridentifier={$useridentifier}; \$participantdata=" . print_r($participantdata, true));

    foreach ($participantdata as $p) {
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Looking at $p=' . print_r($p, true));
        if ($p->ParticipantIdentifier == $useridentifier) {
            return $p;
        }
    }
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::No data found for \$useridentifier={$useridentifier}");
    return false;
}

/**
 * Parse the IA participants status code against a whitelist of INTEGRITYADVOCATE_API_STATUS_* constants.
 *
 * @param stdClass $participant an IA participant data object
 * @return int An integer representing the status matching one of the INTEGRITYADVOCATE_API_STATUS_* constants.
 * @throws InvalidValueException
 */
function block_integrityadvocate_parse_status(stdClass $participant) {
    switch ($reviewstatus = clean_param($participant->ReviewStatus, PARAM_TEXT)) {
        case INTEGRITYADVOCATE_API_STATUS_INPROGRESS:
            $status = -1;
            break;
        case INTEGRITYADVOCATE_API_STATUS_VALID:
            $status = 0;
            break;
        case INTEGRITYADVOCATE_API_STATUS_INVALID_ID:
            $status = 1;
            break;
        case INTEGRITYADVOCATE_API_STATUS_INVALID_RULES:
            $status = 2;
            break;
        default:
            $error = 'Invalid participant review status value=' . serialize($reviewstatus);
            block_integrityadvocate_log($error);
            throw new InvalidValueException($error);
    }
    return $status;
}

/**
 * Generate the HTML to view details for this user.
 *
 * @global object $OUTPUT Moodle OUTPUT object so we can use its single_button() function
 * @param int $blockinstanceid The block instance id
 * @param int $courseid The course id
 * @param int $userid The user id
 * @return HTML to view user details
 */
function block_integrityadvocate_get_overview_user_button($blockinstanceid, $courseid, $userid) {
    global $OUTPUT;
    $parameters = array('instanceid' => $blockinstanceid, 'courseid' => $courseid, 'userid' => $userid, 'sesskey' => sesskey());
    $url = new moodle_url('/blocks/integrityadvocate/overview.php', $parameters);
    $label = get_string('overview_view_details', INTEGRITYADVOCATE_BLOCKNAME);
    $options = array('class' => 'block_integrityadvocate_overview_btn_view_details');
    return $OUTPUT->single_button($url, $label, 'post', $options);
}

/**
 * Generate the HTML to view details for all course users.
 *
 * @global object $OUTPUT Moodle OUTPUT object so we can use its single_button() function
 * @param int $blockinstanceid The block instance id
 * @param int $courseid The course id
 * @return HTML to view user details
 */
function block_integrityadvocate_get_overview_course_button($blockinstanceid, $courseid) {
    global $OUTPUT;
    $parameters = array('instanceid' => $blockinstanceid, 'courseid' => $courseid, 'sesskey' => sesskey());
    $url = new moodle_url('/blocks/integrityadvocate/overview.php', $parameters);
    $label = get_string('button_overview', INTEGRITYADVOCATE_BLOCKNAME);
    $options = array('class' => 'overviewButton');
    return $OUTPUT->single_button($url, $label, 'post', $options);
}

/**
 * Convert unix time to a time in the IA API's timezone and format
 *
 * @param int $unixtime Unix time (GMT)
 * @return string Formatted as "yyyy-MM-d HH:mm:ss.fff" in the API timezone
 */
function block_integrityadvocate_to_apitimezone($unixtime) {
    return userdate($unixtime, '%F %T.000', INTEGRITYADVOCATE_API_TIMEZONE, false, false);
}

/**
 * Convert an IA API-provided time (format and timezone) to unix time.
 *
 * @param string $datetime String representation of datetime in API timezone
 * @return int unix timestamp in GMT
 */
function block_integrityadvocate_from_apitimezone($datetime) {
    $tz = date_default_timezone_get();
    date_default_timezone_set(INTEGRITYADVOCATE_API_TIMEZONE);
    $converteddatetime = strtotime($datetime);
    date_default_timezone_set($tz);
    return $converteddatetime;
}

/**
 * Parse the IA $participant object and return HTML output showing latest status, flags, and photos
 *
 * @param stdClass $participant Participant object from the IA API
 * @param int $blockinstanceid The block instance id
 * @param int $courseid The course id
 * @param int $userid The user id
 * @param boolean $showviewdetailsbutton True to show the viewDetails button
 * @param boolean $includephoto True to include the user photo
 * @return string HTML output showing latest status, flags, and photos
 * @throws InvalidValueException If the participant status field does not match one of our known values
 */
function block_integrityadvocate_get_participant_summary_output(stdClass $participant, $blockinstanceid, $courseid, $userid, $showviewdetailsbutton = true, $includephoto = true) {
    $debug = false;
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started with $userid=' . $userid . '; $participant' . print_r($participant, true));

    $out = '<div class="block_integrityadvocate_overview_participant_summary_div">';
    $out .= '<div class="block_integrityadvocate_overview_participant_summary_text">';

    // The params [$participant->Created and $participant->Completed] are strings in API timezone, so must be converted.
    date_default_timezone_set(INTEGRITYADVOCATE_API_TIMEZONE);
    // The params $sdate, $edate are int unix timestamps.
    $startdate = block_integrityadvocate_from_apitimezone(clean_param($participant->Created, PARAM_TEXT));
    if ($participant->Completed != null) {
        $enddate = block_integrityadvocate_from_apitimezone(clean_param($participant->Completed, PARAM_TEXT));
    } else {
        $enddate = null;
    }

    $reviewstatus = clean_param($participant->ReviewStatus, PARAM_TEXT);
    $statusnumeric = block_integrityadvocate_parse_status($participant);
    $resubmithtml = '';
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::About to switch on $reviewstatus=' . $reviewstatus);

    switch ($reviewstatus) {
        case INTEGRITYADVOCATE_API_STATUS_INPROGRESS:
            $statushtml = '<span class="block_integrityadvocate_status_inprogress">' . get_string('status_in_progress', INTEGRITYADVOCATE_BLOCKNAME) . '</span>';
            break;
        case INTEGRITYADVOCATE_API_STATUS_VALID:
            $statushtml = '<span class="block_integrityadvocate_status_valid">' . get_string('status_valid', INTEGRITYADVOCATE_BLOCKNAME) . '</span>';
            break;
        case INTEGRITYADVOCATE_API_STATUS_INVALID_ID:
            $statushtml = '<span class="block_integrityadvocate_status_invalid_id">' . get_string('status_invalid_id', INTEGRITYADVOCATE_BLOCKNAME) . '</span>';
            $resubmiturl = isset($participant->IDResubmitUrl) ? block_integrityadvocate_fix_api_url($participant->IDResubmitUrl) : '';
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Status is INTEGRITYADVOCATE_API_STATUS_INVALID_ID; got $resubmiturl=' . $resubmiturl);
            if ($resubmiturl) {
                $resubmithtml = '<span class="block_integrityadvocate_resubmit_link">' . format_text(html_writer::link($resubmiturl, get_string('resubmit_link', INTEGRITYADVOCATE_BLOCKNAME), array('target' => '_blank')), FORMAT_HTML) . '</span>';
            }
            break;
        case INTEGRITYADVOCATE_API_STATUS_INVALID_RULES:
            $statushtml = '<span class="block_integrityadvocate_status_invalid_rules">' . get_string('status_invalid_id', INTEGRITYADVOCATE_BLOCKNAME) . '</span>';
            break;
        default:
            $error = 'Invalid participant review status value=' . serialize($reviewstatus);
            block_integrityadvocate_log($error);
            throw new InvalidValueException($error);
    }

    $out .= '<div class="block_integrityadvocate_overview_participant_summary_status"><span class="block_integrityadvocate_overview_participant_summary_status_label">' . get_string('overview_user_status', INTEGRITYADVOCATE_BLOCKNAME) . ': </span>' . $statushtml . '</div>';
    if ($resubmithtml) {
        $out .= '<div class="block_integrityadvocate_overview_participant_summary_resubmit">' . $resubmithtml . '</div>';
    }
    $out .= '<div class="block_integrityadvocate_overview_participant_summary_start"><span class="block_integrityadvocate_overview_participant_summary_status_label">' . get_string('start_time', INTEGRITYADVOCATE_BLOCKNAME) . ': </span>' . date('Y-m-d H:i', $startdate) . '</div>';

    $out .= '<div class="block_integrityadvocate_overview_participant_summary_end"><span class="block_integrityadvocate_overview_participant_summary_status_label">' . get_string('end_time', INTEGRITYADVOCATE_BLOCKNAME) . ': </span>';
    if ($enddate == null) {
        $out .= get_string('status_in_progress', INTEGRITYADVOCATE_BLOCKNAME);
    } else {
        $out .= date('Y-m-d H:i', $enddate);
    }
    // Close .block_integrityadvocate_overview_participant_summary_end.
    $out .= '</div>';

    if ($showviewdetailsbutton) {
        $out .= block_integrityadvocate_get_overview_user_button($blockinstanceid, $courseid, $userid);
    }

    // Close .block_integrityadvocate_overview_participant_summary_text.
    $out .= '</div>';

    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::About to check if should include photo; $include_photo=' . $includephoto);
    if ($includephoto) {
        $photohtml = block_integrityadvocate_get_summary_photo_html($participant, $statusnumeric);
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Built photo html=' . $photohtml);
        $out .= $photohtml;
    }

    // Close .block_integrityadvocate_overview_participant_summary_div.
    $out .= '</div>';

    // Start next section on a new line.
    $out .= '<div style="clear:both"></div>';

    return $out;
}

/**
 * Get the HTML used to display the user photo in the IA summary output
 *
 * @param stdClass $participant An IA participant object to pull info from.
 * @param int $status an INTEGRITYADVOCATE_API_STATUS_* status
 * @return string HTML to output
 */
function block_integrityadvocate_get_summary_photo_html(stdClass $participant, $status) {
    $out = html_writer::start_tag('div', array('class' => 'block_integrityadvocate_overview_participant_summary_img_div'));
    $url = block_integrityadvocate_fix_api_url($participant->UserPhotoUrl);
    if ($url) {
        $out .= '<span class="block_integrityadvocate_overview_participant_summary_img block_integrityadvocate_overview_participant_summary_img_' . ($status === 0 ? '' : 'in') . 'valid">' . html_writer::img($url, '') . '</span>';
    }
    // Close .block_integrityadvocate_overview_participant_summary_img_div.
    $out .= html_writer::end_tag('div');

    return $out;
}

/**
 * Build the HTML to display IA flags (errors and corresponding messages)
 *
 * @param stdClass $participant The IA participant object to pull info from.
 * @return string HTML to output
 */
function block_integrityadvocate_get_participant_flags_output(stdClass $participant) {
    if (count($participant->Flags) < 1) {
        return '';
    }
    $out = '<div class="block_integrityadvocate_overview_flags_div">';
    $out .= '<span class="block_integrityadvocate_overview_flags_title">' . get_string('overview_flags', INTEGRITYADVOCATE_BLOCKNAME) . ':</span>';
    $out .= '<table class="flexible block_integrityadvocate_overview_flags_table">';
    foreach ($participant->Flags as $f) {
        $out .= '<tr class="block_integrityadvocate_overview_flags_tr">';
        $out .= '<td class="block_integrityadvocate_overview_flags_td_dataid">' . clean_param($f->ParticipantDataId, PARAM_INT) . '</td>';
        $out .= '<td class="block_integrityadvocate_overview_flags_td_errorinfo">' .
                '<div class="block_integrityadvocate_overview_flags_errorcode"><span class="block_integrityadvocate_overview_flags_errorcode_title">' . get_string('flag_errorcode', INTEGRITYADVOCATE_BLOCKNAME) . '</span> ' .
                '<span class="block_integrityadvocate_overview_flags_errorcode_number">' . clean_param($f->FlagId, PARAM_INT) . ': </span>' .
                '<span class="block_integrityadvocate_overview_flags_errorcode_text">' . htmlentities(clean_param($f->FlagName, PARAM_TEXT)) . '</span></div>';
        $out .= '<div class="block_integrityadvocate_overview_flags_details"><span class="block_integrityadvocate_overview_flags_details_title">' . get_string('flag_details', INTEGRITYADVOCATE_BLOCKNAME) . '</span>: ' .
                '<span class="block_integrityadvocate_overview_flags_details_text">' . htmlentities(clean_param($f->FlagDetails, PARAM_TEXT)) . '</span></div>';
        $out .= '</td><td>';
        if ($url = block_integrityadvocate_fix_api_url($f->ImageUrl)) {
            $out .= '<span class="block_integrityadvocate_overview_flags_img"><img src="' . filter_var($url, FILTER_SANITIZE_URL) . '"/></span>';
        }
        $out .= '</td></tr>';
    }
    $out .= '</table>';

    // Close .block_integrityadvocate_overview_flags_div.
    $out .= '</div>';

    return $out;
}

/**
 * Get the user_enrolment.id (UEID) for the given course-user combo
 * Ignores deleted and suspended users
 *
 * @global moodle_database $DB Moodle DB object
 * @param context $modulecontext The context of the module the IA block is attached to.
 * @param int $userid The user id to get the ueid for
 * @return int the ueid
 * @throws InvalidArgumentException
 */
function block_integrityadvocate_get_ueid(context $modulecontext, $userid) {
    global $DB;
    $debug = true;
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Started with userid={$userid}");

    if (!is_numeric($userid)) {
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Param validation failed");
        throw new InvalidArgumentException('userid must be an int');
    }
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Param validation done");

    // --This section adapted from enrollib.php::get_enrolled_with_capabilities_join().
    // Initialize empty arrays to be filled later.
    $joins = array();
    $wheres = array();

    $enrolledjoin = get_enrolled_join($modulecontext, 'u.id;', true);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::get_enrolled_join() returned=" . print_r($enrolledjoin, true));

    // Make the parts easier to use.
    $joins[] = $enrolledjoin->joins;
    $wheres[] = $enrolledjoin->wheres;
    $params = $enrolledjoin->params;

    // Clean up Moodle-provided joins.
    $joins = implode("\n", str_replace(';', ' ', $joins));
    // Add our critariae.
    $wheres[] = "u.suspended=0 AND u.deleted=0 AND u.id=" . intval($userid);
    $wheres = implode(' AND ', $wheres);

    // Figure out what prefix was used.
    $matches = array();
    preg_match('/ej[0-9]+_/', $joins, $matches);
    $prefix = $matches[0];
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Got prefix=$prefix");

    // Build the full join part of the sql.
    $sqljoin = new \core\dml\sql_join($joins, $wheres, $params);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Built sqljoin=' . print_r($sqljoin, true));
    /*
     * The value of $sqljoin is something like this:
     * JOIN {user_enrolments} ej1_ue ON ej1_ue.userid = u.id;
     * JOIN {enrol} ej1_e ON (ej1_e.id = ej1_ue.enrolid AND ej1_e.courseid = :ej1_courseid)
     * [wheres] => 1 = 1
     *   AND ej1_ue.status = :ej1_active
     *   AND ej1_e.status = :ej1_enabled
     *   AND ej1_ue.timestart < :ej1_now1
     *   AND (ej1_ue.timeend = 0 OR ej1_ue.timeend > :ej1_now2)
     *
     * [params] => Array
     *       (
     *           [ej1_courseid] => 2
     *           [ej1_enabled] => 0
     *           [ej1_active] => 0
     *           [ej1_now1] => 1577401300
     *           [ej1_now2] => 1577401300
     *       )
     */
    //
    // --This section adapted from enrollib.php::get_enrolled_join()
    // Build the query including our select clause.
    // Use MAX and GROUP BY in case there are multiple user-enrolments.
    $sql = "
                SELECT  {$prefix}ue.id, max({$prefix}ue.timestart)
                FROM    {user} u
                {$sqljoin->joins}
                WHERE {$sqljoin->wheres}
                GROUP BY {$prefix}ue.id
                ";
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Built sql={$sql} with params=" . print_r($params, true));

    $enrolmentinfo = $DB->get_record_sql($sql, $sqljoin->params, IGNORE_MULTIPLE);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got $userEnrolmentInfo=' . print_r($enrolmentinfo, true));

    if (!$enrolmentinfo) {
        block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Failed to find an active user_enrolment.id for \$userid={$userid} and \$modulecontext->id={$modulecontext->id} with \$sql={$sql}");
        // Return a guaranteed-invalid userid.
        return -1;
    }

    return $enrolmentinfo->id;
}

/**
 * Build a user identifier string to give to the IA API
 * "This field is the unique identifier provided by your system when the Participant was engaged in the activity,
 * for use in linking the Integrity Advocate data back to the appropriate enrollment in your system."
 *
 * @link https://integrityadvocate.com/Developers#aEndpointMethods
 * @param context $modulecontext containing the block_integrityadvocate instance
 * @param int $userid The userid to encode
 * @return string The built identifier
 */
function block_integrityadvocate_encode_useridentifier(context $modulecontext, $userid) {
    $debug = false;
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started with userid=' . $userid);

    if (!is_numeric($userid)) {
        throw new InvalidArgumentException('Input $userid must be numeric');
    }

    $coursecontext = $modulecontext->get_course_context();
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Got courseid={$coursecontext->instanceid}");

    return bin2hex("{$coursecontext->instanceid}-{$userid}");
}

/**
 * Given the useridentifier (built with block_integrityadvocate_encode_useridentifier()),
 * return the courseid and userid
 *
 * @param string $useridentifier a hex2bin-encoded string
 * @return array of (courseid, userid); null if nothing valid found.
 */
function block_integrityadvocate_decode_useridentifier($useridentifier) {
    $debug = false;
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started with $useridentifier=' . $useridentifier);

    $strlen = strlen($useridentifier);
    // The minimum valid length of the identifier is 6 chars, e.g. hex2bin('0-0').
    if ($strlen < 6) {
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::The $useridentifier is too short, so skip it');
        return null;
    }

    if ($strlen % 2 !== 0) {
        // Note: hex2bin(): Hexadecimal input string must have an even length.
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::The $useridentifier does not have an even length, so skip it');
        return null;
    }

    $decoded = hex2bin($useridentifier);
    if (strlen($decoded) < 3) {
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::The $useridentifier is too short, so skip it');
        return null;
    }
    if (stripos($decoded, '-') === false) {
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::The $useridentifier does not contain the expected delimiter (-), so skip it');
        return null;
    }

    return explode('-', $decoded);
}

/**
 * Returns the results of user_get_user_details() for the user in this course, plus the course-lastaccess time
 *
 * @global moodle_database $DB Moodle DB object
 * @param stdClass $user Moodle user object
 * @param stdClass $course Moodle course object
 * @return array of user info
 */
function block_integrityadvocate_get_course_userinfo(stdClass $user, stdClass $course) {
    global $DB;
    $userinfo = user_get_user_details($user, $course);

    // The core function user_get_user_details returns lastcourseaccess=0 so get it manually.
    $userinfo['lastaccess'] = get_last_access($user->id, $course->id);

    return $userinfo;
}

function get_last_access($userid, $courseid) {
    global $DB;
    // Disabled on purpose: $debug && block_integrityadvocate_log('Got $lastaccesses_record=' . print_r($lastaccesses_record, true));.
    return $DB->get_field('user_lastaccess', 'timeaccess', array('courseid' => $courseid, 'userid' => $userid));
}

/**
 * Get IA proctoring participant data from the remote API for the given API key
 * @link https://integrityadvocate.com/Developers#aEndpointMethods
 * The values get basic sanitization(no tags, etc) before being returned.
 *
 * The results look like this:
 * integrityadvocate/locallib.php::block_integrityadvocate_get_ia_participant_data::Got $response=stdClass Object
 * ( [Participants] => Array (
 *        [0] => stdClass Object (
 *                [ParticipantId] => 118407
 *                [ReviewStatus] => Valid
 *                [ParticipantIdentifier] => 2
 *                [FirstName] => Admin
 *                [LastName] => User
 *                [Email] =>
 *                [Application] => Moodle Demo Quiz
 *                [Created] => 2017-11-28T10:22:58.43
 *                [Completed] => 2019-12-27T17:27:01.49
 *                [SessionCount] => 2
 *                [FlagCount] => 1
 *                [UserPhotoUrl] => //integrityadvocate.com/Capture/GetCapture?v=L0ZyYW1lcy8xMTg0MDcvNjk0MjQ3MDQuanBn
 *                [IDResubmitUrl] =>
 *                [Flags] => Array
 *                        [0] => stdClass Object
 *                            (
 *                                [ParticipantDataId] => 69444704
 *                                [FlagId] => 8
 *                                [FlagName] => Information received was unclear and/or did not meet requirements
 *                                [FlagDetails] => ID was not provided that matches user image. Please present your government-issued photo ID card to the camera and take a clear, readable photo of the card.
 *                                [ImageUrl] => //integrityadvocate.com/Capture/GetCapturdsv=L0ZyYW1lcy8xMTg0MDcvNjk0MjQ3MDQuanBn
 *                            )
 *                    )
 *            )
 *        [1] => stdClass Object (
 *                [ParticipantId] => 187158
 *                ...
 *            ),
 *           ...
 *  ),
 *  [ParticipantCount] => 14
 * )
 *
 * @param string $apikey The API Key to get data for
 * @param string $appid The AppId to get data for
 * @param array[string]string $params API params per the URL above.  e.g. array('participantidentifier'=>$user_identifier).
 * @return object[]|boolean false if nothing found; else array of IA participants objects
 */
function block_integrityadvocate_get_ia_participant_data($apikey, $appid, array $params = array()) {
    $debug = false;
    $path = '/Participants/Get';
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Start with params=' . print_r($params, true));

    // Gets a max of 50 records.
    $result = block_integrityadvocate_do_api_get($path, $apikey, $appid, $params);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got $response=' . print_r($result, true));

    if (empty($result) || empty($result->ParticipantCount)) {
        block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::' . get_string('no_remote_participants', INTEGRITYADVOCATE_BLOCKNAME));
        return false;
    }

    // If there are 50+ results, get more.
    // Collect results in $participants_collector.
    $maxrecords = 50;
    $participants = $result->Participants;
    if (intval($result->ParticipantCount) > $maxrecords) {
        $lcnt = ceil((intval($result->ParticipantCount) - $maxrecords) / $maxrecords);
        for ($i = 0; $i < $lcnt; $i++) {
            $offset = ($i * $maxrecords) + $maxrecords;

            $params['offset'] = $offset;
            $params['next'] = $maxrecords;
            $result = block_integrityadvocate_do_api_get($path, $apikey, $appid, $params);
            array_push($result->Participants, $participants);
        }
    }

    // Clean API participant results for safe use.
    // Note the use of & here to update the array itself.
    foreach ($participants as &$p) {
        // Make it compatible with clean_param_array().
        if ($p->FlagCount && $p->Flags) {
            // Use $f by reference here so we can update it.
            foreach ($p->Flags as &$f) {
                $f->FlagId = clean_param($f->FlagId, PARAM_INT);
                $f->ImageUrl = filter_var($f->ImageUrl, FILTER_SANITIZE_URL);
                $f = (array) $f;
            }
        }
        $p = (array) $p;
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::About to clean $p=' . print_r($p, true));

        // Clean all as PARAM_TEXT.
        $p = clean_param_array($p, PARAM_TEXT, true);
        if ($p['FlagCount'] && $p['Flags']) {
            // Use $f by reference here so we can update it.
            foreach ($p['Flags'] as &$f) {
                $f = (object) $f;
            }
        }
        $p = (object) $p;

        // Do more specific cleaning.
        $p->IDResubmitUrl = filter_var($p->IDResubmitUrl, FILTER_SANITIZE_URL);
        $p->UserPhotoUrl = filter_var($p->UserPhotoUrl, FILTER_SANITIZE_URL);
        $p->Email = clean_param($p->Email, PARAM_EMAIL);
        $p->SessionCount = clean_param($p->SessionCount, PARAM_INT);
        $p->FlagCount = clean_param($p->FlagCount, PARAM_INT);
    }

    return $participants;
}

/**
 * Convert userid to moodle user object into if needed.
 *
 * @param int|stdClass $user The user object or id to convert
 * @return boolean false if no user found; else moodle user object
 * @throws InvalidArgumentException
 */
function block_integrityadvocate_user_as_obj($user) {
    $debug = false;
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started with $user=' . print_r($user, true));

    if (is_numeric($user)) {
        $userarr = user_get_users_by_id(array(intval($user)));
        if (empty($userarr)) {
            return false;
        }
        $user = array_pop($userarr);
    }
    if (gettype($user) != 'object') {
        throw new InvalidArgumentException('$user should be of type stdClass; got ' . gettype($user));
    }

    return $user;
}

/**
 * Convert userid to moodle course object into if needed.
 *
 * @param int|stdClass $course The course object or courseid to check
 * @return boolean false if no course found; else moodle course object
 * @throws InvalidArgumentException
 */
function block_integrityadvocate_course_as_obj($course) {
    if (is_numeric($course)) {
        $course = get_course(intval($course));
    }
    if (empty($course)) {
        return false;
    }
    if (gettype($course) != 'object' || !isset($course->id)) {
        throw new InvalidArgumentException('$course should be of type stdClass; got ' . gettype($course));
    }

    return $course;
}

/**
 * Get the activities in this course that have a configured, visible IA block attached,
 * optionally filtered to IA blocks having a matching apikey and appid
 *
 * @param stdClass|int $course The course to get activities from; if int the course object will be looked up
 * @param string $apikey The apikey to filter results for
 * @param string $appid The appID to filter results for
 * @return string|array Array of activities that match; else string error identifier
 */
function block_integrityadvocate_get_course_ia_activities($course, $apikey = false, $appid = false) {
    $debug = false;

    // Massage the course input if needed.
    $course = block_integrityadvocate_course_as_obj($course);
    if (!$course) {
        return 'no_course';
    }
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started with courseid=' . $course->id . '; $apikey=' . $apikey . '; $appid=' . $appid);

    // Get activities in this course.
    $activities = block_integrityadvocate_get_activities_with_completion($course->id);
    if (!$activities) {
        return 'no_activities_message';
    }
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Found ' . count($activities) . ' activities in this course');

    // Filter for activities that use an IA block.
    $activities = block_integrityadvocate_filter_activities_use_ia_block($activities);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Found ' . count($activities) . ' activities that use IA');
    if (!$activities) {
        return 'no_activities_config_message';
    }

    if (empty($apikey) || empty($apikey)) {
        return $activities;
    }

    // Filter for activities with matching apikey and appid.
    foreach ($activities as $key => $a) {
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Looking at activity with id=' . $a['id'] . '; name=' . $a['name']);
        $blockinstanceid = $a['block_integrityadvocate_instance']['id'];
        $blockinstance = $a['block_integrityadvocate_instance']['instance'];
        // Disabled on purpose: $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got $blockinstance->config=' . print_r($blockinstance->config, true));.
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Looking at activity with id=' . $a['id'] . '; name=' . $a['name']);

        if (empty($blockinstance) || !isset($blockinstance->config) || (!isset($blockinstance->config->apikey) && !isset($blockinstance->config->appid))) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::No blockinstance, or config, or apikey/appid');
            unset($activities[$key]);
            continue;
        }

        if (isset($apikey) && $blockinstance->config->apikey !== $apikey) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Found $blockinstance->config->apikey=' . $blockinstance->config->apikey . ' does not match requested apikey=' . $apikey);
            unset($activities[$key]);
            continue;
        }
        if (isset($appid) && $blockinstance->config->appid !== $apikey) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Found $blockinstance->config->apikey=' . $blockinstance->config->apikey . ' does not match requested appid=' . $appid);
            unset($activities[$key]);
            continue;
        }
    }

    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::After filtering for apikey/appid, count($activities)=' . count($activities));

    if (!$activities) {
        return 'no_activities_config_message';
    }

    return $activities;
}

/**
 * Pull together IA participation data for all activities in the given course for the given user.
 *
 * @param int|stdClass $course course->id to look it up, or stdClass Moodle course object.
 * @param int|stdClass $user user->id to look it up, or stdClass Moodle user object.
 * @param int|false $activitycontextid If specified, match only this activity contextid.
 * @return string lang string name if error; else
 *      array {
 *         'activity' => Moodle activity object with additional property
 *                  array{['block_integrityadvocate_instance']['instance']}
 *         'ia_participant_data' => stdClass - see description in comment for block_integrityadvocate_get_ia_participant_data();
 *      }
 * @throws InvalidArgumentException if user or course are invalid.
 */
function block_integrityadvocate_get_course_user_ia_data($course, $user, $activitycontextid = false) {
    $debug = false;

    // Massage the course input if needed.
    $course = block_integrityadvocate_course_as_obj($course);
    if (!$course) {
        return 'no_course';
    }

    // Massage the user input if needed.
    $user = block_integrityadvocate_user_as_obj($user);
    if (!$user) {
        return 'no_user';
    }
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started with course->id=' . $course->id . '; $user->id=' . $user->id);

    $results = array();
    $iaactivities = block_integrityadvocate_get_course_ia_activities($course);
    if (is_string($iaactivities)) {
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::About to return $iaactivities=' . print_r($iaactivities));
        return $iaactivities;
    }
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got block_integrityadvocate_get_course_ia_activities() count=' . count($iaactivities));

    // How to identify this user to the IA API?.
    $useridentifier = block_integrityadvocate_encode_useridentifier(\context_course::instance($course->id), $user->id);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Looking for $useridentifier=' . $useridentifier);

    foreach ($iaactivities as $a) {
        // Disabled on purpose: $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Looking at $a=' . print_r($a, true));.
        if ($activitycontextid && $a['context']->id !== $activitycontextid) {
            continue;
        }

        $blockinstanceid = $a['block_integrityadvocate_instance']['id'];
        $blockinstance = $a['block_integrityadvocate_instance']['instance'];
        // Disabled on purpopse: $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got $blockinstance=' . print_r($blockinstance, true));.
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got $blockinstance->id=' . $blockinstanceid);

        if (!isset($blockinstance->config) || (!isset($blockinstance->config->apikey) && !isset($blockinstance->config->appid))) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Skipping: isset($blockinstance->config)=' . isset($blockinstance->config) .
                            '; isset($blockinstance->config->apikey)=' . isset($blockinstance->config->apikey) .
                            '; isset($blockinstance->config->appid)=' . isset($blockinstance->config->appid)
            );
            continue;
        }

        // Get IA participation data for this user in this course-activity.
        $iaparticipantdata = block_integrityadvocate_get_ia_participant_data($blockinstance->config->apikey, $blockinstance->config->appid, array('participantidentifier' => $useridentifier));
        // Disabled on purpose: $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::For this activity got $ia_participant_data=<PRE>' . print_r($participantdata, true) . "<PRE><br />\n");.
        if (empty($iaparticipantdata)) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Skipping: $iaparticipantdata is empty');
            continue;
        }

        $hash = md5(json_encode($iaparticipantdata));
        $results[$hash] = array('activity' => $a, 'ia_participant_data' => array_pop($iaparticipantdata));
    }

    return array_values($results);
}

/**
 * Interact with the IA-side API to get results.
 *
 * @param string $endpointpath e.g. /Participants/Get
 * @param string $apikey The API Key to get data for
 * @param string $appid The AppId to get data for
 * @param array[string]string $params API params per the URL above.  e.g. array('participantidentifier'=>$user_identifier).
 * @return string|boolean false if nothing found or error; else the json-decoded curl response body
 */
function block_integrityadvocate_do_api_get($endpointpath, $apikey, $appid, array $params = array()) {
    $debug = false;

    // Set up request variables to get IA participant info.
    // Ref API docs at https://integrityadvocate.com/Developers#aEvents.
    $requesturi = INTEGRITYADVOCATE_BASEURL . '/' . INTEGRITYADVOCATE_API_PATH . $endpointpath . ($params ? '?' . http_build_query($params) : '');
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Built $requesturi=' . $requesturi);
    $requesttimestamp = time();
    $microtime = explode(' ', microtime());
    $nonce = $microtime[1] . substr($microtime[0], 2, 6);

    // Create the signature data.
    $signaturerawdata = $appid . 'GET' . strtolower(urlencode($requesturi)) . $requesttimestamp . $nonce;
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Built $signaturerawdata=' . $signaturerawdata);

    // Decode the API Key.
    $secretkeybytearray = base64_decode($apikey);

    // Encode the signature.
    $signature = utf8_encode($signaturerawdata);

    // Calculate the hash.
    $signaturebytes = hash_hmac('sha256', $signature, $secretkeybytearray, true);

    // Convert to base64.
    $requestsignaturebase64string = base64_encode($signaturebytes);

    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    // Caches for the duration of $CFG->curlcache.
    $curl = new \curl(array('cache' => true));
    $curl->setopt(array(
        'CURLOPT_CERTINFO' => 1,
        'CURLOPT_FOLLOWLOCATION' => 1,
        'CURLOPT_MAXREDIRS' => 5,
        'CURLOPT_RETURNTRANSFER' => 1,
    ));
    $curl->setHeader('Authorization: amx ' . $appid . ':' . $requestsignaturebase64string . ':' . $nonce . ':' . $requesttimestamp);
    $response = $curl->get($requesturi);

    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got curl response=' . print_r($response, true));
    $response = json_decode(clean_param($response, PARAM_RAW_TRIMMED));
    if (empty($response)) {
        block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::' . get_string('no_remote_participants', INTEGRITYADVOCATE_BLOCKNAME));
        return false;
    }

    return $response;
}

/**
 * Return whether an IA block is visible in the given context
 *
 * @global moodle_database $DB Moodle DB object
 * @param int $activitycontextid The activity context id
 * @param int $blockinstanceid The block instance id
 * @return boolean true if the block is visible in the given context
 */
function block_integrityadvocate_blocks_get_visibility($activitycontextid, $blockinstanceid) {
    if (!is_numeric($activitycontextid)) {
        throw new InvalidArgumentException('Input $activitycontextid must be numeric');
    }
    if (!is_numeric($blockinstanceid)) {
        throw new InvalidArgumentException('Input $blockinstanceid must be numeric');
    }

    global $DB;
    $debug = false;
    $record = $DB->get_record('block_positions', array('blockinstanceid' => $blockinstanceid, 'contextid' => $activitycontextid));
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got $bp_record=' . print_r($record, true));
    if (empty($record)) {
        // There is no block_positions record, and the default is visible.
        return true;
    }

    return $record->visible;
}

/**
 * Get all instances of block_integrityadvocate
 *
 * @global moodle_database $DB Moodle DB object
 * @param boolean $visibleonly Set to true to return only visible instances
 * @return array of block_integrityadvocate instances
 */
function block_integrityadvocate_get_all_blocks($visibleonly = true) {
    global $DB;
    $debug = false;

    // We cannot filter for if the block is visible here b/c the block_participant row is usually NULL in these cases.
    $params = array('blockname' => INTEGRITYADVOCATE_SHORTNAME);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Looking in table block_instances with params=" . print_r($params, true));
    $records = $DB->get_records('block_instances', $params);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Found $records=' . print_r($records, true));
    if (!$records) {
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::No instances of block_integrityadvocate found");
        return false;
    }

    // Go through each of the block instances and check visibility.
    $blockinstances = array();
    foreach ($records as $br) {
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Looking at $br=' . print_r($br, true));

        // Check if it is visible and get the IA appid from the block instance config.
        $blockinstancevisible = block_integrityadvocate_blocks_get_visibility($br->parentcontextid, $br->id);
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Found \$blockinstancevisible={$blockinstancevisible}");

        if ($visibleonly && !$blockinstancevisible) {
            continue;
        }

        if (isset($blockinstances[$br->id])) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Multiple visible block_integrityadvocate instances found in the same parentcontextid - just return the first one");
            continue;
        }

        $blockinstances[$br->id] = block_instance(INTEGRITYADVOCATE_SHORTNAME, $br);
    }

    return $blockinstances;
}

/**
 * Filter the input Moodle activities array for ones that use an IA block.
 *
 * @param object[] $activities Course activities to check
 * @param boolean $visibleonly True to include only visible blocks (default)
 * @return array of course activities.
 */
function block_integrityadvocate_filter_activities_use_ia_block(array $activities, $visibleonly = true) {
    $debug = false;
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started with ') . count($activities) . ' activities';

    foreach ($activities as $key => $a) {
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Looking at activity=' . print_r($a, true));
        $blockactivitycontext = $a['context'];
        list($blockinstanceid, $blockinstance) = block_integrityadvocate_get_ia_block($blockactivitycontext, $visibleonly);
        // Disabled on purpose: $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::This activity has $blockinstance_row=' . print_r($blockinstance_row, true));.
        if (empty($blockinstance)) {
            unset($activities[$key]);
            continue;
        }
        $activities[$key]['block_integrityadvocate_instance']['id'] = $blockinstanceid;
        $activities[$key]['block_integrityadvocate_instance']['instance'] = $blockinstance;
    }

    // Disabled on purpose: $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::About to return $activities=' . print_r($activities, true));.
    return $activities;
}

/**
 * Get the first block_integrityadvocate instance found in the given context
 *
 * @global moodle_database $DB Moodle DB object
 * @param context $modulecontext Context to find the IA block in.
 * @param boolean $visibleonly Return only visible instances.
 * @param boolean $rownotinstance Since the instance can be hard to deal with, this returns the DB row instead.
 * @return boolean false if none found or if no visible instances found; else an instance of block_integrityadvocate.
 */
function block_integrityadvocate_get_ia_block(context $modulecontext, $visibleonly = true, $rownotinstance = false) {
    global $DB;
    $debug = false;

    // We cannot filter for if the block is visible here b/c the block_participant row is usually NULL in these cases.
    $params = array('blockname' => INTEGRITYADVOCATE_SHORTNAME, 'parentcontextid' => $modulecontext->id);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Looking in table block_instances with params=" . print_r($params, true));
    // If there are multiple blocks in this context just return the first one .
    $record = $DB->get_record('block_instances', $params, '*', IGNORE_MULTIPLE);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Found blockinstance=' . print_r($record, true));
    if (!$record) {
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::No instance of block_integrityadvocate is associated with this context");
        return false;
    }

    // Check if it is visible and get the IA appid from the block instance config.
    $record->visible = block_integrityadvocate_blocks_get_visibility($modulecontext->id, $record->id);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Found \$record->visible={$record->visible}");

    if ($visibleonly && !$record->visible) {
        return false;
    }

    if ($rownotinstance) {
        return $record;
    }

    return array($record->id, block_instance(INTEGRITYADVOCATE_SHORTNAME, $record));
}

/**
 * Check if the string is a guid
 * Requires dashes and removes braces
 * @link https://stackoverflow.com/a/1253417
 * @param String $str
 * @return true if is a valid guid
 */
function block_integrityadvocate_is_guid($str) {
    return preg_match('/^[a-f\d]{8}-?(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i', $str);
}

/**
 * Build the mailfrom address to use for emails from this plugin.
 *
 * @return \stdClass Moodle User object to sent email from.
 */
function block_integrityadvocate_email_build_mailfrom() {
    $mailfrom = new \stdClass;
    $mailfrom->username = 'integrityadvocate';
    $mailfrom->email = 'admin@integrityadvocate.com';
    $mailfrom->firstname = 'Integrity';
    $mailfrom->firstnamephonetic = '';
    $mailfrom->lastname = 'Advocate';
    $mailfrom->lastnamephonetic = '';
    $mailfrom->middlename = '';
    $mailfrom->alternatename = '';
    $mailfrom->maildisplay = true;
    $mailfrom->mailformat = 1;
    $mailfrom->id = -99;

    return $mailfrom;
}

/**
 * Depending on the IA status, send an appropriate email to the user.
 *
 * @global object $CFG Moodle CFG object
 * @global object $SITE Moodle SITE object
 * @param stdClass $mailfrom User to send the email from
 * @param stdClass $mailto User to send the email to
 * @param stdClass $participant IA participant info
 * @param int $courseid The course the IA participation applies to
 * @return boolean true if email sent
 * @throws InvalidValueException
 */
function block_integrityadvocate_email_user_ia_status_update(stdClass $mailfrom, stdClass $mailto, stdClass $participant, $courseid) {
    $debug = false;
    $message = '';
    $reviewstatus = clean_param($participant->ReviewStatus, PARAM_TEXT);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Started with \$reviewStatus=" . print_r($reviewstatus, true));

    $body = get_string('email_greeting', INTEGRITYADVOCATE_BLOCKNAME, fullname($mailto));
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Built start of body=" . print_r($body, true));

    switch ($reviewstatus) {
        case INTEGRITYADVOCATE_API_STATUS_INPROGRESS:
            // Do not send any email.
            return true;
        case INTEGRITYADVOCATE_API_STATUS_VALID:
            // Process enrolment with passed ID verification/participation monitoring.
            // Send valid email notification.
            $subject = get_string('email_valid_subject', INTEGRITYADVOCATE_BLOCKNAME);
            $body .= get_string('email_valid_body_top', INTEGRITYADVOCATE_BLOCKNAME, $participant->Application);
            $body .= get_string('email_valid_body_bottom', INTEGRITYADVOCATE_BLOCKNAME);
            break;
        case INTEGRITYADVOCATE_API_STATUS_INVALID_ID:
            // Invalid (ID) status allows for a participant to re-submit their photo ID.
            // Send email with link to re-submit URL.
            $subject = get_string('email_invalid_id_subject', INTEGRITYADVOCATE_BLOCKNAME);
            $body .= get_string('email_invalid_id_body_top', INTEGRITYADVOCATE_BLOCKNAME, $participant->Application);
            foreach ($participant->Flags as $flag) {
                $body .= get_string('email_invalid_flags', INTEGRITYADVOCATE_BLOCKNAME, $flag);
            }
            $body .= get_string('email_invalid_id_body_bottom', INTEGRITYADVOCATE_BLOCKNAME, $participant);
            break;
        case INTEGRITYADVOCATE_API_STATUS_INVALID_RULES:
            // Process enrolment with failed ID verification/participation monitoring.
            // Send invalid email notification.
            $subject = get_string('email_invalid_rules_subject', INTEGRITYADVOCATE_BLOCKNAME);
            $body .= get_string('email_invalid_rules_body_top', INTEGRITYADVOCATE_BLOCKNAME, $participant->Application);
            foreach ($participant->Flags as $flag) {
                $body .= get_string('email_invalid_flags', INTEGRITYADVOCATE_BLOCKNAME, $flag);
            }
            $body .= get_string('email_invalid_rules_body_bottom', INTEGRITYADVOCATE_BLOCKNAME, $participant);
            break;
        default:
            $error = 'Invalid participant review status value=' . serialize($reviewstatus);
            block_integrityadvocate_log($error);
            throw new InvalidValueException($error);
    }
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::In select built body=" . print_r($body, true));
    $body .= get_string('email_signoff', INTEGRITYADVOCATE_BLOCKNAME, $mailfrom);

    global $CFG, $SITE;

    // Do string substitutions.
    $message = str_replace('[[mailcontent]]', $body, get_string('email_template', INTEGRITYADVOCATE_BLOCKNAME));
    $course = get_course($courseid);
    $message = str_replace(
            array('[[wwwroot]]', '[[site_fullname]]', '[[course_id]]', '[[course_shortname]]', '[[course_fullname]]'),
            array($CFG->wwwroot, $SITE->fullname, $course->id, $course->shortname, $course->fullname),
            $message
    );

    return email_to_user($mailto, $mailfrom, $subject, html_to_text($message), $message);
}

/**
 * Get the student role (in the course) to show by default e.g. on the course-overview page dropdown box.
 *
 * @global moodle_database $DB Moodle DB object
 * @param context $context Course context in which to get the default role.
 * @return int the role id that is for student archetype in this course
 */
function block_integrityadvocate_overview_get_default_role(context $context) {
    global $DB;
    $sql = 'SELECT  DISTINCT r.id, r.name, r.archetype
            FROM    {role} r, {role_assignments} ra
            WHERE   ra.contextid = :contextid
            AND     r.id = ra.roleid
            AND     r.archetype = :archetype';
    $params = array('contextid' => $context->id, 'archetype' => 'student');
    $studentrole = $DB->get_record_sql($sql, $params);
    if ($studentrole) {
        $studentroleid = $studentrole->id;
    } else {
        $studentroleid = 0;
    }
    return $studentroleid;
}

/**
 * Compares two table row elements for ordering.
 *
 * @param  mixed $a element containing name, online time and progress info
 * @param  mixed $b element containing name, online time and progress info
 * @return order of pair expressed as -1, 0, or 1
 */
function block_integrityadvocate_compare_rows($a, $b) {
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

/**
 * Sanitize the url and add https:// scheme if there is not a scheme there already.
 *
 * @param string $url The url to sanitize
 * @return string The sanitized url
 */
function block_integrityadvocate_fix_api_url($url) {
    $debug = false;
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::About to clean \$url={$url}");
    $urlscheme = parse_url($url, PHP_URL_SCHEME);
    if (empty($urlscheme)) {
        $url = 'https://' . ltrim($url, '/:');
    }
    $url = filter_var($url, FILTER_SANITIZE_URL);
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Cleaned \$url={$url}");

    // Make sure the url matches the expected IA URL.
    if (stripos($url, INTEGRITYADVOCATE_BASEURL) !== 0) {
        return '';
    }

    return $url;
}

/**
 * Attempt to close the remote IA proctoring session.  404=failed to find the session.
 *
 * @param string $appid IA AppID
 * @param context $modulecontext Module context containing the IA block
 * @param int $userid The userid to close the proctoring session for
 * @return boolean true if the remote API close says it succeeded; else false
 */
function block_integrityadvocate_close_api_session($appid, context $modulecontext, $userid) {
    $debug = false;

    // Do not cache these requests.
    $curl = new \curl();
    $curl->setopt(array(
        'CURLOPT_CERTINFO' => 1,
        'CURLOPT_FOLLOWLOCATION' => 1,
        'CURLOPT_MAXREDIRS' => 5,
        'CURLOPT_HEADER' => 0
    ));
    $url = INTEGRITYADVOCATE_BASEURL . '/Integrity/SessionComplete?appid=' . urlencode($appid) . '&participantid=' . urlencode(block_integrityadvocate_encode_useridentifier($modulecontext, $userid));
    $response = $curl->get($url);
    $responsecode = $curl->get_info('http_code');
    $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Send url=' . $url . '; http_code=' . $responsecode . '; response body=' . print_r($response, true));

    return intval($responsecode) < 400;
}

/**
 * Check if site and optionally also course completion is enabled.
 *
 * @global object $CFG Moodle CFG object
 * @param int|object $course Optional courseid or course object to check.  If not specified, only site-level completion is checked.
 * @return array of error identifier strings
 */
function block_integrityadvocate_completion_setup_errors($course = null) {
    global $CFG;
    $errors = array();

    // Check if completion is enabled at site level.
    if (!$CFG->enablecompletion) {
        $errors[] = 'completion_not_enabled';
    }

    if ($course = block_integrityadvocate_course_as_obj($course)) {
        // Check if completion is enabled at course level.
        $completion = new completion_info($course);
        if (!$completion->is_enabled()) {
            $errors[] = 'completion_not_enabled_course';
        }
    }

    return $errors;
}

/**
 * Same as stripos but with an array of needles
 *
 * @link https://stackoverflow.com/a/9220624
 * @param string $haystack The string to search in
 * @param string[] $needles Regexes to search for
 * @param int $offset Optional string offset to start from
 * @return boolean true if found; else false
 */
function block_integrityadvocate_strposa($haystack, $needles, $offset = 0) {
    if (!is_array($needles)) {
        $needles = array($needles);
    }
    foreach ($needles as $query) {
        if (strpos($haystack, $query, $offset) !== false) {
            // Stop on first true result.
            return true;
        }
    }
    return false;
}
