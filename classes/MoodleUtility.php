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

require_once($CFG->libdir . '/blocklib.php');

/**
 * Utility functions not specific to this module that interact with Moodle core.
 */
class MoodleUtility {

    /**
     * Return true if there are other block instances of the same name (e.g. "navigation" or "block_navigation") on the $page (where "other" means having a different instance id).
     * Adapted from Moodle 3.10 lib/blocklib.php::is_block_present().
     *
     * @link https://moodle.org/mod/forum/discuss.php?d=359669
     * @param \block_manager $blockmanager The $page->block object.
     * @param \block_base $block The block instance to look for other instances of the same name/type.
     * @return bool True if there are other block instances of the same name (e.g. "navigation" or "block_navigation") on the $page.
     */
    public static function another_blockinstance_exists(\block_manager $blockmanager, \block_base $block): bool {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && \debugging($fxn . '::Started with $block->name=' . $block->instance->blockname .
            '; $block->id=' . $block->instance->id);

        $blockmanager->load_blocks(true);
        $blocknameclean = ia_u::remove_prefix('block_', $block->instance->blockname);

        $requiredbythemeblocks = $blockmanager->get_required_by_theme_block_types();
        foreach ($blockmanager->get_regions() as $region) {
            foreach ($blockmanager->get_blocks_for_region($region) as $instance) {
                if (empty($instance->instance->blockname)) {
                    continue;
                }
                $debug && \debugging($fxn . '::Looking at blockname=' . $instance->instance->blockname . '; instance->id=' . $instance->instance->id);
                if ($instance->instance->blockname == $blocknameclean && intval($instance->instance->id) != intval($block->instance->id)) {
                    if ($instance->instance->requiredbytheme && !in_array($blocknameclean, $requiredbythemeblocks)) {
                        continue;
                    }
                    $debug && \debugging($fxn . '::Found blockname=' . $instance->instance->blockname .
                        '; instance->id=' . $instance->instance->id . ' that does not match the passed in block id=' . $block->instance->id);
                    return true;
                }
            }
        }

        $debug && \debugging($fxn . '::No other instances found with differing ids ');
        return false;
    }

    /**
     * Return true if the block passed in is the first (by display order) visible block of that type on the $page.
     *
     * @param \block_manager $blockmanager The $page->block object.
     * @param \block_base $block The block instance to look for other instances of the same name/type.
     * @return bool True if the block passed in is the first (by display order) visible block of that type on the $page.
     */
    public static function is_first_visible_block_of_type(\block_manager $blockmanager, \block_base $block): bool {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && \debugging($fxn . '::Started with $block->name=' . $block->instance->blockname .
            '; $block->id=' . $block->instance->id);

        $blockmanager->load_blocks(true);
        $blocknameclean = ia_u::remove_prefix('block_', $block->instance->blockname);

        $requiredbythemeblocks = $blockmanager->get_required_by_theme_block_types();
        // This goes through the blocks in their display order.
        foreach ($blockmanager->get_regions() as $region) {
            foreach ($blockmanager->get_blocks_for_region($region) as $instance) {
                if (empty($instance->instance->blockname)) {
                    continue;
                }
                $debug && \debugging($fxn . '::Looking at visible=' . $instance->instance->visible . '; blockname=' .
                    $instance->instance->blockname . '; instance->id=' . $instance->instance->id);
                if ($instance->instance->visible && $instance->instance->blockname == $blocknameclean) {
                    if ($instance->instance->requiredbytheme && !in_array($blocknameclean, $requiredbythemeblocks)) {
                        continue;
                    }
                    if (intval($instance->instance->id) === intval($block->instance->id)) {
                        $debug && \debugging($fxn . '::Found blockname=' . $instance->instance->blockname . '; instance->id=' .
                            $instance->instance->id . ' that matches the passed in block id=' . $block->instance->id);
                        return true;
                    } else {
                        return false;
                    }
                }
            }
        }

        $debug && \debugging($fxn . '::Could not find any matching blocks,so assume it is the first');
        return true;
    }

