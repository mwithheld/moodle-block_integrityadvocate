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
 * IntegrityAdvocate class to represent a single IA participant session flag.
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

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__DIR__) . '/lib.php');

/**
 * Generate fake data for testing this block.
 */
class MockData {

    /**
     * Return pre-set data for each endpoint, loaded from tests/fixtures.
     *
     * @param string $endpoint An Api::endpoint value.
     * @param string $apikey Ignored.
     * @param string $appid Ignored.
     * @param array $params Ignored.
     * @return mixed Pre-set data objects for each endpoint
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public static function get(string $endpoint, string $apikey, string $appid, array $params) {
        $debug = true;
        $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . "::Started with \$endpoint={$endpoint}; \$params=" . ($params ? var_export($params, true) : ''));

        switch ($endpoint) {
            case ia_api::ENDPOINT_PARTICIPANT:
                // Get the test participant.
                return self::get_participant(845798, $params['courseid'], $params['participantidentifier']);
                break;
            case ia_api::ENDPOINT_PARTICIPANTS:
                // Get the test participants.
                throw new \Exception('Not implemented');
                break;
            default:
                throw new \InvalidArgumentException("Unhandled endpoint={$endpoint}");
        }
    }

    /**
     * Get the test participant data as an Participant object, with these values overridden:
     * - Participant->participantidentifier = userid
     * - Participant->courseid = courseid
     * - Participant->sessions->activityid = CMID
     *
     * @param int $userid
     * @return mixed False if not found; Else the pre-set Participant object.
     */
    private static function get_participant(int $userid, int $overridcourseid, int $overrideuserid) {
        $debug = true;
        $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . "::Started with \$userid={$userid}; \$overridcourseid={$overridcourseid}; \$overrideuserid={$overrideuserid}");

        $participantstdclass = json_decode(self::get_participant_json($userid));
        $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . "::Got \$participantstdclass=" . var_export($participantstdclass, true));

        // Set the Participant->courseid to the current course so it can show up in the participants table etc.
        $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . "::About to set_participant_params('courseid' => {$overridcourseid})");
        self::set_participant_params($participantstdclass, array('courseid' => $overridcourseid));

        // Set the Participant->participantidentifier to the target userid.
        $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . "::About to set_participant_params('participantidentifier' => {$overrideuserid})");
        self::set_participant_params($participantstdclass, array('participantidentifier' => $overrideuserid));

        // Set the sessions' activityid to the current moduleid.
        global $INTEGRITYADVOCATE_MOCK_CMID;
        if ($INTEGRITYADVOCATE_MOCK_CMID) {
            $debug && ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . "::About to set_session_params('activityid' => " . $INTEGRITYADVOCATE_MOCK_CMID . ')');
            self::set_session_params($participantstdclass, array('activityid' => $INTEGRITYADVOCATE_MOCK_CMID));
        }

        if (ia_u::is_empty($participantstdclass)) {
            return false;
        }

        return $participantstdclass;
    }

    private static function get_participant_json(int $userid): string {
        global $CFG;
        return self::get_json_file(dirname(__DIR__) . \clean_param('/tests/fixtures/' . "participant{$userid}.json", PARAM_PATH));
    }

    private static function get_json_file(string $filepath): string {
        global $CFG;
        $maxfilesizebytes = 1000000;

        // Sanity check.
        if (empty($filepath) || stripos($filepath, $CFG->dirroot) === false || filesize($filepath) > $maxfilesizebytes) {
            $msg = 'Input params are invalid';
            ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . "::Started with \$filepath={$filepath}");
            ia_mu::log(__CLASS__ . '::' . __FUNCTION__ . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        $jsonfilecontents = file_get_contents($filepath);
        if ($jsonfilecontents === false || json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to load test data file');
        }

        return $jsonfilecontents;
    }

    private static function set_participant_params(object &$participantstdclass, array $params) {
        foreach ($params as $key => $val) {
            if (!property_exists($participantstdclass, $key)) {
                throw new \InvalidArgumentException("Invalid participant property {$key}");
            }
            $participantstdclass->$key = $val;
        }
    }

    private static function set_session_params(object &$participantstdclass, array $params) {
        foreach ($participantstdclass->sessions as $s) {
            foreach ($params as $key => $val) {
                if (!property_exists($s, $key)) {
                    throw new \InvalidArgumentException("Invalid session property {$key}");
                }
                $s->$key = $val;
            }
        }
    }

}
