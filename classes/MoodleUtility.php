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
 * IntegrityAdvocate utility functions not specific to this module that interact with Moodle core.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

use block_integrityadvocate\Logger as Logger;
use block_integrityadvocate\Utility as ia_u;

\defined('MOODLE_INTERNAL') || die;

/**
 * Utility functions not specific to this module that interact with Moodle core.
 */
class MoodleUtility {

    public static function get_button_html(\context $context, string $label, string $url, array $options = []): string {
        $page = new \moodle_page();
        $page->set_url('/user/profile.php');
        $page->set_context($context->get_course_context());
        $output = $page->get_renderer('core', 'course');
        $button = new \single_button(new \moodle_url($url), $label, 'get', false, $options);
        return $output->render($button);
    }

    /**
     * Get all instances of block_integrityadvocate in the Moodle site.
     * If there are multiple blocks in a single parent context just return the first from that context.
     *
     * @param string $blockname Shortname of the block to get.
     * @param bool $visibleonly Set to true to return only visible instances.
     * @return array<\block_base> Array of block_integrityadvocate instances with key=block instance id.
     */
    public static function get_all_blocks(string $blockname, bool $visibleonly = true): array {
        global $DB;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debug && Logger::log($fxn . "::Started with \$blockname={$blockname}; \$visibleonly={$visibleonly}");

        // We cannot filter for if the block is visible here b/c the block_participant row is usually NULL in these cases.
        $params = ['blockname' => \preg_replace('/^block_/', '', $blockname)];
        $debug && Logger::log($fxn . "::Looking in table block_instances with params=" . ia_u::var_dump($params, true));

        $records = $DB->get_records('block_instances', $params);
        $debug && Logger::log($fxn . '::Found $records=' . (ia_u::is_empty($records) ? '' : ia_u::var_dump($records, true)));
        if (ia_u::is_empty($records)) {
            $debug && Logger::log($fxn . "::No instances of block_{$blockname} found");
            return [];
        }

        // Go through each of the block instances and check visibility.
        $blockinstances = [];
        foreach ($records as $r) {
            $debug && Logger::log($fxn . '::Looking at $br=' . ia_u::var_dump($r, true));

            // Check if it is visible and get the IA appid from the block instance config.
            $blockinstancevisible = self::is_block_visibile($r->parentcontextid, $r->id);
            $debug && Logger::log($fxn . "::Found \$blockinstancevisible={$blockinstancevisible}");

            if ($visibleonly && !$blockinstancevisible) {
                continue;
            }

            if (isset($blockinstances[$r->id])) {
                $debug && Logger::log($fxn . "::Multiple visible block_{$blockname} instances found in the same parentcontextid - just return the first one");
                continue;
            }

            $blockinstances[$r->id] = \block_instance_by_id($r->id);
        }

        return $blockinstances;
    }

    /**
     * Get blocks in the given contextid (not recursively)
     *
     * @param int $contextid The context id to look in
     * @param string $blockname Name of the block to get instances for.
     * @return array where key=block_instances.id; val=block_instance object.
     */
    private static function get_blocks_in_context(int $contextid, string $blockname, bool $visibleonly = false): array {
        global $DB;

        $blockinstances = [];
        $records = $DB->get_records('block_instances', ['parentcontextid' => $contextid, 'blockname' => \preg_replace('/^block_/', '', $blockname)]);
        foreach ($records as $r) {
            // Check if it is visible.
            if ($visibleonly && !self::is_block_visibile($r->parentcontextid, $r->id)) {
                continue;
            }

            $blockinstances[$r->id] = \block_instance_by_id($r->id);
        }

        return $blockinstances;
    }

