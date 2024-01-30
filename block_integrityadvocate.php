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

use block_integrityadvocate\Api as ia_api;
use block_integrityadvocate as ia;
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
     * Sets the block title.
     *
     * @return void
     */
    public function init() {
        $this->title = \get_string('config_default_title', \INTEGRITYADVOCATE_BLOCK_NAME);
    }

    /**
     *  We have global config/settings data.
     *
     * @return bool True if we have global config/settings data.
     */
    public function has_config(): bool {
        return false;
    }

    /**
     * Do any additional initialization you may need at the time a new block instance is created.
     *
     * @return bool True if we have additional initializations.
     */
    public function instance_create() {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && debugging($fxn . '::Started with configdata=' . ia_u::var_dump($this->config, true));

        // Get the first IA block with APIKey and APPId, and use it for this block.
        global $COURSE;
        $blocks = ia_mu::get_all_course_blocks($COURSE->id, INTEGRITYADVOCATE_SHORTNAME);
        $debug && debugging($fxn . '::Got count(blocks)=' . ia_u::count_if_countable($blocks));
        foreach ($blocks as $key => $b) {
            $debug && debugging($fxn . "::Looking at block_instance.id={$key}");

            // Only look in other blocks, and skip those with apikey/appid errors.
            if (($this->instance->id === $b->instance->id) || $b->get_apikey_appid_errors()) {
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
        $debug && debugging($fxn . "::Looking at pagetype={$this->page->pagetype}");
        if (str_starts_with($this->page->pagetype, 'mod-quiz-')) {
            // A. Show blocks during quiz attempt; and...
            $modulecontext = $this->context->get_parent_context();
            $debug && debugging($fxn . '::Got $modulecontext=' . ia_u::var_dump($modulecontext, true));
            $modinfo = \get_fast_modinfo($COURSE, -1);
            $cm = $modinfo->get_cm($modulecontext->instanceid);
            $debug && debugging($fxn . '::Got $cm->instance=' . ia_u::var_dump($cm->instance, true));
            global $DB;
            $record = $DB->get_record('quiz', ['id' => (int) ($cm->instance)], '*', \MUST_EXIST);
            $debug && debugging($fxn . '::Got record=' . ia_u::var_dump($record, true));
            if ($record->showblocks < 1) {
                $record->showblocks = 1;
                $DB->update_record('quiz', $record);
            }

            // B. By default show the block on all quiz pages.
            $DB->set_field('block_instances', 'pagetypepattern', 'mod-quiz-*', ['id' => $this->instance->id]);
            $debug && debugging($fxn . '::Set DB [pagetypepattern] = mod-quiz-*');
        }

        return true;
    }

    /**
     * Controls the block title based on instance configuration.
     *
     * @return void.
     */
    public function specialization() {
        if (isset($this->config->progressTitle) && \trim($this->config->progressTitle) != '') {
            $this->title = \format_string($this->config->progressTitle);
        }
    }

    /**
     * Controls whether multiple instances of the block are allowed on a page.
     *
     * @return bool True if multiple instances of the block are allowed on a page.
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Controls whether the block is configurable.
     *
     * @return bool True if the block is configurable.
     */
    public function instance_allow_config(): bool {
        return true;
    }

    /**
     * Defines where the block can be added.
     *
     * @return array<string, mixed> where key=location; value=whether it can be added.
     */
    public function applicable_formats(): array {
        return ['admin' => false,
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
        ];
    }

    /**
     * Check of errors in the APIKey and AppId.
     *
     * @return string[] Array of error messages from lang file: error_*.  Empty array if no errors.
     */
    public function get_apikey_appid_errors(): array {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && debugging($fxn . '::Started');

        $errors = [];
        $hasblockconfig = isset($this->config) && !ia_u::is_empty($this->config);

        if (!$hasblockconfig) {
            $debug && debugging($fxn . '::Error: This block has no config');
            $errors = ['This block has no config'];
        }

        if (!isset($this->config->apikey) || !self::is_valid_apikey($this->config->apikey)) {
            $errors['config_apikey'] = \get_string('error_noapikey', \INTEGRITYADVOCATE_BLOCK_NAME);
            $debug && debugging($fxn . '::' . $errors['config_apikey']);
        }
        if (!isset($this->config->appid) || !self::is_valid_appid($this->config->appid)) {
            $errors['config_appid'] = \get_string('error_noappid', \INTEGRITYADVOCATE_BLOCK_NAME);
            $debug && debugging($fxn . '::' . $errors['config_appid']);
        }

        return $errors;
    }

    /**
     * Check if the input API key is valid.
     *
     * @param string $apikey The API key to check.
     * @return bool True if valid.
     */
    public static function is_valid_apikey(string $apikey): bool {
        // Cache bc this can be called many times even in one request.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = ia_mu::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__, $apikey]));
        if (ia\FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            return $cachedvalue;
        }

        $returnthis = strlen($apikey) > 40 && ia_mu::is_base64($apikey);

        if (ia\FeatureControl::CACHE && !$cache->set($cachekey, $returnthis)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $returnthis;
    }

    /**
     * Check if the AppId is valid.
     *
     * @param string $appid The AppId to check.
     * @return bool True if valid.
     */
    public static function is_valid_appid(string $appid): bool {
        return ia_u::is_guid($appid);
    }

    /**
     * Return config errors if there are any.
     *
     * @return array<string, string> Config error array where field = error field; message=error message.
     */
    public function get_config_errors(): array {
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'perrequest');
        $cachekey = ia_mu::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__, $this->instance->id]));
        if (ia\FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            return $cachedvalue;
        }

        // Check for errors that don't matter what context we are in.
        $errors = $this->get_apikey_appid_errors();

        $modulecontext = $this->context->get_parent_context();

        // Check the context we got is module context and not course context.
        // If this is a course-level block, just return what errors we have so far.
        if (ia_u::is_empty($modulecontext) || $modulecontext->contextlevel !== \CONTEXT_MODULE) {
            if (ia\FeatureControl::CACHE && !$cache->set($cachekey, $errors)) {
                throw new \Exception('Failed to set value in the cache');
            }
            return $errors;
        }

        $courseid = $this->context->get_course_context()->instanceid;
        if ($courseid === \SITEID) {
            throw new \Exception('This block cannot exist on the site context');
        }

        /*
         * If this block is added to a a quiz, warn instructors if the block is hidden to students during quiz attempts.
         */
        global $DB;
        if (str_starts_with($modulecontext->get_context_name(), 'quiz')) {
            $modinfo = \get_fast_modinfo($courseid, -1);
            $cm = $modinfo->get_cm($modulecontext->instanceid);
            $record = $DB->get_record('quiz', ['id' => $cm->instance], 'id, showblocks', \MUST_EXIST);
            if ($record->showblocks < 1) {
                $errors['config_quiz_showblocks'] = get_string('error_quiz_showblocks', \INTEGRITYADVOCATE_BLOCK_NAME);
            }
        }

        if (ia\FeatureControl::CACHE && !$cache->set($cachekey, $errors)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $errors;
    }

    /**
     * Add proctoring JS to the page.
     *
     * @param \stdClass $user Moodle user to get the JS for - the request is encoded for this user.
     * @param bool $hidemodulecontent True to hide the module content by adding a style tag to the block output.
     *
     * @return void.
     */
    private function add_proctor_js(\stdClass $user, bool $hidemodulecontent = true) {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        global $OUTPUT;
        $debug && debugging($fxn . '::Started with $user=' . $user->id);

        $this->page->requires->string_for_js('proctorjs_load_failed', INTEGRITYADVOCATE_BLOCK_NAME);
        $this->page->requires->string_for_js('exitactivity', 'scorm');

        // Hide module content until JS is loaded and the IA modal is open.
        // These styles are removed in the js by simply removing this element.
        if ($hidemodulecontent) {
            $this->content->text .= '<style id="block_integrityadvocate_hidemodulecontent">'
                    . "#responseform, #scormpage, div[role=\"main\"]{display:none}\n"
                    . "#user-notifications{height:100px;background:center no-repeat url('" .
                    $OUTPUT->image_url('i/loading') . "')}\n"
                    . '</style>';
        }

        // This must hold some content, otherwise this function runs twice.
        $this->content->text .= get_string('studentmessage', INTEGRITYADVOCATE_BLOCK_NAME);

        $debug && debugging($fxn . '::About to add_block_js() with $user=' . $user->id);
        ia_output::add_block_js($this, ia_output::get_proctor_js_url($this, $user));
    }

    /**
     * Populate $this->content->text with the list of modules using IA blocks.
     *
     * @return bool True if some output was built.
     */
    private function populate_course_modulelist(): bool {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && debugging($fxn . '::Started');

        // Security check.
        if (!$this->page->user_allowed_editing()) {
            return false;
        }

        $prefix = 'block_integrityadvocate_modulelist';
        $iablocksinthiscourse = ia_mu::get_all_course_blocks($this->get_course()->id, INTEGRITYADVOCATE_SHORTNAME);
        $iamodulesexist = ($blockcount = ia_u::count_if_countable($iablocksinthiscourse)) > 0;

        $this->content->text .= \html_writer::start_tag('div', ['class' => "{$prefix}_div"]);
        $this->content->text .= \html_writer::tag('h6', \get_string('blocklist_title', INTEGRITYADVOCATE_BLOCK_NAME,
                                (string) $blockcount), ['class' => "{$prefix}_div_title"]);

        if (!$iamodulesexist) {
            $debug && debugging($fxn . '::No modules exist');
            $this->content->text .= \html_writer::end_tag('div');
            return true;
        }

        global $CFG, $COURSE;

        // The start of the form is the same for each module, so just build it once.
        $formstart = '&nbsp;<form class="' . $prefix . '_form" method="post" action="' . $CFG->wwwroot . '/course/view.php">';
        $formstart .= '<input type="hidden" name="id" value="' . $COURSE->id . '">';
        $formstart .= '<input type="hidden" name="sesskey" value="' . \sesskey() . '">';
        $formstart .= '<input type="hidden" name="edit" value="on">';

        $userisediting = $this->page->user_is_editing();
        $debug && debugging($fxn . '::Course editing mode=' . ($userisediting ? 1 : 0));

        foreach ($iablocksinthiscourse as $blockinstance) {
            $debug && debugging($fxn . '::Looking at block $b=' . ia_u::var_dump($blockinstance));

            // Get the parent module.
            $parentcontext = $blockinstance->context->get_parent_context();
            switch (true) {
                case($parentcontext->contextlevel == CONTEXT_COURSE) :
                    $this->content->text .= \html_writer::link($parentcontext->get_url(), get_string('course'));
                    break;
                case($parentcontext->contextlevel == CONTEXT_MODULE) :
                    // Output a link to the module.
                    $module = \context_module::instance($parentcontext->instanceid);
                    $this->content->text .= \html_writer::link($module->get_url(), $module->get_context_name(false));

                    if (ia\FeatureControl::MODULE_LIST_CONFIGLINK && \has_capability('moodle/block:edit', $blockinstance->context)) {
                        $blocktitle = get_string('configureblock', 'block', $blockinstance->title);
                        if ($userisediting) {
                            // Output a link to module's block config.
                            $this->content->text .= '<a href="' . $CFG->wwwroot . '/mod/quiz/view.php?id=' . $module['id'] .
                                    '&sesskey=' . \sesskey() . '&bui_editid=' . $blockinstance->get_id() . '">&nbsp;<i class="' .
                                    $prefix . '_blockconfig icon fa fa-cog fa-fw " title="' . $blocktitle . '" aria-label="' .
                                    $blocktitle . '"></i></a>';
                        } else {
                            // We need a form to turn course editing on; then go to block config.
                            $this->content->text .= $formstart .
                                    '<input type="hidden" name="return" value="/mod/quiz/view.php?id=' . $module['id'] .
                                    '&sesskey=' . \sesskey() . '&bui_editid=' . $blockinstance->get_id() . '">';
                            $this->content->text .= '<a href="#" onclick="javascript:$(this).closest(\'form\').submit();'
                                    . 'e.preventDefault();return false;"><i class="'
                                    . $prefix . '_blockconfig icon fa fa-cog fa-fw " title="' .
                                    $blocktitle . '" aria-label="' . $blocktitle . '"></i></a>';
                            $this->content->text .= '</form>';
                        }
                    }
                    break;
                default:
                    $msg = $fxn . '::Found unexpected contextlevel=' . $parentcontext->contextlevel;
                    debugging($msg);
                    throw new \Exception($msg);
            }
            $this->content->text .= ia_output::BRNL;
        }

        $this->content->text .= \html_writer::end_tag('div');
        return true;
    }

    /**
     * Get the context for this block.
     *
     * @return \context_block The context for this block.
     */
    public function get_context(): \context_block {
        return $this->context;
    }

    /**
     * Creates the blocks main content.
     *
     * @return void.
     */
    public function get_content() {
        global $USER, $COURSE, $DB, $CFG;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && debugging($fxn . '::Started with url=' . $this->page->url . '; courseid=' . $COURSE->id .
                        '; $USER->id=' . $USER->id . '; $USER->username=' . $USER->username);

        if (\is_object($this->content) && isset($this->content->text) && !empty(\trim($this->content->text))) {
            return;
        }

        $this->content = new \stdClass;
        $this->content->text = '';
        $this->content->footer = '';
        $debug && debugging($fxn . '::Done setting up $this->content');

        // Guests do not have any IA use. Don't show them the block.
        if (!\isloggedin() || \isguestuser()) {
            $debug && debugging($fxn . '::Not logged in or is guest user, so skip it');
            return;
        }

        // The block is hidden so don't show anything.
        if (!$this->is_visible()) {
            $debug && debugging($fxn . '::This block is not visible, so skip it');
            return;
        }

        $setuperrors = ia_mu::get_completion_setup_errors($COURSE);
        $hascapabilityoverview = \has_capability('block/integrityadvocate:overview', $this->context);
        $debug && debugging($fxn . '::Permissions check: has_capability(\'block/integrityadvocate:overview\')=' . (bool) $hascapabilityoverview);
        $debug && debugging($fxn . '::Got setup errors=' . ($setuperrors ? ia_u::var_dump($setuperrors, true) : ''));

        if ($hascapabilityoverview) {
            $this->content->footer = $this->get_footer();
        }

        if ($setuperrors) {
            if ($hascapabilityoverview) {
                foreach ($setuperrors as $err) {
                    $this->content->text .= get_string($err, \INTEGRITYADVOCATE_BLOCK_NAME) . ia_output::BRNL;
                }
            }
            $debug && debugging($fxn . '::Setup errors, so skip it');
            return;
        }

        if (!ia_mu::is_first_visible_block_of_type($this->page->blocks, $this)) {
            $debug && debugging($fxn . '::Found another_blockinstance_exists=true so refuse to show the content for this one');
            if ($hascapabilityoverview) {
                $this->content->text = get_string('error_twoblocks', \INTEGRITYADVOCATE_BLOCK_NAME);
            }
            $this->instance->visible = false;
            return;
        }

        // Check if any modules have been created.
        $exclusions = ia_mu::get_gradebook_exclusions($DB, $COURSE->id);
        $modules = ia_mu::get_modules_with_completion($COURSE->id);
        $modules = ia_mu::filter_for_visible($CFG, $modules, $USER->id, $COURSE->id, $exclusions);
        $debug && debugging($fxn . '::Modules found=' . ia_u::count_if_countable($modules));
        if (empty($modules)) {
            if ($hascapabilityoverview) {
                $this->content->text .= get_string('no_modules_config_message', \INTEGRITYADVOCATE_BLOCK_NAME);
            }
            $debug && debugging($fxn . '::No modules, so skip it');
            return;
        }

        $hascapabilityview = \has_capability('block/integrityadvocate:view', $this->context);
        $hascapabilityselfview = \has_capability('block/integrityadvocate:selfview', $this->context);

        // Check if there is any errors.
        if ($configerrors = $this->get_config_errors()) {
            $debug && debugging($fxn . '::Error: ' . ia_u::var_dump($configerrors, true));

            // Error output is visible only to instructors.
            if ($hascapabilityoverview) {
                $this->content->text .= \implode(ia_output::BRNL, $configerrors);
            }
            return;
        }

        // Figure out what context we are in so we can decide what to show for whom.
        $parentcontext = $this->context->get_parent_context();

        switch ($parentcontext->contextlevel) {
            case CONTEXT_COURSE:
                $debug && debugging($fxn . '::Context=CONTEXT_COURSE');
                switch (true) {
                    case $hascapabilityoverview:
                        $debug && debugging($fxn . '::Teacher viewing a course student profile: Show latest student info');
                        $isparticipantspage = str_contains($this->page->url, '/user/view.php?');
                        if ($isparticipantspage && ia\FeatureControl::OVERVIEW_USER_LTI) {
                            $courseid = required_param('course', PARAM_INT);
                            $targetuserid = optional_param('id', $USER->id, PARAM_INT);
                            $debug && debugging($fxn . '::This is the course-user page, so in the block show the IA proctor summary for this '
                                            . 'course-user combo: courseid=' . $courseid . '; $targetuserid=' . $targetuserid);

                            // Check the user is enrolled in this course, even if inactive.
                            if (!\is_enrolled($parentcontext, $targetuserid)) {
                                throw new \Exception('That user is not in this course');
                            }

                            // Do not show the latest status.
                            try {
                                $participant = ia_api::get_participant($this->config->apikey, $this->config->appid,
                                                $this->get_course()->id, $targetuserid, $this->instance->id);
                            } catch (ia\HttpException $e) {
                                // Ignore so that the Participants page can still render.
                                $msg = "::Get of participant info failed using appid={$this->config->appid}; courseid={$courseid}; ";
                                $msg += "targetuserid={$targetuserid}; exception=".$e->getMessage();
                                debugging($fxn . $msg);
                                $participant = null;
                            }

                            if (ia_u::is_empty($participant)) {
                                $debug && debugging($fxn . '::Got empty participant, so return empty result');
                                $this->content->text .= get_string('studentmessage', INTEGRITYADVOCATE_BLOCK_NAME);
                            } else {
                                $this->content->text .= ia_output::get_participant_summary_output($this, $participant,
                                                /* $showphoto= */ true, /* $showoverviewbutton= */ false, /* $showstatus= */ false);
                            }
                        }

                        $debug && debugging($fxn . '::Teacher viewing a course: Show the overview button and the module list.');
                        if (ia\FeatureControl::OVERVIEW_COURSE_LTI) {
                            $this->content->text .= ia_output::get_button_overview_course($this);
                        }
                        if (!$isparticipantspage && ia\FeatureControl::MODULE_LIST) {
                            // Adds to $this->context->text and $this->context->footer.
                            $this->populate_course_modulelist();
                        }
                        break;
                    case $hascapabilityselfview:
                        // Check the user is enrolled in this course, but they must be active.
                        if (!\is_role_switched($this->get_course()->id) && !\is_enrolled($parentcontext, $USER, null, true)) {
                            throw new \Exception('That user is not in this course');
                        }

                        $debug && debugging($fxn . '::Student viewing a course: show the overview button only');
                        // Do not show the latest status.
                        $this->content->text .= ia_output::get_user_summary_output($this, $USER->id);
                        break;
                }

                break;
            case CONTEXT_MODULE:
                $debug && debugging($fxn . '::Context=CONTEXT_MODULE');
                switch (true) {
                    case $hascapabilityoverview:
                        $debug && debugging($fxn . '::Teacher viewing a module: Show the overview module button AND '
                                        . 'the overview course button');
                        ia\FeatureControl::OVERVIEW_MODULE_LTI && $this->content->text .= ia_output::get_button_overview_module($this);
                        ia\FeatureControl::OVERVIEW_COURSE_LTI && $this->content->text .= ia_output::get_button_overview_course($this);
                        break;
                    case $hascapabilityview && (\is_role_switched($this->get_course()->id) ||
                    \is_enrolled($parentcontext, $USER, null, true)):
                        // This is someone in a student role.
                        switch (true) {
                            case (str_starts_with($this->page->pagetype, 'mod-scorm-')):
                                global $scorm;
                                if (!isset($scorm)) {
                                    throw new \moodle_exception('Failed to find the global $scorm variable');
                                }
                                $debug && debugging($fxn . '::Student viewing a module: Show the proctoring UI maybe');
                                // If this is the entry page for a SCORM "new window" instance, we launch the
                                // IA proctoring on the SCORM entry page.
                                if ($scorm->popup) {
                                    if ($this->page->pagetype === 'mod-scorm-view') {
                                        $this->add_proctor_js($USER, false);
                                    } else {
                                        // The SCORM popup window (mod-scorm-view) does not load any blocks or JS, so
                                        // we ignore that possibility.
                                        // Other pages should show the overview.
                                        $this->content->text .= ia_output::get_user_summary_output($this, $USER->id,
                                                        true, true, true);
                                    }
                                } else {
                                    // Else it is a SCORM "same window" instance.
                                    // The player page should show the IA procotoring UI.
                                    // Other pages like the entry page should show the overview.
                                    if ($this->page->pagetype === 'mod-scorm-player') {
                                        $this->add_proctor_js($USER, true);
                                    } else {
                                        $this->content->text .= ia_output::get_user_summary_output($this, $USER->id, true, true, true);
                                    }
                                }
                                break;
                            case(str_starts_with($this->page->pagetype, 'mod-quiz-')):
                                // If we are in a quiz, only show the JS proctoring UI if on the quiz attempt page.
                                // Other pages should show the summary.
                                if ($this->page->pagetype == 'mod-quiz-attempt' || ($this->page->pagetype == 'mod-quiz-view' &&
                                        (isset($this->config->proctorquizinfopage) && $this->config->proctorquizinfopage))) {
                                    $debug && debugging($fxn . '::Quiz:Student should see proctoring JS');
                                    $this->add_proctor_js($USER);
                                } else if ($hascapabilityselfview) {
                                    $debug && debugging($fxn . '::Quiz:Student should see summary info');
                                    $this->content->text .= ia_output::get_user_summary_output($this, $USER->id, true, true, true);
                                }
                                break;
                            default:
                                $debug && debugging($fxn . '::default:Student should see proctoring JS');
                                $this->add_proctor_js($USER);
                                break;
                        }
                        break;
                    default:
                        $debug && debugging($fxn . '::The user is not enrolled in this course, so show nothing');
                        break;
                }

                break;
            default:
                $debug && debugging($fxn . '::In some unknown context, so show nothing');
                break;
        }

        $debug && debugging($fxn . '::Done');
    }

    /**
     * Get the block footer HTML.
     *
     * @return string Footer HTML.
     */
    private function get_footer() {
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = ia_mu::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__, $this->instance->id, isset($this->config->appid) ? $this->config->appid : '']));
        if (ia\FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            return $cachedvalue;
        }

        global $CFG;
        $returnthis = '';
        $lanstring = get_string('config_blockversion', INTEGRITYADVOCATE_BLOCK_NAME);
        $returnthis .= '<div class="' . INTEGRITYADVOCATE_BLOCK_NAME . '_plugininfo" title="' . $lanstring . '">' .
                "{$lanstring} " . get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version') . ' on M' . $CFG->release . '</div>';
        $lanstring = get_string('config_appid', INTEGRITYADVOCATE_BLOCK_NAME);
        $returnthis .= '<div class="' . INTEGRITYADVOCATE_BLOCK_NAME . '_plugininfo" title="' .
                $lanstring . '">' . "{$lanstring} " . (isset($this->config->appid) ? $this->config->appid : '') . '</div>';

        if (ia\FeatureControl::CACHE && !$cache->set($cachekey, $returnthis)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $returnthis;
    }

    /**
     * Get the course this block belongs to.
     *
     * @return \stdClass The $COURSE object.
     */
    public function get_course(): \stdClass {
        global $COURSE;
        return $COURSE;
    }

    /**
     * Get the block user.
     *
     * @return \stdClass The $USER object.
     */
    public function get_user(): stdClass {
        global $USER;
        return $USER;
    }

    /**
     * Get the current block instance.
     *
     * @return \block_integrityadvocate Block instance.
     */
    public function get_instance(): \block_integrityadvocate {
        return $this->instance;
    }

    /**
     * Get the current block instance id.
     *
     * @return int Block instance id.
     */
    public function get_id(): int {
        return $this->instance->id;
    }

    /**
     * Return true if the block is configured to be visible.
     *
     * @return bool True if the block is configured to be visible.
     */
    public function is_visible(): bool {
        if (\property_exists($this->instance, 'visible') && isset($this->instance->visible) && \is_bool($this->instance->visible)) {
            return $this->instance->visible;
        }
        if (\property_exists($this, 'visible') && isset($this->visible) && \is_bool($this->visible)) {
            return $this->visible;
        }

        $parentcontext = $this->context->get_parent_context();
        return $this->visible = ia_mu::is_block_visibile($parentcontext->id, $this->context->id);
    }
}
