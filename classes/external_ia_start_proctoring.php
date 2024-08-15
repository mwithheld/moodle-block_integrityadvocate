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
 * IntegrityAdvocate external functions - things to do when the IA proctoring actually starts
 * (after rules, photo, ID, room check etc).
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

defined('MOODLE_INTERNAL') || die;
require_once(\dirname(__DIR__) . '/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
require_once($CFG->dirroot . '/mod/quiz/accessmanager.php');

use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

/**
 * External methods to run when IA proctoring setup (rules, ID card, room check etc.) is complete and the proctoring actually begins.
 */
trait external_ia_start_proctoring {

    /**
     *
     * @var quiz_attempt $attemptThe quiz attempt object.
     */
    private static $attemptobj;

    /**
     * Describes the parameters for these functions - these are reused.
     *
     * @return \external_function_parameters The parameters for these functions.
     */
    private static function start_proctoring_function_params(): \external_function_parameters {
        return new \external_function_parameters(
            [
                'attemptid' => new \external_value(PARAM_INT, 'attemptid'),
            ]
        );
    }

    /**
     * Calls self::validate_params() and check for things that should make this external request fail.
     *
     * @param int $attemptid The quiz attempt id.
     * @return array Result array that sent back as the AJAX result.
     */
    private static function start_proctoring_validate_params(int $attemptid): array {
        global $USER;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debugvars = $fxn . "::Started with \$attemptid={$attemptid}";
        $debug && \debugging($debugvars);

        self::validate_parameters(
            self::start_proctoring_function_params(),
            [
                'attemptid' => $attemptid,
            ]
        );

        $result = [
            'submitted' => false,
            'success' => true,
            'warnings' => [],
        ];
        $blockversion = \get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version');
        $coursecontext = null;

        $debug && \debugging($fxn . '::About to check for things that should make this fail');
        switch (true) {
            case (!\confirm_sesskey()):
                \debugging($fxn . '::Failed check: confirm_sesskey');
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => \get_string('confirmsesskeybad'),
                ];
                break;
            case (!\defined('INTEGRITYADVOCATE_FEATURE_QUIZATTEMPT_TIME_UPDATED') || !INTEGRITYADVOCATE_FEATURE_QUIZATTEMPT_TIME_UPDATED):
                \debugging($fxn . '::Failed check: Feature disabled');
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => 'Feature is disabled',
                ];
                break;
            case (!(self::$attemptobj = \quiz_attempt::create($attemptid)) || get_class(self::$attemptobj) !== 'quiz_attempt'):
                \debugging($fxn . '::Failed check: Create quiz_attempt');
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => 'Failed to get quiz attempt',
                ];
                break;
            case (self::$attemptobj->is_finished()):
                \debugging($fxn . '::Failed check: Create quiz_attempt');
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => 'Quiz attempt is finished',
                ];
                break;
            case (!ia_mu::is_block_visible_on_quiz_attempt(self::$attemptobj->get_cmid(), INTEGRITYADVOCATE_SHORTNAME)):
                \debugging($fxn . '::Failed check: Get courseid');
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => 'Failed is_block_visible_on_quiz_attempt',
                ];
                break;
            case (!($courseid = self::$attemptobj->get_courseid())):
                \debugging($fxn . '::Failed check: Get courseid');
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => 'Failed to get quiz attempt::courseid',
                ];
                break;
            case (!($coursecontext = \context_course::instance($courseid))):
                \debugging($fxn . '::Failed check: Get coursecontext');
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => 'The course context is invalid',
                ];
                break;
            case (!($userid = self::$attemptobj->get_userid())):
                \debugging($fxn . '::Failed check: Get userid');
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => 'Failed to get quiz attempt::userid',
                ];
                break;
            case ((int) $userid !== (int) ($USER->id)):
                \debugging($fxn . '::Failed check: Check userid is current USER');
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => 'The attempt userid is not the current user',
                ];
                break;
            case (!\is_enrolled($coursecontext, $userid, 'block/integrityadvocate:view', true /* Only active users */)):
                \debugging($fxn . '::Failed check: is_enrolled');
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => "Course id={$courseid} does not have targetuserid={$userid} enrolled",
                ];
                break;
            case (!($user = ia_mu::get_user_as_obj($userid))):
                \debugging($fxn . '::Failed check: get_user_as_obj');
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => 'The userid is not a valid user',
                ];
                break;
            case ($user->deleted || $user->suspended):
                \debugging($fxn . '::Failed check: deleted or suspended');
                $result['warnings'][] = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => 'The user is suspended or deleted',
                ];
                break;
            default:
                $debug && \debugging($fxn . '::Found no reason to fail the request');
                break;
        }

        $debug && \debugging($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));
        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            \debugging($fxn . '::' . \serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }
        $debug && \debugging($fxn . "'::No warnings; Got courseid=$courseid; userid=$userid'");

        // If this is not a quiz, do nothing and return success.

        // Makes sure the current user may execute functions in this context.
        self::validate_context(self::$attemptobj->get_quizobj()->get_context());

        return $result;
    }

    /**
     * Describes the parameters for start_proctoring.
     *
     * @return \external_function_parameters The parameters for start_proctoring.
     */
    public static function start_proctoring_parameters(): \external_function_parameters {
        return self::start_proctoring_function_params();
    }

    /**
     * Things to do when the user actually starts proctoring.
     *
     * @param int $attemptid The quiz attempt id.
     * @return array Result array that sent back as the AJAX result.
     */
    public static function start_proctoring(int $attemptid): array {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debugvars = $fxn . "::Started with \$attemptid={$attemptid}";
        $debug && \debugging($debugvars);

        $result = \array_merge(
            ['submitted' => false, 'success' => true, 'warnings' => []],
            self::start_proctoring_validate_params($attemptid)
        );
        $debug && \debugging($fxn . '::After checking failure conditions, warnings=' . ia_u::var_dump($result['warnings'], true));

        $result['submitted'] = true;

        if (isset($result['warnings']) && !empty($result['warnings'])) {
            $result['success'] = false;
            \debugging($fxn . '::' . \serialize($result['warnings']) . "; \$debugvars={$debugvars}");
            return $result;
        }
        $debug && \debugging($fxn . '::No warnings');

        // The user is allowed to update the quiz timer only once per quiz attempt.
        $cache = \cache::make('block_integrityadvocate', 'persession');
        $cachekey = ia_mu::get_cache_key(\implode('_', [INTEGRITYADVOCATE_SHORTNAME, $attemptid, \sesskey()]));
        $debug && \debugging($fxn . '::Got cachekey=' . ia_u::var_dump($cachekey));

        // Tested the AJAX network (web services) call with these scenarios:.
        // z- ===========.
        // z- Should succeed:
        // z- ===========.
        // z- On the quiz attempt page, first ajax call.
        // z- Quiz re-attempt.
        // z- Multi-page quiz.
        // z- ===========.
        // z- Should fail (AJAX call succeeds with warning):.
        // z- ===========.
        // z- JS alert box is up and quiz screen still displaying: M.block_integrityadvocate.startProctoring() does not make a network call.
        // z- Quiz timer expired (on the attempt page or not).
        // z- On a subsequent quiz attempt, resubmit old AJAX network call.
        // z- Use back button then click return to quiz attempt.
        // z- While on the Finish Attempt page.
        // z- While on the Reviewing Attempt page.
        // z- Invalid (future/past) quiz attemptid.
        // z- Someone else's quiz attemptid.
        // z- Open attempt in another browser.

        $blockversion = \get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version');
        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && \debugging($fxn . '::Got cachedvalue=' . ia_u::var_dump($cachedvalue));
            $debug && \debugging($fxn . '::Original quiz attempt timestart=' . self::$attemptobj->get_attempt()->timestart);
            $newtimestart = time();
            $result['success'] = ia_mu::quiz_set_timestart(self::$attemptobj->get_attemptid(), $newtimestart);

            // Log this to the Moodle log.
            $params = [
                'objectid' => self::$attemptobj->get_attemptid(),
                'relateduserid' => self::$attemptobj->get_userid(),
                'courseid' => self::$attemptobj->get_courseid(),
                'context' => self::$attemptobj->get_quizobj()->get_context(),
            ];
            $event = \block_integrityadvocate\event\quizattempt_time_updated::create($params);
            $event->add_record_snapshot('quiz', self::$attemptobj->get_quizobj()->get_quiz());
            $event->add_record_snapshot('quiz_attempts', self::$attemptobj->get_attempt());
            $event->trigger();

            if (!$result['success']) {
                $msg = 'Failed to run start_proctoring items';
                $result['warnings'][]  = [
                    'warningcode' => \implode('a', [$blockversion, __LINE__]),
                    'message' => $msg,
                ];
                \debugging($fxn . "::{$msg}; \$debugvars={$debugvars}");
            } else {
                $cache->delete($cachekey);
            }
        } else {
            $msg = 'Not set or already done';
            $debug && \debugging($fxn . "::$msg");
            $result['warnings'][] = [
                'warningcode' => \implode('a', [$blockversion, __LINE__]),
                'message' => $msg,
            ];
            $result['success'] = false;
        }

        $debug && \debugging($fxn . '::About to return result=' . ia_u::var_dump($result, true));
        return $result;
    }

    /**
     * Describes the start_proctoring return value.
     *
     * @return \external_single_structure
     */
    public static function start_proctoring_returns(): \external_single_structure {
        return self::returns_boolean_submitted();
    }
}
