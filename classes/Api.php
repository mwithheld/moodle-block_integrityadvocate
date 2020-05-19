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
use block_integrityadvocate\PaticipantStatus as ia_participant_status;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

/**
 * Functions to interact with the IntegrityAdvocate API.
 */
class Api {

    const ENDPOINT_CLOSE_SESSION = '/participants/endsession';
    const ENDPOINT_PARTICIPANT = '/participant';
    const ENDPOINT_PARTICIPANTS = '/course';

    /**
     * Attempt to close the remote IA proctoring session.  404=failed to find the session.
     *
     * @param string $appid IA AppID
     * @param string $appid The AppId to get data for
     * @param int $userid The userid to close the proctoring session for
     * @param int $moduleid The module id
     * @param int $courseid The course id
     * @param int $userid The user id
     * @return bool true if the remote API close says it succeeded; else false
     */
    public static function close_remote_session(string $appid, int $courseid, int $moduleid, int $userid): bool {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$appid={$appid}; \$courseid={$courseid}; \$moduleid={$moduleid}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (!ia_u::is_guid($appid)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // For Behat tests, assume the session close succeeds.
        if (ia_mu::is_testingmode()) {
            return true;
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
                '&participantidentifier=' . urlencode($userid) .
                '&courseid=' . urlencode($courseid) .
                '&activityid=' . urlencode($moduleid);
        $response = $curl->get($url);
        $responsecode = $curl->get_info('http_code');
        $debug && ia_mu::log($fxn . '::Sent url=' . var_export($url, true)
                        . '; http_code=' . var_export($responsecode, true) . '; response body=' . var_export($response, true));

        return intval($responsecode) < 400;
    }

    /**
     * Interact with the IA-side API to get results.
     *
     * @param string $endpoint One of the self::ENDPOINT* constants.
     * @param string $apikey The API Key to get data for
     * @param string $appid The AppId to get data for
     * @param array[string]string $params API params per the URL above.  e.g. array('participantidentifier'=>$user_identifier).
     * @return string|bool false if nothing found or error; else the json-decoded curl response body (an array)
     */
    private static function get(string $endpoint, string $apikey, string $appid, array $params = array()) {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn .
                "::Started with \$endpointpath={$endpoint}; \$apikey={$apikey}; \$appid={$appid}; \$params=" . var_export($params, true);
        $debug && ia_mu::log($debugvars);

        // If the block is not configured yet, simply return empty result.
        if (empty($apikey) || empty($appid)) {
            return array();
        }

        // Sanity check.
        if (stripos($endpoint, '/') === false || !ia_mu::is_base64($apikey) || !ia_u::is_guid($appid) || !is_array($params)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // Make sure the required params are present, there's no extra params, and param types are valid.
        self::validate_endpoint_params($endpoint, $params);

        // For Behat tests, use mocked data.
        if (ia_mu::is_testingmode()) {
            return MockData::get($endpoint, $apikey, $appid, $params);
        }

        // For the Participants endpoint, add the remaining part of the URL.
        if ($endpoint === self::ENDPOINT_PARTICIPANTS) {
            $endpoint .= "/{$params['courseid']}/participants";
        }

        // Cache responses in a per-request cache so multiple calls in one request don't repeat the same work .
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCKNAME, 'perrequest');
        $cachekey = __CLASS__ . '_' . __FUNCTION__ . '_' . sha1($endpoint . $appid . json_encode($params, JSON_PARTIAL_OUTPUT_ON_ERROR));

        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && ia_mu::log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        // Set up request variables to get IA participant info.
        // Ref API docs at https://integrityadvocate.com/Developers#aEvents.
        $debug && ia_mu::log($fxn . '::About to build $requesturi with $params=' . ($params ? var_export($params, true) : ''));
        $requestapiurl = INTEGRITYADVOCATE_BASEURL . INTEGRITYADVOCATE_API_PATH . $endpoint;
        $requesturi = $requestapiurl . ($params ? '?' . http_build_query($params, null, '&') : '');
        $debug && ia_mu::log($fxn . '::Built $requesturi=' . $requesturi);

        $requesttimestamp = time();
        $microtime = explode(' ', microtime());
        $nonce = $microtime[1] . substr($microtime[0], 2, 6);
        $debug && ia_mu::log($fxn .
                        "::About to build \$requestsignature from \$requesttimestamp={$requesttimestamp}; \$nonce={$nonce}; \$apikey={$apikey}; \$appid={$appid}");
        $requestsignature = self::get_request_signature($requestapiurl, 'GET', $requesttimestamp, $nonce, $apikey, $appid);

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
        $debug && ia_mu::log($fxn .
                        '::Sent url=' . var_export($requesturi, true) . '; err_no=' . $curl->get_errno() .
                        '; $responsedetails=' . ($responsedetails ? var_export($responsedetails, true) : '') .
                        '; $response=' . var_export($response, true) .
                        '; $responseparsed=' . (ia_u::is_empty($responseparsed) ? '' : var_export($responseparsed, true)));

        if (empty($responseparsed)) {
            $debug && ia_mu::log($fxn . '::' . \get_string('no_remote_participants', INTEGRITYADVOCATE_BLOCKNAME));
            return false;
        }

        if (!$cache->set($cachekey, $responseparsed)) {
            throw new \Exception('Failed to set value in perrequest cache');
        }
        return $responseparsed;
    }

    public static function get_js_url(string $appid, int $courseid, int $cmid, \stdClass $user) {
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
     * @return array Array of sessions.
     * @throws \InvalidArgumentException
     */
    public static function get_module_user_sessions(\context $modulecontext, int $userid): array {
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

        // Get the APIKey and AppID for this module.
        $blockinstance = ia_mu::get_first_block($modulecontext, INTEGRITYADVOCATE_SHORTNAME, true);

        $notfoundval = array();
        // If the block is not configured yet, simply return empty result.
        if (!isset($blockinstance->config->apikey) || empty($blockinstance->config->apikey) || !isset($blockinstance->config->appid) || empty($blockinstance->config->appid)) {
            return array();
        }

        $participantcoursedata = self::get_participant($blockinstance->config->apikey, $blockinstance->config->appid, $modulecontext->get_course_context()->instanceid, $userid);

        if (!isset($participantcoursedata->sessions) || empty($participantcoursedata->sessions)) {
            return $notfoundval;
        }

        $moduleusersessions = array();
        foreach ($participantcoursedata->sessions as $s) {
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
     * This endpoint does not allow specifying activityid so you'll have to iterate sessions[] to get the relevant records for just one module.
     *
     * @param string $apikey The API Key to get data for
     * @param string $appid The AppId to get data for
     * @param int $courseid The course id
     * @param int $userid The user id
     * @return object[]|bool false if nothing found; else array of Participant objects
     */
    public static function get_participant(string $apikey, string $appid, int $courseid, int $userid) {
        $debug = true;
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
        $debug && ia_mu::log($fxn . '::Got $participantraw=' . (ia_u::is_empty($participantraw) ? '' : var_export($participantraw, true)));
        if (ia_u::is_empty($participantraw)) {
            return false;
        }

        $participant = self::parse_participant($participantraw);
        $debug && ia_mu::log($fxn . '::Built $participant=' . (ia_u::is_empty($participant) ? '' : var_export($participant, true)));

        return $participant;
    }

    /**
     * Get IA participant data for a single course-user.
     *
     * @param string $apikey The API key.
     * @param string $appid The app id.
     * @param type $courseid The course id to get info for.
     * @param type $userid The user id to get info for.
     * @return stdClass Json-decoded object which is the API curl result - needs to be parsed into a Participant object.
     * @throws InvalidArgumentException
     */
    private static function get_participant_data(string $apikey, string $appid, int $courseid, int $userid) {
        $debug = true;
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
        $debug && ia_mu::log($fxn . '::Got API result=' . (ia_u::is_empty($result) ? '' : var_export($result, true)));

        if (empty($result)) {
            $debug && ia_mu::log($fxn . '::' . \get_string('no_remote_participants', INTEGRITYADVOCATE_BLOCKNAME));
            return false;
        }

        return $result;
    }

    /**
     * Get IA proctoring participants from the remote API for the given inputs.
     * Note there is no session data attached to these results.
     *
     * @link https://integrityadvocate.com/Developers#aEndpointMethods
     *
     * @param string $apikey The API Key to get data for
     * @param string $appid The AppId to get data for
     * @return object[]|bool false if nothing found; else array of IA participants objects
     */
    public static function get_participants(string $apikey, string $appid, int $courseid, $userid = null) {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn .
                "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid}; \$userid={$userid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (!ia_mu::is_base64($apikey) || !ia_u::is_guid($appid) || (!is_null($userid) && !is_numeric($userid))) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // This gets a json-decoded object of the IA API curl result.
        $result = self::get_participants_data($apikey, $appid, $courseid);
        $debug && ia_mu::log($fxn .
                        '::Got API result=' . (ia_u::is_empty($result) ? '' : var_export($result, true)));

        if (ia_u::is_empty($result)) {
            $debug && ia_mu::log($fxn . '::' . \get_string('no_remote_participants', INTEGRITYADVOCATE_BLOCKNAME));
            return false;
        }

        $nexttoken = false;
        if (isset($result->NextToken) && !empty($result->NextToken)) {
            $nexttoken = $result->NextToken;
        }

        // The API returns a max of 20 results per call.  Get them all.
        $maxrecords = 20;
        // Collect results in $participants.
        $participantsraw = $result->Participants;

        while ($nexttoken && (count($result->Participants) == $maxrecords)) {
            $debug && ia_mu::log($fxn .
                            "::Getting more records: \$nexttoken=[$nexttoken}; count(\$result->Participants)=" . count($result->Participants));
            $params = array('courseid' => $courseid, 'nexttoken' => $result->NextToken, 'backwardsearch' => false);
            $result = self::get(self::ENDPOINT_PARTICIPANTS, $apikey, $appid, $params);
            $debug && ia_mu::log($fxn . '::Got API result = ' . (ia_u::is_empty($result) ? '' : var_export($result, true)));
            $nexttoken = false;
            if (isset($result->NextToken) && !empty($result->NextToken)) {
                $nexttoken = $result->NextToken;
            }
            if (!empty($result) && isset($result->Participants) && !empty($result->Participants)) {
                array_push($participantsraw, $result->Participants);
            }
        }
        $debug && ia_mu::log($fxn . '::Built $participantsraw=' . ($participantsraw ? var_export($participantsraw, true) : ''));

        // Process the partcipants returned.
        $parsedparticipants = array();
        foreach ($participantsraw as $pr) {
            $debug && ia_mu::log($fxn . '::Looking at $pr=' . (ia_u::is_empty($pr) ? '' : var_export($pr, true)));
            if (empty($pr)) {
                continue;
            }

            // Parse the partcipants returned.
            $participant = self::parse_participant($pr);
            $debug && ia_mu::log($fxn . '::Built $participant=' . (ia_u::is_empty($participant) ? '' : var_export($participant, true)));

            // Filter for the courseid and userid.
            if (is_numeric($courseid) && intval($participant->courseid) !== intval($courseid)) {
                $debug && ia_mu::log($fxn . "::Skip: \$participant->courseid={$participant->courseid} !== \$courseid={$courseid}");
                continue;
            }
            if (is_numeric($userid) && ($participant->participantidentifier) !== intval($userid)) {
                $debug && ia_mu::log($fxn . "::Skip: \$participant->participantidentifier={$participant->participantidentifier} !== \$userid={$userid}");
                continue;
            }

            $parsedparticipants[] = $participant;
        }

        $debug && ia_mu::log($fxn .
                        '::About to return count($parsedparticipants)=' . (is_countable($parsedparticipants) ? count($parsedparticipants) : 0));
        return $parsedparticipants;
    }

    /**
     * Get IA participant data for multiple course-users.
     * There is no ability here to filter by course or user, so filter the results in the calling function.
     *
     * @param string $apikey The API key.
     * @param string $appid The app id.
     * @return bool|array False if not found; else json-decoded array = the API curl result.
     * @throws InvalidArgumentException
     */
    private static function get_participants_data(string $apikey, string $appid, int $courseid) {
        $debug = true;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (!ia_mu::is_base64($apikey) || !ia_u::is_guid($appid) || !is_numeric($courseid)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // The $result is a json-decoded array.
        $result = self::get(self::ENDPOINT_PARTICIPANTS, $apikey, $appid, array('courseid' => $courseid));
        $debug && ia_mu::log($fxn . '::Got API result=' . (ia_u::is_empty($result) ? '' : var_export($result, true)));

        if (empty($result)) {
            $debug && ia_mu::log($fxn . '::' . \get_string('no_remote_participants', INTEGRITYADVOCATE_BLOCKNAME));
            return false;
        }

        return $result;
    }

    /**
     * Build the request signature.
     *
     * @param string $requesturi Full API URI with no querystring.
     * @param int $requesttimestamp Unix timestamp of the request.
     * @param string $nonce Nonce built like this:
     *      $microtime = explode(' ', microtime());
     *      $nonce = $microtime[1] . substr($microtime[0], 2, 6);
     * @param string $apikey API key for the block instance.
     * @param string $appid App ID fot the block instance.
     * @return string the request signature to be sent in the header of the request.
     */
    private static function get_request_signature(string $requesturi, string $requestmethod, int $requesttimestamp, string $nonce, string $apikey, string $appid): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn .
                "::Started with $requesturi={$requesturi}; \$requestmethod={$requestmethod}; \$requesttimestamp={$requesttimestamp}; "
                . "\$nonce={$nonce}; \$apikey={$apikey}; \$appid={$appid}";
        $debug && ia_mu::log($debugvars);

        // Sanity check.
        if (!filter_var($requesturi, FILTER_VALIDATE_URL) || strlen($requestmethod) < 3 || !is_numeric($requesttimestamp) || $requesttimestamp < 0 || empty($nonce) || !is_string($nonce) ||
                !ia_mu::is_base64($apikey) || !ia_u::is_guid($appid)) {
            $msg = 'Input params are invalid';
            ia_mu::log($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        if (parse_url($requesturi, PHP_URL_QUERY)) {
            $msg = 'The requesturi should not contain a querystring';
            ia_mu::log($fxn .
                    "::Started with $requesturi={$requesturi}; \$requestmethod={$requestmethod}; \$requesttimestamp={$requesttimestamp}; "
                    . "\$nonce={$nonce}; \$apikey={$apikey}; \$appid={$appid}");
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
     * @return Session The most recent session for that user in that activity.
     * @throws \InvalidArgumentException
     */
    public static function get_session_latest(\context $modulecontext, int $userid) {
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
     *
     * @param \context $modulecontext The module context to look in.
     * @param int $userid The userid to get IA info for.
     * @return int A ParticipantStatus status constant _INT value.
     * @throws \InvalidArgumentException
     */
    public static function get_status_in_module(\context $modulecontext, int $userid): int {
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

        // Get the int value representing this constant so it's equivalent to what is stored in Session->status.
        $notfoundval = ia_participant_status::INPROGRESS_INT;

        $latestsession = self::get_session_latest($modulecontext, $userid);
        if (ia_u::is_empty($latestsession)) {
            $debug && ia_mu::log($fxn . "::The latest session for userid={$userid} was not found");
            return $notfoundval;
        }

        return $latestsession->status;
    }

    /**
     * Get if the status value for the user in the module represents "In Progress".
     *
     * @param \block_integrityadvocate\context $modulecontext The context to look in.
     * @param int $userid The user id to look for.
     * @return bool True if the status value for the user in the module represents "In Progress"
     * @throws \InvalidArgumentException
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

        return self::get_status_in_module($modulecontext, $userid) === ia_participant_status::INPROGRESS;
    }

    /**
     * Get if the status value for the user in the module represents "Invalid".
     *
     * @param \block_integrityadvocate\context $modulecontext The context to look in.
     * @param int $userid The user id to look for.
     * @return bool True if the status value for the user in the module represents "Invalid"
     * @throws \InvalidArgumentException
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

        return in_array(
                self::get_status_in_module($modulecontext, $userid),
                array(
                    ia_participant_status::INVALID_ID_INT,
                    ia_participant_status::INVALID_RULES_INT,
                ), true
        );
    }

    /**
     * Get if the status value for the user in the module represents "Valid".
     *
     * @param \block_integrityadvocate\context $modulecontext The context to look in.
     * @param int $userid The user id to look for.
     * @return bool True if the status value for the user in the module represents "Valid"
     * @throws \InvalidArgumentException
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

        $statusinmodule = self::get_status_in_module($modulecontext, $userid);
        $debug && ia_mu::log($fxn . "::Comparing \$statusinmodule={$statusinmodule} === " . ia_participant_status::VALID);
        $statusisvalid = (intval($statusinmodule) === intval(ia_participant_status::VALID));

        return $statusisvalid;
    }

    /**
     * Extract Flag object info from API session data, cleaning all the fields.
     *
     * @param stdClass $input API flag data
     * @return bool|Session[] False if failed to parse, otherwise a Flag object.
     */
    private static function parse_flags(\stdClass $input) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $f=' . (ia_u::is_empty($input) ? '' : var_export($input, true)));

        if (ia_u::is_empty($input)) {
            $debug && ia_mu::log($fxn . '::Empty object found, so return false');
            return false;
        }

        // Check required field #1.
        if (!isset($input->Id) || !ia_u::is_guid($input->Id)) {
            $debug && ia_mu::log($fxn . '::Minimally-required fields not found');
            return false;
        }
        $flag = new Flag();
        $flag->id = $input->Id;

        // Clean int fields.
        if (true) {
            isset($input->Created) && ($flag->created = \clean_param($input->Created, PARAM_INT));
            isset($input->CaptureDate) && ($flag->capturedate = \clean_param($input->CaptureDate, PARAM_INT));
            isset($input->FlagType_Id) && ($flag->flagtypeid = \clean_param($input->FlagType_Id, PARAM_INT));
        }

        // Clean text fields.
        if (true) {
            isset($input->Comment) && ($flag->comment = \clean_param($input->Comment, PARAM_TEXT));
            isset($input->FlagType_Name) && ($flag->flagtypename = \clean_param($input->FlagType_Name, PARAM_TEXT));
        }

        // This field is a data uri ref https://css-tricks.com/data-uris/.
        $matches = array();
        if (isset($input->CaptureData) && preg_match(INTEGRITYADVOCATE_REGEX_DATAURI, $input->CaptureData, $matches)) {
            $flag->capturedata = $matches[0];
        }

        $debug && ia_mu::log($fxn . '::About to return $flag=' . var_export($flag, true));
        return $flag;
    }

    /**
     * Extract Session objects from API Participant data, cleaning all the fields.
     *
     * @param stdClass $input API session data
     * @return bool|Session[] False if failed to parse, otherwise an array of parsed Session objects.
     */
    private static function parse_session(\stdClass $input) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $s=' . (ia_u::is_empty($input) ? '' : var_export($input, true)));

        // Sanity check.
        if (ia_u::is_empty($input)) {
            $debug && ia_mu::log($fxn . '::Empty object found, so return false');
            return false;
        }

        $debug && ia_mu::log($fxn . '::About to create \block_integrityadvocate\Session()');
        $session = new \block_integrityadvocate\Session();

        // Check required field #1.
        if (!isset($input->Id) || !ia_u::is_guid($input->Id)) {
            $debug && ia_mu::log($fxn . '::Minimally-required fields not found: Id');
            return false;
        }
        $session->id = $input->Id;
        $debug && ia_mu::log($fxn . '::Got $session->id=' . $session->id);

        // Check required field #2.
        if (!isset($input->Status) || !is_string($input->Status) || strlen($input->Status) < 5) {
            $debug && ia_mu::log($fxn . '::Minimally-required fields not found: Status');
            return false;
        }
        // This function throws an error if the status is invalid.
        $session->status = ia_participant_status::parse_status_string($input->Status);
        $debug && ia_mu::log($fxn . '::Got $session->status=' . $session->status);

        // Clean int fields.
        if (true) {
            isset($input->Activity_Id) && ($session->activityid = \clean_param($input->Activity_Id, PARAM_INT));
            isset($input->Click_IAmHere_Count) && ($session->clickiamherecount = \clean_param($input->Click_IAmHere_Count, PARAM_INT));
            isset($input->Start) && ($session->start = \clean_param($input->Start, PARAM_INT));
            isset($input->End) && ($session->end = \clean_param($input->End, PARAM_INT));
            isset($input->Exit_Fullscreen_Count) && ($session->exitfullscreencount = \clean_param($input->Exit_Fullscreen_Count, PARAM_INT));
        }
        $debug && ia_mu::log($fxn . '::Done int fields');

        // This field is a data uri ref https://css-tricks.com/data-uris/.
        $matches = array();
        if (isset($input->Participant_Photo) && preg_match(INTEGRITYADVOCATE_REGEX_DATAURI, $input->Participant_Photo, $matches)) {
            $session->participantphoto = $matches[0];
        }

        $debug && ia_mu::log($fxn . '::About to check of we have flags');

        if (isset($input->Flags) && is_array($input->Flags)) {
            foreach ($input->Flags as $f) {
                if ($flag = self::parse_flags($f)) {
                    $session->flags[] = $flag;
                }
            }
        }

        $debug && ia_mu::log($fxn . '::About to return $session= ' . var_export($session, true));
        return $session;
    }

    /**
     * Extract a Participant object from API data, cleaning all the fields.
     *
     * @param stdClass $input API participant data
     * @return bool|ia_participant False if failed to parse, otherwise the parsed Participant object.
     */
    public static function parse_participant(\stdClass $input) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . '::Started with $p=' . (ia_u::is_empty($input) ? '' : var_export($input, true)));

        // Sanity check.
        if (ia_u::is_empty($input)) {
            $debug && ia_mu::log($fxn . '::Empty object found, so return false');
            return false;
        }

        $participant = new ia_participant();

        // Clean int fields.
        if (true) {
            isset($input->ParticipantIdentifier) && ($participant->participantidentifier = \clean_param($input->ParticipantIdentifier, PARAM_INT));
            isset($input->Course_Id) && ($participant->courseid = \clean_param($input->Course_Id, PARAM_INT));

            // Check for minimally-required data.
            if (!isset($participant->participantidentifier) || !isset($participant->courseid)) {
                $debug && ia_mu::log($fxn . '::Minimally-required fields not found');
                return false;
            }

            isset($input->Created) && ($participant->created = \clean_param($input->Created, PARAM_INT));
            isset($input->Modified) && ($participant->modified = \clean_param($input->Modified, PARAM_INT));

            isset($input->Override_Date) && ($participant->overridedate = \clean_param($input->Override_Date, PARAM_INT));
            isset($input->Override_LMSUser_Id) && ($participant->overridelmsuserid = \clean_param($input->Override_LMSUser_Id, PARAM_INT));
        }
        // Disabled on purpose: $debug && ia_mu::log($fxn . '::Done int fields');.
        //
        // Clean text fields.
        if (true) {
            isset($input->FirstName) && ($participant->firstname = \clean_param($input->FirstName, PARAM_TEXT));
            isset($input->LastName) && ($participant->lastname = \clean_param($input->LastName, PARAM_TEXT));

            if (isset($input->Email) && !empty(($val = \clean_param($input->Email, PARAM_EMAIL)))) {
                $participant->email = $val;
            }

            isset($input->Override_LMSUser_FirstName) && ($participant->overridelmsuserfirstname = \clean_param($input->Override_LMSUser_FirstName, PARAM_TEXT));
            isset($input->Override_LMSUser_LastName) && ($participant->overridelmsuserlastname = \clean_param($input->Override_LMSUser_LastName, PARAM_TEXT));
            isset($input->Override_Reason) && ($participant->overridereason = \clean_param($input->Override_Reason, PARAM_TEXT));
        }
        // Disabled on purpose: $debug && ia_mu::log($fxn . '::Done text fields');.
        //
        // Clean URL fields.
        if (true) {
            isset($input->Participant_Photo) && ($participant->participantphoto = filter_var($input->Participant_Photo, FILTER_SANITIZE_URL));
            isset($input->ResubmitUrl) && ($participant->resubmiturl = filter_var($input->ResubmitUrl, FILTER_SANITIZE_URL));
        }
        // Disabled on purpose: $debug && ia_mu::log($fxn . '::Done url fields');.
        //
        // Clean status vs whitelist.
        if (isset($input->Status)) {
            $participant->status = ia_participant_status::parse_status_string($input->Status);
        }
        if (isset($input->Override_Status) && !empty($input->Override_Status)) {
            $participant->overridestatus = ia_participant_status::parse_status_string($input->Override_Status);
        }
        // Disabled on purpose: $debug && ia_mu::log($fxn . '::Done status fields');.
        //
        // Handle sessions data.
        $participant->sessions = array();
        if (isset($input->Sessions) && is_array($input->Sessions)) {
            $debug && ia_mu::log($fxn . '::Found some sessions to look at');
            foreach ($input->Sessions as $s) {
                if ($session = self::parse_session($s)) {
                    $debug && ia_mu::log($fxn . '::Got a valid session back, so add it to the participant');
                    $participant->sessions[] = $session;
                } else {
                    $debug && ia_mu::log($fxn . '::This session failed to parse');
                }
            }
        } else {
            $debug && ia_mu::log($fxn . '::No sessions found');
        }

        $debug && ia_mu::log($fxn . '::About to return $participant= ' . var_export($participant, true));
        return $participant;
    }

    /**
     * Make sure the required params are present, there's no extra params, and param types are valid.
     *
     * @param string $endpoint One of the constants self::ENDPOINT*
     * @param array $params Key-value array of params being sent to the API endpoint.
     * @return bool True if everything seems valid.
     * @throws \invalid_parameter_exception if $param is not of given type
     */
    public static function validate_endpoint_params(string $endpoint, array $params = array()): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && ia_mu::log($fxn . "::Started with \$endpoint={$endpoint}; \$args=" . ($params ? var_export($params, true) : ''));

        if (!in_array($endpoint, array(self::ENDPOINT_PARTICIPANT, self::ENDPOINT_PARTICIPANTS), true)) {
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
                $validparams = array('nexttoken' => \PARAM_TEXT, 'backwardsearch' => \PARAM_BOOL, 'courseid' => \PARAM_INT);
                $requiredparams = array('courseid');
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
            $msg = 'The ' . $endpoint . ' endpoint does not accept params=' . implode(', ', $extraparams) . '; got params=' . implode(', ', array_keys($params));
            ia_mu::log($fxn . '::' . $msg);
            throw new \invalid_parameter_exception($msg);
        }

        // Check each of the param types matches what is specified in $validparams[] for that param.
        // Throws an exception if there is a mismatch.
        foreach ($params as $argname => $argval) {
            \validate_param($argval, $validparams[$argname]);
        }

        // Everything is valid.
        return true;
    }

}
