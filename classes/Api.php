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

namespace block_integrityadvocate;

use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Participant as ia_participant;
use block_integrityadvocate\Status as ia_status;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

/**
 * Functions to interact with the IntegrityAdvocate API.
 */
class Api {

    /** @var string URI to close the remote IA session */
    const ENDPOINT_CLOSE_SESSION = '/participants/endsession';

    /** @var string URI to get participant info */
    const ENDPOINT_PARTICIPANT = '/participant';

    /** @var string URI to get course info */
    const ENDPOINT_PARTICIPANTS = '/course/courseid/participants';

    /** @var string URI to get course info */
    const ENDPOINT_PARTICIPANTSESSIONS = '/course/courseid/participantsessions';

    /** @var [string] List of valid endpoints so we can validate calls. */
    const ENDPOINTS = array(self::ENDPOINT_PARTICIPANT, self::ENDPOINT_PARTICIPANTS, self::ENDPOINT_PARTICIPANTSESSIONS);

    /** @var int The API returns 10 results max per call by default, but our UI shows 20 users per page.  Set the number we want per UI page here. Ref https://integrityadvocate.com/developers. */
    const RESULTS_PERPAGE = 20;

    /** @var int In case of errors, these params limit the recursion to some reasonable maximum. */
    const RECURSEMAX = 1000 / self::RESULTS_PERPAGE;

    /** @var int Consider recursion failed after this time.  In seconds = 5 minutes. */
    const RECURSION_TIMEOUT = 5 * 60;

    /**
     * Attempt to close the remote IA proctoring session.  404=failed to find the session.
     *
     * @param string $appid The AppId to get data for
     * @param int $courseid The course id
     * @param int $moduleid The module id
     * @param int $userid The user id
     * @return bool true if the remote API close says it succeeded; else false
     */
    public static function close_remote_session(string $appid, int $courseid, int $moduleid, int $userid): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$appid={$appid}; \$courseid={$courseid}; \$moduleid={$moduleid}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (!ia_u::is_guid($appid)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // Do not cache these requests.
        $curl = new \curl();
        $curl->setopt(array(
            'CURLOPT_CERTINFO' => 1,
            'CURLOPT_FOLLOWLOCATION' => 1,
            'CURLOPT_MAXREDIRS' => 5,
            'CURLOPT_HEADER' => 0
        ));
        $url = INTEGRITYADVOCATE_BASEURL . self::ENDPOINT_CLOSE_SESSION . '?' .
                'appid=' . urlencode($appid) .
                '&participantidentifier=' . $userid .
                '&courseid=' . $courseid .
                '&activityid=' . $moduleid;
        $response = $curl->get($url);
        $responsecode = $curl->get_info('http_code');
        $debug && ia_mu::log($fxn . '::Sent url=' . var_export($url, true) . '; http_code=' . var_export($responsecode, true) . '; response body=' . var_export($response, true));

        return intval($responsecode) < 400;
    }

    /**
     * Interact with the IA-side API to get results.
     *
     * @param string $endpoint One of the self::ENDPOINT* constants.
     * @param string $apikey The API Key to get data for
     * @param string $appid The AppId to get data for
     * @param array<key=val> $params API params per the URL above.  e.g. array('participantidentifier'=>$user_identifier).
     * @return mixed The JSON-decoded curl response body - see json_decode() return values.
     */
    private static function get(string $endpoint, string $apikey, string $appid, array $params = array()) {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$endpointpath={$endpoint}; \$apikey={$apikey}; \$appid={$appid}; \$params=" . ia_u::var_dump($params, true);
        $debug && ia_mu::log($debugvars);

        // If the block is not configured yet, simply return empty result.
        if (empty($apikey) || empty($appid)) {
            return array();
        }

        // Sanity check.
        if (!str_starts_with($endpoint, '/') || !ia_mu::is_base64($apikey) || !ia_u::is_guid($appid) || !is_array($params)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // Make sure the required params are present, there's no extra params, and param types are valid.
        self::validate_endpoint_params($endpoint, $params);

        // For the Participants and ParicipantSessions endpoints, add the remaining part of the URL.
        if ($endpoint === self::ENDPOINT_PARTICIPANTS || $endpoint === self::ENDPOINT_PARTICIPANTSESSIONS) {
            $endpoint = str_replace('courseid', $params['courseid'], $endpoint);
            unset($params['courseid']);
        }

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'perrequest');
        $cachekey = ia_mu::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . '_' . $endpoint . $appid . json_encode($params, JSON_PARTIAL_OUTPUT_ON_ERROR));

        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && ia_mu::log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        // Set up request variables to get IA participant info.
        // Ref API docs at https://integrityadvocate.com/Developers#aEvents.
        $debug && ia_mu::log($fxn . '::About to build $requesturi with $params=' . ($params ? ia_u::var_dump($params, true) : ''));
        $requestapiurl = INTEGRITYADVOCATE_BASEURL . INTEGRITYADVOCATE_API_PATH . $endpoint;
        $requesturi = $requestapiurl . ($params ? '?' . http_build_query($params, null, '&') : '');
        $debug && ia_mu::log($fxn . '::Built $requesturi=' . $requesturi);

        $requesttimestamp = time();
        $requestmethod = 'GET';
        $microtime = explode(' ', microtime());
        $nonce = $microtime[1] . substr($microtime[0], 2, 6);
        $debug && ia_mu::log($fxn . "::About to build \$requestsignature from \$requesttimestamp={$requesttimestamp}; \; \$requestmethod={$requestmethod}; \$nonce={$nonce}; \$apikey={$apikey}; \$appid={$appid}");
        $requestsignature = self::get_request_signature($requestapiurl, $requestmethod, $requesttimestamp, $nonce, $apikey, $appid);

        // Set cache to false, otherwise caches for the duration of $CFG->curlcache.
        $curl = new \curl(array('cache' => false));
        $curl->setopt(array(
            'CURLOPT_CERTINFO' => 1,
            'CURLOPT_FOLLOWLOCATION' => 1,
            'CURLOPT_MAXREDIRS' => 5,
            'CURLOPT_RETURNTRANSFER' => 1,
            'CURLOPT_SSL_VERIFYPEER' => 1,
        ));

        $header = 'Authorization: amx ' . $appid . ':' . $requestsignature . ':' . $nonce . ':' . $requesttimestamp;
        $curl->setHeader($header);
        $debug && ia_mu::log($fxn . '::Set $header=' . $header);

        $response = $curl->get($requesturi);

        $responseparsed = json_decode($response);
        $responsedetails = $curl->get_info('http_code');
        unset($responsedetails['certinfo']);
        $debug && ia_mu::log($fxn .
                        '::Sent url=' . ia_u::var_dump($requesturi, true) . '; err_no=' . $curl->get_errno() .
                        '; $responsedetails=' . ($responsedetails ? ia_u::var_dump($responsedetails, true) : '') .
                        '; $response=' . ia_u::var_dump($response, true) .
                        '; $responseparsed=' . (ia_u::is_empty($responseparsed) ? '' : ia_u::var_dump($responseparsed, true)));

