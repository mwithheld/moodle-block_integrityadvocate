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

defined('MOODLE_INTERNAL') || die;

// Polyfill for PHP7.1 b/c we use is_countable().
require_once('polyfills.php');

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
        if (!\is_object($obj)) {
            return empty($obj);
        }

        $arr = (array) $obj;
        return empty($arr);
    }

    /**
     * Check if the string is a guid.
     * Requires dashes and removes braces.
     *
     * @link https://stackoverflow.com/a/1253417
     * @param string $str String to test.
     * @return bool True if is a valid guid.
     */
    public static function is_guid(string $str): bool {
        return (bool) \preg_match('/^[a-f\d]{8}-?(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i', $str);
    }

    /**
     * Check if the value is an integer or integer string UNIX timestamp before the time now but greater than zero.
     *
     * @param mixed $unixtime integer or integer string.
     * @return bool True if the input value is a valid past unix time > 0.
     */
    public static function is_unixtime_past($unixtime): bool {
        if (\is_numeric($unixtime)) {
            $unixtime = (int) $unixtime;
        }

        return \is_int($unixtime) && ($unixtime > 0) && ($unixtime <= \time());
    }

    /**
     * Same as strpos but with an array of needles
     *
     * @link https://stackoverflow.com/a/9220624
     * @param string $haystack The string to search in
     * @param string[] $needles Regexes to search for
     * @param int $offset Optional string offset to start from
     * @return bool true if found; else false
     */
    public static function strposabool(string $haystack, array $needles, int $offset = 0): bool {
        if (!\is_array($needles)) {
            $needles = [$needles];
        }
        foreach ($needles as $query) {
            if (\mb_strpos($haystack, $query, $offset) !== false) {
                // Stop on first true result.
                return true;
            }
        }
        return false;
    }

    /**
     * Sort the object by the created property in descending order
     * E.g. an IA Flag object.
     * Type hints commented out for PHP7.1 compat.
     *
     * @param object $a The first object to sort.
     * @param object $b The second object to sort.
     * @return int 0 if the same; -1 if $a->created exceeds $b->created; else 1.
     */
    public static function sort_by_created_desc(/* object */ $a, /* object */ $b): int {
        if ($a->created == $b->created) {
            return 0;
        }
        return ($a->created > $b->created) ? -1 : 1;
    }

    /**
     * Sort the object by the start property in descending order.
     * Type hints commented out for PHP7.1 compat.
     *
     * @param object $a The first object to sort.
     * @param object $b The second object to sort.
     * @return int 0 if the same; -1 if $a->start exceeds $b->start; else 1.
     */
    public static function sort_by_start_desc(/* object */ $a, /* object */ $b): int {
        if ($a->start == $b->start) {
            return 0;
        }
        return ($a->start > $b->start) ? -1 : 1;
    }

    /**
     * Just wraps print_r(), but defaults to returning as a string.  If $expression is an object that has implemented __toString() then this is used.
     *
     * @param mixed $expression <p>The expression to be printed.</p>
     * @param bool $return <p>If you would like to capture the output of <b>print_r()</b>, use the <code>return</code> parameter. When this parameter is set
     * to <b><code>TRUE</code></b>, <b>print_r()</b> will return the information rather than print it.</p>
     * @return mixed <p>If given a <code>string</code>, <code>integer</code> or <code>float</code>, the value itself will be printed.
     * If given an <code>array</code>, values will be presented in a format that shows keys and elements. Similar notation is used
     * for <code>object</code>s.</p><p>When the <code>return</code> parameter is <b><code>TRUE</code></b>, this function will return
     * a <code>string</code>. Otherwise, the return value is <b><code>TRUE</code></b>.</p>
     */
    public static function var_dump($expression, bool $return = true) {
        if (self::is_empty($expression)) {
            // phpcs:ignore
            return \print_r('', $return);
        }

        // Avoid OOM errors.
        \raise_memory_limit(\MEMORY_HUGE);

        if (\is_object($expression)) {
            if (\property_exists($expression, 'page') && (\gettype($expression->page) == 'object') &&
                    \class_exists('moodle_page', false) && $expression->page instanceof \moodle_page) {
                $expression->page = null;
            }
            if (\method_exists(\get_class($expression), '__toString')) {
                $expression = $expression->__toString();
            }
        }

        if (\is_array($expression) && isset($expression['page']) && (\gettype($expression['page']) == 'object') &&
                \class_exists('moodle_page', false) && $expression['page'] instanceof \moodle_page) {
            unset($expression['page']);
        }

        // Preg_replace prevents dying on base64-encoded images.
        // phpcs:ignore
        return \print_r(\preg_replace(INTEGRITYADVOCATE_REGEX_DATAURI, 'redacted_base64_image', \print_r($expression, true)), $return);
    }

    /**
     * Returns the count (including if zero), or -1 if not countable.
     *
     * @param mixed $var Variable to get the count for
     * @return int The count; -1 if not countable.
     */
    public static function count_if_countable($var): int {
        return \is_countable($var) ? \count($var) : -1;
    }

    /**
     * Remove $prefix from string $text and return the result.
     *
     * @link https://stackoverflow.com/a/16638231
     * @param string $prefix The prefix to remove.
     * @param string $text The text to clean.
     * @return string The result.
     */
    public static function remove_prefix(string $prefix, string $text): string {
        if (0 === strpos($text, $prefix)) {
            $text = substr($text, strlen($prefix)) . '';
        }
        return $text;
    }

    /**
     * Get the file path relative to this plugin.
     *
     * @param string $filepath File path.
     * @return string File path relative to this plugin.
     */
    public static function filepath_relative_to_plugin(string $filepath): string {
        return \ltrim(\str_replace(\dirname(__DIR__), '', $filepath), '/');
    }

    /**
     * In the unlikely case apache_request_headers() does not exist, attempt fallback functionality.
     * @return Array Array of headers as key-value strings.
     */
    public static function get_request_headers(): array {
        if (function_exists('apache_request_headers')) {
            return apache_request_headers();
        } else {
            // Ref https://www.php.net/manual/en/function.apache-request-headers.php#116645 .
            foreach ($_SERVER as $key => $val) {
                $a = explode('_', $key);
                if (array_shift($a) == 'HTTP') {
                    array_walk($a, function (&$v) {
                        $v = ucfirst(strtolower($v));
                    });
                    $retval[join('-', $a)] = $val;
                }
            } return $retval;
        }
    }
}