    /**
     * Get all blocks in the course and child contexts (modules) matching $blockname.
     *
     * @param int $courseid The courseid to look in.
     * @param string $blockname Name of the block to get instances for.
     * @return array where key=block_instances.id; val=block_instance object.
     */
    public static function get_all_course_blocks(int $courseid, string $blockname, bool $visibleonly = false): array {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debug && Logger::log($fxn . "::Started with courseid={$courseid}; \$blockname={$blockname}; \$visibleonly={$visibleonly}");

        $coursecontext = \context_course::instance($courseid, MUST_EXIST);

        // Get course-level instances.
        $blockinstances = self::get_blocks_in_context($coursecontext->id, $blockname, $visibleonly);
        $debug && Logger::log($fxn . '::Found course level block count=' . ia_u::count_if_countable($blockinstances));

        // Look in modules for more blocks instances.
        foreach ($coursecontext->get_child_contexts() as $c) {
            $debug && Logger::log($fxn . "::Looking at \$c->id={$c->id}; \$c->instanceid={$c->instanceid}; \$c->contextlevel={$c->contextlevel}");
            if ((int) ($c->contextlevel) !== (int) (\CONTEXT_MODULE)) {
                continue;
            }

            $blocksinmodule = self::get_blocks_in_context($c->id, $blockname, $visibleonly);
            $debug && Logger::log($fxn . '::Found module level block count=' . ia_u::count_if_countable($blocksinmodule));
            $blockinstances += $blocksinmodule;
        }

        $debug && Logger::log($fxn . '::About to return blockinstances count=' . ia_u::count_if_countable(ia_u::var_dump($blockinstances)));
        return $blockinstances;
    }

    /**
     * Return if Moodle is in testing mode, e.g. Behat.
     * Checking this instead of defined('BEHAT_SITE_RUNNING') directly allow me to return an arbitrary value if I want.
     *
     * @return bool True if Moodle is in testing mode, e.g. Behat.
     */
    public static function is_testingmode(): bool {
        return \defined('BEHAT_SITE_RUNNING');
    }

    /**
     * Used to compare two modules based on order on course page.
     *
     * @param object[] $a array of event information
     * @param object[] $b array of event information
     * @return int Is less than 0, 0 or greater than 0 depending on order of modules on course page
     */
    protected static function modules_compare_events($a, $b): int {
        if ($a['section'] != $b['section']) {
            return (int) ($a['section'] - $b['section']);
        } else {
            return (int) ($a['position'] - $b['position']);
        }
    }

    /**
     * Used to compare two modules based their expected completion times
     *
     * @param object[] $a array of event information
     * @param object[] $b array of event information
     * @return int Is less than 0, 0 or greater than 0 depending on time then order of modules.
     */
    protected static function modules_compare_times($a, $b): int {
        if ($a['expected'] != 0 && $b['expected'] != 0 && $a['expected'] != $b['expected']) {
            return (int) ($a['expected'] - $b['expected']);
        } else if ($a['expected'] != 0 && $b['expected'] == 0) {
            return -1;
        } else if ($a['expected'] == 0 && $b['expected'] != 0) {
            return 1;
        } else {
            return self::modules_compare_events($a, $b);
        }
    }

    /**
     * Given a context, get array of roles usable in a roles select box.
     *
     * @param \context $context The course context.
     * @return array<roleid=role name>.
     */
    public static function get_roles_for_select(\context $context): array {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debug && Logger::log($fxn . "::Started with \$context->id={$context->id}");

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make('block_integrityadvocate', 'persession');
        $cachekey = self::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . '_' . $context->id);
        if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            $debug && Logger::log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        $sql = 'SELECT  DISTINCT r.id, r.name, r.shortname
                    FROM    {role} r, {role_assignments} ra
                   WHERE    ra.contextid = :contextid
                     AND    r.id = ra.roleid';
        $params = ['contextid' => $context->id];
        global $DB;
        $roles = \role_fix_names($DB->get_records_sql($sql, $params), $context);
        $rolestodisplay = [0 => \get_string('allparticipants')];
        foreach ($roles as $role) {
            $rolestodisplay[$role->id] = $role->localname;
        }

