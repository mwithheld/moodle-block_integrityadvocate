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
 * IntegrityAdvocate block common configuration and helper functions
 *
 * This entire file comes from block_completion_progress
 * https://moodle.org/plugins/block_completion_progress
 * with full credit and thanks due to Michael de Raadt.
 *
 * Changes include:
 *   - Remove unused code.
 *   - Rename functions so they do not conflict.
 *   - Slight tweaks.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

$block_integrityadvocate_wwwroot = dirname(__FILE__, 3);
require_once($block_integrityadvocate_wwwroot . '/config.php');
require_once($block_integrityadvocate_wwwroot . '/lib/completionlib.php');
require_once(__DIR__ . '/locallib.php');

/**
 * Returns the activities with completion set in current course
 *
 * @param int courseid The id of the course
 * @param object $config The block instance configuration
 * @param string $forceorder An override for the course order setting
 * @return array(activities) Activities with completion settings in the course
 */
function block_integrityadvocate_get_activities_with_completion($courseid, $config = null, $forceorder = null) {
    $modinfo = get_fast_modinfo($courseid, -1);
    $sections = $modinfo->get_sections();
    $activities = array();
    foreach ($modinfo->instances as $module => $instances) {
        $modulename = get_string('pluginname', $module);
        foreach ($instances as $cm) {
            if (
                    $cm->completion != COMPLETION_TRACKING_NONE && (
                    $config == null || (
                    !isset($config->activitiesincluded) || (
                    $config->activitiesincluded != 'selectedactivities' ||
                    !empty($config->selectactivities) &&
                    in_array($module . '-' . $cm->instance, $config->selectactivities))))
            ) {
                $activities[] = array(
                    'type' => $module,
                    'modulename' => $modulename,
                    'id' => $cm->id,
                    'instance' => $cm->instance,
                    'name' => $cm->name,
                    'expected' => $cm->completionexpected,
                    'section' => $cm->sectionnum,
                    'position' => array_search($cm->id, $sections[$cm->sectionnum]),
                    'url' => method_exists($cm->url, 'out') ? $cm->url->out() : '',
                    'context' => $cm->context,
                    //'icon' => $cm->get_icon_url(),
                    'available' => $cm->available,
                );
            }
        }
    }

    usort($activities, 'block_integrityadvocate_compare_times');

    return $activities;
}

/**
 * Used to compare two activities/resources based on order on course page
 *
 * @param object[] $a array of event information
 * @param object[] $b array of event information
 * @return int <0, 0 or >0 depending on order of activities/resources on course page
 */
function block_integrityadvocate_compare_events($a, $b) {
    if ($a['section'] != $b['section']) {
        return $a['section'] - $b['section'];
    } else {
        return $a['position'] - $b['position'];
    }
}

/**
 * Used to compare two activities/resources based their expected completion times
 *
 * @param object[] $a array of event information
 * @param object[] $b array of event information
 * @return int <0, 0 or >0 depending on time then order of activities/resources
 */
function block_integrityadvocate_compare_times($a, $b) {
    if (
            $a['expected'] != 0 &&
            $b['expected'] != 0 &&
            $a['expected'] != $b['expected']
    ) {
        return $a['expected'] - $b['expected'];
    } else if ($a['expected'] != 0 && $b['expected'] == 0) {
        return -1;
    } else if ($a['expected'] == 0 && $b['expected'] != 0) {
        return 1;
    } else {
        return block_integrityadvocate_compare_events($a, $b);
    }
}

/**
 * Filters activities that a user cannot see due to grouping constraints
 *
 * @param stdClass $cfg Pass in the Moodle $CFG object.
 * @param stdClass $activities The possible activities that can occur for modules
 * @param int $userid The user's id
 * @param string $courseid the course for filtering visibility
 * @param int[] $exclusions Assignment exemptions for students in the course
 * @return object[] The array without the restricted activities
 */
