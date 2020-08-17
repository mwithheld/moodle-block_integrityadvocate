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
 * Polyfills for earlier PHP versions.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/*
 * Polyfill functions
 */
if (version_compare(phpversion(), '7.3.0', '<')) {
    if (!function_exists('is_countable')) {

        /**
         * Polyfill for is_countable()
         *
         * @link https://www.php.net/manual/en/function.is-countable.php#123089
         * @param Countable $var object to check if it is countable.
         * @return bool true if is countable.
         */
        function is_countable($var): bool {
            return (is_array($var) || $var instanceof Countable);
        }

    }
}
if (version_compare(phpversion(), '8', '<')) {
    if (!function_exists('str_contains')) {

        function str_contains(string $haystack, string $needle): bool {
            return '' === $needle || false !== strpos($haystack, $needle);
        }

    }

    if (!function_exists('str_starts_with')) {

        function str_starts_with(string $haystack, string $needle): bool {
            return 0 === \strncmp($haystack, $needle, \strlen($needle));
        }

    }

    if (!function_exists('str_ends_with')) {

        function str_ends_with(string $haystack, string $needle): bool {
            return '' === $needle || ('' !== $haystack && 0 === \substr_compare($haystack, $needle, -\strlen($needle)));
        }

    }
}