        if (FeatureControl::CACHE && !$cache->set($cachekey, $rolestodisplay)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $rolestodisplay;
    }

    /**
     * Returns the modules with completion set in current course.
     *
     * @param int courseid The id of the course.
     * @return array<module<name=value>> Modules with completion settings in the course.
     */
    public static function get_modules_with_completion(int $courseid): array {
        $modinfo = \get_fast_modinfo($courseid, -1);
        // Used for sorting.
        $sections = $modinfo->get_sections();
        $modules = [];
        foreach ($modinfo->instances as $module => $instances) {
            $modulename = \get_string('pluginname', $module);
            foreach ($instances as $cm) {
                if ($cm->completion != \COMPLETION_TRACKING_NONE) {
                    $modules[] = ['type' => $module,
                        'modulename' => $modulename,
                        'id' => $cm->id,
                        'instance' => $cm->instance,
                        'name' => $cm->name,
                        'expected' => $cm->completionexpected,
                        'section' => $cm->sectionnum,
                        // Used for sorting.
                        'position' => \array_search($cm->id, $sections[$cm->sectionnum], true),
                        'url' => \method_exists($cm->url, 'out') ? $cm->url->out() : '',
                        'context' => $cm->context,
                        // Removed b/c it caused error with developer debug display on: 'icon' => $cm->get_icon_url().
                        'available' => $cm->available,
                    ];
                }
            }
        }

        \usort($modules, ['self', 'modules_compare_times']);

        return $modules;
    }

    /**
     * Filters modules that a user cannot see due to grouping constraints.
     *
     * @param \stdClass $cfg Pass in the Moodle $CFG object.
     * @param array<object> $modules The possible modules that can occur for modules.
     * @param int $userid The user's id.
     * @param int $courseid the course for filtering visibility.
     * @param array<int> $exclusions Assignment exemptions for students in the course.
     * @return array<object> The array without the restricted modules.
     */
    public static function filter_for_visible(\stdClass $cfg, array $modules, int $userid, int $courseid, array $exclusions): array {
        $filteredmodules = [];
        $modinfo = \get_fast_modinfo($courseid, $userid);
        $coursecontext = \CONTEXT_COURSE::instance($courseid);
        $hascapability_viewhiddenactivities = \has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid);

