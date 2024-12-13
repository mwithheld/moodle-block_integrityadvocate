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

/**
 * IntegrityAdvocate Diagnostics handler.
 */
class diagnostics_manager {
    /**
     * Run diagnostics and return results.
     *
     * @param int $courseid CourseID to put on requests.
     * @return array Check results.
     */
    public function do_diagnostics(int $courseid = \SITEID): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \debugging($fxn . '::Started with courseid=' . $courseid);

        $results = [];
        // Block has an API key.
        // Block has an app Id.
        // Course has activity completion enabled.
        // Course activity is visible.
        // IA remote side: Activity is enabled.
        // IA remote side: Activity has some rules.

        // IA remote side: /ping returns 'healthy'.
        $debug && \debugging($fxn . '::About to test_url_ping()');
        $results[] = self::test_url_ping();

        // IA remote endpoint for participantsessions works with no real credentials or params.
        $debug && \debugging($fxn . '::About to test_url_participantsessions_activity()');
        $results[] = self::test_url_participantsessions_activity();

        // $result[] = self::test_url_participantstatus($courseid);

        $debug && \debugging($fxn . '::About to return $result=' . ia_u::var_dump($results));
        return $results;
    }

    /**
     * Test the IA API endpoint ping.
     * @return moodle_checkresult Check result.
     */
    private function test_url_ping(): moodle_checkresult {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \debugging($fxn . '::Started');

        $requesturi = ia_api::ENDPOINT_PING;
        $debug && \debugging($fxn . "'::About to curl_get_unsigned({$requesturi})'");
        [$responsecode, $response, $responseinfo] = ia_api::curl_get_unsigned($requesturi);
        $debug && \debugging($fxn . '::Got GET $responsecode]' . $responsecode
            . '; $response=' . ia_u::var_dump($response, true) . '; $responseinfo=' . ia_u::var_dump($responseinfo));

        $responsecodeisok = ia_api::http_response_code_is_acceptable($responsecode);
        $debug && \debugging($fxn . '::Got $responsecodeisok=' . ia_u::var_dump($responsecodeisok));

        $responsebodyisok = strcmp('healthy', $response) === 0;
        $debug && \debugging($fxn . '::Got $responsebodyisok=' . ia_u::var_dump($responsebodyisok));

        $outputcheck = \get_string('api_endpoint_name', INTEGRITYADVOCATE_BLOCK_NAME, $requesturi);
        if ($responsecodeisok && $responsebodyisok) {
            $outputsummary = $responsecode . ' ' . \get_string('diagnostics_success', INTEGRITYADVOCATE_BLOCK_NAME) .  '; '
                . \clean_param($responseinfo['total_time'], PARAM_FLOAT) . ' ' . \get_string('seconds') . '; ip=' . $responseinfo['primary_ip'];
            $returnthis = new moodle_checkresult(moodle_checkresult::OK, $outputcheck, $outputsummary);
        } else {
            $outputsummary = \get_string('diagnostics_fail', INTEGRITYADVOCATE_BLOCK_NAME);
            if (!$responsecodeisok) {
                $outputsummary .= '; ' . \get_string('bad_response_code', INTEGRITYADVOCATE_BLOCK_NAME);
            }
            if (!$responsebodyisok) {
                $outputsummary .= '; ' . \get_string('bad_response_body', INTEGRITYADVOCATE_BLOCK_NAME);
            }
            $outputsummary .= '; responseinfo=' . \clean_param(ia_u::var_dump($responseinfo), \PARAM_TEXT);
            $returnthis = new moodle_checkresult(moodle_checkresult::CRITICAL, $outputcheck, $outputsummary);
        }

        $debug && \debugging($fxn . '::About to return returnthis=' . ia_u::var_dump($returnthis));
        return $returnthis;
    }

    /**
     * Test the IA API endpoint participantsessions_activity.
     * @return moodle_checkresult Check result.
     */
    private function test_url_participantsessions_activity(): moodle_checkresult {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \debugging($fxn . '::Started');

        // All the bogus data here should work.
        $params = [
            'courseid' => -1,
            'activityid' => -1,
            'participantidentifier' => -1,
            'limit' => -1,
            'backwardsearch' => 'true',
        ];

        $requesturi = ia_api::ENDPOINT_PARTICIPANTSESSIONS_ACTIVITY;
        $debug && \debugging($fxn . "'::About to curl_get_unsigned({$requesturi})'");
        [$responsecode, $response, $responseinfo] = ia_api::curl_get_unsigned($requesturi, $params);
        $debug && \debugging($fxn . '::Got GET $responsecode]' . $responsecode
            . '; $response=' . \htmlentities(ia_u::var_dump($response)) . '; $responseinfo=' . ia_u::var_dump($responseinfo));

        $responsecodeisok = ia_api::http_response_code_is_acceptable($responsecode);
        $debug && \debugging($fxn . '::Got $responsecodeisok=' . ia_u::var_dump($responsecodeisok));

        $responsebodyisok = strcmp('{}', $response) === 0;
        $debug && \debugging($fxn . '::Got $responsebodyisok=' . ia_u::var_dump($responsebodyisok));

        $returnthis = new moodle_checkresult(moodle_checkresult::CRITICAL, '$outputcheck here', '$outputsummary here');

        $outputcheck = \get_string('api_endpoint_name', INTEGRITYADVOCATE_BLOCK_NAME, $requesturi);
        if ($responsecodeisok && $responsebodyisok) {
            $outputsummary = $responsecode . ' ' . \get_string('diagnostics_success', INTEGRITYADVOCATE_BLOCK_NAME) .  '; '
                . \clean_param($responseinfo['total_time'], PARAM_FLOAT) . ' ' . \get_string('seconds') . '; ip=' . $responseinfo['primary_ip'];
            $returnthis = new moodle_checkresult(moodle_checkresult::OK, $outputcheck, $outputsummary);
        } else {
            $outputsummary = \get_string('diagnostics_fail', INTEGRITYADVOCATE_BLOCK_NAME);
            if (!$responsecodeisok) {
                $outputsummary .= '; ' . \get_string('bad_response_code', INTEGRITYADVOCATE_BLOCK_NAME);
            }
            if (!$responsebodyisok) {
                $outputsummary .= '; ' . \get_string('bad_response_body', INTEGRITYADVOCATE_BLOCK_NAME);
            }
            $outputsummary .= '; responseinfo=' . \clean_param(ia_u::var_dump($responseinfo), \PARAM_TEXT);
            $returnthis = new moodle_checkresult(moodle_checkresult::CRITICAL, $outputcheck, $outputsummary);
        }

        $debug && \debugging($fxn . '::About to return returnthis=' . ia_u::var_dump($returnthis));
        return $returnthis;
    }

    /**
     * Test the IA API endpoint participantsessions_activity.
     * @return moodle_checkresult Check result.
     */
    private function test_url_participantstatus(): void {
        throw new \Exception('Not implemented');
    }
}
