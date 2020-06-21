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
 * IntegrityAdvocate generic utility functions not specific to Moodle.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

/**
 * Generic utility functions not specific to Moodle.
 */
class Utility {

    /**
     * Wrapper around PHP empty() that also works for objects.
     * If the object has any properties it is considered not empty.
     * Unlike the language construct empty(), it will throw an error if the variable does not exist.
     *
     * @link https://stackoverflow.com/a/25320265
     * @param mixed $obj The variable to test for empty-ness.
     * @return bool true if empty; else false.
     */
    public static function is_empty($obj): bool {
        if (!is_object($obj)) {
            return empty($obj);
        }

        $arr = (array) $obj;
        return empty($arr);
    }

    /**
     * Check if the string is a guid
     * Requires dashes and removes braces
     * @link https://stackoverflow.com/a/1253417
     * @param String $str
     * @return true if is a valid guid
     */
    public static function is_guid(string $str): bool {
        return preg_match('/^[a-f\d]{8}-?(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i', $str);
    }

    /**
     * Same as stripos but with an array of needles
     *
     * @link https://stackoverflow.com/a/9220624
     * @param string $haystack The string to search in
     * @param string[] $needles Regexes to search for
     * @param int $offset Optional string offset to start from
     * @return bool true if found; else false
     */
    public static function strposabool(string $haystack, array $needles, int $offset = 0): bool {
        if (!is_array($needles)) {
            $needles = array($needles);
        }
        foreach ($needles as $query) {
            if (strpos($haystack, $query, $offset) !== false) {
                // Stop on first true result.
                return true;
            }
        }
        return false;
    }

    /**
     * Sort the object by the created property in descending order
     * E.g. an IA Flag object.
     *
     * @param object $a
     * @param object $b
     * @return int 0 if the same; -1 if $a->created exceeds $b->created; else 1.
     */
    public static function sort_by_created_desc($a, $b): int {
        if ($a->created == $b->created) {
            return 0;
        }
        return ($a->created > $b->created) ? -1 : 1;
    }

    /**
     * Sort the object by the start property in descending order
     * E.g. an IA Session object.
     *
     * @param object $a
     * @param object $b
     * @return int 0 if the same; -1 if $a->start exceeds $b->start; else 1.
     */
    public static function sort_by_start_desc($a, $b): int {
        if ($a->start == $b->start) {
            return 0;
        }
        return ($a->start > $b->start) ? -1 : 1;
    }

    /**
     * In a "screw you" gesture to Moodle coding standards, just wraps print_r().
     */
    public static function var_dump($var, bool $tostring = false): string {
        return print_r($var, $tostring);
    }

}
