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
 * IntegrityAdvocate block definition
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Output as ia_output;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/lib.php');

/**
 * IntegrityAdvocate block class
 *
 * @copyright IntegrityAdvocate.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_integrityadvocate extends block_base {

    /**
     * Sets the block title
     *
     * @return void
     */
    public function init() {
        $this->title = \get_string('config_default_title', \INTEGRITYADVOCATE_BLOCK_NAME);
    }

    /**
     *  we have global config/settings data
     *
     * @return bool
     */
    public function has_config(): bool {
        return false;
    }

    /**
     * Do any additional initialization you may need at the time a new block instance is created
     * @return boolean
     */
    public function instance_create() {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with configdata=' . ia_u::var_dump($this->config, true));

        // Get the first IA block with APIKey and APPId, and use it for this block.
        global $COURSE;
        $blocks = ia_mu::get_all_course_blocks($COURSE->id, INTEGRITYADVOCATE_SHORTNAME);
        $debug && ia_mu::log($fxn . '::Got count(blocks)=' . count($blocks));
        foreach ($blocks as $key => $b) {
            $debug && ia_mu::log($fxn . "::Looking at block_instance.id={$key}");

            // Only look in other blocks, and skip those with apikey/appid errors.
            if ($b->get_apikey_appid_errors()) {
                continue;
            }

            // Holds the block config and changes we want to make to it.
            $configdata = (array) $this->config;
            $configdata['apikey'] = $b->config->apikey;
            $configdata['appid'] = $b->config->appid;
            $this->instance_config_save((object) $configdata);
            break;
        }

        // If this is a quiz, auto-configure the quiz to...
        $debug && ia_mu::log($fxn . "::Looking at pagetype={$this->page->pagetype}");
        if (stripos($this->page->pagetype, 'mod-quiz-') !== false) {
            // A. Show blocks during quiz attempt; and...
            $modulecontext = $this->context->get_parent_context();
            $debug && ia_mu::log($fxn . '::Got $modulecontext=' . ia_u::var_dump($modulecontext, true));
            $modinfo = \get_fast_modinfo($COURSE, -1);
            $cm = $modinfo->get_cm($modulecontext->instanceid);
            $debug && ia_mu::log($fxn . '::Got $cm->instance=' . ia_u::var_dump($cm->instance, true));
            global $DB;
            $record = $DB->get_record('quiz', array('id' => intval($cm->instance)), '*', \MUST_EXIST);
            $debug && ia_mu::log($fxn . '::Got record=' . ia_u::var_dump($record, true));
            if ($record->showblocks < 1) {
                $record->showblocks = 1;
                $DB->update_record('quiz', $record);
            }

            // B. By default show the block on all quiz pages.
            $DB->set_field('block_instances', 'pagetypepattern', 'mod-quiz-*', array('id' => $this->instance->id));
            $debug && ia_mu::log($fxn . '::Set DB [pagetypepattern] = mod-quiz-*');
        }

        return true;
    }

    /**
     * Controls the block title based on instance configuration
     *
     * @return bool
     */
    public function specialization() {
        if (isset($this->config->progressTitle) && trim($this->config->progressTitle) != '') {
            $this->title = \format_string($this->config->progressTitle);
        }
    }

    /**
     * Controls whether multiple instances of the block are allowed on a page
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Controls whether the block is configurable
     *
     * @return bool
     */
    public function instance_allow_config(): bool {
        return true;
    }

    /**
     * Defines where the block can be added
     *
     * @return array
     */
    public function applicable_formats(): array {
        return array(
            'admin' => false,
            'course-view' => true,
            'mod' => true,
            'my' => false,
            'site' => false,
                // Unused: 'all'.
                // Unused: 'course'.
                // Unused: 'course-view-social'.
                // Unused: 'course-view-topics'.
                // Unused: 'course-view-weeks'.
                // Unused: 'mod-quiz'.
                // Unused: 'site-index'.
                // Unused: 'tag'.
                // Unused: 'user-profile'.
        );
    }

    private function get_apikey_appid_errors(): array {
        $debug = false;
        $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::Started');

        $errors = array();
        $hasblockconfig = isset($this->config) && !ia_u::is_empty($this->config);

        if (!$hasblockconfig) {
            $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::Error: This block has no config');
            $errors = array('This block has no config');
        }

        if (!isset($this->config->apikey) || !ia_mu::is_base64($this->config->apikey)) {
            $errors['config_apikey'] = \get_string('error_noapikey', \INTEGRITYADVOCATE_BLOCK_NAME);
            $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::' . $errors['config_apikey']);
        }
        if (!isset($this->config->appid) || !ia_u::is_guid($this->config->appid)) {
            $errors['config_appid'] = \get_string('error_noappid', \INTEGRITYADVOCATE_BLOCK_NAME);
            $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::' . $errors['config_appid']);
        }

        return $errors;
    }

    /**
     * Return config errors if there are any.
     *
     * @throws Exception If error
     * @return array(field=>error message)
     */
    public function get_config_errors(): array {
        // Check for errors that don't matter what context we are in.
        $errors = $this->get_apikey_appid_errors();

        $modulecontext = $this->context->get_parent_context();
        // Check the context we got is module context and not course context.
        // If this is a course-level block, just return what errors we have so far.
        if (ia_u::is_empty($modulecontext) || $modulecontext->contextlevel !== \CONTEXT_MODULE) {
            return $errors;
        }

        /*
         * If this block is added to a a quiz, warn instructors if the block is hidden to students during quiz attempts.
         */
        if (stripos($modulecontext->get_context_name(), 'quiz') === 0) {
            global $DB, $COURSE;
            if ($COURSE->id == \SITEID) {
                throw new \Exception('This block cannot exist on the site context');
            }
            $modinfo = \get_fast_modinfo($COURSE, -1);
            $cm = $modinfo->get_cm($modulecontext->instanceid);
            $record = $DB->get_record('quiz', array('id' => $cm->instance), 'id, showblocks', \MUST_EXIST);
            if ($record->showblocks < 1) {
                $errors['config_quiz_showblocks'] = get_string('error_quiz_showblocks', \INTEGRITYADVOCATE_BLOCK_NAME);
            }
        }

        return $errors;
    }

    /**
     * Creates the blocks main content
     */
    public function get_content() {
        global $USER, $COURSE, $DB, $CFG;
        $debug = false;
        $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ .
                        '::Started with url=' . $this->page->url . '; courseid=' . $COURSE->id . '; $USER->id=' . $USER->id .
                        '; $USER->username=' . $USER->username);

        if (is_object($this->content) && isset($this->content->text) && !empty(trim($this->content->text))) {
            $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ .
                            "::Content has already been generated, so do not generate it again: \n" . ia_u::var_dump($this->content, true));
            return;
        }

        $this->content = new \stdClass;
        $this->content->text = '';
        $this->content->footer = '';
        $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::Done setting up $this->content');

        // Guests do not have any IA use. Don't show them the block.
        if (!\isloggedin() or \isguestuser()) {
            $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::Not logged in or is guest user, so skip it');
            return;
        }

        // The block is hidden so don't show anything.
        if (!$this->instance->visible) {
            return;
        }

        $setuperrors = ia_mu::get_completion_setup_errors($COURSE);
        $hasoverviewcapability = \has_capability('block/integrityadvocate:overview', $this->context);
        if ($debug) {
            ia_mu::log(__CLASS__ . '::' . __FUNCTION__ .
                    '::Permissions check: has_capability(\'block/integrityadvocate:overview\')=' . ia_u::var_dump($hasoverviewcapability, true));
            ia_mu::log(__CLASS__ . '::' . __FUNCTION__ .
                    '::Got setup errors=' . ($setuperrors ? ia_u::var_dump($setuperrors, true) : ''));
        }
        if ($setuperrors && $hasoverviewcapability) {
            foreach ($setuperrors as $err) {
                $this->content->text .= get_string($err, \INTEGRITYADVOCATE_BLOCK_NAME) . "<br />\n";
            }
            $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::Setup errors, so skip it');
            return;
        }

        // Check if any modules have been created.
        $exclusions = ia_mu::get_gradebook_exclusions($DB, $COURSE->id);
        $modules = ia_mu::get_modules_with_completion($COURSE->id);
        $modules = ia_mu::filter_for_visible($CFG, $modules, $USER->id, $COURSE->id, $exclusions);
        $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__
                        . '::Modules found=' . (is_countable($modules) ? count($modules) : 0));
        if (empty($modules)) {
            if ($hasoverviewcapability) {
                $this->content->text .= get_string('no_modules_config_message', \INTEGRITYADVOCATE_BLOCK_NAME);
            }
            $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::No modules, so skip it');
            return;
        }

        $blockinstancesonpage = array($this->instance->id);

        // ATM we have no module-specific JS file, so this stays false.
        $needmodulejs = false;

        $hasselfviewcapability = \has_capability('block/integrityadvocate:selfview', $this->context);

        // Check if there is any errors.
        if ($configerrors = $this->get_config_errors()) {
            $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::Error: ' . ia_u::var_dump($configerrors, true));

            // Error output is visible only to instructors.
            if ($hasoverviewcapability) {
                $this->content->text .= implode("<br />\n", $configerrors);
            }
            return;
        }

        // Figure out what context we are in so we can decide what to show for whom.
        $blockcontext = $this->context;
        $parentcontext = $blockcontext->get_parent_context();

        switch ($parentcontext->contextlevel) {
            case CONTEXT_COURSE:
                $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::Context=CONTEXT_COURSE');
                switch (true) {
                    case $hasoverviewcapability:
                        if (stripos($this->page->url, '/user/view.php?') > 0) {
                            $courseid = required_param('course', PARAM_INT);
                            $targetuserid = optional_param('id', $USER->id, PARAM_INT);
                            $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ .
                                            '::This is the course-user page, so in the block show the IA proctor summary for ' .
                                            'this course-user combo: courseid=' . $courseid .
                                            '; $targetuserid=' . $targetuserid);

                            // Check the user is enrolled in this course, even if inactive.
                            if (!\is_enrolled($parentcontext, $targetuserid)) {
                                throw new \Exception('That user is not in this course');
                            }

                            $this->content->text .= ia_output::get_user_basic_output($this, $targetuserid);
                        }

                        $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::Teachers should see the overview button');
                        $this->content->text .= ia_output::get_button_course_overview($this);
                        break;
                    case $hasselfviewcapability:
                        $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::Student should see their own summary IA results');
                        // Check the user is enrolled in this course, but they must be active.
                        if (!\is_enrolled($parentcontext, $USER, null, true)) {
                            throw new \Exception('That user is not in this course');
                        }

                        $this->content->text .= ia_output::get_user_basic_output($this, $USER->id);
                        break;
                }

                break;
            case CONTEXT_MODULE:
                $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::Context=CONTEXT_MODULE');
                switch (true) {
                    case $hasoverviewcapability:
                        $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::Teacher should see the overview button');
                        $this->content->text .= ia_output::get_button_course_overview($this);
                        break;
                    case \is_enrolled($parentcontext, $USER, null, true):
                        // This is someone in a student role.
                        switch (true) {
                            case(stripos($this->page->pagetype, 'mod-quiz-') !== false &&
                            ($this->page->pagetype !== 'mod-quiz-attempt')):
                                // If we are in a quiz, only show the JS proctoring UI if on the quiz attempt page.
                                // Other pages should show the summary.
                                $this->content->text .= ia_output::get_user_basic_output($this, $USER->id);
                                break;
                            case (stripos($this->page->pagetype, 'mod-scorm-') !== false &&
                            ($this->page->pagetype !== 'mod-scorm-player')):
                                // If we are in a scorm, only show the JS proctoring UI if on the scorm player page.
                                // Other pages should show the summary.
                                $this->content->text .= ia_output::get_user_basic_output($this, $USER->id);
                                break;
                            default:
                                $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::Student should see proctoring JS');
                                $this->content->text .= ia_output::add_proctor_js($this, $USER);
                                break;
                        }
                        break;
                    default:
                        throw new \Exception('The user is not enrolled in this course');
                }

                break;
            default:
                $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::In some unknown context, so show nothing');
                return;
        }

        if ($needmodulejs) {
            $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::About to add module JS');

            // Organize access to JS.
            $jsmodule = array(
                'name' => \INTEGRITYADVOCATE_BLOCK_NAME,
                'fullpath' => '/blocks/integrityadvocate/module.js',
                'requires' => array(),
                'strings' => array(),
            );
            $arguments = array($blockinstancesonpage, array($USER->id));
            $this->page->requires->js_init_call('M.block_integrityadvocate.init', $arguments, false, $jsmodule);
        }
    }

    public function get_course() {
        global $COURSE;
        return $COURSE;
    }

}
