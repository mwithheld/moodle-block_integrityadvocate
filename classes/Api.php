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

use block_integrityadvocate as ia;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Participant as ia_participant;
use block_integrityadvocate\Status as ia_status;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

/**
 * Functions to interact with the IntegrityAdvocate API.
 */
class Api {
    // API ref https://www.integrityadvocateserver.com/developers#apiendpoints .

    /** @var string URI to ping IA and see if there is a good response */
    public const ENDPOINT_PING = '/ping';

    /** @var string URI to close the remote IA session */
    public const ENDPOINT_CLOSE_SESSION = '/participants/endsession';

    /** @var string URI to get participant info */
    public const ENDPOINT_PARTICIPANT = '/participant';

    /** @var string URI to get participants info */
    public const ENDPOINT_PARTICIPANTS = '/course/courseid/participants';

    /** @var string URI to get participant session info */
    public const ENDPOINT_PARTICIPANTSESSIONS = '/course/courseid/participantsessions';

    /** @var string URI to get participant sessions activity info */
    public const ENDPOINT_PARTICIPANTSESSIONS_ACTIVITY = '/participantsessions/activity';

    /** @var string URI to get only the overall status for a single participant for the specified activity. */
    public const ENDPOINT_PARTICIPANT_STATUS = '/2-0/participantstatus';

    /** @var int The API returns 10 results max per call by default, but our UI shows 20 users per page.  Set the number we want per
     * UI page here. */
    // Unused at the moment: const RESULTS_PERPAGE = 20;.

    /** @var int In case of errors, this limits recursion to some reasonable maximum. */
    private const RECURSEMAX = 250;

    /** @var array<int> Accept these HTTP success response codes as successful */
    public const HTTP_CODE_SUCCESS = [200, 201, 202, 204, 205];

    /** @var array<int> Accept these HTTP success response codes as successful */
    public const HTTP_CODE_REDIRECT = [303, 304];

    /** @var array<int> Accept these HTTP success response codes as successful */
    public const HTTP_CODE_CLIENTERROR = [404, 410];

    /**
     * Check if the HTTP response code is one we can consider OK.
     *
     * @param int $httpresponsecode The HTTP response code to check.
     * @return bool True if it is considered OK.
     */
    public static function http_response_code_is_acceptable(int $httpresponsecode): bool {
        return \in_array($httpresponsecode, \array_merge(self::HTTP_CODE_SUCCESS, self::HTTP_CODE_REDIRECT, self::HTTP_CODE_CLIENTERROR), true);
    }

    /**
     * Attempt to close the remote IA proctoring session.  404=failed to find the session.
     * @link https://www.integrityadvocateserver.com/Developers#endingthesession .
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
        $debug && \debugging($debugvars);

        // Sanity check.
        if (!ia_u::is_guid($appid)) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        if (!ia_mu::nonce_validate(\implode('_', [INTEGRITYADVOCATE_SESSION_STARTED_KEY, $appid, $courseid, $moduleid, $userid]), true)) {
            $debug && \debugging(__CLASS__ . '::' . __FUNCTION__ . '::Found no session_started value - do not close the session');
            return false;
        } else {
            $debug && \debugging(__CLASS__ . '::' . __FUNCTION__ . '::Found a session_started value so close the session and clear the session_started flag');
            // Just fall out of the if-else.
        }

        // Do not cache these requests.
        $requesturi = INTEGRITYADVOCATE_BASEURL_API . self::ENDPOINT_CLOSE_SESSION . '?' .
            'appid=' . \urlencode($appid) .
            '&participantidentifier=' . $userid .
            '&courseid=' . $courseid .
            '&activityid=' . $moduleid;
        [$responsecode, $response, $responseinfo] = self::curl_get_unsigned($requesturi);

        $success = self::http_response_code_is_acceptable($responsecode);
        if (!$success) {
            $msg = $fxn . '::Request to the IA server failed: GET url=' . \var_export($requesturi, true) . '; Response http_code=' . ia_u::var_dump($responsecode);
            \debugging($msg);
        }
        return $success;
    }

    /**
     * Do a curl GET with the given URL with no authorization header.
     * This function does no validation and minimal input/output cleaning.
     *
     * @param string $endpoint The URL to GET.
     * @return array [HTTP response code, response body, curl get_info() results].
     */
    public static function curl_get_unsigned(string $endpoint, array $params = []): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$url={$endpoint}; \$params=" . ia_u::var_dump($params);
        $debug && \debugging($debugvars);

