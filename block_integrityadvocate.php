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
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/blocks/integrityadvocate/lib.php');

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
        $this->title = get_string('config_default_title', INTEGRITYADVOCATE_BLOCKNAME);
    }

    /**
     *  we have global config/settings data
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * Controls the block title based on instance configuration
     *
     * @return bool
     */
    public function specialization() {
        if (isset($this->config->progressTitle) && trim($this->config->progressTitle) != '') {
            $this->title = format_string($this->config->progressTitle);
        }
    }

    /**
     * Controls whether multiple instances of the block are allowed on a page
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Controls whether the block is configurable
     *
     * @return bool
     */
    public function instance_allow_config() {
        return true;
    }

    /**
     * Defines where the block can be added
     *
     * @return array
     */
    public function applicable_formats() {
        return array(
            'course-view' => true,
            'site' => false,
            'mod' => true,
            'my' => false
        );
    }

    /**
     * Creates the blocks main content
     *
     * @return string
     */
    public function get_content() {
        global $USER, $COURSE, $PAGE, $DB, $CFG;
        $debug = false;
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started with courseid=' . $COURSE->id . '; userid=' . $USER->id . '; username=' . $USER->username);

        if (is_object($this->content) && isset($this->content->text) && !empty(trim($this->content->text))) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Content has already been generated, so do not generate it again: \n" . print_r($this->content, true));
            return;
        }
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Done setting up $this->content');

        // Guests do not have any progress. Don't show them the block.
        if (!isloggedin() or isguestuser()) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Not logged in or is guest user, so skip it');
            return;
        }

        $setuperrors = block_integrityadvocate_completion_setup_errors($COURSE);
        $hasoverviewcapability = has_capability('block/integrityadvocate:overview', $this->context);
        if ($debug) {
            block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Permissions check: has_capability(\'block/integrityadvocate:overview\')=' . print_r($hasoverviewcapability, true));
            block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got setup errors=' . print_r($setuperrors, true));
        }
        if ($setuperrors && $hasoverviewcapability) {
            foreach ($setuperrors as $err) {
                $this->content->text .= get_string($err, INTEGRITYADVOCATE_BLOCKNAME) . "<br />\n";
            }
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Setup errors, so skip it');
            return;
        }

        // Check if any activities/resources have been created.
        $exclusions = block_integrityadvocate_exclusions($DB, $COURSE->id);
        $activities = block_integrityadvocate_get_activities_with_completion($COURSE->id, $this->config);
        $activities = block_integrityadvocate_filter_visibility($CFG, $activities, $USER->id, $COURSE->id, $exclusions);
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Activities found=' . count($activities));
        if (empty($activities)) {
            if ($hasoverviewcapability) {
                $this->content->text .= get_string('no_activities_config_message', INTEGRITYADVOCATE_BLOCKNAME);
            }
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::No activities, so skip it');
            return;
        }

        $blockinstancesonpage = array($this->instance->id);

        // ATM we *never* need the module JS, so this stays false.
        $needmodulejs = false;

        $hasselfviewcapability = has_capability('block/integrityadvocate:selfview', $this->context);

        // Check if there is any errors.
        if ($configerrors = block_integrityadvocate_ia_config_errors($this)) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Error: ' . print_r($configerrors, true));

            // Error output is visible only to instructors.
            if ($hasoverviewcapability) {
                $this->content->text .= implode("<br />\n", $configerrors);
                return;
            }
        }

        // Figure out what context we are in so we can decide what to show for whom.
        $blockcontext = $this->context;
        $parentcontext = $blockcontext->get_parent_context();
        switch ($parentcontext->contextlevel) {
            case CONTEXT_COURSE:
                $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Context=CONTEXT_COURSE');
                switch (true) {
                    case $hasoverviewcapability:
                        if (stripos($PAGE->url, '/user/view.php?') > 0) {
                            $courseid = required_param('course', PARAM_INT);
                            $userid = optional_param('id', $USER->id, PARAM_INT);
                            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::This is the course-user page, so in the block show the IA proctor summary for the specified courseid=' . $courseid . '; userid=' . $userid);
                            $this->content->text .= $this->get_summary_output($courseid, $userid);
                        }

                        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Teachers should see the overview button');
                        $this->content->text .= block_integrityadvocate_get_overview_course_button($this->instance->id, $COURSE->id);
                        break;
                    case $hasselfviewcapability:
                        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Student should see their own summary IA results');
                        $this->content->text .= $this->get_summary_output($COURSE->id, $USER->id);
                        break;
                }

                break;
            case CONTEXT_MODULE:
                $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Context=CONTEXT_MODULE');
                switch (true) {
                    case $hasoverviewcapability:
                        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Teacher should see the overview button');
                        $this->content->text .= block_integrityadvocate_get_overview_course_button($this->instance->id, $COURSE->id);
                        break;
                    default:
                        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Student should see proctoring JS');
                        $this->content->text .= $this->get_proctor_output($DB, $COURSE, $USER);
                }

                break;
            default:
                $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::In some unknown context, so show nothing');
                return;
        }

        if ($needmodulejs) {
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::About to organize access to JS.');

            // Organize access to JS.
            $jsmodule = array(
                'name' => INTEGRITYADVOCATE_BLOCKNAME,
                'fullpath' => '/blocks/integrityadvocate/module.js',
                'requires' => array(),
                'strings' => array(),
            );
            $arguments = array($blockinstancesonpage, array($USER->id));
            $this->page->requires->js_init_call('M.block_integrityadvocate.init', $arguments, false, $jsmodule);
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Done.');
        }
    }

    /**
     * Build the HTML output for the "summary" output - the summary info for all users in the course
     *
     * @param int $courseid Course to get IA participant info for
     * @param int $userid User to get IA info for
     * @return string HTML to output
     */
    public function get_summary_output($courseid, $userid) {
        $debug = false;
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started with $courseid=' . $courseid . '; $userid=' . $userid);

        // Content to return.
        $out = '';

        $hasoverviewcapability = has_capability('block/integrityadvocate:overview', $this->context);

        $useriaresults = block_integrityadvocate_get_course_user_ia_data($courseid, $userid);
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got count($useriadata)=' . count($useriaresults));
        // Warning: Huge object output: $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got $useriadata=' . print_r($useriadata, true));

        if (empty($useriaresults)) {
            return $out;
        }

        // If we get back a string we got an error, so quit.
        if (is_string($useriaresults)) {
            if ($hasoverviewcapability) {
                $out .= $useriaresults;
            }
            // Error output is visible only to instructors.
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Error: ' . print_r($useriaresults, true));
            return $out;
        }

        foreach ($useriaresults as $a) {
            $blockinstanceid = $a['activity']['block_integrityadvocate_instance']['id'];
            $participantdata = $a['ia_participant_data'];

            // Display summary.
            $summaryoutput = block_integrityadvocate_get_participant_summary_output($participantdata, $blockinstanceid, $courseid, $userid, false);
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Got $summaryoutput=' . print_r($summaryoutput, true));
            $out .= $summaryoutput;
        }

        return $out;
    }

    /**
     * Build content to show to students and returns it.  ATM if there are no errors, nothing is visible.
     * Side effects: Adds JS for video monitoring popup to the page.
     *
     * @param moodle_database $db Moodle DB object
     * @param stdClass $course Current course object; needed so we can identify this user to the IA API
     * @param stdClass $user Current user object; needed so we can identify this user to the IA API
     * @return string HTML to output
     */
    public function get_proctor_output(moodle_database $db, stdClass $course, stdClass $user) {
        $debug = false;
        // Set to true to disable the IA proctor JS.
        $debugnoiaproctoroutput = false;
        $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::Started');

        // Content to return.
        $out = '';

        $blockcontext = $this->context;
        $hasoverviewcapability = has_capability('block/integrityadvocate:overview', $blockcontext);
        $configerrors = block_integrityadvocate_ia_config_errors($this);
        if ($configerrors) {
            // Only teachers should see config errors.
            if ($hasoverviewcapability) {
                $out = $configerrors;
            }
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::' . $configerrors);
            return $out;
        }

        try {
            $sql = "SELECT  ue.id, max(ue.timestart)
                    FROM    {role_assignments} ra
                    JOIN    {context} ctx ON ra.contextid = ctx.id
                    JOIN    {course} c ON ctx.instanceid = c.id
                    JOIN    {enrol} e ON c.id = e.courseid
                    JOIN    {user} u ON ra.userid = u.id
                    JOIN    {user_enrolments} ue ON e.id = ue.enrolid
                    WHERE   ctx.contextlevel = :contextlevel
                    AND     ue.userid = :userid
                    AND     ctx.instanceid = :courseid
                    AND     u.deleted=0 AND u.suspended=0
                    GROUP BY ue.id";
            // CONTEXT_COURSE=50.
            $userenrolment = $db->get_record_sql($sql, array('contextlevel' => CONTEXT_COURSE, 'userid' => $user->id, 'courseid' => $course->id), IGNORE_MULTIPLE);
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::user={$user->id}; courseid={$course->id}; Got userenrolment from DB=" . print_r($userenrolment, true));

            if (empty($userenrolment) || !isset($userenrolment->id)) {
                $error = get_string('error_notenrolled', INTEGRITYADVOCATE_BLOCKNAME);
                // Teachers and students can see this error.
                $out = $error;
                $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::user={$user->id}; courseid={$course->id}: error={$error}");
                return $out;
            }

            $modulecontext = $blockcontext->get_parent_context();
            // Warning: Disabled on purpose: $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Got modulecontext=" . print_r($modulecontext, true));.
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Got modulecontext->id=" . print_r($modulecontext->id, true));

            // The moodle_url class stores params non-urlencoded but outputs them encoded.
            $url = new moodle_url(INTEGRITYADVOCATE_BASEURL . '/Integrity',
                    array(
                'appid' => $this->config->appid,
                'participantid' => block_integrityadvocate_encode_useridentifier($modulecontext, $user->id),
                'participantfirstname' => $user->firstname,
                'participantlastname' => $user->lastname,
                'participantemail' => $user->email,
                    // Disabled on purpose: 'proctorname' => 'sampleproctorname', /* This does not do anything with APIv2; s/b fixed in future API */.
                    )
            );
            $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::Built url={$url}");

            if ($debugnoiaproctoroutput) {
                $this->page->requires->js_init_call('alert("IntegrityAdvocate Proctor block JS output would occur here with url=' . $url . ' if not suppressed")');
            } else {
                // Pass the URL w/o urlencoding.
                $debug && block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . '::About to require->js(' . $url->out(false) . ')');
                $this->page->requires->js($url);
            }

            /*
             * Development / testing code
              //$this->page->requires->js_init_code("jQuery.getScript('$url');", true);
              // Test JS injection.
              global $CFG;
              $encodeUrlComponentJSEquivalent = strtr(rawurlencode($encodeUrlComponentJSEquivalent), array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')'));
              $this->page->requires->js("{$CFG->wwwroot}/blocks/integrityadvocate/helloworld.js?ia_url=". $encodeUrlComponentJSEquivalent, true);
             */
        } catch (Exception $e) {
            block_integrityadvocate_log(__FILE__ . '::' . __FUNCTION__ . "::user={$user->id}; courseid={$course->id}");
            // Some other error happened - show it to anyone.
            $out = $e->getMessage();
        }

        return $out;
    }

}