        // Keep only modules that are visible.
        foreach ($modules as $m) {
            $coursemodule = $modinfo->cms[$m['id']];

            // Check visibility in course.
            if (!$coursemodule->visible && !$hascapability_viewhiddenactivities) {
                continue;
            }

            // Check availability, allowing for visible, but not accessible items.
            if (!empty($cfg->enableavailability)) {
                if ($hascapability_viewhiddenactivities) {
                    $m['available'] = true;
                } else {
                    if (isset($coursemodule->available) && !$coursemodule->available && empty($coursemodule->availableinfo)) {
                        continue;
                    }
                    $m['available'] = $coursemodule->available;
                }
            }

            // Check visibility by grouping constraints (includes capability check).
            if (!empty($cfg->enablegroupmembersonly)) {
                if (isset($coursemodule->uservisible)) {
                    if ($coursemodule->uservisible != 1 && empty($coursemodule->availableinfo)) {
                        continue;
                    }
                }
            }

            // Check for exclusions.
            if (\in_array($m['type'] . '-' . $m['instance'] . '-' . $userid, $exclusions, true)) {
                continue;
            }

            // Save the visible event.
            $filteredmodules[] = $m;
        }
        return $filteredmodules;
    }

    /**
     * Return whether an IA block is visible in the given context
     *
     * @param int $parentcontextid The module context id
     * @param int $blockinstanceid The block instance id
     * @return bool true if the block is visible in the given context
     */
    public static function is_block_visibile(int $parentcontextid, int $blockinstanceid): bool {
        global $DB;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debug && Logger::log($fxn . "::Started with \$parentcontextid={$parentcontextid}; \$blockinstanceid={$blockinstanceid}");

        $record = $DB->get_record('block_positions', ['blockinstanceid' => $blockinstanceid, 'contextid' => $parentcontextid], 'id,visible', \IGNORE_MULTIPLE);
        $debug && Logger::log($fxn . '::Got $bp_record=' . (ia_u::is_empty($record) ? '' : ia_u::var_dump($record, true)));
        if (ia_u::is_empty($record)) {
            // There is no block_positions record, and the default is visible.
            return true;
        }

        return (bool) $record->visible;
    }

    /**
     * Check if site and optionally also course completion is enabled.
     *
     * @param int|object $course Optional courseid or course object to check. If not specified, only site-level completion is checked.
     * @return array<string> of error identifier strings
     */
    public static function get_completion_setup_errors($course = null): array {
        global $CFG;
        $errors = [];

        // Check if completion is enabled at site level.
        if (!$CFG->enablecompletion) {
            $errors[] = 'completion_not_enabled';
        }

        if ($course = self::get_course_as_obj($course)) {
            // Check if completion is enabled at course level.
            $completion = new \completion_info($course);
            if (!$completion->is_enabled()) {
                $errors[] = 'completion_not_enabled_course';
            }
        }

        return $errors;
    }

    /**
     * Given the cmid, get the courseid without throwing an error.
     *
     * @param int $cmid The CMID to look up.
     * @return int The courseid if found, else -1.
     */
    public static function get_courseid_from_cmid(int $cmid): int {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debug && Logger::log($fxn . "::Started with $cmid={$cmid}");

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make('block_integrityadvocate', 'perrequest');
        $cachekey = self::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . '_' . $cmid);
        $debug && Logger::log($fxn . "::Built cachekey={$cachekey}");
        if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            $debug && Logger::log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        global $DB;
        $course = $DB->get_record_sql("
                    SELECT c.id
                      FROM {course_modules} cm
                      JOIN {course} c ON c.id = cm.course
                     WHERE cm.id = ?", [$cmid], 'id', \IGNORE_MULTIPLE);
        $debug && Logger::log($fxn . '::Got course=' . ia_u::var_dump($course, true));

        if (ia_u::is_empty($course) || !isset($course->id)) {
            $debug && Logger::log($fxn . "::No course found for cmid={$cmid}");
            $returnthis = -1;
        } else {
            $returnthis = $course->id;
        }

        if (FeatureControl::CACHE && !$cache->set($cachekey, $returnthis)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $returnthis;
    }

    /**
     * Convert course id to moodle course object into if needed.
     *
     * @param int|\stdClass $course The course object or courseid to check
     * @return bool|stdClass False if no course found; else Moodle course object.
     */
    public static function get_course_as_obj($course): ?\stdClass {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debug && Logger::log($fxn . '::Started with type(\$course)=' . \gettype($course));

        if (\is_numeric($course)) {
            // Cache so multiple calls don't repeat the same work.
            $cache = \cache::make('block_integrityadvocate', 'perrequest');
            $cachekey = self::get_cache_key($fxn . '_' . \json_encode($course, \JSON_PARTIAL_OUTPUT_ON_ERROR));
            if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
                $debug && Logger::log($fxn . '::Found a cached value, so return that');
                return $cachedvalue;
            }

            $course = \get_course((int) $course);

            if (FeatureControl::CACHE && !$cache->set($cachekey, $course)) {
                throw new \Exception('Failed to set value in the cache');
            }
        }
        if (ia_u::is_empty($course)) {
            return null;
        }
        if (\gettype($course) != 'object' || !isset($course->id)) {
            throw new \InvalidArgumentException('$course should be of type stdClass; got ' . \gettype($course));
        }

        return $course;
    }

    /**
     * Finds gradebook exclusions for students in a course.
     *
     * @param \moodle_database $db Moodle DB object.
     * @param int $courseid The ID of the course containing grade items.
     * @return array of exclusions as module-user pairs.
     */
    public static function get_gradebook_exclusions(\moodle_database $db, int $courseid): array {
        $query = "SELECT g.id, " . $db->sql_concat('i.itemmodule', "'-'", 'i.iteminstance', "'-'", 'g.userid') . " as exclusion
                   FROM {grade_grades} g, {grade_items} i
                  WHERE i.courseid = :courseid
                    AND i.id = g.itemid
                    AND g.excluded <> 0";
        $params = ['courseid' => $courseid];
        $results = $db->get_records_sql($query, $params);
        $exclusions = [];
        foreach ($results as $value) {
            $exclusions[] = $value->exclusion;
        }
        return $exclusions;
    }

    /**
     * Get the student role (in the course) to show by default e.g. on the course-overview page dropdown box.
     *
     * @param \context $coursecontext Course context in which to get the default role.
     * @return int the role id that is for student archetype in this course.
     */
    public static function get_default_course_role(\context $coursecontext): int {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debug && Logger::log($fxn . "::Started with \$coursecontext={$coursecontext->instanceid}");

        // Sanity check.
        if (ia_u::is_empty($coursecontext) || ($coursecontext->contextlevel !== \CONTEXT_COURSE)) {
            $msg = 'Input params are invalid';
            Logger::log($fxn . "::Started with \$coursecontext->instanceid={$coursecontext->instanceid}");
            Logger::log($fxn . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        $sql = 'SELECT  DISTINCT r.id, r.name, r.archetype
                FROM    {role} r, {role_assignments} ra
                WHERE   ra.contextid = :contextid
                AND     r.id = ra.roleid
                AND     r.archetype = :archetype';
        $params = ['contextid' => $coursecontext->id, 'archetype' => 'student'];

        global $DB;
        $studentrole = $DB->get_record_sql($sql, $params, 'id', MUST_EXIST);
        if (!ia_u::is_empty($studentrole)) {
            $studentroleid = $studentrole->id;
        } else {
            $studentroleid = 0;
        }
        return $studentroleid;
    }

    /**
     * Get the first block instance matching the shortname in the given context.
     *
     * @param \context $modulecontext Context to find the IA block in.
     * @param string $blockname Block shortname e.g. for block_html it would be html.
     * @param bool $visibleonly Return only visible instances.
     * @param bool $rownotinstance Since the instance can be hard to deal with, this returns the DB row instead.
     * @return \block_integrityadvocate Null if none found or if no visible instances found; else an instance of block_integrityadvocate.
     */
    public static function get_first_block(\context $modulecontext, string $blockname, bool $visibleonly = true, bool $rownotinstance = false): ?\block_integrityadvocate {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debug && Logger::log($fxn . "::Started with \$modulecontext->id={$modulecontext->id}; \$blockname={$blockname}; \$visibleonly={$visibleonly}; \$rownotinstance={$rownotinstance}");

        // We cannot filter for if the block is visible here b/c the block_participant row is usually NULL in these cases.
        $params = ['blockname' => \preg_replace('/^block_/', '', $blockname), 'parentcontextid' => $modulecontext->id];
        $debug && Logger::log($fxn . "::Looking in table block_instances with params=" . ia_u::var_dump($params, true));

        // Z--.
        // Danger: Caching the resulting $record in the perrequest cache didn't work - we get an invalid stdClass back out.
        // Z--.
        global $DB;
        $records = $DB->get_records('block_instances', $params);
        $debug && Logger::log($fxn . '::Found blockinstance records=' . (ia_u::is_empty($records) ? '' : ia_u::var_dump($records, true)));
        if (ia_u::is_empty($records)) {
            $debug && Logger::log($fxn . "::No instances of block_{$blockname} is associated with this context");
            return null;
        }

        // If there are multiple blocks in this context just return the first valid one.
        $blockrecord = null;
        foreach ($records as $r) {
            // Check if it is visible and get the IA appid from the block instance config.
            $r->visible = self::is_block_visibile($modulecontext->id, $r->id);
            $debug && Logger::log($fxn . "::For \$modulecontext->id={$modulecontext->id} and \$record->id={$r->id} found \$record->visible={$r->visible}");
            if ($visibleonly && !$r->visible) {
                $debug && Logger::log($fxn . "::\$visibleonly=true and this instance is not visible so skip it");
                continue;
            }

            $blockrecord = $r;
            break;
        }
        if (empty($blockrecord)) {
            $debug && Logger::log($fxn . "::No valid blockrecord found, so return false");
            return null;
        }

        if ($rownotinstance) {
            return $blockrecord;
        }

        return \block_instance_by_id($blockrecord->id);
    }

    /**
     * Convert userid to moodle user object into if needed.
     *
     * @param int|\stdClass $user The user object or id to convert
     * @return false|\stdClass False if no user found; else moodle user object.
     */
    public static function get_user_as_obj($user): ?\stdClass {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debug && Logger::log($fxn . '::Started with type($user)=' . \gettype($user));


        if (\is_numeric($user)) {
            // Cache so multiple calls don't repeat the same work.
            $cache = \cache::make('block_integrityadvocate', 'perrequest');
            $cachekey = self::get_cache_key($fxn . '_' . \json_encode($user, \JSON_PARTIAL_OUTPUT_ON_ERROR));
            if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
                $debug && Logger::log($fxn . '::Found a cached value, so return that');
                return $cachedvalue;
            }

            $userarr = user_get_users_by_id([(int) $user]);
            if (empty($userarr)) {
                return null;
            }
            $user = \array_pop($userarr);

            if (isset($user->deleted) && $user->deleted) {
                return null;
            }

            if (FeatureControl::CACHE && !$cache->set($cachekey, $user)) {
                throw new \Exception('Failed to set value in the cache');
            }
        }
        if (\gettype($user) != 'object') {
            throw new \InvalidArgumentException('$user should be of type stdClass; got ' . \gettype($user));
        }

        return $user;
    }

    /**
     * Build the formatted Moodle user info HTML with optional params.
     *
     * @param \stdClass $user Moodle User object.
     * @param array $params Optional e.g ['courseid' => $courseid].
     * @return string User picture URL - it will not include fullname, size, img tag, or anything else.
     */
    public static function get_user_picture(\stdClass $user, array $params = []): string {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debug && Logger::log($fxn . "::Started with \$user->id={$user->id}; \$params=" . \serialize($params));

        // Cache so multiple calls don't repeat the same work.  Persession cache b/c is keyed on hash of $blockinstance.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = self::get_cache_key($fxn . '_' . \json_encode($user, \JSON_PARTIAL_OUTPUT_ON_ERROR) . '_' . \json_encode($params, \JSON_PARTIAL_OUTPUT_ON_ERROR));
        if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            $debug && Logger::log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        $user_picture = new \user_picture($user);
        foreach ($params as $key => $val) {
            if (object_property_exists($user_picture, $key)) {
                $user_picture->{$key} = $val;
            }
        }
        $debug && Logger::log($fxn . '::Built user_picture=' . ia_u::var_dump(user_picture));

        $page = new \moodle_page();
        $page->set_url('/user/profile.php');
        if (isset($params['courseid']) && !ia_u::is_empty($params['courseid'])) {
            $page->set_context(\context_course::instance($params['courseid']));
        } elseif (!ia_u::is_empty($user_picture->courseid)) {
            $page->set_context(\context_course::instance($user_picture->courseid));
        } else {
            $page->set_context(\context_system::instance());
        }
        $picture = $user_picture->get_url($page, $page->get_renderer('core'))->out(false);


        if (FeatureControl::CACHE && !$cache->set($cachekey, $picture)) {
            throw new \Exception('Failed to set value in the cache');
        }
        return $picture;
    }

    /**
     * Get user last access in course.
     *
     * @param int $userid The user id to look for.
     * @param int $courseid The course id to look in.
     * @return int User last access unix time.
     */
    public static function get_user_last_access(int $userid, int $courseid): int {
        global $DB;
        return $DB->get_field('user_lastaccess', 'timeaccess', ['courseid' => $courseid, 'userid' => $userid]);
    }

    /**
     * Get the UNIX timestamp for the last user access to the course.
     *
     * @param int $courseid The courseid to look in.
     * @return int User last access unix time.
     */
    public static function get_course_lastaccess(int $courseid): int {
        $courseidcleaned = \filter_var($courseid, \FILTER_VALIDATE_INT);
        if (!\is_numeric($courseidcleaned)) {
            throw new \InvalidArgumentException('Input $courseid must be an integer');
        }

        global $DB;
        $lastaccess = $DB->get_field_sql('SELECT MAX("timeaccess") lastaccess FROM {user_lastaccess} WHERE courseid=?',
                [$courseidcleaned], \IGNORE_MISSING);

        // Convert false to int 0.
        return (int) $lastaccess;
    }

    /**
     * Return true if the input $str is base64-encoded.
     *
     * @uses moodlelib:clean_param Cleans the param as \PARAM_BASE64 and checks for empty.
     * @param string $str the string to test.
     * @return bool true if the input $str is base64-encoded.
     */
    public static function is_base64(string $str): bool {
        return !empty(\clean_param($str, \PARAM_BASE64));
    }

    /**
     * Build a unique reproducible cache key from the given string.
     *
     * @param string $key The string to use for the key.
     * @return string The cache key.
     */
    public static function get_cache_key(string $key): string {
        return \sha1(\get_config('block_integrityadvocate', 'version') . $key);
    }

    /**
     * Create a UNIX timestamp nonce and store it in the Moodle $SESSION variable.
     *
     * @param string $key Key for the nonce that is stored in $SESSION.
     * @return int Unix timestamp The value of the nonce.
     */
    public static function nonce_set(string $key): int {
        global $SESSION;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debug && Logger::log($fxn . "::Started with \$key={$key}");

        // Make sure $key is a safe cache key.
        $sessionkey = self::get_cache_key($key);

        $debug && Logger::log($fxn . "::About to set \$SESSION key={$key}");
        return $SESSION->{$sessionkey} = \time();
    }

    /**
     * Check the nonce key exists in $SESSION and is not timed out.  Deletes the nonce key.
     *
     * @param string $key The Nonce key to check.  This gets cleaned automatically.
     * @param bool $returntrueifexists True means: Return true if the nonce key exists, ignoring the timeout..
     * @return bool $returntrueifexists=false: True if the nonce key exists, is not empty (unixtime=0), and is not timed out.  $returntrueifexists=true: Returns true if the nonce key exists and is not empty (unixtime=0).
     */
    public static function nonce_validate(string $key, bool $returntrueifexists = false): bool {
        global $SESSION;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false || Logger::do_log_for_function($fxn);
        $debug && Logger::log($fxn . "::Started with \$key={$key}; \$returntrueifexists={$returntrueifexists}");

        // Clean up $contextname so it is a safe cache key.
        $sessionkey = self::get_cache_key($key);
        if (!isset($SESSION->{$sessionkey}) || empty($SESSION->{$sessionkey}) || $SESSION->{$sessionkey} < 0) {
            $debug && Logger::log($fxn . "::\$SESSION does not contain key={$key}");
            return false;
        }

        $nonce = $SESSION->{$sessionkey};
        $debug && Logger::log($fxn . "::\Found nonce={$nonce}");

        // Delete it since it should only be used once.
        $SESSION->{$sessionkey} = null;

        if ($returntrueifexists) {
            return true;
        }

        global $CFG;

        // The nonce is valid if the time is after $CFG->sessiontimeout ago.
        $valid = $nonce >= (\time() - $CFG->sessiontimeout);
        $debug && Logger::log($fxn . "::\Found valid={$valid}");
        return $valid;
    }

}