        // Sanity check.
        // @phpcs:ignore
        if (!\str_starts_with($endpoint, '/')) {
            $msg = 'Invalid endpoint: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // Set up request params (querystring). Ref API docs at https://www.integrityadvocateserver.com/developers#aEvents.
        $debug && \debugging($fxn . '::About to build $requesturi with $params=' . ($params ? ia_u::var_dump($params) : ''));
        if (strcmp($endpoint, self::ENDPOINT_PING) === 0) {
            $requestapiurl = INTEGRITYADVOCATE_BASEURL_API . $endpoint;
        } else {
            $requestapiurl = INTEGRITYADVOCATE_BASEURL_API . INTEGRITYADVOCATE_API_PATH . $endpoint;
        }
        $requesturi = $requestapiurl . ($params ? '?' . \http_build_query($params, '', '&') : '');
        $debug && \debugging($fxn . '::Built $requesturi=' . $requesturi);

        // Here is a place to test HTTP responses, reponse code and timeouts.
        // $requesturi = 'https://httpstat.us/444';

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_CERTINFO' => 1,
            'CURLOPT_FOLLOWLOCATION' => 1,
            'CURLOPT_MAXREDIRS' => 5,
            'CURLOPT_HEADER' => 0,
            // 'CURLOPT_TIMEOUT' => 5,
        ]);

        $response = $curl->get($requesturi);

        $responseinfo = $curl->get_info();
        $responseinfo['errno'] = $curl->get_errno();
        $responsecode = (int) ($responseinfo['http_code'] ?? -1);
        // Remove certinfo b/c it too much info and we do not need it for \debugging.
        unset($responseinfo['certinfo']);
        $debug && \debugging($fxn . '::Sent url=' . \var_export($endpoint, true) . '; responsecode=' . \var_export($responsecode, true));
        $debug && \debugging($fxn . '::Got responseinfo=' . ia_u::var_dump($responseinfo));
        $debug && \debugging($fxn . '::Got response body=' . htmlentities(\var_export($response, true)));

        return [$responsecode, $response, $responseinfo];
    }

    /**
     * Interact with the IA-side API to get results.
     *
     * @param string $endpoint One of the self::ENDPOINT* constants.
     * @param string $apikey The API Key to get data for
     * @param string $appid The AppId to get data for
     * @param array $params API params per the URL above.  e.g. ['participantidentifier'=>$user_identifier].
     * @return mixed The JSON-decoded curl response body - see json_decode() return values.
     */
    public static function get(string $endpoint, string $apikey, string $appid, array $params = []) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$endpointpath={$endpoint}; \$apikey={$apikey}; \$appid={$appid}; \$params=" . ia_u::var_dump($params);
        $debug && \debugging($debugvars);

        // If the block is not configured yet, simply return empty result.
        if (empty($apikey) || empty($appid)) {
            return [];
        }

        // Sanity check.
        // @phpcs:ignore
        if (!\str_starts_with($endpoint, '/') || !ia::is_valid_apikey($apikey) || !ia_u::is_guid($appid)) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $blockinstanceid = $params['blockinstanceid'] ?? -1;
        unset($params['blockinstanceid']);

        // Make sure the required params are present, there's no extra params, and param types are valid.
        self::validate_endpoint_params($endpoint, $params);

        // For the Participants and ParicipantSessions endpoints, add the remaining part of the URL.
        if ($endpoint === self::ENDPOINT_PARTICIPANTS || $endpoint === self::ENDPOINT_PARTICIPANTSESSIONS) {
            $endpoint = \str_replace('courseid', $params['courseid'], $endpoint);
            unset($params['courseid']);
        }

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'perrequest');
        $cachekey = ia_mu::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__, $endpoint, $appid]) . \json_encode($params, \JSON_PARTIAL_OUTPUT_ON_ERROR));
        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && \debugging($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        // Set up request params (querystring). Ref API docs at https://www.integrityadvocateserver.com/developers#aEvents.
        $debug && \debugging($fxn . '::About to build $requesturi with $params=' . ($params ? ia_u::var_dump($params) : ''));
        $requestapiurl = INTEGRITYADVOCATE_BASEURL_API . INTEGRITYADVOCATE_API_PATH . $endpoint;
        $requesturi = $requestapiurl . ($params ? '?' . \http_build_query($params, '', '&') : '');
        $debug && \debugging($fxn . '::Built $requesturi=' . $requesturi);

        // Build the request signature to put in the header later.
        $requesttimestamp = \time();
        $requestmethod = 'GET';
        $microtime = \explode(' ', \microtime());
        $nonce = $microtime[1] . \mb_substr($microtime[0], 2, 6);
        $debug && \debugging($fxn . "::About to build \$requestsignature from \$requesttimestamp={$requesttimestamp}; "
            . "\$requestmethod={$requestmethod}; \$nonce={$nonce}; \$apikey={$apikey}; \$appid={$appid}");
        $requestsignature = self::get_request_signature($requestapiurl, $requestmethod, $requesttimestamp, $nonce, $apikey, $appid);

        // Set cache to false, otherwise caches for the duration of $CFG->curlcache.
        $curl = new \curl(['cache' => false]);
        $curl->setopt([
            'CURLOPT_CERTINFO' => 1,
            'CURLOPT_FOLLOWLOCATION' => 1,
            'CURLOPT_MAXREDIRS' => 5,
            'CURLOPT_RETURNTRANSFER' => 1,
            'CURLOPT_SSL_VERIFYPEER' => 1,
        ]);

        $header = \implode(':', ["Authorization: amx {$appid}", $requestsignature, $nonce, $requesttimestamp]);
        $curl->setHeader([$header]);
        $curl->setHeader(['X-IntegrityAdvocate-Blockversion:' . \get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version')]);
        $curl->setHeader(["X-IntegrityAdvocate-Appid:{$appid}"]);
        $curl->setHeader(["X-IntegrityAdvocate-Blockinstanceid:{$blockinstanceid}"]);
        $debug && \debugging($fxn . '::Set $header=' . $header);

        // This returns bool|string.
        $response = $curl->get($requesturi);

        $responseparsed = \json_decode((string)$response);

        $responseinfo = $curl->get_info();
        $responseinfo['errno'] = $curl->get_errno();
        $responsecode = (int) ($responseinfo['http_code'] ?? -1);
        // Remove certinfo b/c it too much info and we do not need it for \debugging.
        unset($responseinfo['certinfo']);
        $debug && \debugging($fxn . '::$responseinfo=' . ia_u::var_dump($responseinfo));

        $debug && \debugging($fxn . '::Sent url=' . \var_export($endpoint, true) . '; responsecode=' . \var_export($responsecode, true));
        $debug && \debugging($fxn . '::Got responseinfo=' . ia_u::var_dump($responseinfo));
        $debug && \debugging($fxn . '::Got response body=' . htmlentities(\var_export($response, true)));
        $debug && \debugging($fxn . 'Got responseparsed=' . ia_u::var_dump($responseparsed));

        $success = \in_array($responsecode, \array_merge(self::HTTP_CODE_SUCCESS, self::HTTP_CODE_REDIRECT, self::HTTP_CODE_CLIENTERROR), true);
        if (!$success) {
            $msg = $fxn . '::Request to the IA server failed on: ' . $requestmethod . ' url=' . \var_export($requesturi, true)
                . '; Response http_code=' . ia_u::var_dump($responsecode);
            \debugging($msg);

            $userid = isset($USER->id) ? $USER->id : 0;
            // Cache so we only log once per user session.
            $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
            $cachekey = ia_mu::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__, 'ia_request_failed', $userid, $appid, $requestmethod, $requesturi, $responsecode]));
            if (!$cache->get($cachekey)) {
                global $USER;
                $params = [
                    'context' => \context_system::instance(),
                    'relateduserid' => $userid,
                    'other' => [
                        'method' => $requestmethod,
                        'url' => $requesturi,
                        'responsecode' => $responsecode,
                    ],
                ];
                $event = \block_integrityadvocate\event\ia_request_failed::create($params);
                $event->trigger();

                if (!$cache->set($cachekey, 1)) {
                    throw new \Exception('Failed to set value in the cache');
                }
            }

            throw new HttpException($msg, $responsecode, $requesturi);
        }

        if ($responseparsed === null && \json_last_error() === \JSON_ERROR_NONE) {
            $msg = 'Error: json_decode found no results: ' . \json_last_error_msg();
            $debug && \debugging($fxn . '::' . $msg);
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
        return new \moodle_url(
            INTEGRITYADVOCATE_BASEURL_API . '/participants/integrity',
            [
                'appid' => $appid,
                'courseid' => $courseid,
                'activityid' => $cmid,
                'participantidentifier' => $user->id,
                'participantfirstname' => $user->firstname,
                'participantlastname' => $user->lastname,
                'participantemail' => $user->email,
            ]
        );
    }

    /**
     * Get a single IA proctoring participant data from the remote API.
     * The IA remote endpoint does not allow specifying activityid so you'll
     * have to iterate sessions[] to get the relevant records for just one module.
     *
     * @param string $apikey The API Key to get data for.
     * @param string $appid The AppId to get data for.
     * @param int $courseid The courseid.
     * @param int $userid The userid.
     * @param int $blockinstanceid The block instance id.
     * @return null|Participant Null if nothing found; else the parsed Participant object.
     */
    public static function get_participant(string $apikey, string $appid, int $courseid, int $userid, int $blockinstanceid): ?Participant {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid}; \$userid={$userid}";
        $debug && \debugging($debugvars);

        // Sanity check.
        if (!ia::is_valid_apikey($apikey) || !ia_u::is_guid($appid)) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        if (empty($userid)) {
            return null;
        }

        // This gets a json-decoded object of the IA API curl result.
        $participantraw = self::get(
            self::ENDPOINT_PARTICIPANT,
            $apikey,
            $appid,
            [
                'courseid' => $courseid,
                'participantidentifier' => $userid,
                'blockinstanceid' => $blockinstanceid,
            ]
        );
        $debug && \debugging($fxn . '::Got $participantraw=' . ia_u::var_dump($participantraw));
        if (ia_u::is_empty($participantraw) || !($participantraw instanceof \stdClass)) {
            $debug && \debugging($fxn . '::' . \get_string('no_remote_participants', INTEGRITYADVOCATE_BLOCK_NAME));
            return null;
        }

        $participant = self::parse_participant($participantraw);
        if (!($participant instanceof Participant)) {
            return null;
        }
        $debug && \debugging($fxn . '::Built $participant=' . ia_u::var_dump($participant));

        return $participant;
    }

    /**
     * Get IA proctoring participants from the remote API for the given inputs. Note there is no session data attached to these
     * results.
     *
     * @param string $apikey The API Key to get data for.
     * @param string $appid The AppId to get data for.
     * @param int $courseid Get info for this course.
     * return array<int moodleuserid, Participant> Empty array if nothing found; else array of IA participants objects; keys are
     * @return array<int,Participant> Empty array if nothing found; else array of IA participants objects; keys are
     * Moodle user ids.
     */
    public static function get_participants(string $apikey, string $appid, int $courseid): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid}";
        $debug && \debugging($debugvars);

        // Sanity check.
        if (!ia::is_valid_apikey($apikey) || !ia_u::is_guid($appid)) {
            $msg = 'Input params are invalid - both these must be true: ia::is_valid_apikey($apikey)=' .
                ia::is_valid_apikey($apikey) . '; ia_u::is_guid($appid)=' . ia_u::is_guid($appid);
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        raise_memory_limit(MEMORY_EXTRA);
        $params = ['courseid' => $courseid];

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'perrequest');
        $cachekey = ia_mu::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__, $debugvars]));
        if (($participantscached = $cache->get($cachekey)) && isset($participantscached->modified) && $participantscached->modified > 0) {
            // We have a cached participants set, so get only those modified after the modified timestamp.
            $params += ['lastmodified' => $participantscached->modified];
            $debug && \debugging($fxn . '::Got some $participantscached=' . ia_u::var_dump($participantscached));
        } else {
            $participantscached = new ParticipantsCache($courseid);
        }

        // This gets a json-decoded object of the IA API curl result.
        $participantscached->participantsraw = \array_merge($participantscached->participantsraw, self::get_participants_data($apikey, $appid, $params));
        $debug && \debugging($fxn . '::After get_participants_data() and merge, count($participantscached)=' .
            ia_u::count_if_countable($participantscached->participantsraw) . '; API result=' .
            ia_u::var_dump($participantscached->participantsraw));

        if (ia_u::is_empty($participantscached->participantsraw)) {
            $debug && \debugging($fxn . '::' . \get_string('no_remote_participants', INTEGRITYADVOCATE_BLOCK_NAME));
            $cache->delete($cachekey);
            return [];
        }

        $debug && \debugging($fxn . '::About to process the participants returned');
        $participantsparsed = [];
        foreach ($participantscached->participantsraw as $pr) {
            $debug && \debugging($fxn . '::Looking at $pr=' . ia_u::var_dump($pr));
            if (ia_u::is_empty($pr) || !($pr instanceof \stdClass)) {
                $debug && \debugging($fxn . '::Skip: This $pr entry is empty');
                continue;
            }

            // Parse the participants returned.
            $participant = self::parse_participant($pr);
            if (!($participant instanceof Participant)) {
                continue;
            }
            $debug && \debugging($fxn . '::Built $participant=' . ia_u::var_dump($participant));

            // Skip if parsing failed.
            if (ia_u::is_empty(($participant))) {
                $debug && \debugging($fxn . '::Skip: The $pr failed to parse');
                continue;
            }

            $debug && \debugging($fxn . '::About to add participant with $participant->participantidentifier=' .
                $participant->participantidentifier . ' to the list of ' . \count($participantsparsed) . ' participants');
            $participantsparsed[$participant->participantidentifier] = $participant;

            // Update the participants list lastmodified if needed.
            if ($participant->modified > $participantscached->modified) {
                $debug && \debugging($fxn . "::Updated \$participantscached->modified={$participantscached->modified}");
                $participantscached->modified = $participant->modified;
            }
        }

        // Cache the participants list so we can just get the "modified since x time" ones next time.
        if (!ia_u::is_empty($participantscached->participantsraw) && !$cache->set($cachekey, $participantscached)) {
            throw new \Exception('Failed to set value in the cache');
        }
        $debug && \debugging($fxn . "::Cached the participants list with \$participantscached->modified={$participantscached->modified}");
        $debug && \debugging($fxn . '::About to return count($participantsparsed)=' . ia_u::count_if_countable($participantsparsed));

        return $participantsparsed;
    }

    /**
     * Get IA participant data (non-parsed) for multiple course-users. There is no ability here to filter by user or module, so filter
     * the results in the calling function. Note there is no session data attached to these results.
     *
     * @param string $apikey The API key.
     * @param string $appid The Appid.
     * @param array $params Query params in key-value format: courseid=>someval is required.  Optional externaluserid=user email.
     * @param string $nexttoken The next token to get subsequent results from the API.
     * @return array<moodleuserid,Participant> Empty array if nothing found; else array of IA participants objects; keys are Moodle
     * user ids.
     */
    private static function get_participants_data(string $apikey, string $appid, array $params, $nexttoken = null): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$params=" . \json_encode($params, \JSON_PARTIAL_OUTPUT_ON_ERROR) . " \$nexttoken={$nexttoken}";
        $debug && \debugging($debugvars);

        static $recursecountparticipants = 0;

        // Stop recursion when $result->NextToken = 'null'. WTF: It's a string with content 'null' when other fields returned are
        // actual NULL.
        if ($nexttoken == 'null') {
            return [];
        }

        // Sanity check. We are not validating $nexttoken b/c I don't actually care what the value is - only the remote API does.
        if (!ia::is_valid_apikey($apikey) || !ia_u::is_guid($appid) || !isset($params['courseid']) || !\is_number($params['courseid'])) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }
        // Make sure $params contains only valid parameters.
        foreach (\array_keys($params) as $key) {
            if (!\in_array($key, ['courseid', 'externaluserid', 'lastmodified'], true)) {
                $msg = 'Input params are invalid: ' . $debugvars;
                \debugging($fxn . '::' . $msg . '::' . $debugvars);
                throw new \InvalidArgumentException($msg);
            }
        }

        if ($nexttoken) {
            $params['nexttoken'] = $nexttoken;
        }

        // The $result is an array from the json-decoded results.
        $result = self::get(self::ENDPOINT_PARTICIPANTS, $apikey, $appid, $params);
        $debug && \debugging($fxn . '::Got API result=' . ia_u::var_dump($result));

        if (ia_u::is_empty($result)) {
            $debug && \debugging($fxn . '::' . \get_string('no_remote_participants', INTEGRITYADVOCATE_BLOCK_NAME));
            return [];
        }

        $participants = $result->Participants;
        $debug && \debugging($fxn . '::$result->NextToken=:' . $result->NextToken);

        if (isset($result->NextToken) && !empty($result->NextToken) && ($result->NextToken != $nexttoken)) {
            // Check if we should recurse any more.
            $debug && \debugging($fxn . '::Started with $recursecountparticipants=' . $recursecountparticipants);
            if ($recursecountparticipants++ > self::RECURSEMAX) {
                throw new \Exception($fxn . "::Maximum recursion limit={$recursecountparticipants} reached: params=" . \json_encode($params, \JSON_PARTIAL_OUTPUT_ON_ERROR));
            }

            $debug && \debugging($fxn . '::About to recurse to get more results');
            // The nexttoken value is only needed for the above get request.
            unset($params['nexttoken']);
            // Attempt to not die, then recurse.
            \core_php_time_limit::raise();
            $participants = \array_merge($participants, self::get_participants_data($apikey, $appid, $params, $result->NextToken));
            $recursecountparticipants--;
        }

        // Disabled on purpose: $debug && \debugging($fxn . '::About to return $participants=' . ia_u::var_dump($participants));.
        $debug && \debugging($fxn . '::About to return count($participants)=' . ia_u::count_if_countable($participants));
        return $participants;
    }

    /**
     * Get IA proctoring participant sessions from the remote API for the given inputs.
     *
     * @param string $apikey The API Key to get data for.
     * @param string $appid The AppId to get data for.
     * @param int $courseid Get info for this course.
     * @param int $moduleid Get info for this course module.
     * @param int $userid Optionally get info for this user.
     * @param int $limit Optionally limit to this number of results.  Min 0; max 10; default=IA API default (10).
     * @return array Empty array if nothing found; Else array of Session objects.
     */
    public static function get_participantsessions(string $apikey, string $appid, int $courseid, int $moduleid, int $userid = -1, int $limit = 0): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid}; \$moduleid={$moduleid}; \$userid={$userid}; \$limit={$limit}";
        $debug && \debugging($debugvars);

        // Sanity check.
        if (!ia::is_valid_apikey($apikey) || !ia_u::is_guid($appid) || $courseid < 1 || $moduleid < 1) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // Build the parameters array to pass to the API data getter.
        $params = [
            'courseid' => $courseid,
            'activityid' => $moduleid,
        ];
        ($userid > 0) && ($params['participantidentifier'] = $userid);
        if ($limit > 0 && $limit < 100) {
            $params['limit'] = $limit;
            $params['backwardsearch'] = 'true';
        }

        // This gets a json-decoded object of the IA API curl result.
        $participantsessionsraw = self::get_participantsessions_data($apikey, $appid, $params);
        $debug && \debugging($fxn . '::Got ' . ia_u::count_if_countable($participantsessionsraw) . ' API results= ' . ia_u::var_dump($participantsessionsraw));

        if (ia_u::is_empty($participantsessionsraw)) {
            $debug && \debugging($fxn . '::' . \get_string('no_remote_participant_sessions', INTEGRITYADVOCATE_BLOCK_NAME));
            return [];
        }

        $parsedparticipantsessions = self::attach_sessions_to_mock_participants($courseid, $moduleid, $userid, $participantsessionsraw);

        $debug && \debugging($fxn . '::About to return count($parsedparticipantsessions)=' . ia_u::count_if_countable($parsedparticipantsessions));
        $debug && \debugging($fxn . '::About to return $parsedparticipantsessions=' . ia_u::var_dump($parsedparticipantsessions));
        return $parsedparticipantsessions;
    }

    /**
     * Get IA participant sessions data (non-parsed) for 1+ course-users. There is no ability here to filter by course or user, so
     * filter the results in the calling function. Note there is no session data attached to these results.
     *
     * @param string $apikey The API key.
     * @param string $appid The AppId.
     * @param array $params Query params in key-value format: [courseid=>intval, activityid=>intval] are required, optional
     * userid=>intval.
     * @param string $nexttoken The next token to get subsequent results from the API.
     * @return array Empty array if nothing found; Else array of Session objects.
     */
    private static function get_participantsessions_data(string $apikey, string $appid, array $params, $nexttoken = null): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$params=" . \json_encode($params, \JSON_PARTIAL_OUTPUT_ON_ERROR) . " \$nexttoken={$nexttoken}";
        $debug && \debugging($debugvars);

        static $recursecountparticipantsessions = 0;

        // Stop recursion when $result->NextToken = 'null'. WTF: It's a string with content 'null' when other fields returned are
        // actual NULL.
        if ($nexttoken == 'null') {
            return [];
        }

        // Sanity check. We are not validating $nexttoken b/c I don't actually care what the value is - only the remote API does.
        if (
            !ia::is_valid_apikey($apikey) || !ia_u::is_guid($appid) ||
            !isset($params['courseid']) || !\is_number($params['courseid']) ||
            !isset($params['activityid']) || !\is_number($params['activityid']) ||
            (isset($params['participantidentifier']) && !\is_number($params['participantidentifier']))
        ) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }
        foreach (\array_keys($params) as $key) {
            if (!\in_array($key, ['courseid', 'activityid', 'participantidentifier', 'limit', 'backwardsearch'], true)) {
                $msg = "Input param {$key} is invalid";
                \debugging($fxn . '::' . $msg . '::' . $debugvars);
                throw new \InvalidArgumentException($msg);
            }
        }

        if ($nexttoken) {
            $params['nexttoken'] = $nexttoken;
        }

        // The $result is an array from the json-decoded results.
        $result = self::get(self::ENDPOINT_PARTICIPANTSESSIONS, $apikey, $appid, $params);
        $debug && \debugging($fxn . '::Got API result=' . ia_u::var_dump($result));

        if (ia_u::is_empty($result)) {
            $debug && \debugging($fxn . '::' . \get_string('no_remote_participant_sessions', INTEGRITYADVOCATE_BLOCK_NAME));
            return [];
        }

        $participantsessions = $result->ParticipantSessions;
        $debug && \debugging($fxn . '::count($participantsessions)=' . ia_u::count_if_countable($participantsessions) . '; isset($params[\'limit\'])=' . isset($params['limit']));

        if (isset($params['limit']) && ia_u::count_if_countable($participantsessions) >= $params['limit']) {
            $debug && \debugging($fxn . '::Found ($params[\'limit\']=' . $params['limit'] . ' and we have reached that number of $participantsessions');
        } else {
            $debug && \debugging($fxn . "::We have no limit set or have not reached it; check for a NextToken: \$result->NextToken={$result->NextToken}");
            if (isset($result->NextToken) && !empty($result->NextToken) && ($result->NextToken != $nexttoken)) {
                // Check if we should recurse any more.
                $debug && \debugging($fxn . '::Started with $recursecountparticipantsessions=' . $recursecountparticipantsessions);
                if ($recursecountparticipantsessions++ > self::RECURSEMAX) {
                    throw new \Exception($fxn . "::Maximum recursion limit={$recursecountparticipantsessions} "
                        . "reached: params=" . \json_encode($params, \JSON_PARTIAL_OUTPUT_ON_ERROR));
                }

                $debug && \debugging($fxn . '::About to recurse to get more results');
                // The nexttoken value is only needed for the above get request.
                unset($params['nexttoken']);
                // Attempt to not die, then recurse.
                \core_php_time_limit::raise();
                $participantsessions = \array_merge($participantsessions, self::get_participantsessions_data($apikey, $appid, $params, $result->NextToken));
                $recursecountparticipantsessions--;
            }
        }

        // Disabled on purpose: $debug && \debugging($fxn . '::About to return $participantsessions=' .
        // ia_u::var_dump($participantsessions));.
        $debug && \debugging($fxn . '::About to return count($participantsessions)=' . ia_u::count_if_countable($participantsessions));
        return $participantsessions;
    }

    /**
     * Sessions must be attached to parent participant objects.  For each participantsessions raw data, attach it to a mock
     * Participant parent.
     *
     * @param int $courseid The courseid.
     * @param int $moduleid The moduleid.
     * @param int $userid The userid.
     * @param array $participantsessionsraw An array representing the ParticipantSession response body from the IA API.
     * @return array ParticipantSession An array of parsed ParticipantSession objects with parent attribute populated with mock
     * Participant objects.
     */
    private static function attach_sessions_to_mock_participants(int $courseid, int $moduleid, int $userid, array $participantsessionsraw): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$courseid={$courseid}; \$moduleid={$moduleid}; \$participantsessionsraw=" . ia_u::var_dump($participantsessionsraw);
        $debug && \debugging($debugvars);

        // Sanity check.
        if ($courseid < 1 || $moduleid < 1) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        if (empty($participantsessionsraw) || $userid < 1) {
            return [];
        }

        // Cache so multiple calls don't repeat the same work.  Persession cache b/c is keyed on hash of $input.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = ia_mu::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__, $courseid, $moduleid, $userid]) .
            \json_encode($participantsessionsraw, \JSON_PARTIAL_OUTPUT_ON_ERROR));
        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && \debugging($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        // Sessions must be attached to parent participant objects. Collect them here as we retrieve the data and build them.
        $participants = [];

        $debug && \debugging($fxn . '::About to process the participantsessions returned');
        $parsedparticipantsessions = [];
        $newparticipant = new ia_participant();
        foreach ($participantsessionsraw as $pr) {
            $debug && \debugging($fxn . '::Looking at $pr=' . ia_u::var_dump($pr));
            if (ia_u::is_empty($pr) || !isset($pr->ParticipantIdentifier) || !\is_numeric($participantidentifier = $pr->ParticipantIdentifier)) {
                $debug && \debugging($fxn . '::Skip: This $participantsessionsraw entry is empty or invalid');
                continue;
            }
            if ($userid && (int) $participantidentifier !== (int) $userid) {
                $debug && \debugging($fxn . "::Skip: This \$participantidentifier={$participantidentifier} does not match the \$userid={$userid}");
                continue;
            }

            // It is expensive and unneccesary to call the participant API endpoint for each. Sessions will be attached to this mock
            // Participant object.
            if (!isset($participants[$participantidentifier])) {
                $debug && \debugging($fxn . '::$user=' . ia_u::var_dump($user = ia_mu::get_user_as_obj($participantidentifier)));
                switch (true) {
                    case (ia_u::is_empty($user = ia_mu::get_user_as_obj($participantidentifier))):
                        $debug && \debugging($fxn . "::Moodle has no user matching user->id={$participantidentifier}");
                        continue 2;
                    case (!isset($pr->Course_Id) || (int) ($pr->Course_Id) !== (int) $courseid):
                        $debug && \debugging($fxn . "::The participant Course_Id={$pr->Course_Id} is invalid or does not match this Moodle course={$courseid}");
                        continue 2;
                    case (!isset($pr->Activity_Id) || (int) ($pr->Activity_Id) !== (int) $moduleid):
                        $debug && \debugging($fxn . "::The participant Activity_Id={$pr->Activity_Id} is invalid or does not match this Moodle moduleid={$moduleid}");
                        continue 2;
                    case ((int) (ia_mu::get_courseid_from_cmid($moduleid)) !== (int) $courseid):
                        $debug && \debugging($fxn . "::The moduleid={$moduleid} is not part of the course with id={$courseid}");
                        continue 2;
                    case (!($cm = \get_course_and_cm_from_cmid($moduleid, null, $courseid /* Include even if the participant cannot access the module */)[1])):
                        $debug && \debugging($fxn . "::Failed to get the course module from cmid={$moduleid} or the current Moodle user cannot access this module");
                        continue 2;
                    case (!\is_enrolled($cm->context, $user /* Include inactive enrolments. */)):
                        $debug && \debugging($fxn . "::The user with id={$user->id} is no longer enrolled in this course");
                        continue 2;
                }

                $participant = clone $newparticipant;
                $participant->courseid = $courseid;
                $participant->participantidentifier = (int) $participantidentifier;
                $participant->firstname = $user->firstname;
                $participant->lastname = $user->lastname;
                $participant->email = $user->email;
                $participants[$participantidentifier] = $participant;
            }
            $debug && \debugging($fxn . '::Got $participant=' . ia_u::var_dump($participant));

            // Use the stored participant.
            $participant = $participants[$participantidentifier];

            // Parse the participant session returned.
            $participantsession = self::parse_session($pr, $participant);
            $debug && \debugging($fxn . '::Built $participantsession=' . ia_u::var_dump($participantsession));

            // Skip if parsing failed.
            if (ia_u::is_empty(($participantsession)) || !isset($participantsession->id)) {
                $debug && \debugging($fxn . '::Skip: The $participantsession failed to parse');
                continue;
            }

            $debug && \debugging($fxn . '::About to add $participantsession with $participantsession->id=' . $participantsession->id .
                ' to the list of ' . ia_u::count_if_countable($parsedparticipantsessions) . ' participantsessions');
            $parsedparticipantsessions[$participantsession->id] = $participantsession;
        }

        if (!$cache->set($cachekey, $parsedparticipantsessions)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $parsedparticipantsessions;
    }

    /**
     * Get IA proctoring participant sessions activity from the remote API for the given inputs. No photo data is returned.
     *
     * @param string $apikey The API Key to get data for.
     * @param string $appid The AppId to get data for.
     * @param int $courseid Get info for this course.
     * @param int $moduleid Get info for this course module.
     * @param int $userid Get info for this user.
     * @param int $limit Optionally limit to this number of results.  Min=0; max=10; default=IA API default if 0 = 10.
     * @return array Empty array if nothing found; Else array of Session objects with no photo info.
     */
    public static function get_participantsessions_activity(string $apikey, string $appid, int $courseid, int $moduleid, int $userid, int $limit = 0): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$courseid={$courseid}; \$moduleid={$moduleid}; \$userid={$userid}; \$limit={$limit}";
        $debug && \debugging($debugvars);

        // Sanity check.
        if (!ia::is_valid_apikey($apikey) || !ia_u::is_guid($appid) || $moduleid < 1) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        if (empty($userid) || empty($courseid)) {
            $debug && \debugging($fxn . '::Either userid is empty or courseid is empty, so return empty results');
            return [];
        }

        // Build the parameters array to pass to the API data getter.
        $params = [
            'courseid' => $courseid,
            'activityid' => $moduleid,
            'participantidentifier' => $userid,
        ];
        if ($limit > 0 && $limit < 100) {
            $params['limit'] = $limit;
            $params['backwardsearch'] = 'true';
        }

        // This gets a json-decoded object of the IA API curl result.
        $participantsessionsraw = self::get_participantsessions_activity_data($apikey, $appid, $params);
        $debug && \debugging($fxn . '::Got ' . ia_u::count_if_countable($participantsessionsraw) . ' API results= ' . ia_u::var_dump($participantsessionsraw));

        if (ia_u::is_empty($participantsessionsraw)) {
            $debug && \debugging($fxn . '::' . \get_string('no_remote_participant_sessions', INTEGRITYADVOCATE_BLOCK_NAME));
            return [];
        }

        $parsedparticipantsessions = self::attach_sessions_to_mock_participants($courseid, $moduleid, $userid, $participantsessionsraw);

        $debug && \debugging($fxn . '::About to return count($parsedparticipantsessions)=' . ia_u::count_if_countable($parsedparticipantsessions));
        $debug && \debugging($fxn . '::About to return $parsedparticipantsessions=' . ia_u::var_dump($parsedparticipantsessions));
        return $parsedparticipantsessions;
    }

    /**
     * Get IA participant sessions activity data (non-parsed) for 1 course-users. It returns all of a participant’s sessions for a
     * specific activityid sorted by the End timestamp (newest to oldest). A “0” timestamp means that the session has not been ended
     * yet. So if you use backwardssearch (always true here) and limit of 1, you’ll receive the participant’s most recent COMPLETED
     * session. Note there is no photo data attached to these results.
     *
     * @param string $apikey The API key.
     * @param string $appid The AppId.
     * @param array $params Query params in key-value format: [courseid=>intval, activityid=>intval, participantidentifier=>intval]
     * are required.
     * @param string $nexttoken The next token to get subsequent results from the API.
     * @return array Empty array if nothing found; Else array of Session objects.
     */
    private static function get_participantsessions_activity_data(string $apikey, string $appid, array $params, $nexttoken = null): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$apikey={$apikey}; \$appid={$appid}; \$params=" . \json_encode($params, \JSON_PARTIAL_OUTPUT_ON_ERROR) . " \$nexttoken={$nexttoken}";
        $debug && \debugging($debugvars);

        static $recursecountparticipantsessionsactivity = 0;

        // Stop recursion when $result->NextToken = 'null'. WTF: It's a string with content 'null' when other fields returned are
        // actual NULL.
        if ($nexttoken == 'null') {
            return [];
        }

        // Sanity check. We are not validating $nexttoken b/c I don't actually care what the value is - only the remote API does.
        if (
            !ia::is_valid_apikey($apikey) || !ia_u::is_guid($appid) ||
            !isset($params['courseid']) || !\is_number($params['courseid']) ||
            !isset($params['activityid']) || !\is_number($params['activityid']) ||
            !isset($params['participantidentifier']) || !\is_number($params['participantidentifier'])
        ) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }
        foreach (\array_keys($params) as $key) {
            if (!\in_array($key, ['courseid', 'activityid', 'participantidentifier', 'limit', 'backwardsearch'], true)) {
                $msg = "Input param {$key} is invalid";
                \debugging($fxn . '::' . $msg . '::' . $debugvars);
                throw new \InvalidArgumentException($msg);
            }
        }

        if ($nexttoken) {
            $params['nexttoken'] = $nexttoken;
        }

        // The $result is an array from the json-decoded results.
        $result = self::get(self::ENDPOINT_PARTICIPANTSESSIONS_ACTIVITY, $apikey, $appid, $params);
        $debug && \debugging($fxn . '::Got API result=' . ia_u::var_dump($result));

        if (ia_u::is_empty($result)) {
            $debug && \debugging($fxn . '::' . \get_string('no_remote_participant_sessions', INTEGRITYADVOCATE_BLOCK_NAME));
            return [];
        }

        $participantsessions = $result->ParticipantSessions;
        $debug && \debugging($fxn . '::count($participantsessions)=' . ia_u::count_if_countable($participantsessions) . '; isset($params[\'limit\'])=' . isset($params['limit']));

        if (isset($params['limit']) && ia_u::count_if_countable($participantsessions) >= $params['limit']) {
            $debug && \debugging($fxn . '::We have a limit set and we have reached it');
        } else {
            $debug && \debugging($fxn . "::We have no limit set or have not reached it; check for a NextToken: \$result->NextToken={$result->NextToken}");
            if (isset($result->NextToken) && !empty($result->NextToken) && ($result->NextToken != $nexttoken)) {
                // Check if we should recurse any more.
                $debug && \debugging($fxn . '::Started with $recursecountparticipantsessionsactivity=' . $recursecountparticipantsessionsactivity);
                if ($recursecountparticipantsessionsactivity++ > self::RECURSEMAX) {
                    throw new \Exception($fxn . "::Maximum recursion limit={$recursecountparticipantsessionsactivity} reached: params=" .
                        \json_encode($params, \JSON_PARTIAL_OUTPUT_ON_ERROR));
                }

                $debug && \debugging($fxn . '::About to recurse to get more results');
                // The nexttoken value is only needed for the above get request.
                unset($params['nexttoken']);
                // Attempt to not die, then recurse.
                \core_php_time_limit::raise();
                $participantsessions = \array_merge($participantsessions, self::get_participantsessions_activity_data($apikey, $appid, $params, $result->NextToken));
                $recursecountparticipantsessionsactivity--;
            }
        }

        // Disabled on purpose: $debug && \debugging($fxn . '::About to return $participantsessions=' .
        // ia_u::var_dump($participantsessions));.
        $debug && \debugging($fxn . '::About to return count($participantsessions)=' . ia_u::count_if_countable($participantsessions));
        return $participantsessions;
    }

    /**
     * Build the request signature.
     *
     * @param string $requesturi Full API URI with no querystring.
     * @param string $requestmethod Request method e.g. GET, POST, PATCH.
     * @param int $requesttimestamp Unix timestamp of the request.
     * @param string $nonce Nonce built like this: $microtime = explode(' ', microtime()); $nonce = $microtime[1] .
     *      substr($microtime[0], 2, 6);
     * @param string $apikey API key for the block instance.
     * @param string $appid App ID for the block instance.
     * @return string The request signature to be sent in the header of the request.
     */
    public static function get_request_signature(string $requesturi, string $requestmethod, int $requesttimestamp, string $nonce, string $apikey, string $appid): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$requesturi={$requesturi}; \$requestmethod={$requestmethod}; "
            . "\$requesttimestamp={$requesttimestamp}; \$nonce={$nonce}; \$apikey={$apikey}; \$appid={$appid}";
        $debug && \debugging($debugvars);

        // Sanity check.
        if (
            !\filter_var($requesturi, \FILTER_VALIDATE_URL) || \mb_strlen($requestmethod) < 3 ||
            !\is_number($requesttimestamp) || $requesttimestamp < 0 ||
            empty($nonce) || !\is_string($nonce) ||
            !ia::is_valid_apikey($apikey) || !ia_u::is_guid($appid)
        ) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        if (\parse_url($requesturi, \PHP_URL_QUERY)) {
            $msg = 'The requesturi should not contain a querystring';
            \debugging($fxn . "::Started with $requesturi={$requesturi}; \$requestmethod={$requestmethod};  \$requesttimestamp={$requesttimestamp}; "
                . "\$nonce={$nonce}; \$apikey={$apikey}; \$appid={$appid}");
            \debugging($fxn . '::' . $msg);
            throw new \InvalidArgumentException();
        }

        // Create the signature data.
        $signaturerawdata = $appid . $requestmethod . \mb_strtolower(\urlencode($requesturi)) . $requesttimestamp . $nonce;
        $debug && \debugging($fxn . '::Built $signaturerawdata = ' . $signaturerawdata);

        // Decode the API Key.
        $secretkeybytearray = \base64_decode($apikey, true);

        // Encode the signature.
        $signature = \mb_convert_encoding($signaturerawdata, 'UTF-8', mb_list_encodings());

        // Calculate the hash.
        $signaturebytes = \hash_hmac('sha256', $signature, $secretkeybytearray, true);

        // Convert to base64.
        return \base64_encode($signaturebytes);
    }

    /**
     * Get the most recent session from the user's participant info.
     *
     * @param \context $modulecontext The module context to look for IA info in.
     * @param int $userid The userid to get participant info for.
     * @return null|Session Null if nothing found; else the most recent session for that user in that activity.
     */
    public static function get_module_session_latest(\context $modulecontext, int $userid): ?Session {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$modulecontext->instanceid={$modulecontext->instanceid}; \$userid={$userid}";
        $debug && \debugging($debugvars);

        // Sanity check.
        if (ia_u::is_empty($modulecontext) || ($modulecontext->contextlevel !== \CONTEXT_MODULE)) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        if ($userid < 1) {
            return null;
        }

        // Get the APIKey and AppID for this module.
        $blockinstance = ia_mu::get_first_block($modulecontext, INTEGRITYADVOCATE_SHORTNAME, true);
        $debug && \debugging($fxn . '::Got blockinstance with id=' . isset($blockinstance->instance->id) ?: 'empty');

        // If the block is not configured yet, simply return empty result.
        if (ia_u::is_empty($blockinstance) || !ia_u::is_empty($blockinstance->get_config_errors())) {
            $debug && \debugging($fxn . '::The blockinstance is empty or has config errors, so return empty array');
            return null;
        }

        $latestsessions = self::get_participantsessions_activity(
            $blockinstance->config->apikey,
            $blockinstance->config->appid,
            $modulecontext->get_course_context()->instanceid,
            $modulecontext->instanceid,
            $userid,
            1
        );

        // If $latestsession is empty then we didn't find anything.
        if (ia_u::is_empty($latestsessions) || empty($latestsession = reset($latestsessions)) || !($latestsession instanceof Session) || !isset($latestsession->id)) {
            $debug && \debugging($fxn . "::The latest session for userid={$userid} was not found");
            return null;
        }

        return $latestsession;
    }

    /**
     * Get the user's participant status in the module. This may differ from the Participant-level (overall) status, which is not
     * module-specific. There is login and enrolled checks in this function b/c it is used by the IA availability condition.
     *
     * @param \context $modulecontext The module context to look in.
     * @param int $userid The userid to get IA info for.
     * @return int A block_integrityadvocate\Status status constant _INT value.
     */
    public static function get_module_status(\context $modulecontext, int $userid): int {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$modulecontext->instanceid={$modulecontext->instanceid}; \$userid={$userid}";
        $debug && \debugging($debugvars);

        // Sanity check.
        if (ia_u::is_empty($modulecontext) || ($modulecontext->contextlevel !== \CONTEXT_MODULE)) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        // Get the int value representing this constant so it's equivalent to what is stored in Session->status.
        $notfoundval = ia_status::INPROGRESS_INT;

        // Security: Is the user logged in? Copied from moodlelib.php::require_login().
        if ((!isloggedin() || isguestuser())) {
            return $notfoundval;
        }

        // Security: Can the user access this course?
        $course = ia_mu::get_course_as_obj($modulecontext->get_course_context()->instanceid);
        if (!can_access_course($course, $userid, '', true)) {
            $debug && \debugging($fxn . "::The userid={$userid} is not enrolled in the course");
            return $notfoundval;
        }

        // Security: Can the user access this module context?
        if (!\is_enrolled($modulecontext, $userid)) {
            $debug && \debugging($fxn . "::The userid={$userid} is not enrolled in the course");
            return $notfoundval;
        }

        // Get the APIKey and AppID for this module.
        $blockinstance = ia_mu::get_first_block($modulecontext, INTEGRITYADVOCATE_SHORTNAME, true);
        $debug && \debugging($fxn . '::Got blockinstance with id=' . isset($blockinstance->instance->id) ?: 'empty');

        // If the block is not configured yet, simply return empty result.
        if (ia_u::is_empty($blockinstance) || !ia_u::is_empty($blockinstance->get_config_errors())) {
            $debug && \debugging($fxn . '::The blockinstance is empty or has config errors, so return notfoundval');
            return $notfoundval;
        }

        // Build params[].
        $params = [
            'participantidentifier' => $userid,
            'courseid' => $course->id,
            'activityid' => $modulecontext->instanceid,
        ];
        $debug && \debugging($fxn . '::Built params=' . ia_u::var_dump($params));

        // Do a GET https://ca.integrityadvocateserver.com/api/2-0/participantstatus?participantidentifier=123456&courseid=101&activityid=2
        $input = self::get(self::ENDPOINT_PARTICIPANT_STATUS, $blockinstance->config->apikey, $blockinstance->config->appid, $params);
        $debug && \debugging($fxn . '::Got participantstatus input=' . ia_u::var_dump($input));

        // Check returned data required field.
        if (!isset($input->Status) || !\is_string($input->Status) || \mb_strlen($input->Status) < 5) {
            $debug && \debugging($fxn . '::Minimally-required fields not found: Status, so return notfoundval');
            return $notfoundval;
        }

        $status = ia_status::parse_status_string($input->Override_Status ?? $input->Status);
        $debug && \debugging($fxn . '::About to return status=' . ia_u::var_dump($status));

        return $status;
    }

    /**
     * Returns true if the status value for the user in the latest session for the module represents "In Progress". This may differ
     * from the Participant-level (overall) status.
     *
     * @param \context $modulecontext The context to look in.
     * @param int $userid The user id to look for.
     * @return bool True if the status value for the user in the module represents "In Progress".
     */
    public static function is_status_inprogress(\context $modulecontext, int $userid): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$modulecontext->instanceid={$modulecontext->instanceid}; \$userid={$userid}";
        $debug && \debugging($debugvars);

        // Sanity check.
        if (ia_u::is_empty($modulecontext) || ($modulecontext->contextlevel !== \CONTEXT_MODULE)) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        return self::get_module_status($modulecontext, $userid) == ia_status::INPROGRESS_INT;
    }

    /**
     * Returns true if the status value for the user in the latest session for the module represents "Invalid". This may differ from
     * the Participant-level (overall) status.
     *
     * @param \context $modulecontext The context to look in.
     * @param int $userid The user id to look for.
     * @return bool True if the status value for the user in the module represents "Invalid".
     */
    public static function is_status_invalid(\context $modulecontext, int $userid): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$modulecontext->instanceid={$modulecontext->instanceid}; \$userid={$userid}";
        $debug && \debugging($debugvars);

        // Sanity check.
        if (ia_u::is_empty($modulecontext) || ($modulecontext->contextlevel !== \CONTEXT_MODULE)) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $statusinmodule = self::get_module_status($modulecontext, $userid);
        $debug && \debugging($fxn . "::Got \$statusinmodule={$statusinmodule}");
        return ia_status::is_invalid_status((int) $statusinmodule);
    }

    /**
     * Returns true if the status value for the user in the latest session for the module represents "Valid". This may differ from the
     * Participant-level (overall) status.
     *
     * @param \context $modulecontext The context to look in.
     * @param int $userid The user id to look for.
     * @return bool True if the status value for the user in the module represents "Valid".
     */
    public static function is_status_valid(\context $modulecontext, int $userid): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$modulecontext->instanceid={$modulecontext->instanceid}; \$userid={$userid}";
        $debug && \debugging($debugvars);

        // Sanity check.
        if (ia_u::is_empty($modulecontext) || ($modulecontext->contextlevel !== \CONTEXT_MODULE)) {
            $msg = 'Input params are invalid: ' . $debugvars;
            \debugging($fxn . '::' . $msg . '::' . $debugvars);
            throw new \InvalidArgumentException($msg);
        }

        $statusinmodule = self::get_module_status($modulecontext, $userid);
        $debug && \debugging($fxn . "::Got \$statusinmodule={$statusinmodule}");
        return ia_status::is_valid_status((int) $statusinmodule);
    }

    /**
     * Validate $photostring as either a URL or a base64-encoded image and return it, else return empty string.
     *
     * @param string $photostring String to parse.
     * @return string A URL or a base64-encoded image and return it, else return empty string.
     */
    private static function parse_participantphoto(string $photostring): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \debugging($fxn . '::Started with $photostring=' . ia_u::var_dump($photostring));

        $matches = [];
        switch (true) {
            case (\preg_match(INTEGRITYADVOCATE_REGEX_DATAURI, $photostring, $matches)):
                return $matches[0];
            case (!ia_u::is_empty(clean_param($photostring, PARAM_URL))):
                return $photostring;
            default:
                $debug && \debugging($fxn . '::No valid photo found');
                return '';
        }
    }

    /**
     * Extract a Session object from API Participant data, cleaning all the fields.
     *
     * @param \stdClass $input API session data.
     * @param ia_participant $participant Parent Participant object.
     * @return null|Session Null if failed to parse, otherwise a parsed Session object.
     */
    private static function parse_session(\stdClass $input, ia_participant $participant): ?Session {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \debugging($fxn . '::Started with $input=' . ia_u::var_dump($input));

        // Sanity check.
        if (ia_u::is_empty($input)) {
            $debug && \debugging($fxn . '::Empty object found, so return false');
            return null;
        }

        // Cache so multiple calls don't repeat the same work.  Persession cache b/c is keyed on hash of $input.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = ia_mu::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__]) . \json_encode($input, \JSON_PARTIAL_OUTPUT_ON_ERROR));
        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && \debugging($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        $debug && \debugging($fxn . '::About to create \block_integrityadvocate\Session()');
        $output = new \block_integrityadvocate\Session();

        // Check returned data required field #1.
        if (!isset($input->Id) || !ia_u::is_guid($input->Id)) {
            $debug && \debugging($fxn . '::Minimally-required fields not found: Id');
            return null;
        }
        $output->id = $input->Id;
        $debug && \debugging($fxn . '::Got $session->id=' . $output->id);

        // Check returned data required field #2.
        if (!isset($input->Status) || !\is_string($input->Status) || \mb_strlen($input->Status) < 5) {
            $debug && \debugging($fxn . '::Minimally-required fields not found: Status, so return empty');
            return null;
        }

        // This function throws an error if the status is invalid.
        $output->status = ia_status::parse_status_string($input->Status);
        $debug && \debugging($fxn . '::Got $session->status=' . $output->status);
        if (isset($input->Override_Status) && !empty($input->Override_Status)) {
            $output->overridestatus = ia_status::parse_status_string($input->Override_Status);
        }
        $debug && \debugging($fxn . '::Got status=' . $output->status . ' overridestatus=' . $output->overridestatus);

        // Clean int fields.
        if (true) {
            if (isset($input->Activity_Id)) {
                $output->activityid = \clean_param($input->Activity_Id, PARAM_INT);
                if (!($courseid = ia_mu::get_courseid_from_cmid($output->activityid)) || $courseid !== $participant->courseid) {
                    $debug && \debugging($fxn . "::This session activity_id={$output->activityid} belongs to courseid={$courseid} "
                        . "vs participant->courseid={$participant->courseid}, so return empty");
                    return null;
                }
            }
            isset($input->Click_IAmHere_Count) && ($output->clickiamherecount = \clean_param($input->Click_IAmHere_Count, PARAM_INT));
            isset($input->Start) && ($output->start = \clean_param($input->Start, PARAM_INT));
            isset($input->End) && ($output->end = \clean_param($input->End, PARAM_INT));
            isset($input->Exit_Fullscreen_Count) && ($output->exitfullscreencount = \clean_param($input->Exit_Fullscreen_Count, PARAM_INT));
            isset($input->Override_Date) && ($output->overridedate = \clean_param($input->Override_Date, PARAM_INT));
            isset($input->Override_LMSUser_Id) && ($output->overridelmsuserid = \clean_param($input->Override_LMSUser_Id, PARAM_INT));
        }
        $debug && \debugging($fxn . '::Done int fields');

        // Clean text fields.
        if (true) {
            isset($input->Override_LMSUser_FirstName) && ($output->overridelmsuserfirstname = \clean_param($input->Override_LMSUser_FirstName, PARAM_TEXT));
            isset($input->Override_LMSUser_LastName) && ($output->overridelmsuserlastname = \clean_param($input->Override_LMSUser_LastName, PARAM_TEXT));
            isset($input->Override_Reason) && ($output->overridereason = \clean_param($input->Override_Reason, PARAM_TEXT));
        }

        // Clean URL fields.
        if (true) {
            isset($input->ResubmitUrl) && ($output->resubmiturl = \filter_var($input->ResubmitUrl, \FILTER_SANITIZE_URL));
        }
        $debug && \debugging($fxn . '::Done url fields');

        // This Photo field is either a URL or a data uri ref https://css-tricks.com/data-uris/.
        if (isset($input->Participant_Photo)) {
            $output->participantphoto = self::parse_participantphoto($input->Participant_Photo);
        }

        // Link in the parent Participant object.
        $output->participant = $participant;

        if (!$cache->set($cachekey, $output)) {
            throw new \Exception('Failed to set value in the cache');
        }

        $debug && \debugging($fxn . '::About to return $session=' . ia_u::var_dump($output));
        return $output;
    }

    /**
     * Extract a Participant object from API data, cleaning all the fields.
     *
     * @param \stdClass $input API participant data
     * @return null|Participant Null if failed to parse, otherwise the parsed Participant object.
     */
    public static function parse_participant(\stdClass $input): ?Participant {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \debugging($fxn . '::Started with $input=' . ia_u::var_dump($input));

        // Sanity check.
        if (ia_u::is_empty($input)) {
            $debug && \debugging($fxn . '::Empty object found, so return false');
            return null;
        }

        // Check for minimally-required data.
        if (!isset($input->ParticipantIdentifier) || !isset($input->Course_Id)) {
            $debug && \debugging($fxn . '::Minimally-required fields not found');
            return null;
        }
        $debug && \debugging($fxn . '::Minimally-required fields found');

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = ia_mu::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__, \json_encode($input, \JSON_PARTIAL_OUTPUT_ON_ERROR)]));
        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && \debugging($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }
        $debug && \debugging($fxn . '::Not a cached value; build a Participant');

        $output = new ia_participant();

        // Clean int fields.
        if (true) {
            isset($input->ParticipantIdentifier) && ($output->participantidentifier = \clean_param($input->ParticipantIdentifier, PARAM_INT));
            isset($input->Course_Id) && ($output->courseid = \clean_param($input->Course_Id, PARAM_INT));
            if (isset($input->Email) && !empty(($val = \clean_param($input->Email, PARAM_EMAIL)))) {
                $output->email = $val;
            }

            // Check for minimally-required data.
            if (!isset($output->participantidentifier) || !isset($output->courseid) || !isset($output->email)) {
                $debug && \debugging($fxn . '::Minimally-required fields not found');
                return null;
            }

            $userid = $output->participantidentifier;
            $courseid = $output->courseid;
            switch (true) {
                case (!($user = ia_mu::get_user_as_obj($userid))):
                    $debug && \debugging($fxn . "::User not found for participantidentifier={$userid}");
                    return null;
                case (ia_u::is_empty(\get_course($courseid)) || ia_u::is_empty($coursecontext = \context_course::instance($courseid, MUST_EXIST))):
                    $debug && \debugging($fxn . "::Invalid \$courseid={$courseid} specified or course context not found");
                    return null;
                case (!\is_enrolled($coursecontext, $user /* Include inactive enrolments. */)):
                    $debug && \debugging($fxn . "::User {$user->id} is not enrolled in courseid={$courseid}");
                    return null;
            }

            isset($input->Created) && ($output->created = \clean_param($input->Created, PARAM_INT));
            isset($input->Modified) && ($output->modified = \clean_param($input->Modified, PARAM_INT));

            isset($input->Override_Date) && ($output->overridedate = \clean_param($input->Override_Date, PARAM_INT));
            isset($input->Override_LMSUser_Id) && ($output->overridelmsuserid = \clean_param($input->Override_LMSUser_Id, PARAM_INT));
        }
        $debug && \debugging($fxn . '::Done int fields');

        // Clean text fields.
        if (true) {
            isset($input->FirstName) && ($output->firstname = \clean_param($input->FirstName, PARAM_TEXT));
            isset($input->LastName) && ($output->lastname = \clean_param($input->LastName, PARAM_TEXT));

            isset($input->Override_LMSUser_FirstName) && ($output->overridelmsuserfirstname = \clean_param($input->Override_LMSUser_FirstName, PARAM_TEXT));
            isset($input->Override_LMSUser_LastName) && ($output->overridelmsuserlastname = \clean_param($input->Override_LMSUser_LastName, PARAM_TEXT));
            isset($input->Override_Reason) && ($output->overridereason = \clean_param($input->Override_Reason, PARAM_TEXT));
        }
        $debug && \debugging($fxn . '::Done text fields');

        // Clean URL fields.
        if (true) {
            isset($input->ResubmitUrl) && ($output->resubmiturl = \filter_var($input->ResubmitUrl, \FILTER_SANITIZE_URL));
        }
        $debug && \debugging($fxn . '::Done url fields');

        // This Photo field is either a URL or a data uri ref https://css-tricks.com/data-uris/.
        if (isset($input->Participant_Photo)) {
            $output->participantphoto = self::parse_participantphoto($input->Participant_Photo);
        }

        // Clean status vs allowlist.
        if (isset($input->Status)) {
            $output->status = ia_status::parse_status_string($input->Status);
        }
        if (isset($input->Override_Status) && !empty($input->Override_Status)) {
            $output->overridestatus = ia_status::parse_status_string($input->Override_Status);
        }
        $debug && \debugging($fxn . '::Done status fields');

        // Handle sessions data.
        $output->sessions = [];
        $time = \time();
        if (isset($input->Sessions) && \is_array($input->Sessions)) {
            $debug && \debugging($fxn . '::Found some sessions to look at');
            foreach ($input->Sessions as $s) {
                if (!ia_u::is_empty($session = self::parse_session($s, $output))) {
                    $debug && \debugging($fxn . '::Got a valid session back, so add it to the participant');
                    if (isset($session->end) && ia_u::is_unixtime_past($session->end)) {
                        $session->end = (int) \filter_var($session->end, \FILTER_SANITIZE_NUMBER_INT);
                    } else {
                        $session->end = $time;
                    }
                    $output->sessions[] = $session;
                } else {
                    $debug && \debugging($fxn . '::This session failed to parse');
                }
            }

            // If the session is in progress, update the global status to reflect this.
            if (ia_u::count_if_countable($output->sessions) && ($highestsessiontimestamp = \max(\array_keys($output->sessions)) >= $output->modified)) {
                $output->status = $output->sessions[$highestsessiontimestamp]->status;
            }
        } else {
            $debug && \debugging($fxn . '::No sessions found');
        }

        if (!$cache->set($cachekey, $output)) {
            throw new \Exception('Failed to set value in the cache');
        }
        $debug && \debugging($fxn . '::Done sessions fields. About to return $participant=' . ia_u::var_dump($output));
        return $output;
    }

    /**
     * Make sure the required params are present, there's no extra params, and param types are valid.
     *
     * @param string $endpoint One of the constants self::ENDPOINT*.
     * @param array $params Key-value array of params being sent to the API endpoint.
     * @return bool True if everything seems valid.
     */
    public static function validate_endpoint_params(string $endpoint, array $params = []): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \debugging($fxn . "::Started with \$endpoint={$endpoint}; \$params=" . ($params ? ia_u::var_dump($params) : ''));

        // For each endpoint, specify what the accepted params are and their types.
        switch ($endpoint) {
            case self::ENDPOINT_PARTICIPANT:
                $validparams = [
                    'participantidentifier' => \PARAM_INT,
                    'courseid' => \PARAM_INT,
                ];
                // All params are required.
                $requiredparams = \array_keys($validparams);
                break;
            case self::ENDPOINT_PARTICIPANTS:
                $validparams = [
                    'backwardsearch' => \PARAM_ALPHA,
                    'courseid' => \PARAM_INT,
                    'lastmodified' => \PARAM_INT,
                    'limit' => \PARAM_INT,
                    'nexttoken' => \PARAM_TEXT,
                    'status' => \PARAM_TEXT,
                    'externaluserid' => \PARAM_INT,
                ];
                $requiredparams = ['courseid'];
                break;
            case self::ENDPOINT_PARTICIPANTSESSIONS:
                $validparams = [
                    'activityid' => \PARAM_INT,
                    // PARAM_ALPHA b/c we need to send the string "true" or "false".
                    'backwardsearch' => \PARAM_ALPHA,
                    'courseid' => \PARAM_INT,
                    'lastmodified' => \PARAM_INT,
                    'limit' => \PARAM_INT,
                    'nexttoken' => \PARAM_TEXT,
                    'participantidentifier' => \PARAM_INT,
                    'status' => \PARAM_TEXT,
                ];
                $requiredparams = ['activityid', 'courseid'];
                break;
            case self::ENDPOINT_PARTICIPANTSESSIONS_ACTIVITY:
                $validparams = [
                    'activityid' => \PARAM_INT,
                    // PARAM_ALPHA b/c we need to send the string "true" or "false".
                    'backwardsearch' => \PARAM_ALPHA,
                    'courseid' => \PARAM_INT,
                    'limit' => \PARAM_INT,
                    'nexttoken' => \PARAM_TEXT,
                    'participantidentifier' => \PARAM_INT,
                ];
                $requiredparams = ['activityid'];
                break;
            case self::ENDPOINT_PARTICIPANT_STATUS:
                $validparams = [
                    'activityid' => \PARAM_INT,
                    'courseid' => \PARAM_INT,
                    'participantidentifier' => \PARAM_INT,
                ];
                $requiredparams = ['activityid', 'courseid', 'participantidentifier'];
                break;
            default:
                throw new \InvalidArgumentException("Unhandled endpoint={$endpoint}");
        }

        // If there are params missing $requiredparams[] list, throw an exception. Compare the incoming keys in $params vs the list of
        // required params (the values of that array).
        if ($missingparams = \array_diff($requiredparams, \array_keys($params))) {
            $msg = 'The ' . $endpoint . ' endpoint requires params=' . \implode(', ', $missingparams) . '; got params=' . \implode(', ', \array_keys($params));
            \debugging($fxn . '::' . $msg);
            throw new \invalid_parameter_exception($msg);
        }
        $debug && \debugging($fxn . '::Done check for missingparams');

        // If there are params specified that are not in the $validparams[] list, they are invalid params. Use array_diff_key: Returns
        // an array containing all the entries from array1 whose keys are absent from all of the other arrays.
        if ($extraparams = \array_diff_key($params, $validparams)) {
            $msg = 'The ' . $endpoint . ' endpoint does not accept params=' . \implode(', ', \array_keys($extraparams)) . '; got params=' . \implode(', ', \array_keys($params));
            \debugging($fxn . '::' . $msg);
            throw new \invalid_parameter_exception($msg);
        }
        $debug && \debugging($fxn . '::Done check for extraparams');

        // Check each of the param types matches what is specified in $validparams[] for that param. Throws an exception if there is a
        // mismatch.
        $remotestatuses = ia_status::get_statuses();
        unset($remotestatuses[ia_status::NOTSTARTED_INT]);
        $truefalse = ['true', 'false'];
        foreach ($params as $paramname => $paramval) {
            try {
                $debug && \debugging($fxn . "::For \$paramname={$paramname}, about to validate_param(\$paramval={$paramval}, \$validparams[\$paramname]=$validparams[$paramname])");
                \validate_param($paramval, $validparams[$paramname]);
                switch ($paramname) {
                    case 'backwardsearch':
                        if (!\in_array($paramval, $truefalse, true)) {
                            throw new \invalid_parameter_exception("The param {$paramname} val={$paramval} is not a string in the list [true, false]");
                        }
                        break;
                    case 'statuses':
                        if (!\in_array($paramval, $remotestatuses, true)) {
                            throw new \invalid_parameter_exception("The param {$paramname} val={$paramval} is not a valid status on the IA side");
                        }
                        break;
                    default:
                        // These paramname items they are checked in the switch-case above: [courseid, activityid, participantidentifier, limit].
                        $debug && \debugging($fxn . "::Missing check for this paramname={$paramname}");
                }
            } catch (\Exception $e) {
                // Log a more useful message than Moodle gives us, then just throw it again.
                \debugging($fxn . '::The param is valid but the type is wrong for param=' . $paramname . '; $paramval=' . ia_u::var_dump($paramval));
                throw $e;
            }
        }

        // Everything is valid.
        return true;
    }
}