        if ($responseparsed === null && json_last_error() === JSON_ERROR_NONE) {
            $msg = 'Error: json_decode found no results: ' . json_last_error_msg();
            $debug && ia_mu::log($fxn . '::' . $msg);
            throw new \Exception('Failed to json_decode: ' . $msg);
        }

        if (!$cache->set($cachekey, $responseparsed)) {
            throw new \Exception('Failed to set value in the cache');
        }
        return $responseparsed;
    }

    /**
     * Get the URL to deliver the IA proctoring JS.
     *
     * @param string $appid The App ID.
     * @param int $courseid The course id.
     * @param int $cmid The CMID.
     * @param \stdClass $user The user.
     * @return \moodle_url The IA proctoring JS url.
     */
    public static function get_js_url(string $appid, int $courseid, int $cmid, \stdClass $user): \moodle_url {
        return new \moodle_url(INTEGRITYADVOCATE_BASEURL . '/participants/integrity',
                array(
            'appid' => $appid,
            'courseid' => $courseid,
            'activityid' => $cmid,
            'participantidentifier' => $user->id,
            'participantfirstname' => $user->firstname,
            'participantlastname' => $user->lastname,
            'participantemail' => $user->email,
                )
        );
    }

    /**
     * Get participant sessions related to a user in a specific module.
     *
     * @param \context $modulecontext Module context to look in.
     * @param int $userid User to get participant data for.
     * @return array<Session> Array of Sessions; Empty array if nothing found.
     */
    public static function get_module_user_sessions(\context $modulecontext, int $userid): array {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$modulecontext->instanceid={$modulecontext->instanceid}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($modulecontext) || ($modulecontext->contextlevel !== \CONTEXT_MODULE)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // Get the APIKey and AppID for this module.
        $blockinstance = ia_mu::get_first_block($modulecontext, INTEGRITYADVOCATE_SHORTNAME, true);

        // If the block is not configured yet, simply return empty result.
        if (ia_u::is_empty($blockinstance) || !ia_u::is_empty($blockinstance->get_config_errors())) {
            ia_mu::log($fxn . '::The blockinstance has config errors, so return empty array');
            return array();
        }

        $participantcoursedata = self::get_participant($blockinstance->config->apikey, $blockinstance->config->appid, $modulecontext->get_course_context()->instanceid, $userid);
        if (!isset($participantcoursedata->sessions) || empty($participantcoursedata->sessions)) {
            ia_mu::log($fxn . '::Found no sessions in $participantcoursedata');
            return array();
        }

        $moduleusersessions = array();
        foreach ($participantcoursedata->sessions as $s) {
            ia_mu::log($fxn . '::Checking if $s->activityid != $modulecontext->instanceid=' . ($s->activityid != $modulecontext->instanceid));
            if ($s->activityid != $modulecontext->instanceid) {
                continue;
            }
            $moduleusersessions[] = $s;
        }

        return $moduleusersessions;
    }

    /**
     * Get a single IA proctoring participant data from the remote API.
     * @link https://integrityadvocate.com/Developers#aEndpointMethods
     * This endpoint does not allow specifying activityid so you'll have to iterate sessions[] to
     * get the relevant records for just one module.
     *
     * @param string $apikey The API Key to get data for
     * @param string $appid The AppId to get data for
     * @param int $courseid The course id
     * @param int $userid The user id
     * @return null|Participant Null if nothing found; else the parsed Participant object.
     */
    public static function get_participant(string $apikey, string $appid, int $courseid, int $userid) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (!ia_mu::is_base64($apikey) || !ia_u::is_guid($appid)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // This gets a json-decoded object of the IA API curl result.
        $participantraw = self::get_participant_data($apikey, $appid, $courseid, $userid);
        $debug && ia_mu::log($fxn . '::Got $participantraw=' . (ia_u::is_empty($participantraw) ? '' : ia_u::var_dump($participantraw, true)));
        if (ia_u::is_empty($participantraw)) {
            return null;
        }

        $participant = self::parse_participant($participantraw);
        $debug && ia_mu::log($fxn . '::Built $participant=' . (ia_u::is_empty($participant) ? '' : ia_u::var_dump($participant, true)));

        return $participant;
    }

    /**
     * Get IA participant data for a single course-user.
     *
     * @param string $apikey The API key.
     * @param string $appid The app id.
     * @param int $courseid The course id to get info for.
     * @param int $userid The user id to get info for.
     * @return \stdClass Empty stdClass if nothing found; else Json-decoded stdClass which needs to be parsed into a single Participant object.
     */
    private static function get_participant_data(string $apikey, string $appid, int $courseid, int $userid): \stdClass {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid}, \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (!ia_mu::is_base64($apikey) || !ia_u::is_guid($appid)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // The $result is a json-decoded array.
        $result = self::get(self::ENDPOINT_PARTICIPANT, $apikey, $appid, array('courseid' => $courseid, 'participantidentifier' => $userid));
        $debug && ia_mu::log($fxn . '::Got API result=' . (ia_u::is_empty($result) ? '' : ia_u::var_dump($result, true)));

        if (ia_u::is_empty($result) || !is_object($result)) {
            $debug && ia_mu::log($fxn . '::' . \get_string('no_remote_participants', INTEGRITYADVOCATE_BLOCK_NAME));
            return new \stdClass();
        }

        return $result;
    }

    /**
     * Get IA proctoring participants from the remote API for the given inputs.
     * Note there is no session data attached to these results.
     *
     * @link https://integrityadvocate.com/Developers#aEndpointMethods
     *
     * @param string $apikey The API Key to get data for.
     * @param string $appid The AppId to get data for.
     * @param int $courseid Get info for this course.
     * @param int $userid Optionally filter for this user.
     * @return array<moodleuserid=Participant> Empty array if nothing found; else array of IA participants objects; keys are Moodle user ids.
     */
    public static function get_participants(string $apikey, string $appid, int $courseid, $userid = null): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (!ia_mu::is_base64($apikey) || !ia_u::is_guid($appid) || (!is_null($userid) && !is_number($userid))) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // In case of infinite loop, bail out after trying for some time.
        $oldexecutionlimit = ini_get('max_execution_time');
        set_time_limit(self::RECURSION_TIMEOUT);

        // This gets a json-decoded object of the IA API curl result.
        $participantsraw = self::get_participants_data($apikey, $appid, ['courseid' => $courseid]);
        $debug && ia_mu::log($fxn . '::Got ' . ia_u::count_if_countable($participantsraw) . ' API result=' . (ia_u::is_empty($participantsraw) ? '' : ia_u::var_dump($participantsraw, true)));

        if (ia_u::is_empty($participantsraw)) {
            $debug && ia_mu::log($fxn . '::' . \get_string('no_remote_participants', INTEGRITYADVOCATE_BLOCK_NAME));
            return array();
        }

        $debug && ia_mu::log($fxn . '::About to process the participants returned');
        $parsedparticipants = array();
        foreach ($participantsraw as $pr) {
            $debug && ia_mu::log($fxn . '::Looking at $pr=' . (ia_u::is_empty($pr) ? '' : ia_u::var_dump($pr, true)));
            if (ia_u::is_empty($pr)) {
                $debug && ia_mu::log($fxn . '::Skip: This $participantsraw entry is empty');
                continue;
            }

            // Parse the participants returned.
            $participant = self::parse_participant($pr);
            $debug && ia_mu::log($fxn . '::Built $participant=' . (ia_u::is_empty($participant) ? '' : ia_u::var_dump($participant, true)));

            // Skip if parsing failed.
            if (ia_u::is_empty(($participant))) {
                $debug && ia_mu::log($fxn . '::Skip: The $participantsraw failed to parse');
                continue;
            }

            // Filter for the input courseid and userid.
            if (is_number($courseid) && intval($participant->courseid) !== intval($courseid)) {
                $debug && ia_mu::log($fxn . "::Skip: \$participant->courseid={$participant->courseid} !== \$courseid={$courseid}");
                continue;
            }
            if (is_number($userid) && ($participant->participantidentifier) !== intval($userid)) {
                $debug && ia_mu::log($fxn . "::Skip: \$participant->participantidentifier={$participant->participantidentifier} !== \$userid={$userid}");
                continue;
            }

            $debug && ia_mu::log($fxn . '::About to add participant with $participant->participantidentifier=' . $participant->participantidentifier . ' to the list of ' . count($parsedparticipants) . ' participants');
            $parsedparticipants[$participant->participantidentifier] = $participant;
        }

        // Reset the execution time limit back to what it was.  This will restart the timer from zero but that's OK.
        set_time_limit($oldexecutionlimit);

        $debug && ia_mu::log($fxn . '::About to return count($parsedparticipants)=' . ia_u::count_if_countable($parsedparticipants));
        return $parsedparticipants;
    }

    /**
     * Get IA participant data (non-parsed) for multiple course-users.
     * There is no ability here to filter by course or user, so filter the results in the calling function.
     * Note there is no session data attached to these results.
     *
     * @param string $apikey The API key.
     * @param string $appid The app id.
     * @param array<key=val> $params Query params in key-value format: courseid=>someval is required, optional userid=>intval.
     * @param string The next token to get subsequent results from the API.
     */
    private static function get_participants_data(string $apikey, string $appid, array $params, $nexttoken = null): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$params=" . json_encode($params, JSON_PARTIAL_OUTPUT_ON_ERROR) . " \$nexttoken={$nexttoken}";
        $debug && ia_mu::log($debugvars);

        static $recursecountparticipants = 0;
        if ($recursecountparticipants++ > self::RECURSEMAX) {
            throw new \Exception('Maximum recursion limit reached');
        }

        // Stop recursion when $result->NextToken = 'null'.
        // WTF: It's a string with content 'null' when other fields returned are actual NULL.
        if ($nexttoken == 'null') {
            return array();
        }

        // Sanity check.
        // We are not validating $nexttoken b/c I don't actually care what the value is - only the remote API does.
        if (!ia_mu::is_base64($apikey) || !ia_u::is_guid($appid) || !is_number($params['courseid'])) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }
        foreach (array_keys($params) as $key) {
            if (!in_array($key, array('courseid', 'userid'))) {
                $msg = 'Input params are invalid';
                ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
                throw new \InvalidArgumentException($msg);
            }
        }

        if ($nexttoken) {
            $params['nexttoken'] = $nexttoken;
        }

        // The $result is a array from the json-decoded results.
        $result = self::get(self::ENDPOINT_PARTICIPANTS, $apikey, $appid, $params);
        $debug && ia_mu::log($fxn . '::Got API result=' . (ia_u::is_empty($result) ? '' : ia_u::var_dump($result, true)));

        if (ia_u::is_empty($result)) {
            $debug && ia_mu::log($fxn . '::' . \get_string('no_remote_participants', INTEGRITYADVOCATE_BLOCK_NAME));
            return new \stdClass();
        }

        $participants = $result->Participants;
        $debug && ia_mu::log($fxn . '::$result->NextToken=' . gettype($result->NextToken) . ':' . $result->NextToken);

        if (isset($result->NextToken) && !empty($result->NextToken) && ($result->NextToken != $nexttoken)) {
            $debug && ia_mu::log($fxn . '::About to recurse to get more results');

            // The nexttoken value is only needed for the above get request.
            unset($params['nexttoken']);
            $participants = array_merge($participants, self::get_participants_data($apikey, $appid, $params, $result->NextToken));
        }

        // Disabled on purpose: $debug && ia_mu::log($fxn . '::About to return $participants=' . ia_u::var_dump($participants, true));.
        $debug && ia_mu::log($fxn . '::About to return count($participants)=' . ia_u::count_if_countable($participants));
        return $participants;
    }

    /**
     * Get IA proctoring participant sessions from the remote API for the given inputs.
     *
     * @link https://integrityadvocate.com/Developers#aEndpointMethods
     *
     * @param string $apikey The API Key to get data for.
     * @param string $appid The AppId to get data for.
     * @param int $courseid Get info for this course.
     * @param int $moduleid Get info for this course module.
     * @param int $userid Optionally get info for this user.
     * @return array<moodleuserid=Participant> Empty array if nothing found; else array of IA participants objects; keys are Moodle user ids.
     */
    public static function get_participantsessions(string $apikey, string $appid, int $courseid, int $moduleid, $userid = null): array {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid}; \$moduleid={$moduleid}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (!ia_mu::is_base64($apikey) || !ia_u::is_guid($appid) ||
                (isset($userid) && !is_number($userid))
        ) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // In case of infinite loop, bail out after trying for some time.
        $oldexecutionlimit = ini_get('max_execution_time');
        set_time_limit(self::RECURSION_TIMEOUT);

        $params = ['courseid' => $courseid, 'activityid' => $moduleid];
        $userid && ($params['userid'] = $userid);

        // This gets a json-decoded object of the IA API curl result.
        $participantsessionsraw = self::get_participantsessions_data($apikey, $appid, $params);
        $debug && ia_mu::log($fxn . '::Got ' . ia_u::count_if_countable($participantsessionsraw) . ' API result = ' . (ia_u::is_empty($participantsessionsraw) ? '' : ia_u::var_dump($participantsessionsraw, true)));

        if (ia_u::is_empty($participantsessionsraw)) {
            $debug && ia_mu::log($fxn . '::' . \get_string('no_remote_participant_sessions', INTEGRITYADVOCATE_BLOCK_NAME));
            return array();
        }

        // Sessions will be attached to this Participant object.
        $participant = self::get_participant($apikey, $appid, $courseid, $userid);
        if (empty($participant)) {
            return array();
        }

        $debug && ia_mu::log($fxn . '::About to process the participant sessions returned');
        $parsedparticipantsessions = array();
        foreach ($participantsessionsraw as $pr) {
            $debug && ia_mu::log($fxn . '::Looking at $pr=' . (ia_u::is_empty($pr) ? '' : ia_u::var_dump($pr, true)));
            if (ia_u::is_empty($pr)) {
                $debug && ia_mu::log($fxn . '::Skip: This $participantsessionsraw entry is empty');
                continue;
            }

            // Parse the participant session returned.
            $participantsession = self::parse_session($pr, $participant);
            $debug && ia_mu::log($fxn . '::Built $participantsession=' . (ia_u::is_empty($participantsession) ? '' : ia_u::var_dump($participantsession, true)));

            // Skip if parsing failed.
            if (ia_u::is_empty(($participantsession))) {
                $debug && ia_mu::log($fxn . '::Skip: The $participantsession failed to parse');
                continue;
            }

            $debug && ia_mu::log($fxn . '::About to add $participantsession with $participantsession->id=' . $participantsession->id . ' to the list of ' . count($parsedparticipantsessions) . ' participants');
            $parsedparticipantsessions[$participantsession->id] = $participantsession;
        }

        // Reset the execution time limit back to what it was.  This will restart the timer from zero but that's OK.
        set_time_limit($oldexecutionlimit);

        $debug && ia_mu::log($fxn . '::About to return count($parsedparticipantsessions)=' . ia_u::count_if_countable($parsedparticipantsessions));
        return $parsedparticipantsessions;
    }

    /**
     * Get IA participant sessions data (non-parsed) for 1+ course-users.
     * There is no ability here to filter by course or user, so filter the results in the calling function.
     * Note there is no session data attached to these results.
     *
     * @param string $apikey The API key.
     * @param string $appid The app id.
     * @param array<key=val> $params Query params in key-value format: [courseid=>intval, activityid=>intval] are required, optional userid=>intval.
     * @param string The next token to get subsequent results from the API.
     */
    private static function get_participantsessions_data(string $apikey, string $appid, array $params, $nexttoken = null): array {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$params=" . json_encode($params, JSON_PARTIAL_OUTPUT_ON_ERROR) . " \$nexttoken={$nexttoken}";
        $debug && ia_mu::log($debugvars);

        static $recursecountparticipantsessions = 0;
        if ($recursecountparticipantsessions++ > self::RECURSEMAX) {
            throw new \Exception('Maximum recursion limit reached');
        }

        // Stop recursion when $result->NextToken = 'null'.
        // WTF: It's a string with content 'null' when other fields returned are actual NULL.
        if ($nexttoken == 'null') {
            return array();
        }

        // Sanity check.
        // We are not validating $nexttoken b/c I don't actually care what the value is - only the remote API does.
        if (!ia_mu::is_base64($apikey) || !ia_u::is_guid($appid) ||
                !isset($params['courseid']) || !is_number($params['courseid']) ||
                !isset($params['activityid']) || !is_number($params['activityid']) ||
                (isset($params['userid']) && !is_number($params['userid']))
        ) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }
        foreach (array_keys($params) as $key) {
            if (!in_array($key, array('courseid', 'activityid', 'userid'))) {
                $msg = "Input param {$key} is invalid";
                ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
                throw new \InvalidArgumentException($msg);
            }
        }

        if ($nexttoken) {
            $params['nexttoken'] = $nexttoken;
        }

        // The $result is a array from the json-decoded results.
        $result = self::get(self::ENDPOINT_PARTICIPANTSESSIONS, $apikey, $appid, $params);
        $debug && ia_mu::log($fxn . '::Got API result=' . (ia_u::is_empty($result) ? '' : ia_u::var_dump($result, true)));

        if (ia_u::is_empty($result)) {
            $debug && ia_mu::log($fxn . '::' . \get_string('no_remote_participant_sessions', INTEGRITYADVOCATE_BLOCK_NAME));
            return new \stdClass();
        }

        $participantsessions = $result->ParticipantSessions;
        $debug && ia_mu::log($fxn . '::$result->NextToken=' . gettype($result->NextToken) . ':' . $result->NextToken);

        if (isset($result->NextToken) && !empty($result->NextToken) && ($result->NextToken != $nexttoken)) {
            $debug && ia_mu::log($fxn . '::About to recurse to get more results');

            // The nexttoken value is only needed for the above get request.
            unset($params['nexttoken']);
            $participantsessions = array_merge($participantsessions, self::get_participantsessions_data($apikey, $appid, $params, $result->NextToken));
        }

        // Disabled on purpose: $debug && ia_mu::log($fxn . '::About to return $participantsessions=' . ia_u::var_dump($participantsessions, true));.
        $debug && ia_mu::log($fxn . '::About to return count($participantsessions)=' . ia_u::count_if_countable($participantsessions));
        return $participantsessions;
    }

    /**
     * Build the request signature.
     *
     * @param string $requesturi Full API URI with no querystring.
     * @param int $requestmethod Request method e.g. GET, POST, PATCH.
     * @param int $requesttimestamp Unix timestamp of the request.
     * @param string $nonce Nonce built like this:
     *      $microtime = explode(' ', microtime());
     *      $nonce = $microtime[1] . substr($microtime[0], 2, 6);
     * @param string $apikey API key for the block instance.
     * @param string $appid App ID fot the block instance.
     * @return string The request signature to be sent in the header of the request.
     */
    public static function get_request_signature(string $requesturi, string $requestmethod, int $requesttimestamp, string $nonce, string $apikey, string $appid): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with $requesturi={$requesturi}; \$requestmethod={$requestmethod}; \$requesttimestamp={$requesttimestamp}; \$nonce={$nonce}; \$apikey={$apikey}; \$appid={$appid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (!filter_var($requesturi, FILTER_VALIDATE_URL) || strlen($requestmethod) < 3 || !is_number($requesttimestamp) || $requesttimestamp < 0 || empty($nonce) || !is_string($nonce) ||
                !ia_mu::is_base64($apikey) || !ia_u::is_guid($appid)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        if (parse_url($requesturi, PHP_URL_QUERY)) {
            $msg = 'The requesturi should not contain a querystring';
            ia_mu::log($fxn . "::Started with $requesturi={$requesturi}; \$requestmethod={$requestmethod};  \$requesttimestamp={$requesttimestamp}; \$nonce={$nonce}; \$apikey={$apikey}; \$appid={$appid}");
            ia_mu::log($fxn . '::' . $msg);
            throw new InvalidArgumentException();
        }

        // Create the signature data.
        $signaturerawdata = $appid . $requestmethod . strtolower(urlencode($requesturi)) . $requesttimestamp . $nonce;
        $debug && ia_mu::log($fxn . '::Built $signaturerawdata = ' . $signaturerawdata);

        // Decode the API Key.
        $secretkeybytearray = base64_decode($apikey);

        // Encode the signature.
        $signature = utf8_encode($signaturerawdata);

        // Calculate the hash.
        $signaturebytes = hash_hmac('sha256', $signature, $secretkeybytearray, true);

        // Convert to base64.
        return base64_encode($signaturebytes);
    }

    /**
     * Get the most recent session from the user's participant info.
     *
     * @param \context $modulecontext The module context to look for IA info in.
     * @param int $userid The userid to get participant info for.
     * @return null|Session Null if nothing found; else the most recent session for that user in that activity.
     */
    public static function get_module_session_latest(\context $modulecontext, int $userid) {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$modulecontext->instanceid={$modulecontext->instanceid}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($modulecontext) || ($modulecontext->contextlevel !== \CONTEXT_MODULE)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // Setup an empty object for comparing the start and end times.
        $latestsession = new \block_integrityadvocate\Session();
        $latestsession->end = -1;
        $latestsession->start = -1;

        // Iterate over the sessions to find the ones relevant to this module.
        // Choose the one with the newest end or start time.
        foreach (self::get_module_user_sessions($modulecontext, $userid) as $s) {
            $debug && ia_mu::log($fxn . "::Looking at \$s->id={$s->id}");
            // Only match the module's activity id.
            if (intval($modulecontext->instanceid) !== intval($s->activityid)) {
                continue;
            }
            if (($s->end > $latestsession->end) || ($s->start > $latestsession->start)) {
                $latestsession = $s;
            }
        }

        // If $latestsession is empty or is just the comparison object, we didn't find anything.
        if (ia_u::is_empty($latestsession) || !isset($latestsession->id)) {
            $debug && ia_mu::log($fxn . "::The latest session for userid={$userid} was not found");
            return null;
        }

        return $latestsession;
    }

    /**
     * Get the user's participant status in the module.
     * This may differ from the Participant-level (overall) status.
     *
     * @param \context $modulecontext The module context to look in.
     * @param int $userid The userid to get IA info for.
     * @return int A Status status constant _INT value.
     */
    public static function get_module_status(\context $modulecontext, int $userid): int {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$modulecontext->instanceid={$modulecontext->instanceid}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($modulecontext) || ($modulecontext->contextlevel !== \CONTEXT_MODULE)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // Get the int value representing this constant so it's equivalent to what is stored in Session->status.
        $notfoundval = ia_status::INPROGRESS_INT;

        $latestsession = self::get_module_session_latest($modulecontext, $userid);
        ia_mu::log($fxn . '::Got $latestsession=' . ia_u::var_dump($latestsession, true));
        if (ia_u::is_empty($latestsession)) {
            $debug && ia_mu::log($fxn . "::The latest session for userid={$userid} was not found");
            return $notfoundval;
        }

        $status = $latestsession->get_status();
        $debug && ia_mu::log("About to return \$latestsession->status={$status}");

        return $status;
    }

    /**
     * Returns true if the status value for the user in the latest session for the module represents "In Progress".
     * This may differ from the Participant-level (overall) status.
     *
     * @param \block_integrityadvocate\context $modulecontext The context to look in.
     * @param int $userid The user id to look for.
     * @return bool True if the status value for the user in the module represents "In Progress".
     */
    public static function is_status_inprogress(context $modulecontext, int $userid): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$modulecontext->instanceid={$modulecontext->instanceid}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($modulecontext) || ($modulecontext->contextlevel !== \CONTEXT_MODULE)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        return self::get_module_status($modulecontext, $userid) === ia_status::INPROGRESS;
    }

    /**
     * Returns true if the status value for the user in the latest session for the module represents "Invalid".
     * This may differ from the Participant-level (overall) status.
     *
     * @param \block_integrityadvocate\context $modulecontext The context to look in.
     * @param int $userid The user id to look for.
     * @return bool True if the status value for the user in the module represents "Invalid".
     */
    public static function is_status_invalid(\context $modulecontext, int $userid): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$modulecontext->instanceid={$modulecontext->instanceid}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($modulecontext) || ($modulecontext->contextlevel !== \CONTEXT_MODULE)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $statusinmodule = self::get_module_status($modulecontext, $userid);
        $debug && ia_mu::log($fxn . "::Got \$statusinmodule={$statusinmodule}");
        $isstatusvalid = ia_status::is_invalid_status(intval($statusinmodule));

        return $isstatusvalid;
    }

    /**
     * Returns true if the status value for the user in the latest session for the module represents "Valid".
     * This may differ from the Participant-level (overall) status.
     *
     * @param \block_integrityadvocate\context $modulecontext The context to look in.
     * @param int $userid The user id to look for.
     * @return bool True if the status value for the user in the module represents "Valid".
     */
    public static function is_status_valid(\context $modulecontext, int $userid): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$modulecontext->instanceid={$modulecontext->instanceid}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (ia_u::is_empty($modulecontext) || ($modulecontext->contextlevel !== \CONTEXT_MODULE)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $statusinmodule = self::get_module_status($modulecontext, $userid);
        $debug && ia_mu::log($fxn . "::Got \$statusinmodule={$statusinmodule}");
        $isstatusvalid = ia_status::is_valid_status(intval($statusinmodule));

        return $isstatusvalid;
    }

    /**
     * Extract Flag object info from API session data, cleaning all the fields.
     *
     * @param \stdClass $input API flag data
     * @return null|Flag Null if failed to parse; otherwise a Flag object.
     */
    private static function parse_flag(\stdClass $input) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $f=' . (ia_u::is_empty($input) ? '' : ia_u::var_dump($input, true)));

        if (ia_u::is_empty($input)) {
            $debug && ia_mu::log($fxn . '::Empty object found, so return false');
            return null;
        }

        // Cache so multiple calls don't repeat the same work.  Persession cache b/c is keyed on hash of $input.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = ia_mu::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . '_' . json_encode($input, JSON_PARTIAL_OUTPUT_ON_ERROR));
        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && ia_mu::log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        // Check required field #1.
        if (!isset($input->Id) || !ia_u::is_guid($input->Id)) {
            $debug && ia_mu::log($fxn . '::Minimally-required fields not found');
            return null;
        }
        $output = new Flag();
        $output->id = $input->Id;

        // Clean int fields.
        if (true) {
            isset($input->Created) && ($output->created = \clean_param($input->Created, PARAM_INT));
            isset($input->CaptureDate) && ($output->capturedate = \clean_param($input->CaptureDate, PARAM_INT));
            isset($input->FlagType_Id) && ($output->flagtypeid = \clean_param($input->FlagType_Id, PARAM_INT));
        }

        // Clean text fields.
        if (true) {
            isset($input->Comment) && ($output->comment = \clean_param($input->Comment, PARAM_TEXT));
            isset($input->FlagType_Name) && ($output->flagtypename = \clean_param($input->FlagType_Name, PARAM_TEXT));
        }

        // This Photo field is either a URL or a data uri ref https://css-tricks.com/data-uris/.
        if (isset($input->CaptureData)) {
            $matches = array();
            switch (true) {
                case (preg_match(INTEGRITYADVOCATE_REGEX_DATAURI, $input->CaptureData, $matches)):
                    $output->capturedata = $matches[0];
                    break;
                case (validate_param($input->CaptureData, PARAM_URL)):
                    $output->capturedata = $input->CaptureData;
                    break;
            }
        }

        if (!$cache->set($cachekey, $output)) {
            throw new \Exception('Failed to set value in the cache');
        }

        $debug && ia_mu::log($fxn . '::About to return $flag=' . ia_u::var_dump($output, true));
        return $output;
    }

    /**
     * Extract a Session object from API Participant data, cleaning all the fields.
     *
     * @param \stdClass $input API session data
     * @param Participant $participant Parent object
     * @return null|Session Null if failed to parse, otherwise a parsed Session object.
     */
    private static function parse_session(\stdClass $input, Participant $participant) {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $s=' . (ia_u::is_empty($input) ? '' : ia_u::var_dump($input, true)));

        // Sanity check.
        if (ia_u::is_empty($input)) {
            $debug && ia_mu::log($fxn . '::Empty object found, so return false');
            return array();
        }

        // Cache so multiple calls don't repeat the same work.  Persession cache b/c is keyed on hash of $input.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = ia_mu::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . '_' . json_encode($input, JSON_PARTIAL_OUTPUT_ON_ERROR));
        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && ia_mu::log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        $debug && ia_mu::log($fxn . '::About to create \block_integrityadvocate\Session()');
        $output = new \block_integrityadvocate\Session();

        // Check required field #1.
        if (!isset($input->Id) || !ia_u::is_guid($input->Id)) {
            $debug && ia_mu::log($fxn . '::Minimally-required fields not found: Id');
            return array();
        }
        $output->id = $input->Id;
        $debug && ia_mu::log($fxn . '::Got $session->id=' . $output->id);

        // Check required field #2.
        if (!isset($input->Status) || !is_string($input->Status) || strlen($input->Status) < 5) {
            $debug && ia_mu::log($fxn . '::Minimally-required fields not found: Status');
            return array();
        }
        // This function throws an error if the status is invalid.
        $output->status = ia_status::parse_status_string($input->Status);
        $debug && ia_mu::log($fxn . '::Got $session->status=' . $output->status);
        if (isset($input->Override_Status) && !empty($input->Override_Status)) {
            $output->overridestatus = ia_status::parse_status_string($input->Override_Status);
        }
        $debug && ia_mu::log($fxn . '::Got status=' . $output->status . ' overridestatus=' . $output->overridestatus);

        // Clean int fields.
        if (true) {
            if (isset($input->Activity_Id)) {
                $output->activityid = \clean_param($input->Activity_Id, PARAM_INT);
                if (!($courseid = ia_mu::get_courseid_from_cmid($output->activityid)) || $courseid !== $participant->courseid) {
                    $debug && ia_mu::log($fxn . "::This session activity_id={$output->activityid} belongs to courseid={$courseid} vs participant->courseid={$participant->courseid}, so return empty");
                    return array();
                }
            }
            isset($input->Click_IAmHere_Count) && ($output->clickiamherecount = \clean_param($input->Click_IAmHere_Count, PARAM_INT));
            isset($input->Start) && ($output->start = \clean_param($input->Start, PARAM_INT));
            isset($input->End) && ($output->end = \clean_param($input->End, PARAM_INT));
            isset($input->Exit_Fullscreen_Count) && ($output->exitfullscreencount = \clean_param($input->Exit_Fullscreen_Count, PARAM_INT));
            isset($input->Override_Date) && ($output->overridedate = \clean_param($input->Override_Date, PARAM_INT));
            isset($input->Override_LMSUser_Id) && ($output->overridelmsuserid = \clean_param($input->Override_LMSUser_Id, PARAM_INT));
        }
        $debug && ia_mu::log($fxn . '::Done int fields');

        // Clean text fields.
        if (true) {
            isset($input->Override_LMSUser_FirstName) && ($output->overridelmsuserfirstname = \clean_param($input->Override_LMSUser_FirstName, PARAM_TEXT));
            isset($input->Override_LMSUser_LastName) && ($output->overridelmsuserlastname = \clean_param($input->Override_LMSUser_LastName, PARAM_TEXT));
            isset($input->Override_Reason) && ($output->overridereason = \clean_param($input->Override_Reason, PARAM_TEXT));
        }

        // Clean URL fields.
        if (true) {
            isset($input->ResubmitUrl) && ($output->resubmiturl = filter_var($input->ResubmitUrl, FILTER_SANITIZE_URL));
        }
        $debug && ia_mu::log($fxn . '::Done url fields');

        // This Photo field is either a URL or a data uri ref https://css-tricks.com/data-uris/.
        if (isset($input->Participant_Photo)) {
            $matches = array();
            switch (true) {
                case (preg_match(INTEGRITYADVOCATE_REGEX_DATAURI, $input->Participant_Photo, $matches)):
                    $output->participantphoto = $matches[0];
                    break;
                case (validate_param($input->Participant_Photo, PARAM_URL)):
                    $output->participantphoto = $input->Participant_Photo;
                    break;
            }
        }

        $debug && ia_mu::log($fxn . '::About to check of we have flags');

        if (isset($input->Flags) && is_array($input->Flags)) {
            foreach ($input->Flags as $f) {
                if (!ia_u::is_empty($flag = self::parse_flag($f))) {
                    $output->flags[] = $flag;
                }
            }
        }

        // Link in the parent Participant object.
        $output->participant = $participant;

        if (!$cache->set($cachekey, $output)) {
            throw new \Exception('Failed to set value in the cache');
        }

        $debug && ia_mu::log($fxn . '::About to return $session=' . ia_u::var_dump($output, true));
        return $output;
    }

    /**
     * Extract a Participant object from API data, cleaning all the fields.
     *
     * @param \stdClass $input API participant data
     * @return null|Participant Null if failed to parse, otherwise the parsed Participant object.
     */
    public static function parse_participant(\stdClass $input) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $input=' . (ia_u::is_empty($input) ? '' : ia_u::var_dump($input, true)));

        // Sanity check.
        if (ia_u::is_empty($input)) {
            $debug && ia_mu::log($fxn . '::Empty object found, so return false');
            return null;
        }

        // Check for minimally-required data.
        if (!isset($input->ParticipantIdentifier) || !isset($input->Course_Id)) {
            $debug && ia_mu::log($fxn . '::Minimally-required fields not found');
            return null;
        }
        $debug && ia_mu::log($fxn . '::Minimally-required fields found');

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = ia_mu::get_cache_key(implode('_', array(__CLASS__, __FUNCTION__, json_encode($input, JSON_PARTIAL_OUTPUT_ON_ERROR))));
        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && ia_mu::log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }
        $debug && ia_mu::log($fxn . '::Not a cached value; build a Participant');

        $output = new Participant();

        // Clean int fields.
        if (true) {
            isset($input->ParticipantIdentifier) && ($output->participantidentifier = \clean_param($input->ParticipantIdentifier, PARAM_INT));
            isset($input->Course_Id) && ($output->courseid = \clean_param($input->Course_Id, PARAM_INT));

            // Check for minimally-required data.
            if (!isset($output->participantidentifier) || !isset($output->courseid)) {
                $debug && ia_mu::log($fxn . '::Minimally-required fields not found');
                return null;
            }

            isset($input->Created) && ($output->created = \clean_param($input->Created, PARAM_INT));
            isset($input->Modified) && ($output->modified = \clean_param($input->Modified, PARAM_INT));

            isset($input->Override_Date) && ($output->overridedate = \clean_param($input->Override_Date, PARAM_INT));
            isset($input->Override_LMSUser_Id) && ($output->overridelmsuserid = \clean_param($input->Override_LMSUser_Id, PARAM_INT));
        }
        $debug && ia_mu::log($fxn . '::Done int fields');

        // Clean text fields.
        if (true) {
            isset($input->FirstName) && ($output->firstname = \clean_param($input->FirstName, PARAM_TEXT));
            isset($input->LastName) && ($output->lastname = \clean_param($input->LastName, PARAM_TEXT));

            if (isset($input->Email) && !empty(($val = \clean_param($input->Email, PARAM_EMAIL)))) {
                $output->email = $val;
            }

            isset($input->Override_LMSUser_FirstName) && ($output->overridelmsuserfirstname = \clean_param($input->Override_LMSUser_FirstName, PARAM_TEXT));
            isset($input->Override_LMSUser_LastName) && ($output->overridelmsuserlastname = \clean_param($input->Override_LMSUser_LastName, PARAM_TEXT));
            isset($input->Override_Reason) && ($output->overridereason = \clean_param($input->Override_Reason, PARAM_TEXT));
        }
        $debug && ia_mu::log($fxn . '::Done text fields');

        // Clean URL fields.
        if (true) {
            isset($input->ResubmitUrl) && ($output->resubmiturl = filter_var($input->ResubmitUrl, FILTER_SANITIZE_URL));
        }
        $debug && ia_mu::log($fxn . '::Done url fields');

        // This Photo field is either a URL or a data uri ref https://css-tricks.com/data-uris/.
        if (isset($input->Participant_Photo)) {
            $matches = array();
            switch (true) {
                case (preg_match(INTEGRITYADVOCATE_REGEX_DATAURI, $input->Participant_Photo, $matches)):
                    $output->participantphoto = $matches[0];
                    break;
                case (validate_param($input->Participant_Photo, PARAM_URL)):
                    $output->participantphoto = $input->Participant_Photo;
                    break;
            }
        }

        // Clean status vs allowlist.
        if (isset($input->Status)) {
            $output->status = ia_status::parse_status_string($input->Status);
        }
        if (isset($input->Override_Status) && !empty($input->Override_Status)) {
            $output->overridestatus = ia_status::parse_status_string($input->Override_Status);
        }
        $debug && ia_mu::log($fxn . '::Done status fields');

        // Handle sessions data.
        $output->sessions = array();
        if (isset($input->Sessions) && is_array($input->Sessions)) {
            $debug && ia_mu::log($fxn . '::Found some sessions to look at');
            foreach ($input->Sessions as $s) {
                if (!ia_u::is_empty($session = self::parse_session($s, $output))) {
                    $debug && ia_mu::log($fxn . '::Got a valid session back, so add it to the participant');
                    if (isset($session->end) && ia_u::is_unixtime_past($session->end)) {
                        $end = filter_var($session->end, FILTER_SANITIZE_NUMBER_INT);
                    } else {
                        $end = time();
                    }
                    $output->sessions[] = $session;
                } else {
                    $debug && ia_mu::log($fxn . '::This session failed to parse');
                }
            }

            // If the session is in progress, update the global status to reflect this.
            if (ia_u::count_if_countable($output->sessions) && ($highestsessiontimestamp = max(array_keys($output->sessions)) >= $output->modified)) {
                $output->status = $output->sessions[$highestsessiontimestamp]->status;
            }
        } else {
            $debug && ia_mu::log($fxn . '::No sessions found');
        }
        $debug && ia_mu::log($fxn . '::Done sessions fields');

        // A participant is valid only if they have sessions.
        if (empty($output->sessions)) {
            $debug && ia_mu::log($fxn . '::No sessions found, so return null');
            return null;
        }

        $debug && ia_mu::log($fxn . '::About to return $participant= ' . ia_u::var_dump($output, true));

        if (!$cache->set($cachekey, $output)) {
            throw new \Exception('Failed to set value in the cache');
        }
        return $output;
    }

    /**
     * Override the Integrity Advocate ruling for a participant session.
     * Assumes you have validated and cleaned all params.
     *
     * @param string $apikey API key for the block instance.
     * @param string $appid App ID fot the block instance.
     * @param int $status An integer Participant Status value to override the Integrity Advocate ruling: Accepts: 0 or 3
     * @param string $reason User-provided reason for this override.
     * @param int $targetuserid The user to update.
     * @param \stdClass $overrideuser The user doing the overriding.
     * @param int $courseid The course id.
     * @param int $moduleid The cmid.
     * @return bool True on success (HTTP 200 result).
     */
    public static function set_override_session(string $apikey, string $appid, int $status, string $reason, int $targetuserid, \stdClass $overrideuser, int $courseid, int $moduleid): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$status={$status}; \$reason={$reason}; \$targetuserid={$targetuserid}; \$overrideuserid={$overrideuser->id}, \$courseid={$courseid}, \$moduleid={$moduleid}");

        // Sanity check -- not a validity check!
        if (!ia_mu::is_base64($apikey) || !ia_u::is_guid($appid) || ia_u::is_empty($overrideuser) || !isset($overrideuser->id)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg);
            throw new InvalidArgumentException($msg);
        }

        // Do validity checks only if quick and absolutely neccesary.
        if (!ia_status::is_override_status($status)) {
            throw new InvalidArgumentException("Status={$status} not an overridable value");
        }

        $params_url = array(
            'courseid' => $courseid,
            'activityid' => $moduleid,
            'participantidentifier' => $targetuserid,
        );

        $endpoint = '/participantsessions';
        $requestapiurl = INTEGRITYADVOCATE_BASEURL . INTEGRITYADVOCATE_API_PATH . $endpoint;
        $requesturi = $requestapiurl . ($params_url ? '?' . http_build_query($params_url, null, '&') : '');
        $debug && ia_mu::log($fxn . '::Built $requesturi=' . $requesturi);

        $requesttimestamp = time();
        $requestmethod = 'PATCH';
        $microtime = explode(' ', microtime());
        $nonce = $microtime[1] . substr($microtime[0], 2, 6);
        $debug && ia_mu::log($fxn . "::About to build \$requestsignature from \$requesttimestamp={$requesttimestamp}; \$requestmethod={$requestmethod}; \$nonce={$nonce}; \$apikey={$apikey}; \$appid={$appid}");
        $requestsignature = self::get_request_signature($requestapiurl, $requestmethod, $requesttimestamp, $nonce, $apikey, $appid);

        // Set cache to false, otherwise caches for the duration of $CFG->curlcache.
        $curl = new \curl(array('cache' => false));
        $curl->setopt(array(
            'CURLOPT_CERTINFO' => 1,
            'CURLOPT_FOLLOWLOCATION' => 1,
            'CURLOPT_MAXREDIRS' => 5,
            'CURLOPT_RETURNTRANSFER' => 1,
            'CURLOPT_SSL_VERIFYPEER' => 1,
        ));

        $header = 'Authorization: amx ' . $appid . ':' . $requestsignature . ':' . $nonce . ':' . $requesttimestamp;
        $curl->setHeader($header);
        $debug && ia_mu::log($fxn . '::Set $header=' . $header);

        // Our form params are "Valid" and "Invalid", but this API method only accepts "Invalid" and "Valid".  So translate them accordingly.
        $statusstr = '';
        switch ($status) {
            case ($status === ia_status::VALID_INT):
            case ($status === ia_status::INVALID_OVERRIDE_INT):
                $statusstr = ia_status::get_status_string($status);
                break;
            default:
                throw new \InvalidArgumentException('The given status could not be translated to a value the API understands');
        }
        $debug && ia_mu::log($fxn . "::Got \$statusstr=" . var_export($statusstr, true));
        if (empty($statusstr)) {
            throw new \InvalidArgumentException('The given status could not be translated to a value the API understands');
        }

        $params_body = array(
            'Override_Date' => time(),
            'Override_Status' => $statusstr,
            'Override_Reason' => $reason,
            'Override_LMSUser_FirstName' => $overrideuser->firstname,
            'Override_LMSUser_LastName' => $overrideuser->lastname,
            'Override_LMSUser_Id' => $targetuserid,
        );
        $debug && ia_mu::log($fxn . "::Built params=" . var_export($params_body, true));

        $response = $curl->patch($requesturi, json_encode($params_body));

        $responseparsed = json_decode($response);
        $responsedetails = $curl->get_info('http_code');
        unset($responsedetails['certinfo']);
        $debug && ia_mu::log($fxn .
                        '::Sent url=' . var_export($requesturi, true) . '; err_no=' . $curl->get_errno() .
                        '; $responsedetails=' . ($responsedetails ? var_export($responsedetails, true) : '') .
                        '; $response=' . var_export($response, true) .
                        '; $responseparsed=' . (ia_u::is_empty($responseparsed) ? '' : var_export($responseparsed, true)));

        return isset($responsedetails['http_code']) && ($responsedetails['http_code'] == 200);
    }

    /**
     * Make sure the required params are present, there's no extra params, and param types are valid.
     *
     * @param string $endpoint One of the constants self::ENDPOINT*
     * @param array<key=val> $params Key-value array of params being sent to the API endpoint.
     * @return bool True if everything seems valid.
     */
    public static function validate_endpoint_params(string $endpoint, array $params = array()): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . "::Started with \$endpoint={$endpoint}; \$args=" . ($params ? ia_u::var_dump($params, true) : ''));

        if (!in_array($endpoint, self::ENDPOINTS, true)) {
            throw new \InvalidArgumentException("Invalid endpoint={$endpoint}");
        }

        // For each endpoint, specify what the accepted params are and their types.
        switch ($endpoint) {
            case self::ENDPOINT_PARTICIPANT:
                $validparams = array('participantidentifier' => \PARAM_INT, 'courseid' => \PARAM_INT);
                // All params are required.
                $requiredparams = array_keys($validparams);
                break;
            case self::ENDPOINT_PARTICIPANTS:
                $validparams = array(
                    'backwardsearch' => \PARAM_BOOL,
                    'courseid' => \PARAM_INT,
                    'lastmodified' => \PARAM_INT,
                    'limit' => \PARAM_INT,
                    'nexttoken' => \PARAM_TEXT,
                    'status' => \PARAM_TEXT,
                );
                $requiredparams = array('courseid');
                break;
            case self::ENDPOINT_PARTICIPANTSESSIONS:
                $validparams = array(
                    'activityid' => \PARAM_INT,
                    'backwardsearch' => \PARAM_BOOL,
                    'courseid' => \PARAM_INT,
                    'lastmodified' => \PARAM_INT,
                    'limit' => \PARAM_INT,
                    'nexttoken' => \PARAM_TEXT,
                    'participantidentifier' => \PARAM_INT,
                    'status' => \PARAM_TEXT,
                );
                $requiredparams = array('activityid', 'courseid');
                break;
            default:
                throw new \InvalidArgumentException("Unhandled endpoint={$endpoint}");
        }

        // If there are params missing $requiredparams[] list, throw an exception.
        // Compare the incoming keys in $params vs the list of required params (the values of that array).
        if ($missingparams = array_diff($requiredparams, array_keys($params))) {
            $msg = 'The ' . $endpoint . ' endpoint requires params=' . implode(', ', $missingparams) . '; got params=' . implode(', ', array_keys($params));
            ia_mu::log($fxn . '::' . $msg);
            throw new \invalid_parameter_exception($msg);
        }

        // If there are params specified that are not in the $validparams[] list, they are invalid params.
        // Use array_diff_key: Returns an array containing all the entries from array1 whose keys are absent from all of the other arrays.
        if ($extraparams = array_diff_key($params, $validparams)) {
            $msg = 'The ' . $endpoint . ' endpoint does not accept params=' . implode(', ', array_keys($extraparams)) . '; got params=' . implode(', ', array_keys($params));
            ia_mu::log($fxn . '::' . $msg);
            throw new \invalid_parameter_exception($msg);
        }

        // Check each of the param types matches what is specified in $validparams[] for that param.
        // Throws an exception if there is a mismatch.

        foreach ($params as $argname => $argval) {
            try {
                \validate_param($argval, $validparams[$argname]);
            } catch (\invalid_parameter_exception $e) {
                // Log a more useful message than Moodle gives us, then just throw it again.
                ia_mu::log($fxn . '::The param is valid but the type is wrong for param=' . $argname . '; $argval=' . ia_u::var_dump($argval, true));
                throw $e;
            }
        }

        // Everything is valid.
        return true;
    }

}
