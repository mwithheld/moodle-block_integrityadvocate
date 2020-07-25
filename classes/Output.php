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

defined('MOODLE_INTERNAL') || die;

/**
 * Functions for generating user-visible output.
 */
class Output {

    /** @var string Path to this block JS relative to the moodle root - Requires leading slash but no trailing slash. */
    const BLOCK_JS_PATH = '/blocks/integrityadvocate/js';

    /** @var string HTML linebreak */
    const BRNL = "<br />\n";

    /**
     * Add the block's module.js to the current $blockinstance page.
     *
     * @param \block_integrityadvocate $blockinstance Instance of block_integrityadvocate.
     * @param string $proctorjsurl The proctor JS URL.
     * @return string HTML if error, otherwise empty string.  Also adds the JS to the page.
     */
    public static function add_block_js(\block_integrityadvocate $blockinstance, string $proctorjsurl): string {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started');

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ($blockinstance->context->contextlevel !== \CONTEXT_BLOCK) || !filter_var($proctorjsurl, FILTER_VALIDATE_URL)) {
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
        $blockinstance->page->requires->js_init_call('M.block_integrityadvocate.blockinit', [$proctorjsurl], false, $jsmodule);
        return '';
    }

    /**
     * Build proctoring Javascript URL based on user and timestamp.
     *
     * @param \block_integrityadvocate $blockinstance Instance of block_integrityadvocate.
     * @param stdClass $user Current user object; needed so we can identify this user to the IA API
     * @return string HTML if error; Also adds the student proctoring JS to the page.
     */
    public static function get_proctor_js_url(\block_integrityadvocate $blockinstance, \stdClass $user): string {
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
        if (ia_u::is_empty($blockinstance) || ($configerrors = $blockinstance->get_config_errors())) {
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

        return $url;
    }

    /**
     * Generate the HTML for a button to view details for all course users.
     *
     * @param block_integrityadvocate $blockinstance Instance of block_integrityadvocate.
     * @return string HTML button to view user details.
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
     * @param block_integrityadvocate $blockinstance Instance of block_integrityadvocate.
     * @param int $userid The user id.
     * @return string HTML button to view user details.
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

    /**
     * Get the HTML showing the latest IA status overall.
     *
     * @param ia_participant $participant The participant to get the info for.
     * @param string $prefix CSS prefix to add to the HTML.
     * @return string HTML showing the latest IA status overall.
     */
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
            case ia_status::INVALID_OVERRIDE_INT:
                $statushtml = \html_writer::span(\get_string('status_invalid_override', INTEGRITYADVOCATE_BLOCK_NAME), "{$cssclassval} {$prefix}_status_invalid_override");
                break;
            case ia_status::INVALID_ID_INT:
                $statushtml = \html_writer::span(\get_string('status_invalid_id', INTEGRITYADVOCATE_BLOCK_NAME), "{$cssclassval} {$prefix}_status_invalid_id");
                break;
            case ia_status::INVALID_RULES_INT:
                $statushtml = \html_writer::span(\get_string('status_invalid_rules', INTEGRITYADVOCATE_BLOCK_NAME), "{$cssclassval} {$prefix}_status_invalid_rules");
                break;
            default:
                $error = 'Invalid participant status value=' . serialize($participant->status);
                ia_mu::log($error);
                throw new \InvalidArgumentException($error);
        }

        return $statushtml;
    }

    /**
     * Parse the IA $participant object and return HTML output showing latest status, flags, and photos.
     *
     * @param block_integrityadvocate $blockinstance Instance of block_integrityadvocate.
     * @param ia_participant $participant Participant object from the IA API.
     * @param bool $includephoto True to include the user photo.
     * @param bool $showviewdetailsbutton True to show the viewDetails button.
     * @return string HTML output showing latest participant-level status and photo.
     */
    public static function get_participant_basic_output(\block_integrityadvocate $blockinstance, ia_participant $participant, bool $includephoto = true, bool $showviewdetailsbutton = true): string {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$blockinstance->instance->id={$blockinstance->instance->id}; \$showviewdetailsbutton={$showviewdetailsbutton}; \$includephoto={$includephoto} \$participant->participantidentifier" . ia_u::var_dump($participant->participantidentifier, true);
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
        $debugvars = $fxn . "::Started with \$participant->participantidentifier=" . ia_u::var_dump($participant->participantidentifier, true);
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
     * @param int $userid User id to get info for.
     * @param bool $includephoto True to include the photo from the Participant info.
     * @param bool $showviewdetailsbutton True to show the "View Details" button to get more info about the users IA session.
     * @return string HTML output showing latest status, flags, and photos.
     */
    public static function get_user_basic_output(\block_integrityadvocate $blockinstance, int $userid, bool $includephoto = true, bool $showviewdetailsbutton = true): string {
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

        if (ia_u::is_empty($participant)) {
            $debug && ia_mu::log($fxn . '::Got empty participant, so return empty result');
            return '';
        }

        return self::get_participant_basic_output($blockinstance, $participant, $includephoto, $showviewdetailsbutton);
    }

}