    /**
     * Count blocks on the current $page with name matching $blockname.
     * Adapted from Moodle 3.10 lib/blocklib.php::is_block_present().
     *
     * @link https://moodle.org/mod/forum/discuss.php?d=359669
     * @param \block_manager $blockmanager The $page->block object.
     * @param string $blockname The name of a block (e.g. "navigation" or "block_navigation").
     * @return int Count of matching blocks on the page.
     */
    public static function count_blocks(\block_manager $blockmanager, string $blockname): int {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && \debugging($fxn . '::Started with $blockname=' . $blockname);

        $blockmanager->load_blocks(true);
        $blocknameclean = ia_u::remove_prefix('block_', $blockname);

        $requiredbythemeblocks = $blockmanager->get_required_by_theme_block_types();
        $count = 0;
        foreach ($blockmanager->get_regions() as $region) {
            foreach ($blockmanager->get_blocks_for_region($region) as $instance) {
                if (empty($instance->instance->blockname)) {
                    continue;
                }
                $debug && \debugging($fxn . '::Looking at blockname=' . $instance->instance->blockname . '; instance->id=' . $instance->instance->id);
                if ($instance->instance->blockname == $blocknameclean) {
                    if ($instance->instance->requiredbytheme && !in_array($blocknameclean, $requiredbythemeblocks)) {
                        continue;
                    }
                    $debug && \debugging($fxn . '::Found blockname=' . $instance->instance->blockname . '; instance->id=' . $instance->instance->id);
                    $count++;
                }
            }
        }

        return $count;
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
        $debug = false;
        $debug && \debugging($fxn . "::Started with \$blockname={$blockname}; \$visibleonly={$visibleonly}");

        // We cannot filter for if the block is visible here b/c the block_participant row is usually NULL in these cases.
        $params = ['blockname' => \preg_replace('/^block_/', '', $blockname)];
        $debug && \debugging($fxn . '::Looking in table block_instances with params=' . ia_u::var_dump($params));

        $records = $DB->get_records('block_instances', $params);
        $debug && \debugging($fxn . '::Found $records=' . (ia_u::is_empty($records) ? '' : ia_u::var_dump($records)));
        if (ia_u::is_empty($records)) {
            $debug && \debugging($fxn . "::No instances of block_{$blockname} found");
            return [];
        }

        // Go through each of the block instances and check visibility.
        $blockinstancerecords = [];
        foreach ($records as $r) {
            $debug && \debugging($fxn . '::Looking at $br=' . ia_u::var_dump($r));

            // Check if it is visible and get the IA appid from the block instance config.
            $blockinstancevisible = self::is_block_visibile($r->parentcontextid, $r->id);
            $debug && \debugging($fxn . "::Found \$blockinstancevisible={$blockinstancevisible}");

            if ($visibleonly && !$blockinstancevisible) {
                continue;
            }

            if (isset($blockinstancerecords[$r->id])) {
                $debug && \debugging($fxn . "::Multiple visible block_{$blockname} instances found in the same parentcontextid - just return the first one");
                continue;
            }

            $blockinstancerecords[$r->id] = \block_instance_by_id($r->id);
        }

        return $blockinstancerecords;
    }

    /**
     * Get blocks in the given contextid (not recursively).
     *
     * @param int $contextid The context id to look in.
     * @param string $blockname Name of the block to get instances for.
     * @param bool $visibleonly True to return only blocks visible to the student.
     * @return array where key=block_instances.id; val=block_instance object.
     */
    private static function get_blocks_in_context(int $contextid, string $blockname, bool $visibleonly = false): array {
        global $DB;

        $blockinstancerecords = [];
        $records = $DB->get_records('block_instances', ['parentcontextid' => $contextid, 'blockname' => \preg_replace('/^block_/', '', $blockname)]);
        foreach ($records as $r) {
            // Check if it is visible.
            if ($visibleonly && !self::is_block_visibile($r->parentcontextid, $r->id)) {
                continue;
            }

            $blockinstancerecords[$r->id] = \block_instance_by_id($r->id);
        }

        return $blockinstancerecords;
    }

