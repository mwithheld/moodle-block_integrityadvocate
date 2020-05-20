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
use block_integrityadvocate\Output as ia_output;
use block_integrityadvocate\Utility as ia_u;

/**
 * Use a modified Course Participants table to show IntegrityAdvocate summary data
 * b/c we want all the functionality but slightly different columns.
 */
class ParticipantsTable extends \core_user\participants_table {

    /**
     * Set up the table object from the provided data.
     * The goal here was to use parent logic but change it for our purposes.
     *
     * @param int $courseid
     * @param int|false $currentgroup False if groups not used, int if groups used, 0 all groups, USERSWITHOUTGROUP for no group
     * @param int $accesssince The time the user last accessed the site
     * @param int $roleid The role we are including, 0 means all enrolled users
     * @param int $enrolid The applied filter for the user enrolment ID.
     * @param int $status The applied filter for the user's enrolment status.
     * @param string|array $search The search string(s)
     * @param bool $bulkoperations Is the user allowed to perform bulk operations?
     * @param bool $selectall Has the user selected all users on the page?
     */
    public function __construct(int $courseid, $currentgroup, int $accesssince, int $roleid, int $enrolid, int $status, $search, bool $bulkoperations, bool $selectall) {
        parent::__construct($courseid, $currentgroup, $accesssince, $roleid, $enrolid, $status, $search, $bulkoperations, $selectall);

        $this->attributes['class'] .= ' datatable';

        // Add the custom IAData column.
        $columnsflipped = array_flip($this->columns);
        // HTML for the basic user IA info.
        $columnsflipped[] = 'iadata';
        // HTML for the users photo.
        $columnsflipped[] = 'iaphoto';
        // Why does this not need flipping back, Moodle?  Dunno, but it borks otherwise.
        $this->columns = $columnsflipped;

        // Do not strip tags from these colums (i.e. do not pass through the s() function).
        $this->column_nostrip = array('iadata');

        $this->headers[] = \get_string('column_iadata', \INTEGRITYADVOCATE_BLOCKNAME);
        $this->headers[] = \get_string('column_iaphoto', \INTEGRITYADVOCATE_BLOCKNAME);

        $this->prefs['collapse']['status'] = true;
        $this->define_columns($this->columns);

        // Prevent this columns from getting squished.
        $this->column_style('iadata', 'min-width', '20%');

        // The email field was dominating the display, so calm it down.
        $this->column_style('email', 'max-width', '200px');
        $this->column_style('email', 'word-wrap', 'break-word');

        // Hide columns we won't use.
        $this->column_style('roles', 'display', 'none');
        $this->column_style('groups', 'display', 'none');
        $this->column_style('status', 'display', 'none');
    }

    /**
     * Generate this column.
     *
     * @param \stdClass $data
     * @return string The IA photo else empty string
     */
    public function col_iaphoto(\stdClass $data): string {
        return isset($data->iaphoto) ? $data->iaphoto : '';
    }

    /**
     * Generate this column.
     *
     * @param \stdClass $data
     * @return string The IA data else empty string
     */
    public function col_iadata(\stdClass $data): string {
        return isset($data->iadata) ? $data->iadata : '';
    }

    /**
     * This is the beginning half of the parent class out() function.
     * So that we can populate data into the class structure and work with it
     * before the table is output to the end-user.
     *
     * @param int $perpage How many items per page to show.
     */
    public function setup_and_populate(int $perpage) {
        $this->setup();
        $this->query_db($perpage, $useinitialsbar = true);
    }

    /**
     * This is the ending half of the parent class out() function.
     * It outputs the table HTML.
     */
    public function out_end() {
        $this->build_table();
        $this->close_recordset();
        $this->finish_output();
    }

    /**
     * Populate the user basic output and photo for all users in the course.
     *
     * @param stdClass $blockinstance Instance of block_integrityadvocate.
     */
    public function populate_from_blockinstance(\block_integrityadvocate $blockinstance) {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $blockinstance->instance->id=' . $blockinstance->instance->id);

        // Sanity check.
        if (ia_u::is_empty($blockinstance) || !is_numeric($courseid = $blockinstance->get_course()->id)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::Started with $blockinstance->instance->id=' . $blockinstance->instance->id);
            ia_mu::log($fxn . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        $participants = ia_api::get_participants($blockinstance->config->apikey, $blockinstance->config->appid, $courseid);
        $debug && ia_mu::log($fxn . '::Got count($participants)=' . (is_countable($participants) ? count($participants) : 0));

        if (ia_u::is_empty($participants)) {
            return;
        }

        // This class property $participanttable->rawdata already contains a row for each user in this course, with key=userid.
        // Iterate over the returned user data and put it into correct user row in the output table.
        // In addition to the built-in columns (name, email, lastaccess), ...
        // ATM there are two custom columns: iadata and iaphoto.
        foreach ($participants as $p) {
            $debug && ia_mu::log($fxn . "::Looking at participant \$p={$p->participantidentifier}");
            // Make sure the participant belongs to this course.
            if ((intval($p->courseid) !== intval($courseid)) || !\is_enrolled($blockinstance->context->get_parent_context(), $p->participantidentifier)) {
                $debug && ia_mu::log($fxn . "::Skipping user {$p->participantidentifier} is no longer enrolled in this context with courseid={$courseid}");
                continue;
            }

            // Make sure there there is a matching user row in the table.
            if (!isset($this->rawdata[$p->participantidentifier]) && !empty($this->rawdata[$p->participantidentifier]) || !isset($this->rawdata[$p->participantidentifier]->id) || $this->rawdata[$p->participantidentifier]->id !=
                    $p->participantidentifier) {
                $debug && ia_mu::log($fxn . "::Skipping user {$p->participantidentifier} bc there is no user row in the table");
                continue;
            }

            // Participant basic info - skip the photo b/c it is in a different column.
            $debug && ia_mu::log($fxn . "::About to get_participant_basic_output with \$p={$p->participantidentifier}");
            $this->rawdata[$p->participantidentifier]->iadata = ia_output::get_participant_basic_output($blockinstance, $p, true, false);

            // Participant photo.
            $this->rawdata[$p->participantidentifier]->iaphoto = ia_output::get_participant_photo_output($p);
        }

        // Disabled on purpose: $debug && ia_mu::log($fxn . "::About to return; \$this->rawdata=" . var_export($this->rawdata, true));.
        $debug && ia_mu::log($fxn . '::About to return');
    }

}
