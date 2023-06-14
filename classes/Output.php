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
    private const BLOCK_JS_PATH = '/blocks/integrityadvocate/js';

    /** @var string newline */
    public const NL = "\n";

    /** @var string HTML linebreak */
    public const BRNL = '<br />' . self::NL;

    /**
     * Wrap the $str value in an HTML <PRE> tag and return it.
     *
     * @param string $str The string to wrap.
     * @return string The wrapped $str string.
     */
    public static function pre(string $str): string {
        return '<PRE>' . $str . '</PRE>';
    }

    /**
     * Add the block's module.js to the current $blockinstance page.
     *
     * @param \block_integrityadvocate $blockinstance Instance of block_integrityadvocate.
     * @param string $proctorjsurl The proctor JS URL.
     * @return string HTML if error, otherwise empty string.  Also adds the JS to the page.
     */
    public static function add_block_js(\block_integrityadvocate $blockinstance, string $proctorjsurl): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && error_log($fxn . '::Started with ia_u::is_empty($blockinstance)='.ia_u::is_empty($blockinstance).'; $blockinstance->context->contextlevel='.$blockinstance->context->contextlevel.' vs \CONTEXT_BLOCK='.\CONTEXT_BLOCK.'; $proctorjsurl='.$proctorjsurl);

        // If the user is not enrolled as a student, $proctorjsurl is a string error message, so simply return an empty result.
        if(!\filter_var($proctorjsurl, \FILTER_VALIDATE_URL)) {
            error_log($fxn . '::The incoming $proctorjsurl is not a valid url it is '.ia_u::var_dump($proctorjsurl));
            return '';
        }

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ($blockinstance->context->contextlevel !== \CONTEXT_BLOCK)) {
            $msg = 'Input params are invalid';
            error_log($fxn . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        // If the block is not configured yet, simply return empty result.
        if ($configerrors = $blockinstance->get_config_errors()) {
            // No visible IA block found with valid config, so skip any output.
            if (\has_capability('block/integrityadvocate:overview', $blockinstance->context)) {
                echo \implode(self::BRNL, $configerrors);
            }
            return '';
        }

        // Organize access to JS.
        $jsmodule = ['name' => INTEGRITYADVOCATE_BLOCK_NAME,
            'fullpath' => self::BLOCK_JS_PATH . '/module.js',
            'requires' => [],
            'strings' => [],
        ];

        $blockinstance->page->requires->jquery_plugin('jquery');
        $blockinstance->page->requires->js_init_call('M.block_integrityadvocate.blockinit', [$proctorjsurl], false, $jsmodule);

        $debug && error_log($fxn . '::Done requiring IA JS and doing init call; had jsmodule='.serialize($jsmodule));
        return '';
    }

    /**
     * Build proctoring Javascript URL based on user and timestamp.
     *
     * @param \block_integrityadvocate $blockinstance Instance of block_integrityadvocate.
     * @param \stdClass $user Current user object; needed so we can identify this user to the IA API
     * @return string HTML if error; Also adds the student proctoring JS to the page.
     */
    public static function get_proctor_js_url(\block_integrityadvocate $blockinstance, \stdClass $user): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && error_log($fxn . '::Started');

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ($blockinstance->context->contextlevel !== \CONTEXT_BLOCK) || ia_u::is_empty($user) || !isset($user->id)) {
            $msg = 'Input params are invalid';
            error_log($fxn . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        // If the block is not configured yet, simply return empty result.
        if (ia_u::is_empty($blockinstance) || ($configerrors = $blockinstance->get_config_errors())) {
            // No visible IA block found with valid config, so skip any output, but show teachers the error.
            if ($configerrors && \has_capability('block/integrityadvocate:overview', $blockinstance->context)) {
                echo \implode(self::BRNL, $configerrors);
            }
            return '';
        }

        $blockcontext = $blockinstance->context;
        $blockparentcontext = $blockcontext->get_parent_context();
        $debug && error_log($fxn . '::Got $blockparentcontext->id=' . ia_u::var_dump($blockparentcontext->id, true));

        $course = $blockinstance->get_course();

        if ($blockparentcontext->contextlevel !== \CONTEXT_MODULE) {
            error_log($fxn . "::user={$user->id}; courseid={$course->id}: error=This block only shows JS in module context");
            return '';
        }

        if (!\is_enrolled($blockparentcontext, $user->id, null, true)) {
            $error = \get_string('error_notenrolled', INTEGRITYADVOCATE_BLOCK_NAME);
            // Teachers and students can see this error.
            $debug && error_log($fxn . "::user={$user->id}; courseid={$course->id}: error={$error}");
            return $error;
        }

        // The moodle_url class stores params non-urlencoded but outputs them encoded.
        // Note $modulecontext->instanceid is the cmid.
        $url = ia_api::get_js_url($blockinstance->config->appid, $course->id, $blockparentcontext->instanceid, $user);
        $debug && error_log($fxn . "::Built url={$url}");

        return $url;
    }

    /**
     * Generate the HTML for the Overview Course button.
     *
     * @param \block_integrityadvocate $blockinstance Instance of block_integrityadvocate.
     * @param int $userid The user id.  If specified, show the overview_user button.
     * @return string HTML button.
     */
    public static function get_button_overview_course(\block_integrityadvocate $blockinstance, $userid = null): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$blockinstance->instance->id={$blockinstance->instance->id}; \$userid={$userid}";
        $debug && error_log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || !\is_numeric($courseid = $blockinstance->get_course()->id) || (isset($userid) && !\is_int($userid))) {
            $msg = 'Input params are invalid';
            error_log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $params = ['sesskey' => sesskey(), 'instanceid' => $blockinstance->instance->id, 'courseid' => $courseid];

        // If we have a userid we must be a teacher looking at a user profile, so show the view user details button.
        if ($userid) {
            $debug && error_log($fxn . "::We have a \$userid={$userid} so label the button with view details");
            $params += ['userid' => $userid];
            $label = \get_string('btn_overview_user', INTEGRITYADVOCATE_BLOCK_NAME);
        } else {
            // Otherwise show the course overview button.
            $label = \get_string('btn_overview_course', INTEGRITYADVOCATE_BLOCK_NAME);
        }
        $debug && error_log($fxn . '::Built params=' . ia_u::var_dump($params));

        // Cache so multiple calls don't repeat the same work.  Persession cache b/c is keyed on hash of $blockinstance.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = ia_mu::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__, \json_encode($params, \JSON_PARTIAL_OUTPUT_ON_ERROR)]));
        if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            $debug && error_log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        $url = new \moodle_url('/blocks/integrityadvocate/overview.php', $params);
        $options = ['class' => 'block_integrityadvocate_overview_btn_overview_course'];

        global $OUTPUT;
        $output = $OUTPUT->single_button($url, $label, 'post', $options);

        if (FeatureControl::CACHE && !$cache->set($cachekey, $output)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $output;
    }

    /**
     * Generate the HTML for the Overview Module button.
     *
     * @param \block_integrityadvocate $blockinstance Instance of block_integrityadvocate.
     * @param int $userid The user id.  If specified, show the overview_user button.
     * @return string HTML button.
     */
    public static function get_button_overview_module(\block_integrityadvocate $blockinstance, $userid = null): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$blockinstance->instance->id={$blockinstance->instance->id}; \$userid={$userid}";
        $debug && error_log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || !\is_numeric($courseid = $blockinstance->get_course()->id) || (isset($userid) && !\is_int($userid))) {
            $msg = 'Input params are invalid';
            error_log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $blockcontext = $blockinstance->context;
        $parentcontext = $blockcontext->get_parent_context();

        $params = ['sesskey' => sesskey(), 'instanceid' => $blockinstance->instance->id, 'courseid' => $courseid, 'moduleid' => $parentcontext->instanceid];
        if ($userid) {
            $debug && error_log($fxn . "::We have a \$userid={$userid} so label the button with view details");
            $params += ['userid' => $userid];
            $label = \get_string('btn_overview_user', INTEGRITYADVOCATE_BLOCK_NAME);
        } else {
            $label = \get_string('btn_overview_module', INTEGRITYADVOCATE_BLOCK_NAME);
        }
        $debug && error_log($fxn . '::Built params=' . ia_u::var_dump($params));

        // Cache so multiple calls don't repeat the same work.  Persession cache b/c is keyed on hash of $blockinstance.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = ia_mu::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__, \json_encode($params, \JSON_PARTIAL_OUTPUT_ON_ERROR)]));
        if (false && FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            $debug && error_log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        $url = new \moodle_url('/blocks/integrityadvocate/overview.php', $params);
        $options = ['class' => 'block_integrityadvocate_overview_btn_overview_module'];

        global $OUTPUT;
        $output = $OUTPUT->single_button($url, $label, 'post', $options);

        if (FeatureControl::CACHE && !$cache->set($cachekey, $output)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $output;
    }

    /**
     * Generate the HTML for the Overview button, whether for course, module, or person.
     *
     * @param \block_integrityadvocate $blockinstance Instance of block_integrityadvocate.
     * @param int $userid The user id.
     * @return string HTML button.
     */
    public static function get_button_overview(\block_integrityadvocate $blockinstance, $userid = null): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$blockinstance->instance->id={$blockinstance->instance->id}; \$userid={$userid}";
        $debug && error_log($debugvars);

        $blockcontext = $blockinstance->context;
        $parentcontext = $blockcontext->get_parent_context();
        switch ((int) ($parentcontext->contextlevel)) {
            case ((int) (\CONTEXT_COURSE)):
                $debug && error_log($fxn . '::parentcontext=course');
                return self::get_button_overview_course($blockinstance, $userid);
            case ((int) (\CONTEXT_MODULE)):
                $debug && error_log($fxn . '::parentcontext=module');
                return self::get_button_overview_module($blockinstance, $userid);
            default:
                $msg = 'Unrecognized parent context';
                $debug && error_log($fxn . '::' . $msg);
                throw new \Exception($msg);
        }
    }

    /**
     * Get HTML for a link to re-submit to IA.
     * @param string $resubmiturl
     * @param string $prefix
     * @return string
     */
    public static function get_resubmit_html(string $resubmiturl, string $prefix): string {
        return \html_writer::span(
                        \format_text(\html_writer::link($resubmiturl, \get_string('resubmit_link', \INTEGRITYADVOCATE_BLOCK_NAME), ['target' => '_blank']), \FORMAT_HTML),
                        $prefix . '_resubmit_link');
    }

    /**
     * Get HTML for an IA user status.
     *
     * @param int $status
     * @param string $prefix
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function get_status_html(int $status, string $prefix): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$status={$status}; \$prefix={$prefix}";
        $debug && error_log($debugvars);

        $statushtml = '';
        $cssclassval = $prefix . '_status_val ';
        $debug && error_log($fxn . '::Got status=' . $status);
        switch ($status) {
            case ia_status::NOTSTARTED_INT:
                $statushtml = \html_writer::span(\get_string('status_notstarted', INTEGRITYADVOCATE_BLOCK_NAME), "{$cssclassval} {$prefix}_status_notstarted");
                break;
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
                $error = 'Invalid participant status value=' . \serialize($status);
                error_log($error);
                throw new \InvalidArgumentException($error);
        }

        return $statushtml;
    }

    /**
     * Parse the IA $participant object and return HTML output showing latest status, flags, and photos.
     *
     * @param \block_integrityadvocate $blockinstance Instance of block_integrityadvocate.
     * @param ia_participant $participant Participant object from the IA API.
     * @param bool $showphoto True to include the user photo.
     * @param bool $showoverviewbutton True to show the Overview button.
     * @param bool $showstatus True to show the latest IA status for the given module the block IF the block is attached to one.
     * @return string HTML output.
     */
    public static function get_participant_summary_output(\block_integrityadvocate $blockinstance, ia_participant $participant, bool $showphoto = true, bool $showoverviewbutton = true, bool $showstatus = false): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$blockinstance->instance->id={$blockinstance->instance->id}; \$participant->participantidentifier={$participant->participantidentifier}; \$showphoto={$showphoto}; \$showoverviewbutton={$showoverviewbutton}; \$showstatus={$showstatus}; \$participant->status={$participant->status}";
        $debug && error_log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ia_u::is_empty($participant) || !ia_status::is_status_int($participant->status)) {
            $msg = $fxn . '::Input params are invalid; \$debugvars=' . $debugvars;
            error_log($fxn . '::' . $msg);
            error_log($fxn . '::ia_u::is_empty($blockinstance)=' . ia_u::is_empty($blockinstance) . '; ia_u::is_empty($participant)=' . ia_u::is_empty($participant) . '; ia_status::is_status_int($participant->status)=' . ia_status::is_status_int($participant->status));
            throw new \InvalidArgumentException($msg);
        }

        // Cache so multiple calls don't repeat the same work.  Persession cache b/c is keyed on hash of $blockinstance.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = ia_mu::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__, $participant->__toString(), \json_encode($debugvars, \JSON_PARTIAL_OUTPUT_ON_ERROR), $debugvars]));
        if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            $debug && error_log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        $status = Status::NOTSTARTED_INT;
        if ($showstatus && ($blockcontext = $blockinstance->context) && ($modulecontext = $blockcontext->get_parent_context()) && ($modulecontext->contextlevel == CONTEXT_MODULE)) {
            $status = ia_api::get_module_status($modulecontext, $blockinstance->get_user()->id);
        }

        $out = self::get_summary_html(
                        $participant->participantidentifier, $status, $participant->created, $participant->modified,
                        ($showphoto ? self::get_participant_photo_output($participant->participantidentifier, ($participant->participantphoto ?: ''), $participant->status, $participant->email) : ''),
                        ($showoverviewbutton ? self::get_button_overview($blockinstance, $participant->participantidentifier) : ''),
                        $showstatus
        );

        if (FeatureControl::CACHE && !$cache->set($cachekey, $out)) {
            throw new \Exception('Failed to set value in the cache');
        }
        return $out;
    }

    /**
     * Get user summary HTML.
     *
     * @param int $userid
     * @param int $status
     * @param int $start
     * @param int $end
     * @param string $photohtml
     * @param string $overviewbuttonhtml
     * @param bool $showstatus
     * @return string
     */
    public static function get_summary_html(int $userid, int $status, int $start, int $end, string $photohtml = '', string $overviewbuttonhtml = '', bool $showstatus = false): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$userid={$userid}; \$status={$status}; \$start={$start}; \$end={$end}; \$photohtml={$photohtml}; \$overviewbuttonhtml={$overviewbuttonhtml}; \$showstatus={$showstatus}";
        $debug && error_log($debugvars);

        $prefix = INTEGRITYADVOCATE_BLOCK_NAME;
        $outarr = [];
        $outarr[] = \html_writer::start_tag('div', ['class' => $prefix . '_overview_participant_summary_div']);
        $outarr[] = \html_writer::start_tag('div', ['class' => $prefix . '_overview_participant_summary_text']);

        if ($showstatus) {
            $outarr[] = \html_writer::start_tag('div', ['class' => $prefix . '_overview_participant_summary_status']) .
                    \html_writer::span(\get_string('overview_user_status', INTEGRITYADVOCATE_BLOCK_NAME) . ': ', $prefix . '_overview_participant_summary_status_label') .
                    self::get_status_html($status, $prefix) .
                    \html_writer::end_tag('div');
        }

        $outarr[] = \html_writer::start_tag('div', ['class' => $prefix . '_overview_participant_summary_start']) .
                \html_writer::span(\get_string('created', INTEGRITYADVOCATE_BLOCK_NAME) . ': ', $prefix . '_overview_participant_summary_status_label') .
                ($start ? \userdate($start) : '') .
                \html_writer::end_tag('div');

        $outarr[] = \html_writer::start_tag('div', ['class' => $prefix . '_overview_participant_summary_end']) .
                \html_writer::span(\get_string('last_modified', INTEGRITYADVOCATE_BLOCK_NAME) . ': ', $prefix . '_overview_participant_summary_status_label') .
                ($end ? \userdate($end) : '') .
                \html_writer::end_tag('div');

        if ($overviewbuttonhtml) {
            $outarr[] = $overviewbuttonhtml;
        }

        // Close .block_integrityadvocate_overview_participant_summary_text.
        $outarr[] = \html_writer::end_tag('div');

        $debug && error_log($fxn . '::About to check if should include photo=' . ($photohtml ? 1 : 0));
        if ($photohtml) {
            $outarr[] = $photohtml;
        }

        // Close .block_integrityadvocate_overview_participant_summary_div.
        $outarr[] = \html_writer::end_tag('div');

        // Start next section on a new line.
        $outarr[] = '<div style="clear:both"></div>';

        return \implode('', $outarr);
    }

    /**
     * Get the HTML used to display the participant photo in the IA summary output.
     *
     * @param int $userid The user id.
     * @param string $photo The user photo base64 string.
     * @param int $status The IA status.
     * @param string $email The user email.
     * @return string HTML to output
     */
    public static function get_participant_photo_output(int $userid, string $photo, int $status, string $email): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$userid={$userid}; md5(\$photo)=" . \md5($photo) . "\$status={$status}, \$email={$email}";
        $debug && error_log($debugvars);

        $prefix = INTEGRITYADVOCATE_BLOCK_NAME . '_overview_participant';
        $outarr = [];
        $outarr[] = \html_writer::start_tag('div', ['class' => $prefix . '_summary_img_div']);
        if ($photo) {
            $outarr[] = \html_writer::start_tag('span',
                            ['class' => $prefix . '_summary_img ' . $prefix . '_summary_img_' .
                                ($status === ia_status::VALID_INT ? '' : 'in') . 'valid']
                    ) .
                    \html_writer::img($photo, $email) .
                    \html_writer::end_tag('span');
        }
        // Close .block_integrityadvocate_overview_participant_summary_img_div.
        $outarr[] = \html_writer::end_tag('div');

        return \implode('', $outarr);
    }

    /**
     * Get the user $participant info and return HTML output showing latest status, flags, and photos.
     * After getting the participant data for the userid, this is just a wrapper around get_participant_basic_output()
     *
     * @param \block_integrityadvocate $blockinstance Block instance to get participant data for.
     * @param int $userid User id to get info for.
     * @param bool $showphoto True to include the photo from the Participant info.
     * @param bool $showoverviewbutton True to show the "View Details" button to get more info about the users IA session.
     * @param bool $showstatus True to show the latest IA status for the given module the block IF the block is attached to one.
     * @return string HTML output showing latest status, flags, and photos.
     */
    public static function get_user_summary_output(\block_integrityadvocate $blockinstance, int $userid, bool $showphoto = true, bool $showoverviewbutton = true, bool $showstatus = false): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$userid={$userid}; \$showphoto={$showphoto}; \$showoverviewbutton={$showoverviewbutton}; \$showstatusinmodulecontext:gettype=" . \gettype($showstatus);
        $debug && error_log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || ($blockinstance->context->contextlevel !== \CONTEXT_BLOCK)) {
            $msg = 'Input params are invalid';
            error_log($fxn . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        $blockcontext = $blockinstance->context;
        $parentcontext = $blockcontext->get_parent_context();

        try {
            switch ((int) ($parentcontext->contextlevel)) {
                case ((int) (\CONTEXT_COURSE)):
                    // If the block is in a course, show the participant-level latest status, photo, last seen, etc.
                    $debug && error_log($fxn . '::Am in a module context');
                    $participant = ia_api::get_participant($blockinstance->config->apikey, $blockinstance->config->appid, $blockinstance->get_course()->id, $userid, $blockinstance->instance->id);

                    if (ia_u::is_empty($participant)) {
                        $debug && error_log($fxn . '::Got empty participant, so return empty result');
                        return get_string('studentmessage', INTEGRITYADVOCATE_BLOCK_NAME);
                    }

                    return self::get_participant_summary_output($blockinstance, $participant, $showphoto, $showoverviewbutton, $showstatus);

                case ((int) (\CONTEXT_MODULE)):
                    // If block is in a module, show the module's latest status, photo, start, end.
                    $debug && error_log($fxn . '::Am in a module context');
                    $latestsession = ia_api::get_module_session_latest($parentcontext, $userid);
                    $debug && error_log($fxn . '::Got $latestsession=' . ia_u::is_empty($latestsession)?'':$latestsession->__toString());
                    if (ia_u::is_empty($latestsession)) {
                        $debug && error_log($fxn . '::Got empty $latestsession, so return empty result');
                        return get_string('studentmessage', INTEGRITYADVOCATE_BLOCK_NAME);
                    }
                    $participant = $latestsession->participant;
                    // phpcs:ignore .
                    // Usage example: get_summary_html(int $userid, int $status, int $start, int $end, string $photohtml = '', string $overviewbuttonhtml = '', bool $showstatus = false).
                    return self::get_summary_html(
                                    $participant->participantidentifier, $latestsession->status, $latestsession->start, $latestsession->end,
                                    ($showphoto ? self::get_participant_photo_output($participant->participantidentifier, ($latestsession->participantphoto ?: ''), $latestsession->status, $participant->email) : ''),
                                    ($showoverviewbutton ? self::get_button_overview($blockinstance, $participant->participantidentifier) : ''),
                                    $showstatus
                    );

                default:
                    $msg = 'Unrecognized parent context';
                    $debug && error_log($fxn . '::' . $msg);
                    throw new \Exception($msg);
            }
        } catch (\block_integrityadvocate\HttpException $e) {
            error_log($fxn . "::{$debugvars}::Ignoring an HttpException so the page display is not broken");
            return '';
        }
    }

    /**
     * Returns the HTML for a form and its corresponding LTI iframe.
     *
     * @param string $url The URL the iframe points to.
     * @param array $data Form input fields used to launch the LTI iframe.
     * @param string $signature LTI signature.
     * @return string The HTML for a form and its corresponding LTI iframe.
     */
    public static function get_lti_iframe_html(string $url, array $data, string $signature): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$url={$url}; \$signature={$signature}; \$data=" . ia_u::var_dump($data);
        $debug && error_log($debugvars);

        $output = ['<form id="ltiLaunchForm" name="ltiLaunchForm" method="POST" target="iframelaunch" style="display:none" action="' . $url . '">'];
        foreach ($data as $k => $v) {
            $output[] = '<input type="hidden" name="' . $k . '" value="' . \htmlspecialchars($v, \ENT_QUOTES, 'UTF-8') . '">';
        }
        $output[] = '<input type="hidden" name="oauth_signature" value="' . $signature . '"><button type="submit">Launch</button></form>';
        $output[] = '<iframe id="iframelaunch" name="iframelaunch" src="" style="width:100%;height:800px" sandbox="allow-same-origin allow-forms allow-scripts allow-modals allow-downloads"></iframe>';
        $output[] = '<script>document.getElementById("ltiLaunchForm").submit();</script>';
        return \implode('', $output);
    }

}
