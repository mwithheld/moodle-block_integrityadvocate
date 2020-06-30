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
use block_integrityadvocate\Status as ia_status;
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
    const BLOCK_JS_PATH = '/blocks/integrityadvocate/js';
    const BRNL = "<br />\n";

    /**
     * Add block.js to the current $blockinstance page.
     *
     * @param stdClass $blockinstance Instance of block_integrityadvocate.
     * @param stdClass $user Current user object.
     * @return string HTML if error, otherwise empty string.  Also adds the JS to the page.
     */
    public static function add_block_js(\block_integrityadvocate $blockinstance): string {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started');

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ($blockinstance->context->contextlevel !== \CONTEXT_BLOCK)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        // If the block is not configured yet, simply return empty result.
        if ($configerrors = $blockinstance->get_config_errors()) {
            // No visible IA block found with valid config, so skip any output.
            if (\has_capability('block/integrityadvocate:overview', $blockinstance->context)) {
                echo implode("<br />\n", $configerrors);
            }
            return '';
        }

        // Organize access to JS.
        $jsmodule = array(
            'name' => INTEGRITYADVOCATE_BLOCK_NAME,
            'fullpath' => self::BLOCK_JS_PATH . '/module.js',
            'requires' => array(),
            'strings' => array(),
        );

        $blockinstance->page->requires->jquery_plugin('jquery');
        $blockinstance->page->requires->js_init_call('M.block_integrityadvocate.blockinit', null, false, $jsmodule);
        return '';
    }

    /**
     * Add overview.js to the current $blockinstance page.
     *
     * @param stdClass $blockinstance Instance of block_integrityadvocate.
     * @param stdClass $user Current user object.
     * @return string HTML if error, otherwise empty string.  Also adds the JS to the page.
     */
    public static function add_overview_js(\moodle_page $page): string {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started');

        // Organize access to JS.
        $jsmodule = array(
            'name' => INTEGRITYADVOCATE_BLOCK_NAME,
            'fullpath' => self::BLOCK_JS_PATH . '/overview.js',
            'requires' => array(),
            'strings' => array(),
        );

        $page->requires->jquery_plugin('jquery');
        $page->requires->js_init_call('M.block_integrityadvocate.overviewinit', null, false, $jsmodule);
        return '';
    }

    /**
     * Build proctoring.js to the page..
     *
     * @param stdClass $blockinstance Instance of block_integrityadvocate.
     * @param stdClass $user Current user object; needed so we can identify this user to the IA API
     * @return string HTML if error, otherwise empty string.  Also adds the JS to the page.
     */
    public static function add_proctor_js(\block_integrityadvocate $blockinstance, \stdClass $user): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started');

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ($blockinstance->context->contextlevel !== \CONTEXT_BLOCK) || ia_u::is_empty($user) || !isset($user->id)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        // If the block is not configured yet, simply return empty result.
        if ($configerrors = $blockinstance->get_config_errors()) {
            // No visible IA block found with valid config, so skip any output, but show teachers the error.
            if (\has_capability('block/integrityadvocate:overview', $blockinstance->context)) {
                echo implode("<br />\n", $configerrors);
            }
            return '';
        }

        $blockcontext = $blockinstance->context;
        $blockparentcontext = $blockcontext->get_parent_context();
        $debug && ia_mu::log($fxn . "::Got \$blockparentcontext->id=" . ia_u::var_dump($blockparentcontext->id, true));

        $course = $blockinstance->get_course();

        if ($blockparentcontext->contextlevel !== \CONTEXT_MODULE) {
            ia_mu::log($fxn . "::user={$user->id}; courseid={$course->id}: error=This block only shows JS in module context");
            return '';
        }

        if (!\is_enrolled($blockparentcontext, $user->id, null, true)) {
            $error = \get_string('error_notenrolled', INTEGRITYADVOCATE_BLOCK_NAME);
            // Teachers and students can see this error.
            $debug && ia_mu::log($fxn . "::user={$user->id}; courseid={$course->id}: error={$error}");
            return $error;
        }

        // The moodle_url class stores params non-urlencoded but outputs them encoded.
        // Note $modulecontext->instanceid is the cmid.
        $url = ia_api::get_js_url($blockinstance->config->appid, $course->id, $blockparentcontext->instanceid, $user);
        $debug && ia_mu::log($fxn . "::Built url={$url}");

        // Set to true to disable the IA proctor JS.
        $debugnoiaproctoroutput = false;
        if ($debugnoiaproctoroutput) {
            $blockinstance->page->requires->js_init_call('alert("IntegrityAdvocate block JS output would occur here with url=' . $url . ' if not suppressed")');
        } else {
            // Pass the URL w/o urlencoding.
            $debug && ia_mu::log($fxn . '::About to require->js(' . $url->out(false) . ')');

            // We are violating a rather silly Moodle standard here in using an external URL.
            // This is needed b/c the URL is user-specific and contents change.
            // And IntegrityAdvocate does not support offline use.
            // It makes no sense to download it to the Moodle server each time and then send it to the user.
            //
            // This also causes an error in the JS console on first load, but it doesn't cause any problems.
            // I.    Error: Mismatched anonymous define() module...
            $blockinstance->page->requires->js($url, true);
        }

        return '';
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
        $label = \get_string('button_overview', INTEGRITYADVOCATE_BLOCK_NAME);
        $options = array('class' => 'overviewButton');

        global $OUTPUT;
        return $OUTPUT->single_button($url, $label, 'get', $options);
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

        $parameters = array('instanceid' => $blockinstance->instance->id, 'courseid' => $courseid, 'userid' => $userid, 'sesskey' => sesskey());
        $url = new \moodle_url('/blocks/integrityadvocate/overview.php', $parameters);
        $label = \get_string('overview_view_details', INTEGRITYADVOCATE_BLOCK_NAME);
        $options = array('class' => 'block_integrityadvocate_overview_btn_view_details');

        global $OUTPUT;
        return $OUTPUT->single_button($url, $label, 'get', $options);
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

        $prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_overview';
        $out = \html_writer::start_tag('div', array('class' => $prefix . '_flags_div ' . self::CLASS_TABLE));
        $out .= \html_writer::div(\get_string('overview_flags', INTEGRITYADVOCATE_BLOCK_NAME), $prefix . '_flags_title ' . self::CLASS_TABLE_HEADER);

        if (empty($session->flags)) {
            $out .= \html_writer::div(\get_string('flags_none', INTEGRITYADVOCATE_BLOCK_NAME), $prefix . '_flag_type ' . self::CLASS_TABLE_ROW);
        }

        $flags = array_values($session->flags);
        usort($flags, array('\\' . INTEGRITYADVOCATE_BLOCK_NAME . '\Utility', 'sort_by_created_desc'));

        foreach ($session->flags as $f) {
            $out .= \html_writer::start_tag('div', array('class' => $prefix . '_flag_type ' . self::CLASS_TABLE_ROW)) .
                    \html_writer::div(\get_string('flag_type', INTEGRITYADVOCATE_BLOCK_NAME), $prefix . '_flag_type_label ' . self::CLASS_TABLE_LABEL) .
                    \html_writer::div(htmlentities($f->flagtypename), $prefix . '_flag_type_value ' . self::CLASS_TABLE_VALUE) .
                    \html_writer::end_tag('div');

            $out .= \html_writer::start_tag('div', array('class' => $prefix . '_flag_comment ' . self::CLASS_TABLE_ROW)) .
                    \html_writer::div(\get_string('flag_comment', INTEGRITYADVOCATE_BLOCK_NAME), $prefix . '_flag_comment_label ' . self::CLASS_TABLE_LABEL) .
                    \html_writer::div(htmlentities($f->comment), $prefix . '_flag_comment_value ' . self::CLASS_TABLE_VALUE) .
                    \html_writer::end_tag('div');

            if ($f->capturedata) {
                $out .= \html_writer::start_tag('div', array('class' => $prefix . '_flag_img ' . self::CLASS_TABLE_ROW)) .
                        \html_writer::div(\get_string('flag_capture', INTEGRITYADVOCATE_BLOCK_NAME), $prefix . '_flag_img_label ' . self::CLASS_TABLE_LABEL) .
                        \html_writer::div('<img src="' . $f->capturedata . '"/>', $prefix . '_flag_img_value ' . self::CLASS_TABLE_VALUE) .
                        \html_writer::end_tag('div');
            }
        }

        // Close .block_integrityadvocate_overview_flags_div.
        $out .= \html_writer::end_tag('div');

        return $out;
    }

    /**
     * Create HTML for a floated div row with two fields, typically name in the LH side and value on the RHS.
     *
     * @param string $prefix String to add to the beginning of all classes.
     * @param string $identifier Lang string identifier and used after the prefix for class names in the star placeholder here: *_row, *_label, and *_value.
     * @param string $val The HTML-escaped value to display.
     * @return string The built HTML.
     */
    private static function row_nameval(string $prefix, string $identifier, string $val): string {
        $out = '';
        $out .= \html_writer::start_tag('div', array('class' => "{$prefix}_{$identifier}_row " . self::CLASS_TABLE_ROW));
        $out .= \html_writer::div(\get_string($identifier, INTEGRITYADVOCATE_BLOCK_NAME), "{$prefix}_{$identifier}_label " . self::CLASS_TABLE_LABEL);
        $out .= \html_writer::div($val, "{$prefix}_{$identifier}_value " . self::CLASS_TABLE_VALUE);
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

        $prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_overview_session';
        $out = '';
        $out .= \html_writer::start_tag('div', array('class' => $prefix . '_div ' . self::CLASS_TABLE));
        $out .= \html_writer::div(\get_string('overview_session', INTEGRITYADVOCATE_BLOCK_NAME, $cm->name), $prefix . '_title ' . self::CLASS_TABLE_HEADER);

        $out .= self::row_nameval($prefix, 'session_start', ($session->start ? \userdate($session->start) : ''));
        $out .= self::row_nameval($prefix, 'session_end', ($session->end ? \userdate($session->end) : ''));
        $out .= self::row_nameval($prefix, 'session_status', ia_status::get_status_lang($session->status));
        if ($session->has_override()) {
            $out .= self::row_nameval($prefix, 'session_overridedate', ($session->overridedate ? \userdate($session->overridedate) : ''));
            $out .= self::row_nameval($prefix, 'session_overridestatus', ia_status::get_status_lang($session->overridestatus));
            $out .= self::row_nameval($prefix, 'session_overridereason', htmlspecialchars($session->overridereason));
            $out .= self::row_nameval($prefix, 'session_overridename', \fullname(ia_mu::get_user_as_obj($session->overridelmsuserid)));
        }

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

        $output = '';
        if (empty($sessions = array_values($participant->sessions))) {
            return $output;
        }

        usort($sessions, array('\\' . INTEGRITYADVOCATE_BLOCK_NAME . '\Utility', 'sort_by_start_desc'));

        $prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_overview_sessions';
        $output .= \html_writer::start_tag('div', array('class' => $prefix . '_div'));
        $output .= \html_writer::start_tag('div', array('class' => $prefix . '_title')) .
                \html_writer::start_tag('h3') . \get_string('overview_sessions', INTEGRITYADVOCATE_BLOCK_NAME) . \html_writer::end_tag('h3') .
                \html_writer::end_tag('div');

        // Output sessions info.
        foreach ($sessions as $s) {
            $output .= self::get_session_output($s);
        }

        // Close .block_integrityadvocate_overview_sessions_div.
        $output .= \html_writer::end_tag('div');

        return $output;
    }

    /**
     * Build the HTML for an icon wrapped in an anchor link.
     * Adapted from lib/outputcomponents.php::get_primary_actions().
     *
     * @param string $pixpath Path under pix to the icon.
     * @param string $nameprefix Prefix for class, id, and name attributes.
     * @param string $name Lang string name AND the main part of the class, id, and name attributes.
     * @param string $uniqueidsuffix If the anchor id attribute should have a unique identifier (like a userid number), put it here.  Default empty string.
     * @return string The built HTML.
     */
    public static function add_icon(string $pixpath, string $nameprefix = '', string $name, string $uniqueidsuffix = ''): string {
        global $OUTPUT;

        // Add the save icon.
        $label = \get_string($name);
        $anchorattributes = array(
            'class' => "{$nameprefix}_{$name}",
            'title' => $label,
            'aria-label' => $label
        );
        if ($uniqueidsuffix) {
            $anchorattributes['id'] = "{$nameprefix}_{$name}" . ($uniqueidsuffix ? "-{$uniqueidsuffix}" : '');
        }
        $pixicon = $OUTPUT->render(new \pix_icon($pixpath, '', 'moodle', array('class' => ' iconsmall', 'title' => '')));
        return \html_writer::span(\html_writer::link('#', $pixicon, $anchorattributes), "{$nameprefix}_{$name}_span {$nameprefix}_button");
    }