function block_integrityadvocate_filter_visibility(stdClass $cfg, $activities, $userid, $courseid, $exclusions) {
    $filteredactivities = array();
    $modinfo = get_fast_modinfo($courseid, $userid);
    $coursecontext = CONTEXT_COURSE::instance($courseid);

    // Keep only activities that are visible.
    foreach ($activities as $activity) {

        $coursemodule = $modinfo->cms[$activity['id']];

        // Check visibility in course.
        if (!$coursemodule->visible && !has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid)) {
            continue;
        }

        // Check availability, allowing for visible, but not accessible items.
        if (!empty($cfg->enableavailability)) {
            if (has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid)) {
                $activity['available'] = true;
            } else {
                if (isset($coursemodule->available) && !$coursemodule->available && empty($coursemodule->availableinfo)) {
                    continue;
                }
                $activity['available'] = $coursemodule->available;
            }
        }

        // Check visibility by grouping constraints (includes capability check).
        if (!empty($cfg->enablegroupmembersonly)) {
            if (isset($coursemodule->uservisible)) {
                if ($coursemodule->uservisible != 1 && empty($coursemodule->availableinfo)) {
                    continue;
                }
            } else if (!groups_course_module_visible($coursemodule, $userid)) {
                continue;
            }
        }

        // Check for exclusions.
        if (in_array($activity['type'] . '-' . $activity['instance'] . '-' . $userid, $exclusions)) {
            continue;
        }

        // Save the visible event.
        $filteredactivities[] = $activity;
    }
    return $filteredactivities;
}

/**
 * Check if a user has completed an activity/resource
 *
 * @param array $activities  The activities with completion in the course
 * @param int $userid The user's id
 * @param int $course The course instance
 * @param object[] $submissions Submissions by the user
 * @return object[] Array describing the user's attempts based on module+instance identifiers
 */
function block_integrityadvocate_completions($activities, $userid, $course, $submissions) {
    $completions = array();
    $completion = new completion_info($course);
    $cm = new stdClass();

    foreach ($activities as $activity) {
        $cm->id = $activity['id'];
        $activitycompletion = $completion->get_data($cm, true, $userid);
        $completions[$activity['id']] = $activitycompletion->completionstate;
        if ($completions[$activity['id']] === COMPLETION_INCOMPLETE && in_array($activity['id'], $submissions)) {
            $completions[$activity['id']] = 'submitted';
        }
    }

    return $completions;
}

/**
 * Finds gradebook exclusions for students in a course
 *
 * @param moodle_database $db Moodle DB object
 * @param int $courseid The ID of the course containing grade items
 * @return array of exclusions as activity-user pairs
 */
function block_integrityadvocate_exclusions(moodle_database $db, $courseid) {
    $query = "SELECT g.id, " . $db->sql_concat('i.itemmodule', "'-'", 'i.iteminstance', "'-'", 'g.userid') . " as exclusion
               FROM {grade_grades} g, {grade_items} i
              WHERE i.courseid = :courseid
                AND i.id = g.itemid
                AND g.excluded <> 0";
    $params = array('courseid' => $courseid);
    $results = $db->get_records_sql($query, $params);
    $exclusions = array();
    foreach ($results as $value) {
        $exclusions[] = $value->exclusion;
    }
    return $exclusions;
}

/**
 * Determines whether a user is a member of a given group or grouping
 *
 * @param string $group The group or grouping identifier starting with 'group-' or 'grouping-'
 * @param int $courseid The ID of the course containing the block instance
 * @param int $userid ID of the user to find membership for
 * @return boolean value indicating membership
 */
function block_integrityadvocate_group_membership($group, $courseid, $userid) {
    if ($group === '0') {
        return true;
    } else if ((substr($group, 0, 6) == 'group-') && ($groupid = intval(substr($group, 6)))) {
        return groups_is_member($groupid, $userid);
    } else if ((substr($group, 0, 9) == 'grouping-') && ($groupingid = intval(substr($group, 9)))) {
        return array_key_exists($groupingid, groups_get_user_groups($courseid, $userid));
    }

    return false;
}
