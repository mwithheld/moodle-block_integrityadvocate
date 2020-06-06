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
 * IntegrityAdvocate functions for generating user-visible output.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

use block_integrityadvocate\Api as ia_api;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Participant as ia_participant;
use block_integrityadvocate\PaticipantStatus as ia_participant_status;
use block_integrityadvocate\Utility as ia_u;

/**
 * Functions for generating user-visible output.
 */
class Output {

    const CLASS_TABLE = 'block_integrityadvocate_table';
    const CLASS_TABLE_HEADER = 'block_integrityadvocate_tableheader';
    const CLASS_TABLE_ROW = 'block_integrityadvocate_tablerow';
    const CLASS_TABLE_LABEL = 'block_integrityadvocate_tablelabel';
    const CLASS_TABLE_VALUE = 'block_integrityadvocate_tablevalue';

    public static function add_module_js(\block_integrityadvocate $blockinstance, \stdClass $user): string {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started');

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ($blockinstance->context->contextlevel !== \CONTEXT_BLOCK) ||
                ia_u::is_empty($user) || !isset($user->id)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        // If the block is not configured yet, simply return empty result.
        if (ia_u::is_empty($blockinstance) || ($configerrors = $blockinstance->get_config_errors())) {
            // No visible IA block found with valid config, so skip any output.
            if (\has_capability('block/integrityadvocate:overview', $blockinstance->context)) {
                echo implode("<br />\n", $configerrors);
            }
            return '';
        }

        global $CFG;
        $blockinstance->page->requires->jquery();
        $blockinstance->page->requires->js(new \moodle_url($CFG->wwwroot . '/blocks/integrityadvocate/module.js'));

//        // Organize access to JS.
//        $jsmodule = array(
//            'name' => \INTEGRITYADVOCATE_BLOCK_NAME,
//            'fullpath' => '/blocks/integrityadvocate/module.js',
//            'requires' => array(),
//            'strings' => array(),
//        );
//
//        $blockinstancesonpage = array($this->instance->id);
//        $arguments = array($blockinstancesonpage, array($USER->id));
//        $this->page->requires->js_init_call('M.block_integrityadvocate.init', $arguments, false, $jsmodule);

        return '';
    }

    /**
     * Build proctoring content to show to students and returns it.
     * Side effects: Adds JS for video monitoring popup to the page.
     *
     * @param stdClass $blockinstance Instance of block_integrityadvocate.
     * @param stdClass $user Current user object; needed so we can identify this user to the IA API
     * @return string HTML if error; Also adds the student proctoring JS to the page.
     */
    public static function add_proctor_js(\block_integrityadvocate $blockinstance, \stdClass $user): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        // Set to true to disable the IA proctor JS.
        $debugnoiaproctoroutput = false;
        $debug && ia_mu::log($fxn . '::Started');

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ($blockinstance->context->contextlevel !== \CONTEXT_BLOCK) || ia_u::is_empty($user) || !isset($user->id)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        // If the block is not configured yet, simply return empty result.
        if (ia_u::is_empty($blockinstance) || ($configerrors = $blockinstance->get_config_errors())) {
            // No visible IA block found with valid config, so skip any output.
            if (\has_capability('block/integrityadvocate:overview', $blockinstance->context)) {
                echo implode("<br />\n", $configerrors);
            }
            return '';
        }

        $blockcontext = $blockinstance->context;

        // Content to return.
        $out = '';

        $blockparentcontext = $blockcontext->get_parent_context();
        // Disabled on purpose: $debug && ia_mu::log($fxn . "::Got modulecontext=" . ia_u::var_dump($modulecontext, true));.
        $debug && ia_mu::log($fxn . "::Got \$blockparentcontext->id=" . ia_u::var_dump($blockparentcontext->id, true));

        $course = $blockinstance->get_course();

        if ($blockparentcontext->contextlevel !== \CONTEXT_MODULE) {
            ia_mu::log($fxn . "::user={$user->id}; courseid={$course->id}: error=This block only shows JS in module context");
            return '';
        }

        if (!\is_enrolled($blockparentcontext, $user->id, null, true)) {
            $error = \get_string('error_notenrolled', \INTEGRITYADVOCATE_BLOCK_NAME);
            // Teachers and students can see this error.
            $debug && ia_mu::log($fxn . "::user={$user->id}; courseid={$course->id}: error={$error}");
            return $error;
        }

        $blockinstance->page->requires->jquery();