//    /**
//     * Build and returns the Override UI HTML.
//     * Assumes you have a valid participant.
//     *
//     * @param ia_participant $participant A valid IA participant.
//     * @return string The Override UI HTML.
//     */
//    private static function get_override_html(ia_participant $participant): string {
//        global $PAGE;
//        $prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_override';
//        $output = '';
//
//        // Add the edit icon.
//        $output .= self::add_icon('i/edit', $prefix, 'edit');
//
//        // Create a form for the override UI.
//        $output .= \html_writer::start_tag('form', array('id' => $prefix . '_form', 'style' => 'display:none'));
//
//        // Add a label for the form fields.
//        $output .= \html_writer::span(
//                        \get_string('override_form_label', INTEGRITYADVOCATE_BLOCK_NAME),
//                        $prefix . '_overview_participant_summary_status_label ' . $prefix . '_form_label');
//
//        // Add the override status UI hidden to the page so we can just swap it in on click.
//        // Add the select and reason box.
//        $output .= \html_writer::select(
//                        ia_status::get_overriddable(),
//                        ' ' . $prefix . '_select ' . $prefix . '_status_select',
//                        $participant->status,
//                        array('' => 'choosedots'),
//                        array('id' => $prefix . '_status_select', 'required' => true)
//        );
//
//        // Add the override reason textbox.
//        $PAGE->requires->strings_for_js(array('override_reason_label', 'override_reason_invalid'), INTEGRITYADVOCATE_BLOCK_NAME);
//        $output .= \html_writer::tag('input', '', array('id' => $prefix . '_reason', 'name' => $prefix . '_reason', 'maxlength' => 32));
//
//        // Add hidden fields needed for the AJAX call.
//        global $USER;
//        $output .= \html_writer::tag('input', '', array('type' => 'hidden', 'id' => $prefix . '_targetuserid', 'name' => $prefix . '_targetuserid', 'value' => $participant->participantidentifier));
//        $output .= \html_writer::tag('input', '', array('type' => 'hidden', 'id' => $prefix . '_overrideuserid', 'name' => $prefix . '_overrideuserid', 'value' => $USER->id));
//
//        $output .= self::add_icon('e/save', $prefix, 'save');
//        $output .= self::add_icon('i/loading', $prefix, 'loading');
//        $output .= self::add_icon('e/cancel', $prefix, 'cancel');
//
//        $output .= \html_writer::end_tag('form');
//
//        return $output;
//    }

    public static function get_latest_status_html(ia_participant $participant, string $prefix): string {
        $statushtml = '';
        $cssclassval = $prefix . '_status_val ';
        switch ($participant->status) {
            case ia_status::INPROGRESS_INT:
                $statushtml = \html_writer::span(\get_string('status_in_progress', INTEGRITYADVOCATE_BLOCK_NAME), "{$cssclassval} {$prefix}_status_inprogress");
                break;
            case ia_status::VALID_INT:
                $statushtml = \html_writer::span(get_string('status_valid', INTEGRITYADVOCATE_BLOCK_NAME), "{$cssclassval} {$prefix}_status_valid");
                break;
            case ia_status::INVALID_ID_INT:
                $statushtml = \html_writer::span(\get_string('status_invalid_id', INTEGRITYADVOCATE_BLOCK_NAME), "{$cssclassval} {$prefix}_status_invalid_id");
                break;
            case ia_status::INVALID_OVERRIDE_INT:
                $statushtml = \html_writer::span(\get_string('status_invalid_override', INTEGRITYADVOCATE_BLOCK_NAME), "{$cssclassval} {$prefix}_status_invalid_override");
                break;
            case ia_status::INVALID_RULES_INT:
                $statushtml = \html_writer::span(\get_string('status_invalid_rules', INTEGRITYADVOCATE_BLOCK_NAME), "{$cssclassval} {$prefix}_status_invalid_rules");
                break;
            default:
                $error = 'Invalid participant status value=' . serialize($participant->status);
                ia_mu::log($error);
                throw new \InvalidValueException($error);
        }

        return $statushtml;
    }

    /**
     * Parse the IA $participant object and return HTML output showing latest status, flags, and photos.
     *
     * @param stdClass $blockinstance Instance of block_integrityadvocate.
     * @param stdClass $participant Participant object from the IA API
     * @param bool $includephoto True to include the user photo
     * @param bool $showviewdetailsbutton True to show the viewDetails button
     * @return string HTML output showing latest status, flags, and photos
     * @throws InvalidValueException If the participant status field does not match one of our known values
     */
    public static function get_participant_basic_output(\block_integrityadvocate $blockinstance, ia_participant $participant, bool $includephoto = true, bool $showviewdetailsbutton = true, bool $showoverridebutton = false): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$blockinstance->instance->id={$blockinstance->instance->id}; \$showviewdetailsbutton={$showviewdetailsbutton}; \$includephoto={$includephoto}; \$participant" . ia_u::var_dump($participant, true);
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ia_u::is_empty($participant) || !ia_status::is_status_int($participant->status)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $prefix = INTEGRITYADVOCATE_BLOCK_NAME;
        $out = \html_writer::start_tag('div', array('class' => $prefix . '_overview_participant_summary_div'));
        $out .= \html_writer::start_tag('div', array('class' => $prefix . '_overview_participant_summary_text'));
        $resubmithtml = '';
        $statushtml = self::get_latest_status_html($participant, $prefix);

        if ($participant->status === ia_status::INVALID_ID_INT) {
            // The user is allowed to re-submit their identity stuff, so build a link to show later.
            $resubmiturl = $participant->resubmiturl ? $participant->resubmiturl : '';
            $debug && ia_mu::log($fxn . '::Status is INVALID_ID; got $resubmiturl=' . $resubmiturl);
            if ($resubmiturl) {
                $out .= \html_writer::span(
                                format_text(\html_writer::link($resubmiturl, \get_string('resubmit_link', INTEGRITYADVOCATE_BLOCK_NAME), array('target' => '_blank')), FORMAT_HTML),
                                $prefix . '_resubmit_link');
            }
        }

        $out .= \html_writer::start_tag('div', array('class' => $prefix . '_overview_participant_summary_status')) .
                \html_writer::span(\get_string('overview_user_status', INTEGRITYADVOCATE_BLOCK_NAME) . ': ', $prefix . '_overview_participant_summary_status_label') .
                $statushtml .
                \html_writer::end_tag('div');
        if ($resubmithtml) {
            $out .= \html_writer::div($resubmithtml, $prefix . '_overview_participant_summary_resubmit');
        }
        $out .= \html_writer::start_tag('div', array('class' => $prefix . '_overview_participant_summary_start')) .
                \html_writer::span(\get_string('created', INTEGRITYADVOCATE_BLOCK_NAME) . ': ', $prefix . '_overview_participant_summary_status_label') .
                date('Y-m-d H:i', $participant->created) .
                \html_writer::end_tag('div');

        $out .= \html_writer::start_tag('div', array('class' => $prefix . '_overview_participant_summary_end')) .
                \html_writer::span(\get_string('last_modified', INTEGRITYADVOCATE_BLOCK_NAME) . ': ', $prefix . '_overview_participant_summary_status_label') .
                date('Y-m-d H:i', $participant->modified) .
                \html_writer::end_tag('div');

        if ($showviewdetailsbutton) {
            $out .= self::get_button_userdetails($blockinstance, $participant->participantidentifier);
        }

        // Close .block_integrityadvocate_overview_participant_summary_text.
        $out .= \html_writer::end_tag('div');

        $debug && ia_mu::log($fxn . '::About to check if should include photo; $include_photo=' . $includephoto);
        if ($includephoto) {
            $out .= self::get_participant_photo_output($participant);
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
        if (ia_u::is_empty($participant) || !ia_status::is_status_int($participant->status)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_overview_participant';
        $out = \html_writer::start_tag('div', array('class' => $prefix . '_summary_img_div'));
        if ($participant->participantphoto) {
            $out .= \html_writer::start_tag('span',
                            array('class' => $prefix . '_summary_img ' . $prefix . '_summary_img_' .
                                ($participant->status === ia_status::VALID_INT ? '' : 'in') . 'valid')
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
     * @param bool $includephoto True to include the photo from the Participant info.
     * @param bool $showviewdetailsbutton True to show the "View Details" button to get more info about the users IA session.
     * @return string HTML output showing latest status, flags, and photos.
     */
    public static function get_user_basic_output(\block_integrityadvocate $blockinstance, int $userid, bool $includephoto = true, bool $showviewdetailsbutton = true, $showoverridebutton = false): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . "::Started with \$userid={$userid}; \$showviewdetailsbutton={$showviewdetailsbutton}; \$includephoto={$includephoto}");
        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ($blockinstance->context->contextlevel !== \CONTEXT_BLOCK)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        // If the block is not configured yet, simply return empty result.
        if ($configerrors = $blockinstance->get_config_errors()) {
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

        return self::get_participant_basic_output($blockinstance, $participant, $includephoto, $showviewdetailsbutton, $showoverridebutton);
    }

}
