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
    public function populate_from_blockinstance(\block_integrityadvocate $blockinstance, int $page) {
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

        // I am choosing to *not* get all participants from the IA API because...
        // We only get max 10 results per query in an order that does not match the $this->rawdata list.
        // And unenrolled users remain in the IA-side participants list, so...
        // It is slow to get all pages of results.
        // And I need the session data for each user anyway, which is not included in the GET api/course/{courseid}/participants data.

        $perpage = $this->pagesize;
        $offset = $page * $perpage;
        $sliceofusers = array_slice($this->rawdata, $offset, $perpage);
        if (ia_u::is_empty($sliceofusers)) {
            return;
        }

        // Use Guzzle for performance via async parallel GET requests.
        // Ref https://blog.programster.org/php-async-curl-requests.
        // Ref http://docs.guzzlephp.org/en/stable/quickstart.html.
        require_once(dirname(__DIR__) . '/vendor/autoload.php');
        $requestapiurl = INTEGRITYADVOCATE_BASEURL . INTEGRITYADVOCATE_API_PATH . ia_api::ENDPOINT_PARTICIPANT;
        $client = new \GuzzleHttp\Client([
            'base_uri' => $requestapiurl, // Base URI is used with relative requests
            'timeout' => 30.0, // You can set any number of default request options.
        ]);

        $requesttimestamp = time();
        $microtime = explode(' ', microtime());
        $nonce = $microtime[1] . substr($microtime[0], 2, 6);
        $requestsignature = ia_api::get_request_signature($requestapiurl, 'GET', $requesttimestamp, $nonce, $blockinstance->config->apikey, $appid = $blockinstance->config->appid);
        $authheader = 'amx ' . $appid . ':' . $requestsignature . ':' . $nonce . ':' . $requesttimestamp;

        $promises = array();

        foreach ($sliceofusers as $u) {
            $promise = $client->getAsync($requestapiurl, [
                'headers' => [
                    'Authorization' => $authheader,
                ],
                'query' => ['participantidentifier' => $u->id, 'courseid' => $courseid],
            ]);
            $promise->then(function ($response) use ($blockinstance) {
                $debug && ia_mu::log($fxn . '::Then started with response=' . ia_u::var_dump($response, true));
                if (ia_u::is_empty($response) || $response->getStatusCode() !== 200 || ia_u::is_empty($body = $response->getBody())) {
                    $debug && ia_mu::log($fxn . '::Invalid response so skipping');
                    return;
                }
                $responseparsed = json_decode($body);
                if (ia_u::is_empty($responseparsed) && json_last_error() === JSON_ERROR_NONE) {
                    throw new Exception('Failed to json_decode');
                }

                $participant = ia_api::parse_participant($responseparsed);
                if (ia_u::is_empty($participant) || !isset($participant->participantidentifier)) {
                    $debug && ia_mu::log($fxn . '::Empty participant');
                    return;
                }

                $this->rawdata[$participant->participantidentifier]->iadata = ia_output::get_participant_basic_output($blockinstance, $participant, true, false);

                // Participant photo.
                $this->rawdata[$participant->participantidentifier]->iaphoto = ia_output::get_participant_photo_output($participant);
            });
            $promises[] = $promise;
        }

        foreach ($promises as $promise) {
            try {
                $promise->wait();
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                // Ignore 400-level errors.
                // Ref https://stackoverflow.com/a/30957410.
                continue;
            }
        }

        // Disabled on purpose: $debug && ia_mu::log($fxn . "::About to return; \$this->rawdata=" . ia_u::var_dump($this->rawdata, true));.
        $debug && ia_mu::log($fxn . '::About to return');
    }

}