        // The moodle_url class stores params non-urlencoded but outputs them encoded.
        // Note $modulecontext->instanceid is the cmid.
        $url = ia_api::get_js_url($blockinstance->config->appid, $course->id, $blockparentcontext->instanceid, $user);
        $debug && ia_mu::log($fxn . "::Built url={$url}");

        if ($debugnoiaproctoroutput) {
            $blockinstance->page->requires->js_init_call('alert("IntegrityAdvocate block JS output '
                    . 'would occur here with url=' . $url . ' if not suppressed")');
        } else {
            // Pass the URL w/o urlencoding.
            $debug && ia_mu::log($fxn . '::About to require->js(' . $url->out(false) . ')');
            $blockinstance->page->requires->js($url);
        }

        return $out;
    }

    /**
     * Generate the HTML for a button to view details for all course users.
     *
     * @param stdClass $blockinstance Instance of block_integrityadvocate.
     * @return HTML to view user details
     */
    public static function get_button_course_overview(\block_integrityadvocate $blockinstance): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$blockinstance->instance->id={$blockinstance->instance->id}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || !is_numeric($courseid = $blockinstance->get_course()->id)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $parameters = array('instanceid' => $blockinstance->instance->id, 'courseid' => $courseid, 'sesskey' => sesskey());
        $url = new \moodle_url('/blocks/integrityadvocate/overview.php', $parameters);
        $label = \get_string('button_overview', \INTEGRITYADVOCATE_BLOCK_NAME);
        $options = array('class' => 'overviewButton');

        global $OUTPUT;
        return $OUTPUT->single_button($url, $label, 'post', $options);
    }

    /**
     * Generate the HTML to view details for this user.
     *
     * @param stdClass $blockinstance Instance of block_integrityadvocate.
     * @param int $userid The user id
     * @return HTML to view user details
     */
    public static function get_button_userdetails(\block_integrityadvocate $blockinstance, int $userid): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$blockinstance->instance->id={$blockinstance->instance->id}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || !is_numeric($courseid = $blockinstance->get_course()->id)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $parameters = array('instanceid' => $blockinstance->instance->id, 'courseid' => $courseid,
            'userid' => $userid, 'sesskey' => sesskey());
        $url = new \moodle_url('/blocks/integrityadvocate/overview.php', $parameters);
        $label = \get_string('overview_view_details', \INTEGRITYADVOCATE_BLOCK_NAME);
        $options = array('class' => 'block_integrityadvocate_overview_btn_view_details');

        global $OUTPUT;
        return $OUTPUT->single_button($url, $label, 'post', $options);
    }

    /**
     * Build the HTML to display IA flags (errors and corresponding messages)
     *
     * @param stdClass $session The IA participant object to pull info from.
     * @return string HTML to output
     */
    public static function get_flags_output(Session $session): string {
        if (!isset($session->flags) || !is_countable($session->flags)) {
            throw new \InvalidArgumentException('Input $session must contain Flags array');
        }

        $prefix = \INTEGRITYADVOCATE_BLOCK_NAME;
        $out = \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_flags_div ' . self::CLASS_TABLE));
        $out .= \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_flags_title ' . self::CLASS_TABLE_HEADER)) .
                \get_string('overview_flags', \INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('div');

        if (empty($session->flags)) {
            $out .= \html_writer::start_tag('div',
                            array('class' => $prefix . '_overview_flag_type ' . self::CLASS_TABLE_ROW)) .
                    \get_string('flags_none', \INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('div');
        }

        $flags = array_values($session->flags);
        usort($flags, array('\\' . \INTEGRITYADVOCATE_BLOCK_NAME . '\Utility', 'sort_by_created_desc'));

        foreach ($session->flags as $f) {
            $out .= \html_writer::start_tag('div',
                            array('class' => $prefix . '_overview_flag_type ' . self::CLASS_TABLE_ROW)) .
                    \html_writer::start_tag('div',
                            array('class' => $prefix . '_overview_flag_type_label ' . self::CLASS_TABLE_LABEL)) .
                    \get_string('flag_type', \INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('div') .
                    \html_writer::start_tag('div',
                            array('class' => $prefix . '_overview_flag_type_value ' . self::CLASS_TABLE_VALUE)) .
                    htmlentities($f->flagtypename) . \html_writer::end_tag('div') . \html_writer::end_tag('div');

            $out .= \html_writer::start_tag('div',
                            array('class' => $prefix . '_overview_flag_comment ' . self::CLASS_TABLE_ROW)) .
                    \html_writer::start_tag('div',
                            array('class' => $prefix . '_overview_flag_comment_label ' . self::CLASS_TABLE_LABEL)) .
                    \get_string('flag_comment', \INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('div') .
                    \html_writer::start_tag('div',
                            array('class' => $prefix . '_overview_flag_comment_value ' . self::CLASS_TABLE_VALUE)) .
                    htmlentities($f->comment) . \html_writer::end_tag('div') . \html_writer::end_tag('div');

            if ($f->capturedata) {
                $out .= \html_writer::start_tag('div',
                                array('class' => $prefix . '_overview_flag_img ' . self::CLASS_TABLE_ROW)) .
                        \html_writer::start_tag('div',
                                array('class' => $prefix . '_overview_flag_img_label ' . self::CLASS_TABLE_LABEL)) .
                        \get_string('flag_capture', \INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('div') .
                        \html_writer::start_tag('div',
                                array('class' => $prefix . '_overview_flag_img_value ' . self::CLASS_TABLE_VALUE)) .
                        '<img src="' . $f->capturedata . '"/>' . \html_writer::end_tag('div') . \html_writer::end_tag('div');
            }
        }

        // Close .block_integrityadvocate_overview_flags_div.
        $out .= \html_writer::end_tag('div');

        return $out;
    }

    /**
     * Build the HTML to display one IA session, including flag info.
     *
     * @param \block_integrityadvocate\Session $session The IA session object to show.
     * @return string HTML showing this session.
     * @throws \InvalidArgumentException
     */
    public static function get_session_output(Session $session): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . '::Started with $session->id=' . ia_u::var_dump($session->id, true);
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($session) || !isset($session->flags)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $out = '';

        // Make sure we have an activityid, and it is an existing activity in this course.
        if (!isset($session->activityid)) {
            $debugvars = $fxn . '::This session has no activityid so return empty string';
            return $out;
        }
        $cmid = $session->activityid;
        if (!($courseid = ia_mu::get_courseid_from_cmid($cmid)) || intval($courseid) !== intval($session->participant->courseid)) {
            $debugvars = $fxn . "::This session belongs to courseid={$courseid} not matching participant->courseid={$session->participant->courseid}";
            return $out;
        }
        list($unused, $cm) = \get_course_and_cm_from_cmid($session->activityid, null, $courseid, $session->participant->participantidentifier);

        $prefix = \INTEGRITYADVOCATE_BLOCK_NAME;
        $out = '';
        $out .= \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_session_div ' . self::CLASS_TABLE));
        $out .= \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_session_title ' . self::CLASS_TABLE_HEADER)) .
                \get_string('overview_session', \INTEGRITYADVOCATE_BLOCK_NAME, $cm->name) . \html_writer::end_tag('div');
        $out .= \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_session_start ' . self::CLASS_TABLE_ROW)) .
                \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_session_start_label ' . self::CLASS_TABLE_LABEL)) .
                \get_string('session_start', \INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('div') .
                \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_session_start_value ' . self::CLASS_TABLE_VALUE)) .
                ($session->start ? \userdate($session->start) : '') . \html_writer::end_tag('div') .
                \html_writer::end_tag('div');
        $out .= \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_session_end ' . self::CLASS_TABLE_ROW)) .
                \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_session_end_label ' . self::CLASS_TABLE_LABEL)) .
                \get_string('session_end', \INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('div') .
                \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_session_end_value ' . self::CLASS_TABLE_VALUE)) .
                ($session->end ? \userdate($session->end) : '') . \html_writer::end_tag('div') .
                \html_writer::end_tag('div');
        $out .= \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_session_status ' . self::CLASS_TABLE_ROW)) .
                \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_session_status_label ' . self::CLASS_TABLE_LABEL))
                . \get_string('session_status', \INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('div') .
                \html_writer::start_tag('div',
                        array('class' => $prefix . '_overview_session_status_value ' . self::CLASS_TABLE_VALUE)) .
                ia_participant_status::get_status_lang($session->status) . \html_writer::end_tag('div') .
                \html_writer::end_tag('div');

        // Close .block_integrityadvocate_overview_session_div.
        $out .= \html_writer::end_tag('div');

        // Get flag output.
        $out .= self::get_flags_output($session);

        return $out;
    }

    /**
     * Build the HTML to display IA sessions, including flag info.
     *
     * @param ia_participant $participant The IA participant to show sessions for.
     * @return string HTML showing the sessions.
     * @throws \InvalidArgumentException
     */
    public static function get_sessions_output(ia_participant $participant): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . '::Started with $participant=' . ia_u::var_dump($participant, true);
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($participant) || !isset($participant->sessions)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $out = '';
        if (empty($sessions = array_values($participant->sessions))) {
            return $out;
        }

        usort($sessions, array('\\' . INTEGRITYADVOCATE_BLOCK_NAME . '\Utility', 'sort_by_start_desc'));

        $prefix = \INTEGRITYADVOCATE_BLOCK_NAME;
        $out .= \html_writer::start_tag('div', array('class' => $prefix . '_overview_sessions_div'));
        $out .= \html_writer::start_tag('div', array('class' => $prefix . '_overview_sessions_title')) .
                '<h3>' . \get_string('overview_sessions', \INTEGRITYADVOCATE_BLOCK_NAME) . '</h3>' .
                \html_writer::end_tag('div');

        // Output sessions info.
        foreach ($sessions as $s) {
            $out .= self::get_session_output($s);
        }

        // Close .block_integrityadvocate_overview_sessions_div.
        $out .= \html_writer::end_tag('div');

        return $out;
    }

    /**
     * Parse the IA $participant object and return HTML output showing latest status, flags, and photos.
     *
     * @param stdClass $blockinstance Instance of block_integrityadvocate.
     * @param stdClass $participant Participant object from the IA API
     * @param bool $showviewdetailsbutton True to show the viewDetails button
     * @param bool $includephoto True to include the user photo
     * @return string HTML output showing latest status, flags, and photos
     * @throws InvalidValueException If the participant status field does not match one of our known values
     */
    public static function get_participant_basic_output(\block_integrityadvocate $blockinstance, ia_participant $participant, bool $showviewdetailsbutton = true,
            bool $includephoto = true): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$blockinstance->instance->id={$blockinstance->instance->id}; "
                . "\$showviewdetailsbutton={$showviewdetailsbutton}; \$includephoto={$includephoto}; \$participant" . ia_u::var_dump($participant, true);
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ia_u::is_empty($participant) ||
                !ia_participant_status::is_status_int($participant->status)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $prefix = \INTEGRITYADVOCATE_BLOCK_NAME;
        $out = \html_writer::start_tag('div', array('class' => $prefix . '_overview_participant_summary_div'));
        $out .= \html_writer::start_tag('div', array('class' => $prefix . '_overview_participant_summary_text'));
        $resubmithtml = '';

        switch ($participant->status) {
            case ia_participant_status::INPROGRESS_INT:
                $statushtml = \html_writer::start_tag('span', array('class' => $prefix . '_status_inprogress')) .
                        \get_string('status_in_progress', \INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('span');
                break;
            case ia_participant_status::VALID_INT:
                $statushtml = \html_writer::start_tag('span', array('class' => $prefix . '_status_valid')) .
                        \get_string('status_valid', \INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('span');
                break;
            case ia_participant_status::INVALID_ID_INT:
                $statushtml = \html_writer::start_tag('span', array('class' => $prefix . '_status_invalid_id')) .
                        \get_string('status_invalid_id', \INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('span');
                $resubmiturl = $participant->resubmiturl ? $participant->resubmiturl : '';
                $debug && ia_mu::log($fxn .
                                '::Status is INVALID_ID; got $resubmiturl=' . $resubmiturl);
                if ($resubmiturl) {
                    $resubmithtml = \html_writer::start_tag('span', array('class' => $prefix . '_resubmit_link')) .
                            format_text(\html_writer::link(
                                            $resubmiturl,
                                            \get_string('resubmit_link', \INTEGRITYADVOCATE_BLOCK_NAME),
                                            array('target' => '_blank')
                                    ), FORMAT_HTML) .
                            \html_writer::end_tag('span');
                }
                break;
            case ia_participant_status::INVALID_RULES_INT:
                $statushtml = \html_writer::start_tag('span', array('class' => $prefix . '_status_invalid_rules')) .
                        \get_string('status_invalid_rules', \INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('span');
                break;
            default:
                $error = 'Invalid participant status value=' . serialize($participant->status);
                ia_mu::log($error);
                throw new InvalidValueException($error);
        }

        $out .= \html_writer::start_tag('div', array('class' => $prefix . '_overview_participant_summary_status')) .
                \html_writer::start_tag('span', array('class' => $prefix . '_overview_participant_summary_status_label')) .
                \get_string('overview_user_status', \INTEGRITYADVOCATE_BLOCK_NAME) . ': ' .
                \html_writer::end_tag('span') . $statushtml .
                \html_writer::end_tag('div');
        if ($resubmithtml) {
            $out .= \html_writer::start_tag('div', array('class' => $prefix . '_overview_participant_summary_resubmit')) .
                    $resubmithtml . \html_writer::end_tag('div');
        }
        $out .= \html_writer::start_tag('div', array('class' => $prefix . '_overview_participant_summary_start')) .
                \html_writer::start_tag('span', array('class' => $prefix . '_overview_participant_summary_status_label')) .
                \get_string('created', \INTEGRITYADVOCATE_BLOCK_NAME) . ': ' .
                \html_writer::end_tag('span') . date('Y-m-d H:i', $participant->created) .
                \html_writer::end_tag('div');

        $out .= \html_writer::start_tag('div', array('class' => $prefix . '_overview_participant_summary_end')) .
                \html_writer::start_tag('span', array('class' => $prefix . '_overview_participant_summary_status_label')) .
                \get_string('last_modified', \INTEGRITYADVOCATE_BLOCK_NAME) . ': ' .
                \html_writer::end_tag('span') . date('Y-m-d H:i', $participant->modified) .
                \html_writer::end_tag('div');

        if ($showviewdetailsbutton) {
            $out .= self::get_button_userdetails($blockinstance, $participant->participantidentifier);
        }

        // Close .block_integrityadvocate_overview_participant_summary_text.
        $out .= \html_writer::end_tag('div');

        $debug && ia_mu::log($fxn . '::About to check if should include photo; $include_photo=' . $includephoto);
        if ($includephoto) {
            $photohtml = self::get_participant_photo_output($participant);
            // Disabled on purpose: $debug && ia_mu::log($fxn . '::Built photo html=' . $photohtml);.
            $out .= $photohtml;
        }

        // Close .block_integrityadvocate_overview_participant_summary_div.
        $out .= \html_writer::end_tag('div');

        // Start next section on a new line.
        $out .= '<div style="clear:both"></div>';

        return $out;
    }

    /**
     * Get the HTML used to display the participant photo in the IA summary output
     *
     * @param ia_participant $participant An IA participant object to pull info from.
     * @return string HTML to output
     */
    public static function get_participant_photo_output(ia_participant $participant): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$participant=" . ia_u::var_dump($participant, true);
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($participant) || !ia_participant_status::is_status_int($participant->status)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $prefix = \INTEGRITYADVOCATE_BLOCK_NAME;
        $out = \html_writer::start_tag('div', array('class' => $prefix . '_overview_participant_summary_img_div'));
        if ($participant->participantphoto) {
            $out .= \html_writer::start_tag('span',
                            array(
                                'class' => $prefix . '_overview_participant_summary_img '
                                . $prefix . '_overview_participant_summary_img_' .
                                ($participant->status === ia_participant_status::VALID_INT ? '' : 'in') . 'valid')
                    ) .
                    \html_writer::img($participant->participantphoto, $participant->email) .
                    \html_writer::end_tag('span');
        }
        // Close .block_integrityadvocate_overview_participant_summary_img_div.
        $out .= \html_writer::end_tag('div');

        return $out;
    }

    /**
     * Get the user $participant info and return HTML output showing latest status, flags, and photos.
     *
     * @param \block_integrityadvocate $blockinstance Block instance to get participant data for.
     * @param int $userid Userid to get info for.
     * @param bool $showviewdetailsbutton True to show the "View Details" button to get more info about the users IA session.
     * @param bool $includephoto True to include the photo from the Participant info.
     * @return string HTML output showing latest status, flags, and photos.
     */
    public static function get_user_basic_output(\block_integrityadvocate $blockinstance, int $userid, bool $showviewdetailsbutton = true, bool $includephoto = true): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn .
                        "::Started with \$userid={$userid}; \$showviewdetailsbutton={$showviewdetailsbutton}; \$includephoto={$includephoto}");
        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ($blockinstance->context->contextlevel !== \CONTEXT_BLOCK)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        // If the block is not configured yet, simply return empty result.
        if (ia_u::is_empty($blockinstance) || ($configerrors = $blockinstance->get_config_errors())) {
            // No visible IA block found with valid config, so skip any output.
            if (\has_capability('block/integrityadvocate:overview', $blockinstance->context)) {
                echo implode("<br />\n", $configerrors);
            }
            return '';
        }

        $participant = ia_api::get_participant($blockinstance->config->apikey, $blockinstance->config->appid, $blockinstance->get_course()->id, $userid);
        $debug && ia_mu::log($fxn . '::Got $participant=' . (ia_u::is_empty($participant) ? '' : ia_u::var_dump($participant, true)));

        if (ia_u::is_empty($participant)) {
            $debug && ia_mu::log($fxn . '::Got empty participant, so return empty result');
            return '';
        }

        return self::get_participant_basic_output($blockinstance, $participant, $showviewdetailsbutton, $includephoto);
    }

}