    /**
     * Get all blocks in the course and child contexts (modules) matching $blockname.
     *
     * @param int $courseid The courseid to look in.
     * @param string $blockname Name of the block to get instances for.
     * @param bool $visibleonly True to return only blocks visible to the student.
     * @return array where key=block_instances.id; val=block_instance object.
     */
    public static function get_all_course_blocks(int $courseid, string $blockname, bool $visibleonly = false): array {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && \debugging($fxn . "::Started with courseid={$courseid}; \$blockname={$blockname}; \$visibleonly={$visibleonly}");

        $coursecontext = \context_course::instance($courseid, MUST_EXIST);

        // Get course-level instances.
        $blockinstancerecords = self::get_blocks_in_context($coursecontext->id, $blockname, $visibleonly);
        $debug && \debugging($fxn . '::Found course level block count=' . ia_u::count_if_countable($blockinstancerecords));

        // Look in modules for more blocks instances.
        foreach ($coursecontext->get_child_contexts() as $c) {
            $debug && \debugging($fxn . "::Looking at \$c->id={$c->id}; \$c->instanceid={$c->instanceid}; \$c->contextlevel={$c->contextlevel}");
            if ((int) ($c->contextlevel) !== (int) (\CONTEXT_MODULE)) {
                continue;
            }

            $blocksinmodule = self::get_blocks_in_context($c->id, $blockname, $visibleonly);
            $debug && \debugging($fxn . '::Found module level block count=' . ia_u::count_if_countable($blocksinmodule));
            $blockinstancerecords += $blocksinmodule;
        }

        $debug && \debugging($fxn . '::About to return blockinstances count=' . ia_u::count_if_countable(ia_u::var_dump($blockinstancerecords)));
        return $blockinstancerecords;
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
     * @param [] $a array of event information.
     * @param [] $b array of event information.
     * @return int Is less than 0, 0 or greater than 0 depending on order of modules on course page.
     */
    protected static function modules_compare_events(array $a, array $b): int {
        if ($a['section'] != $b['section']) {
            return (int) ($a['section'] - $b['section']);
        } else {
            return (int) ($a['position'] - $b['position']);
        }
    }

    /**
     * Used to compare two modules based their expected completion times.
     *
     * @param object[] $a array of event information.
     * @param object[] $b array of event information.
     * @return int Is less than 0, 0 or greater than 0 depending on time then order of modules.
     */
    protected static function modules_compare_times(array $a, array $b): int {
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
     * returns array[roleid,rolename].
     * @return array<int,string>.
     */
    public static function get_roles_for_select(\context $context): array {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && \debugging($fxn . "::Started with \$context->id={$context->id}");

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make('block_integrityadvocate', 'persession');
        $cachekey = self::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . '_' . $context->id);
        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && \debugging($fxn . '::Found a cached value, so return that');
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

        if (!$cache->set($cachekey, $rolestodisplay)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $rolestodisplay;
    }

    /**
     * Returns the modules with completion set in current course.
     *
     * @param int $courseid The id of the course.
     * @return array Modules with completion settings in the course in the format [module[name-value]].
     */
    public static function get_modules_with_completion(int $courseid): array {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && \debugging($fxn . "::Started with \$courseid={$courseid}");

        $modinfo = \get_fast_modinfo($courseid, -1);
        // Used for sorting.
        $sections = $modinfo->get_sections();
        $modules = [];
        foreach ($modinfo->instances as $module => $instances) {
            $modulename = \get_string('pluginname', $module);
            foreach ($instances as $cm) {
                if (!$cm->deletioninprogress && $cm->completion != \COMPLETION_TRACKING_NONE && $module != 'label') {
                    $debug && \debugging($fxn . '::Looking at module=' . ia_u::var_dump($module));
                    $debug && \debugging($fxn . '::Looking at cm->url=' . ia_u::var_dump($cm->url));
                    $debug && \debugging($fxn . '::Looking at cm-completion=' . ia_u::var_dump($cm->completion));

                    $modules[] = [
                        'type' => $module,
                        'modulename' => $modulename,
                        'id' => $cm->id,
                        'instance' => $cm->instance,
                        'name' => $cm->name,
                        'expected' => $cm->completionexpected,
                        'section' => $cm->sectionnum,
                        // Used for sorting.
                        'position' => \array_search($cm->id, $sections[$cm->sectionnum], true),
                        'url' => is_object($cm->url) && (\method_exists($cm->url, 'out') ? $cm->url->out() : ''),
                        'context' => $cm->context,
                        // Removed b/c it caused error with developer debug display on: 'icon' => $cm->get_icon_url().
                        'available' => $cm->available,
                    ];
                }
            }
        }

        \usort($modules, [static::class, 'modules_compare_times']);

        return $modules;
    }

    /**
     * Filters modules that a user cannot see due to grouping constraints.
     *
     * @param \stdClass $cfg Pass in the Moodle $CFG object.
     * @param array $modules Array of objects representing the possible modules that can occur for modules.
     * @param int $userid The userid it should be visible to.
     * @param int $courseid the course for filtering visibility.
     * @param array $exclusions Array of integers. Assignment exemptions for students in the course.
     * @return array Array of objects representing the input modules without the restricted modules.
     */
    public static function filter_for_visible(\stdClass $cfg, array $modules, int $userid, int $courseid, array $exclusions): array {
        $filteredmodules = [];
        $modinfo = \get_fast_modinfo($courseid, $userid);
        $coursecontext = \context_course::instance($courseid);
        $hascapabilityviewhiddenactivities = \has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid);

        // Keep only modules that are visible.
        foreach ($modules as $m) {
            $coursemodule = $modinfo->cms[$m['id']];

            // Check visibility in course.
            if (!$coursemodule->visible && !$hascapabilityviewhiddenactivities) {
                continue;
            }

            // Check availability, allowing for visible, but not accessible items.
            if (!empty($cfg->enableavailability)) {
                if ($hascapabilityviewhiddenactivities) {
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
     * Return whether a block is visible in the given context.
     *
     * @param int $parentcontextid The module context id.
     * @param int $blockinstanceid The block instance id.
     * @param string $pagetype The page type this block is on.
     * @return bool true if the block is visible in the given context.
     */
    public static function is_block_visibile(int $parentcontextid, int $blockinstanceid, string $pagetype = ''): bool {
        global $DB;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && \debugging($fxn . "::Started with \$parentcontextid={$parentcontextid}; \$blockinstanceid={$blockinstanceid}");

        $params = ['blockinstanceid' => $blockinstanceid, 'contextid' => $parentcontextid];
        if ($pagetype) {
            $params['pagetype'] = $pagetype;
        }
        $record = $DB->get_record('block_positions', $params, 'id,visible', \IGNORE_MULTIPLE);
        $debug && \debugging($fxn . '::Got $record=' . (ia_u::is_empty($record) ? '' : ia_u::var_dump($record)));
        if (ia_u::is_empty($record)) {
            $debug && \debugging($fxn . "::There is no block_positions record, and the default is visible");
            return true;
        }

        return (bool) $record->visible;
    }

    /**
     * Check if a block is visible on a module page given the cmid and block name.
     *
     * @param int $cmid The course module ID.
     * @param string $blockname The shortname of the block, i.e. for block_integrityadvocate it would be integrityadvocate.
     * @return bool True if the block is visible, false otherwise.
     */
    public static function is_block_visible_on_quiz_attempt(int $cmid, string $blockname): bool {
        global $DB;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && \debugging($fxn . "::Started with \$cmid={$cmid}; \$blockname={$blockname}");

        list($course, $cm) = \get_course_and_cm_from_cmid($cmid);
        $debug && \debugging($fxn . "::Got cm=" . ia_u::var_dump($cm));

        // Fetch the block instances for the context.
        $blockinstancerecords = $DB->get_records('block_instances', ['parentcontextid' => $cm->context->id]);
        $debug && \debugging($fxn . "::Got count(blockinstances)=" . ia_u::count_if_countable($blockinstancerecords));

        // Loop through the block instances and check if the block is visible.
        foreach ($blockinstancerecords as $b) {
            $debug && \debugging($fxn . "::Looking at b=" . ia_u::var_dump($b));
            if ($b->blockname === $blockname && self::is_block_visibile($b->parentcontextid, $b->id, 'mod-quiz-attempt')) {
                $debug && \debugging($fxn . '::Found a visible block so return true');
                return true;
            }
        }

        $debug && \debugging($fxn . '::Found no matching visible block');
        return false;
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
        $debug = false;
        $debug && \debugging($fxn . "::Started with \$cmid={$cmid}");

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make('block_integrityadvocate', 'perrequest');
        $cachekey = self::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . '_' . $cmid);
        $debug && \debugging($fxn . "::Built cachekey={$cachekey}");
        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && \debugging($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        global $DB;
        $course = $DB->get_record_sql('
                    SELECT c.id
                      FROM {course_modules} cm
                      JOIN {course} c ON c.id = cm.course
                     WHERE cm.id = ?', [$cmid], 'id', \IGNORE_MULTIPLE);
        $debug && \debugging($fxn . '::Got course=' . ia_u::var_dump($course));

        if (ia_u::is_empty($course) || !isset($course->id)) {
            $debug && \debugging($fxn . "::No course found for cmid={$cmid}");
            $returnthis = -1;
        } else {
            $returnthis = $course->id;
        }

        if (!$cache->set($cachekey, $returnthis)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $returnthis;
    }

    /**
     * Return true if a course exists.
     *
     * @param int $id The course id.
     * @return bool False if no course found; else true.
     */
    public static function couse_exists(int $id): bool {
        global $DB;
        $exists = false;
        try {
            $exists = $DB->record_exists('course', ['id' => $id]);
        } catch (\dml_missing_record_exception $e) {
            // Ignore these - the false is only to make the Moodle CodeChecker happy.
            false;
        }

        return $exists;
    }

    /**
     * Convert course id to moodle course object into if needed.
     *
     * @param int|\stdClass $course The course object or courseid to check
     * @return null|\stdClass False if no course found; else Moodle course object.
     */
    public static function get_course_as_obj($course): ?\stdClass {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && \debugging($fxn . '::Started with type(\$course)=' . \gettype($course));

        if (\is_numeric($course)) {
            // Cache so multiple calls don't repeat the same work.
            $cache = \cache::make('block_integrityadvocate', 'perrequest');
            $cachekey = self::get_cache_key($fxn . '_' . \json_encode($course, \JSON_PARTIAL_OUTPUT_ON_ERROR));
            if ($cachedvalue = $cache->get($cachekey)) {
                $debug && \debugging($fxn . '::Found a cached value, so return that');
                return $cachedvalue;
            }

            $course = \get_course((int) $course);

            if (!$cache->set($cachekey, $course)) {
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
        $query = 'SELECT g.id, ' . $db->sql_concat('i.itemmodule', "'-'", 'i.iteminstance', "'-'", 'g.userid') . ' as exclusion
                   FROM {grade_grades} g, {grade_items} i
                  WHERE i.courseid = :courseid
                    AND i.id = g.itemid
                    AND g.excluded <> 0';
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
        $debug = false;
        $debug && \debugging($fxn . "::Started with \$coursecontext={$coursecontext->instanceid}");

        // Sanity check.
        if (ia_u::is_empty($coursecontext) || ($coursecontext->contextlevel !== \CONTEXT_COURSE)) {
            $msg = 'Input params are invalid';
            \debugging($fxn . "::Started with \$coursecontext->instanceid={$coursecontext->instanceid}");
            \debugging($fxn . '::' . $msg);
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
     * @return \block_base Null if none found or if no visible instances found; else an instance of block_integrityadvocate.
     */
    public static function get_first_block(\context $modulecontext, string $blockname, bool $visibleonly = true, bool $rownotinstance = false): ?\block_base {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && \debugging($fxn . "::Started with \$modulecontext->id={$modulecontext->id}; \$blockname={$blockname}; "
            . "\$visibleonly={$visibleonly}; \$rownotinstance={$rownotinstance}");

        // We cannot filter for if the block is visible here b/c the block_participant row is usually NULL in these cases.
        $params = ['blockname' => \preg_replace('/^block_/', '', $blockname), 'parentcontextid' => $modulecontext->id];
        $debug && \debugging($fxn . '::Looking in table block_instances with params=' . ia_u::var_dump($params));

        // Z--.
        // Danger: Caching the resulting $record in the perrequest cache didn't work - we get an invalid stdClass back out.
        // Z--.
        global $DB;
        $records = $DB->get_records('block_instances', $params);
        $debug && \debugging($fxn . '::Found blockinstance records=' . (ia_u::is_empty($records) ? '' : ia_u::var_dump($records)));
        if (ia_u::is_empty($records)) {
            $debug && \debugging($fxn . "::No instances of block_{$blockname} is associated with this context");
            return null;
        }

        // If there are multiple blocks in this context just return the first valid one.
        $blockrecord = null;
        foreach ($records as $r) {
            // Check if it is visible and get the IA appid from the block instance config.
            $r->visible = self::is_block_visibile($modulecontext->id, $r->id);
            $debug && \debugging($fxn . "::For \$modulecontext->id={$modulecontext->id} and \$record->id={$r->id} found \$record->visible={$r->visible}");
            if ($visibleonly && !$r->visible) {
                $debug && \debugging($fxn . '::$visibleonly=true and this instance is not visible so skip it');
                continue;
            }

            $blockrecord = $r;
            break;
        }
        if (empty($blockrecord)) {
            $debug && \debugging($fxn . '::No valid blockrecord found, so return false');
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
     * @return null|\stdClass False if no user found; else moodle user object.
     */
    public static function get_user_as_obj($user): ?\stdClass {
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && \debugging($fxn . '::Started with type($user)=' . \gettype($user));

        if (\is_numeric($user)) {
            // Cache so multiple calls don't repeat the same work.
            $cache = \cache::make('block_integrityadvocate', 'perrequest');
            $cachekey = self::get_cache_key($fxn . '_' . \json_encode($user, \JSON_PARTIAL_OUTPUT_ON_ERROR));
            if ($cachedvalue = $cache->get($cachekey)) {
                $debug && \debugging($fxn . '::Found a cached value, so return that');
                return $cachedvalue;
            }

            global $CFG;
            require_once($CFG->dirroot . '/user/lib.php');
            $userarr = \user_get_users_by_id([(int) $user]);
            if (empty($userarr)) {
                return null;
            }
            $user = \array_pop($userarr);

            if (isset($user->deleted) && $user->deleted) {
                return null;
            }

            if (!$cache->set($cachekey, $user)) {
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
        $debug = false;
        $debug && \debugging($fxn . "::Started with \$user->id={$user->id}; \$params=" . \serialize($params));

        // Cache so multiple calls don't repeat the same work.  Persession cache b/c is keyed on hash of $blockinstance.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = self::get_cache_key($fxn . '_' . \json_encode($user, \JSON_PARTIAL_OUTPUT_ON_ERROR) . '_' . \json_encode($params, \JSON_PARTIAL_OUTPUT_ON_ERROR));
        if ($cachedvalue = $cache->get($cachekey)) {
            $debug && \debugging($fxn . '::Found a cached value, so return that');
            return $cachedvalue;
        }

        $userpicture = new \user_picture($user);
        foreach ($params as $key => $val) {
            if (\object_property_exists($userpicture, $key)) {
                $userpicture->{$key} = $val;
            }
        }
        $debug && \debugging($fxn . '::Built user_picture=' . ia_u::var_dump($userpicture));

        $page = new \moodle_page();
        $page->set_url('/user/profile.php');
        if (isset($params['courseid']) && !ia_u::is_empty($params['courseid'])) {
            $page->set_context(\context_course::instance($params['courseid']));
        } else if (!ia_u::is_empty($userpicture->courseid)) {
            $page->set_context(\context_course::instance($userpicture->courseid));
        } else {
            $page->set_context(\context_system::instance());
        }
        $picture = $userpicture->get_url($page, $page->get_renderer('core'))->out(false);

        if (!$cache->set($cachekey, $picture)) {
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
        $lastaccess = $DB->get_field_sql(
            'SELECT MAX("timeaccess") lastaccess FROM {user_lastaccess} WHERE courseid=?',
            [$courseidcleaned],
            \IGNORE_MISSING
        );

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
        $debug = false;
        $debug && \debugging($fxn . "::Started with \$key={$key}");

        // Make sure $key is a safe cache key.
        $sessionkey = self::get_cache_key($key);

        $debug && \debugging($fxn . "::About to set \$SESSION key={$key}");
        return $SESSION->{$sessionkey} = \time();
    }

    /**
     * Check the nonce key exists in $SESSION and is not timed out.  Deletes the nonce key.
     *
     * @param string $key The Nonce key to check.  This gets cleaned automatically.
     * @param bool $returntrueifexists True means: Return true if the nonce key exists, ignoring the timeout..
     * @return bool $returntrueifexists=false: True if the nonce key exists, is not empty (unixtime=0), and is not timed out.
     * $returntrueifexists=true: Returns true if the nonce key exists and is not empty (unixtime=0).
     */
    public static function nonce_validate(string $key, bool $returntrueifexists = false): bool {
        global $SESSION;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug = false;
        $debug && \debugging($fxn . "::Started with \$key={$key}; \$returntrueifexists={$returntrueifexists}");

        // Clean up $contextname so it is a safe cache key.
        $sessionkey = self::get_cache_key($key);
        if (!isset($SESSION->{$sessionkey}) || empty($SESSION->{$sessionkey}) || $SESSION->{$sessionkey} < 0) {
            $debug && \debugging($fxn . "::\$SESSION does not contain key={$key}");
            return false;
        }

        $nonce = $SESSION->{$sessionkey};
        $debug && \debugging($fxn . "::\Found nonce={$nonce}");

        // Delete it since it should only be used once.
        $SESSION->{$sessionkey} = null;

        if ($returntrueifexists) {
            return true;
        }

        global $CFG;

        // The nonce is valid if the time is after $CFG->sessiontimeout ago.
        $valid = $nonce >= (\time() - $CFG->sessiontimeout);
        $debug && \debugging($fxn . "::\Found valid={$valid}");
        return $valid;
    }

    /**
     * Determines if SSL is used. The Moodle weblib.php::is_http() only looks at $CFG->wwwroot, which may not be a reliable indicator.
     * Adapted from WordPress 5.8.
     *
     * @return bool True if SSL, otherwise false.
     */
    public static function is_ssl() {
        global $CFG;
        if (strpos($CFG->wwwroot, 'https://') === 0) {
            return true;
        }

        if (isset($_SERVER['HTTPS'])) {
            if ('on' === strtolower($_SERVER['HTTPS'])) {
                return true;
            }

            if ('1' == $_SERVER['HTTPS']) {
                return true;
            }
        } else if (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
            return true;
        }

        return false;
    }

    /**
     * Get a single database record as an object where all the given conditions met.
     * This version caches per request, and just calls moodle_database.php file moodle_database->get_record().
     *
     * @param string $table The table to select from.
     * @param array $conditions optional array $fieldname=>requestedvalue with AND in between
     * @param string $fields A comma separated list of fields to be returned from the chosen table.
     * @param int $strictness IGNORE_MISSING means compatible mode, false returned if record not found, debug message if more found;
     *                        IGNORE_MULTIPLE means return first, ignore multiple records found(not recommended);
     *                        MUST_EXIST means we will throw an exception if no record or multiple records found.
     *
     * @return mixed a fieldset object containing the first matching record, false or exception if error not found depending on mode
     * @throws \dml_exception A DML specific exception is thrown for any errors.
     */
    public static function get_record_cached($table, array $conditions, $fields = '*', $strictness = \IGNORE_MISSING) {
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'perrequest');
        $cachekey = self::get_cache_key(\implode('_', [__CLASS__, __FUNCTION__, $table, json_encode($conditions), $fields, $strictness]));
        if ($cachedvalue = $cache->get($cachekey)) {
            return $cachedvalue;
        }

        global $DB;
        $returnthis = $DB->get_record($table, $conditions, $fields, $strictness);

        if (!$cache->set($cachekey, $returnthis)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $returnthis;
    }

    /**
     * Get a quiz attempt object.
     * This does call a security check via $attemptobj->check_review_capability().
     *
     * @param int $attemptid The attempt id.
     * @param int $cmid The module id.
     * @return \quiz_attempt $attemptobj all the data about the quiz attempt.
     */
    public static function get_quiz_attemptobj(int $attemptid, int $cmid): \quiz_attempt {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $attemptobj = \quiz_create_attempt_handling_errors($attemptid, $cmid);

        // Security check.
        $attemptobj->check_review_capability();

        return $attemptobj;
    }

    /**
     * Return true if the quiz will show the review page after the attempt.
     *
     * @param int $courseid The course id.
     * @param int $attemptid The quiz attempt id.
     * @param int $cmid The module id.
     * @return bool true if the quiz will show the review page after the attempt.
     */
    public static function quiz_shows_review_page_after_attempt(int $courseid, int $attemptid, int $cmid): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \debugging($fxn . '::Started with $attemptid=' . $attemptid . '; $cmid=' . $cmid);

        $returnthisdefault = true;

        if ($attemptid < 0 && $cmid < 0) {
            return $returnthisdefault;
        }

        $cm = \get_course_and_cm_from_cmid($cmid, 'quiz', $courseid /* Include even if the participant cannot access the module */)[1];
        $debug && \debugging($fxn . '::Got $cm->instance=' . ia_u::var_dump($cm->instance));

        $quizrecord = self::get_record_cached('quiz', ['id' => (int) ($cm->instance)], '*', \MUST_EXIST);
        // Disabled bc TMI: $debug && \debugging($fxn . '::Got $quizrecord=' . ia_u::var_dump($quizrecord));.
        $debug && \debugging($fxn . '::Got $quizrecord->reviewattempt dec=' . $quizrecord->reviewattempt . '; hex=' . dechex($quizrecord->reviewattempt));

        global $CFG;
        if (
            (
                // Moodle 4.2 deprecated \mod_quiz_display_options.
                ($CFG->version >= 2022041900 && $quizrecord->reviewattempt && \mod_quiz\question\display_options::IMMEDIATELY_AFTER)
                || ($quizrecord->reviewattempt && \mod_quiz_display_options::IMMEDIATELY_AFTER)
            )
            // IMMEDIATELY_AFTER = within 2 mins of clicking 'Submit all and finish'.
            // Disabled bc not needed: || $quizrecord->reviewattempt & \mod_quiz_display_options::LATER_WHILE_OPEN /** After 2 mins but before the quiz close date. */.
        ) {
            $debug && \debugging($fxn . '::Quiz review is enabled');
            return true;
        } else {
            $debug && \debugging($fxn . '::Quiz review is disabled');
            return false;
        }
    }

    /**
     * Override a quiz attempt start time with specified additional time.
     *
     * @param int $attemptid The ID of the quiz attempt to modify.
     * @param int $newtimestart The additional time to add in seconds.
     * @return bool True if the time was successfully set, false otherwise.
     */
    public static function quiz_set_timestart(int $attemptid, int $newtimestart): bool {
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \debugging($fxn . '::Started with $attemptid=' . $attemptid . '; $newtimestart=' . $newtimestart);

        global $DB;
        $returnthis = false;

        $returnthis = $DB->set_field('quiz_attempts', 'timestart', $newtimestart, ['id' => $attemptid]);

        return $returnthis;
    }
}
