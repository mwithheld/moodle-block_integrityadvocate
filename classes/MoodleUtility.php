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

use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

/**
 * Utility functions not specific to this module that interact with Moodle core.
 */
class MoodleUtility {

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
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;

        // We cannot filter for if the block is visible here b/c the block_participant row is usually NULL in these cases.
        $params = array('blockname' => $blockname);
        $debug && self::log($fxn . "::Looking in table block_instances with params=" . ia_u::var_dump($params, true));
        $records = $DB->get_records('block_instances', $params);
        $debug && self::log($fxn . '::Found $records=' . (ia_u::is_empty($records) ? '' : ia_u::var_dump($records, true)));
        if (ia_u::is_empty($records)) {
            $debug && self::log($fxn . "::No instances of block_{$blockname} found");
            return [];
        }

        // Go through each of the block instances and check visibility.
        $blockinstances = [];
        foreach ($records as $r) {
            $debug && self::log($fxn . '::Looking at $br=' . ia_u::var_dump($r, true));

            // Check if it is visible and get the IA appid from the block instance config.
            $blockinstancevisible = self::get_block_visibility($r->parentcontextid, $r->id);
            $debug && self::log($fxn . "::Found \$blockinstancevisible={$blockinstancevisible}");

            if ($visibleonly && !$blockinstancevisible) {
                continue;
            }

            if (isset($blockinstances[$r->id])) {
                $debug && self::log($fxn . "::Multiple visible block_{$blockname} instances found in the same parentcontextid - just return the first one");
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
    private static function get_blocks_in_context(int $contextid, string $blockname) {
        global $DB;

        $blockinstances = [];
        $recordset = $DB->get_recordset('block_instances', array('parentcontextid' => $contextid, 'blockname' => $blockname));
        foreach ($recordset as $r) {
            $blockinstances[$r->id] = \block_instance_by_id($r->id);
        }
        $recordset->close();

        return $blockinstances;
    }

    /**
     * Get all blocks in the course and child contexts (modules) matching $blockname.
     *
     * @param int $courseid The courseid to look in.
     * @param string $blockname Name of the block to get instances for.
     * @return array where key=block_instances.id; val=block_instance object.
     */
    public static function get_all_course_blocks(int $courseid, string $blockname): array {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && self::log($fxn . '::Started');

        $coursecontext = \context_course::instance($courseid, MUST_EXIST);

        // Get course-level instances.
        $blockinstances = self::get_blocks_in_context($coursecontext->id, $blockname);
        $debug && self::log($fxn . '::Found course level block count=' . count($blockinstances));

        // Look in modules for more blocks instances.
        foreach ($coursecontext->get_child_contexts() as $c) {
            $debug && self::log($fxn . "::Looking at \$c->id={$c->id}; \$c->instanceid={$c->instanceid}; \$c->contextlevel={$c->contextlevel}");
            if (intval($c->contextlevel) !== intval(\CONTEXT_MODULE)) {
                continue;
            }

            $blocksinmodule = self::get_blocks_in_context($c->id, $blockname);
            $debug && self::log($fxn . '::Found module level block count=' . count($blocksinmodule));
            $blockinstances += $blocksinmodule;
        }

        return $blockinstances;
    }

    /**
     * Return if Moodle is in testing mode, e.g. Behat.
     * Checking this instead of defined('BEHAT_SITE_RUNNING') directly allow me to return an arbitrary value if I want.
     *
     * @return bool True if Moodle is in testing mode, e.g. Behat.
     */
    public static function is_testingmode(): bool {
        return defined('BEHAT_SITE_RUNNING');
    }

    /**
     * Used to compare two modules based on order on course page.
     *
     * @param object[] $a array of event information
     * @param object[] $b array of event information
     * @return int Val <0, 0 or >0 depending on order of modules on course page
     */
    protected static function modules_compare_events($a, $b): int {
        if ($a['section'] != $b['section']) {
            return $a['section'] - $b['section'];
        } else {
            return $a['position'] - $b['position'];
        }
    }

    /**
     * Used to compare two modules based their expected completion times
     *
     * @param object[] $a array of event information
     * @param object[] $b array of event information
     * @return int <0, 0 or >0 depending on time then order of modules.
     */
    protected static function modules_compare_times($a, $b): int {
        if ($a['expected'] != 0 && $b['expected'] != 0 && $a['expected'] != $b['expected']) {
            return $a['expected'] - $b['expected'];
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
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && self::log($fxn . "::Started with \$context->id={$context->id}");

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make('block_integrityadvocate', 'persession');
        $cachekey = self::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . '_' . $context->id);
        if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            $debug && self::log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        $sql = 'SELECT  DISTINCT r.id, r.name, r.shortname
                    FROM    {role} r, {role_assignments} ra
                   WHERE    ra.contextid = :contextid
                     AND    r.id = ra.roleid';
        $params = array('contextid' => $context->id);
        global $DB;
        $roles = \role_fix_names($DB->get_records_sql($sql, $params), $context);
        $rolestodisplay = array(0 => \get_string('allparticipants'));
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
                if ($cm->completion != COMPLETION_TRACKING_NONE) {
                    $modules[] = array(
                        'type' => $module,
                        'modulename' => $modulename,
                        'id' => $cm->id,
                        'instance' => $cm->instance,
                        'name' => $cm->name,
                        'expected' => $cm->completionexpected,
                        'section' => $cm->sectionnum,
                        // Used for sorting.
                        'position' => array_search($cm->id, $sections[$cm->sectionnum]),
                        'url' => method_exists($cm->url, 'out') ? $cm->url->out() : '',
                        'context' => $cm->context,
                        // Removed b/c it caused error with developer debug display on: 'icon' => $cm->get_icon_url().
                        'available' => $cm->available,
                    );
                }
            }
        }

        usort($modules, array('self', 'modules_compare_times'));

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

        // Keep only modules that are visible.
        foreach ($modules as $m) {
            $coursemodule = $modinfo->cms[$m['id']];

            // Check visibility in course.
            if (!$coursemodule->visible && !\has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid)) {
                continue;
            }

            // Check availability, allowing for visible, but not accessible items.
            if (!empty($cfg->enableavailability)) {
                if (\has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid)) {
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
            if (in_array($m['type'] . '-' . $m['instance'] . '-' . $userid, $exclusions)) {
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
    public static function get_block_visibility(int $parentcontextid, int $blockinstanceid): bool {
        global $DB;
        $debug = false;

        $record = $DB->get_record('block_positions', array('blockinstanceid' => $blockinstanceid, 'contextid' => $parentcontextid), 'id,visible', IGNORE_MULTIPLE);
        $debug && self::log(__CLASS__ . '::' . __FUNCTION__ . '::Got $bp_record=' . (ia_u::is_empty($record) ? '' : ia_u::var_dump($record, true)));
        if (ia_u::is_empty($record)) {
            // There is no block_positions record, and the default is visible.
            return true;
        }

        return $record->visible;
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
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && self::log($fxn . "::Started with $cmid={$cmid}");

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make('block_integrityadvocate', 'perrequest');
        $cachekey = self::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . '_' . $cmid);
        $debug && self::log($fxn . "::Built cachekey={$cachekey}");
        if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            $debug && self::log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        global $DB;
        $course = $DB->get_record_sql("
                    SELECT c.id
                      FROM {course_modules} cm
                      JOIN {course} c ON c.id = cm.course
                     WHERE cm.id = ?", array($cmid), 'id', IGNORE_MULTIPLE);
        $debug && self::log($fxn . '::Got course=' . ia_u::var_dump($course, true));

        if (ia_u::is_empty($course) || !isset($course->id)) {
            $debug && self::log($fxn . "::No course found for cmid={$cmid}");
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
     * @return bool false if no course found; else Moodle course object.
     */
    public static function get_course_as_obj($course) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;

        if (is_numeric($course)) {
            // Cache so multiple calls don't repeat the same work.
            $cache = \cache::make('block_integrityadvocate', 'perrequest');
            $cachekey = self::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . '_' . json_encode($course, JSON_PARTIAL_OUTPUT_ON_ERROR));
            if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
                $debug && self::log($fxn . '::Found a cached value, so return that');
                return $cachedvalue;
            }

            $course = \get_course(intval($course));

            if (FeatureControl::CACHE && !$cache->set($cachekey, $course)) {
                throw new \Exception('Failed to set value in the cache');
            }
        }
        if (ia_u::is_empty($course)) {
            return false;
        }
        if (gettype($course) != 'object' || !isset($course->id)) {
            throw new \InvalidArgumentException('$course should be of type stdClass; got ' . gettype($course));
        }

        return $course;
    }

    /**
     * Finds gradebook exclusions for students in a course
     *
     * @param \moodle_database $db Moodle DB object
     * @param int $courseid The ID of the course containing grade items
     * @return array of exclusions as module-user pairs
     */
    public static function get_gradebook_exclusions(\moodle_database $db, int $courseid): array {
        $query = "SELECT g.id, " . $db->sql_concat('i.itemmodule', "'-'", 'i.iteminstance', "'-'", 'g.userid') . " as exclusion
                   FROM {grade_grades} g, {grade_items} i
                  WHERE i.courseid = :courseid
                    AND i.id = g.itemid
                    AND g.excluded <> 0";
        $params = array('courseid' => $courseid);
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
     * @return int the role id that is for student archetype in this course
     */
    public static function get_default_course_role(\context $coursecontext): int {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && self::log($fxn . "::Started with $coursecontext={$coursecontext->instanceid}");

        // Sanity check.
        if (ia_u::is_empty($coursecontext) || ($coursecontext->contextlevel !== \CONTEXT_COURSE)) {
            $msg = 'Input params are invalid';
            self::log($fxn . "::Started with \$coursecontext->instanceid={$coursecontext->instanceid}");
            self::log($fxn . '::' . $msg);
            throw new \InvalidArgumentException($msg);
        }

        $sql = 'SELECT  DISTINCT r.id, r.name, r.archetype
                FROM    {role} r, {role_assignments} ra
                WHERE   ra.contextid = :contextid
                AND     r.id = ra.roleid
                AND     r.archetype = :archetype';
        $params = array('contextid' => $coursecontext->id, 'archetype' => 'student');

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
     * @return bool|\block_integrityadvocate bool False if none found or if no visible instances found; else an instance of block_integrityadvocate.
     */
    public static function get_first_block(\context $modulecontext, string $blockname, bool $visibleonly = true, bool $rownotinstance = false) {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;

        // We cannot filter for if the block is visible here b/c the block_participant row is usually NULL in these cases.
        $params = array('blockname' => $blockname, 'parentcontextid' => $modulecontext->id);
        $debug && self::log($fxn . "::Looking in table block_instances with params=" . ia_u::var_dump($params, true));

        //Z--.
        // Danger: Caching the resulting $record in the perrequest cache didn't work - we get an invalid stdClass back out.
        //Z--.
        global $DB;
        $records = $DB->get_records('block_instances', $params);
        $debug && self::log($fxn . '::Found blockinstance records=' . (ia_u::is_empty($records) ? '' : ia_u::var_dump($records, true)));
        if (ia_u::is_empty($records)) {
            $debug && self::log($fxn . "::No instances of block_{$blockname} is associated with this context");
            return false;
        }

        // If there are multiple blocks in this context just return the first valid one.
        $blockrecord = null;
        foreach ($records as $r) {
            // Check if it is visible and get the IA appid from the block instance config.
            $r->visible = self::get_block_visibility($modulecontext->id, $r->id);
            $debug && self::log($fxn . "::For \$modulecontext->id={$modulecontext->id} and \$record->id={$r->id} found \$record->visible={$r->visible}");
            if ($visibleonly && !$r->visible) {
                $debug && self::log($fxn . "::\$visibleonly=true and this instance is not visible so skip it");
                continue;
            }

            $blockrecord = $r;
            break;
        }
        if (empty($blockrecord)) {
            $debug && self::log($fxn . "::No valid blockrecord found, so return false");
            return false;
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
     * @return null|\stdClass Null if no user found; else moodle user object.
     */
    public static function get_user_as_obj($user) {
        $debug = false;
        $debug && self::log(__CLASS__ . '::' . __FUNCTION__ . '::Started with $user=' . ia_u::var_dump($user, true));


        if (is_numeric($user)) {
            // Cache so multiple calls don't repeat the same work.
            $cache = \cache::make('block_integrityadvocate', 'perrequest');
            $cachekey = self::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . '_' . json_encode($user, JSON_PARTIAL_OUTPUT_ON_ERROR));
            if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
                $debug && self::log($fxn . '::Found a cached value, so return that');
                return $cachedvalue;
            }

            $userarr = user_get_users_by_id(array(intval($user)));
            if (empty($userarr)) {
                return false;
            }
            $user = array_pop($userarr);

            if (isset($user->deleted) && $user->deleted) {
                return false;
            }

            if (FeatureControl::CACHE && !$cache->set($cachekey, $user)) {
                throw new \Exception('Failed to set value in the cache');
            }
        }
        if (gettype($user) != 'object') {
            throw new \InvalidArgumentException('$user should be of type stdClass; got ' . gettype($user));
        }

        return $user;
    }

    /**
     * Build the formatted Moodle user info HTML with optional params.
     *
     * @param \stdClass $user Moodle User object.
     * @param array $params Optional e.g ['size' => 35, 'courseid' => $courseid, 'includefullname' => true].
     * @return string HTML for displaying user info.
     */
    public static function get_user_picture(\stdClass $user, array $params = array()): string {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debugvars = $fxn . "::Started with \$user->id={$user->id}; \$params=" . serialize($params);
        $debug && self::log($debugvars);


        // Cache so multiple calls don't repeat the same work.  Persession cache b/c is keyed on hash of $blockinstance.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = self::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . '_' . json_encode($user, JSON_PARTIAL_OUTPUT_ON_ERROR) . '_' . json_encode($params, JSON_PARTIAL_OUTPUT_ON_ERROR));
        if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            $debug && self::log($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        global $OUTPUT;
        $user_picture = $OUTPUT->user_picture($user, $params);

        if (FeatureControl::CACHE && !$cache->set($cachekey, $user_picture)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $user_picture;
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
        return $DB->get_field('user_lastaccess', 'timeaccess', array('courseid' => $courseid, 'userid' => $userid));
    }

    /**
     * Get the UNIX timestamp for the last user access to the course.
     *
     * @param int $courseid The courseid to look in.
     * @return int User last access unix time.
     */
    public static function get_course_lastaccess(int $courseid): int {
        $courseidcleaned = filter_var($courseid, FILTER_VALIDATE_INT);
        if (!is_numeric($courseidcleaned)) {
            throw new \InvalidArgumentException('Input $courseid must be an integer');
        }

        global $DB;
        $lastaccess = $DB->get_field_sql('SELECT MAX("timeaccess") lastaccess FROM {user_lastaccess} WHERE courseid=?',
                array($courseidcleaned), IGNORE_MISSING);

        // Convert false to int 0.
        return intval($lastaccess);
    }

    /**
     * Log $message to HTML output, mlog, stdout, or error log
     *
     * @param string $message Message to log
     * @param string $dest One of the LogDestination::* constants.
     * @return bool True on completion
     */
    public static function log(string $message, string $dest = ''): bool {
        global $CFG;
        $debug = /* Do not make this true except in unusual circumstances */ false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && error_log($fxn . '::Started with $dest=' . $dest . "\n");

        if (ia_u::is_empty($dest)) {
            $dest = LogDestination::$default;
        }
        $debug && error_log($fxn . '::After cleanup, $dest=' . $dest . "\n");

        // If the file path is included, strip it.
        $cleanedmsg = str_replace(realpath($CFG->dirroot), '', $message);
        // Remove base64-encoded images.
        $cleanedmsg = preg_replace(INTEGRITYADVOCATE_REGEX_DATAURI, 'redacted_base64_image', $cleanedmsg);
        // Trim and remove blank lines.
        $cleanedmsg = trim(preg_replace('/^[ \t]*[\r\n]+/m', '', $cleanedmsg));

        switch ($dest) {
            case LogDestination::HTML:
                print($cleanedmsg) . "<br />\n";
                break;
            case LogDestination::MLOG:
                mtrace(html_to_text($cleanedmsg, 0, false));
                break;
            case LogDestination::STDOUT:
                print(htmlentities($cleanedmsg, 0, false)) . "\n";
                break;
            case LogDestination::LOGGLY:
                if (isset(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1])) {
                    $debugbacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
                } else if (isset(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0])) {
                    $debugbacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0];
                }
                $classorfile = isset($debugbacktrace['class']) ? $debugbacktrace['class'] : '';
                if (empty($classorfile)) {
                    $classorfile = basename(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['file']);
                }

                $functionorline = isset($debugbacktrace['function']) ? $debugbacktrace['function'] : '';
                if (empty($functionorline)) {
                    $functionorline = intval($debugbacktrace['line']);
                } else {
                    $functionorline .= '-' . intval($debugbacktrace['line']);
                }

                $siteslug = preg_replace('/^www\./', '', str_replace(array('http://', 'https://'), '', trim($CFG->wwwroot, '/')));
                // REf https://www-staging.loggly.com/docs/tags/.
                $tag = self::clean_loggly_tag(str_replace(INTEGRITYADVOCATE_SHORTNAME, '', "{$siteslug}-{$classorfile}-{$functionorline}"));

                // Usage https://github.com/Seldaek/monolog/blob/1.x/doc/01-usage.md.
                // Usage https://dzone.com/articles/php-monolog-tutorial-a-step-by-step-guide.
                $log = new \Monolog\Logger("$tag,$siteslug,$classorfile");
                $log->pushHandler(new \Monolog\Handler\LogglyHandler(LogDestination::LOGGLY_TOKEN, \Monolog\Logger::DEBUG));
                $log->debug($cleanedmsg);
                break;
            case LogDestination::ERRORLOG:
            default:
                error_log($cleanedmsg);
                break;
        }

        return true;
    }

    /**
     * Return true if the input $str is base64-encoded.
     *
     * @uses moodlelib:clean_param Cleans the param as PARAM_BASE64 and checks for empty.
     * @param string $str the string to test.
     * @return bool true if the input $str is base64-encoded.
     */
    public static function is_base64(string $str): bool {
        return !empty(clean_param($str, PARAM_BASE64));
    }

    /**
     * Build a unique reproducible cache key from the given string.
     *
     * @param string $key The string to use for the key.
     * @return string The cache key.
     */
    public static function get_cache_key(string $key): string {
        return sha1(get_config('block_integrityadvocate', 'version') . $key);
    }

    /**
     * Clean a tag for use with the Loggly API.
     *
     * @param string $key The tag to clean.
     * @return string The cleaned tag.
     */
    public static function clean_loggly_tag(string $key): string {
        // Ref https://www-staging.loggly.com/docs/tags/
        // Allow alpha-numeric characters, dash, period, and underscore.
        $maxlength = 64;
        return \core_text::substr(trim(preg_replace('/[^0-9a-z_\-.]+/i', '-', clean_param($key, PARAM_TEXT))), 0, $maxlength);
    }

    /**
     * Create a unix timestamp nonce and store it in the Moodle $SESSION variable.
     *
     * @param string $key Key for the nonce that is stored in $SESSION.
     * @return int Unix timestamp The value of the nonce.
     */
    public static function nonce_set(string $key): int {
        global $SESSION;
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && self::log($fxn . "::Started with \$key={$key}");

        // Make sure $key is a safe cache key.
        $sessionkey = self::get_cache_key($key);

        $debug && self::log($fxn . "::About to set \$SESSION key={$key}");
        return $SESSION->$sessionkey = time();
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
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && self::log($fxn . "::Started with \$key={$key}");

        // Clean up $contextname so it is a safe cache key.
        $sessionkey = self::get_cache_key($key);
        if (!isset($SESSION->$sessionkey) || empty($SESSION->$sessionkey) || $SESSION->$sessionkey < 0) {
            $debug && self::log($fxn . "::\$SESSION does not contain key={$key}");
            return false;
        }

        $nonce = $SESSION->$sessionkey;
        $debug && self::log($fxn . "::\Found nonce={$nonce}");

        // Delete it since it should only be used once.
        unset($SESSION->$sessionkey);

        if ($returntrueifexists) {
            return true;
        }

        global $CFG;

        // The nonce is valid if the time is after $CFG->sessiontimeout ago.
        $valid = $nonce >= (time() - $CFG->sessiontimeout);
        $debug && self::log($fxn . "::\Found valid={$valid}");
        return $valid;
    }

}
