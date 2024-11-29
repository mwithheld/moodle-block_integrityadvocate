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
 * IntegrityAdvocate functions to interact with the IntegrityAdvocate API.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// phpcs:ignore Generic.Files.LineLength

namespace block_integrityadvocate;

use block_integrityadvocate\Api as ia_api;
use block_integrityadvocate\Utility as ia_u;
use core\check\result as moodle_checkresult;

defined('MOODLE_INTERNAL') || die;

class diagnostics_manager {
    public function do_diagnostics(int $courseid = \SITEID): array {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \debugging($fxn . '::Started with courseid=' . $courseid);

        $results = [];
        // Block has an API key.
        // Block has an app Id.
        // Course has activity completion enabled.
        // Course activity is visible.

        // IA remote side: /ping returns 'healthy'.
        $debug && \debugging($fxn . '::About to test_url_ping()');
        $results[] = self::test_url_ping();

        // IA remote side: Activity is enabled.
        // IA remote side: Activity has some rules.

        // IA remote endpoint for participantsessions works with no real credentials or params.
        // $debug && \debugging($fxn . '::About to test_url_participantsessions_activity()');
        // $results[] = self::test_url_participantsessions_activity();

        // $result[] = self::test_url_participantstatus($courseid);

        $debug && \debugging($fxn . '::About to return $result=' . ia_u::var_dump($results));
        return $results;
    }

    private function test_url_ping(): moodle_checkresult {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \debugging($fxn . '::Started');

        $returnthis = [];

        $requesturi = ia_api::ENDPOINT_PING;
        $debug && \debugging($fxn . "'::About to curl_get_unsigned({$requesturi})'");
        [$responsecode, $response, $responseinfo] = ia_api::curl_get_unsigned($requesturi);
        $debug && \debugging($fxn . '::Got GET $responsecode]' . $responsecode . '; $response=' . ia_u::var_dump($response) . '; $responseinfo=' . ia_u::var_dump($responseinfo));

        $success = \in_array($responsecode, \array_merge(ia_api::HTTP_CODE_SUCCESS, ia_api::HTTP_CODE_REDIRECT, ia_api::HTTP_CODE_CLIENTERROR), true);

        $output_check = \get_string('api_endpoint_name', INTEGRITYADVOCATE_BLOCK_NAME, $requesturi);
        if ($success && (strcmp('healthy', $response) === 0)) {
            $output_summary = $responsecode . ' ' . \get_string('diagnostics_success', INTEGRITYADVOCATE_BLOCK_NAME) .  '; ' . \clean_param($responseinfo['total_time'], PARAM_FLOAT) . ' ' . \get_string('seconds') . '; ip=' . $responseinfo['primary_ip'];
            $returnthis = new moodle_checkresult(moodle_checkresult::OK, $output_check, $output_summary);
        } else {
            $output_summary = \get_string('diagnostics_fail', INTEGRITYADVOCATE_BLOCK_NAME);
            $output_summary .= '; responseinfo=' . \clean_param(ia_u::var_dump($responseinfo), \PARAM_TEXT);
            $returnthis = new moodle_checkresult(moodle_checkresult::CRITICAL, $output_check, $output_summary);
        }

        $debug && \debugging($fxn . '::About to return returnthis=' . ia_u::var_dump($returnthis));
        return $returnthis;
    }

    // ttps://ca.integrityadvocateserver.com/api/participantsessions/activity?courseid=0&activityid=0&participantidentifier=0&limit=1&backwardsearch=true
    private function test_url_participantsessions_activity(): moodle_checkresult {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \debugging($fxn . '::Started');

        $returnthis = [];

        $requesturi = ia_api::ENDPOINT_PARTICIPANTSESSIONS_ACTIVITY;
        // All the bogus data here should work.
        $params = [
            'courseid' => -1,
            'activityid' => -1,
            'participantidentifier' => -1,
            'limit' => -1,
            'backwardsearch' => 'true',
        ];
        // Random bogus data here too.
        $appid = '3e7f91c8-9a3d-4e6b-bf05-72d48a1c9d7e';
        $apikey = 'HBgGDFC3X5eHXALg1p92/1GI1JLmiCtJsrdE5tTQVvU=';
        $result = ia_api::get($requesturi, $apikey, $appid, $params);
        $debug && \debugging($fxn . '::Got GET result=' . ia_u::var_dump($result));

        $output_check = 'endpoint_participantsessions_activity_name OK';
        $returnthis = new moodle_checkresult(moodle_checkresult::OK, $output_check, \get_string('endpoint_participantsessions_activity_name', INTEGRITYADVOCATE_BLOCK_NAME));

        $debug && \debugging($fxn . '::About to return returnthis=' . ia_u::var_dump($returnthis));
        return $returnthis;
    }

    // private function test_url_participantstatus(int $courseid = \SITEID): array {
    //     $debug = true;
    //     $fxn = __CLASS__ . '::' . __FUNCTION__;
    //     $debug && \debugging($fxn . '::Started with courseid=' . $courseid);

    //     $requesturi = ia_api::ENDPOINT_PARTICIPANT_STATUS;
    //     $input = self::get(self::ENDPOINT_PARTICIPANT_STATUS, $blockinstance->config->apikey, $blockinstance->config->appid, $params);
    //     $debug && \debugging($fxn . '::Got participantstatus input=' . ia_u::var_dump($input));

    //     return [];
    // }
}
