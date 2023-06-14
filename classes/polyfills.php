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
if (\version_compare(\PHP_VERSION, '7.3.0', '<')) {
    if (!\function_exists('is_countable')) {

        /**
         * Polyfill for is_countable()
         *
         * @link https://www.php.net/manual/en/function.is-countable.php#123089
         * @param Countable $var object to check if it is countable.
         * @return bool true if is countable.
         */
        function is_countable($var): bool {
            return ($var instanceof Countable || \is_array($var));
        }

    }
}
if (\version_compare(\PHP_VERSION, '8', '<')) {

    if (!\defined('FILTER_VALIDATE_BOOL') && \defined('FILTER_VALIDATE_BOOLEAN')) {
        \define('FILTER_VALIDATE_BOOL', \FILTER_VALIDATE_BOOLEAN);
    }

    if (!\function_exists('str_contains')) {

        /**
         * Polyfill for str_contains.
         *
         * @param string $haystack The string to search in.
         * @param string $needle The substring to search for in the haystack.
         * @return bool Returns true if needle is in haystack, false otherwise.
         */
        function str_contains(string $haystack, string $needle): bool {
            return '' === $needle || false !== \mb_strpos($haystack, $needle);
        }

    }
    if (!\function_exists('str_icontains')) {

        /**
         * Case-insensitive str_contains.
         *
         * @param string $haystack The string to search in.
         * @param string $needle The substring to search for in the haystack.
         * @return bool Returns true if needle is in haystack, false otherwise.
         */
        function str_icontains(string $haystack, string $needle): bool {
            return '' === $needle || false !== \mb_stripos($haystack, $needle);
        }

    }

    if (!\function_exists('str_starts_with')) {

        /**
         * Performs a case-sensitive check indicating if haystack begins with needle.
         *
         * @param string $haystack The string to search in.
         * @param string $needle The substring to search for in the haystack.
         * @return bool Returns true if haystack begins with needle, false otherwise.
         */
        function str_starts_with(string $haystack, string $needle): bool {
            return 0 === \strncmp($haystack, $needle, \mb_strlen($needle));
        }

    }

    if (!\function_exists('str_ends_with')) {

        /**
         * Performs a case-sensitive check indicating if haystack ends with needle.
         *
         * @param string $haystack The string to search in.
         * @param string $needle The substring to search for in the haystack.
         * @return bool Returns true if haystack ends with needle, false otherwise.
         */
        function str_ends_with(string $haystack, string $needle): bool {
            return '' === $needle || ('' !== $haystack && 0 === \substr_compare($haystack, $needle, -\mb_strlen($needle)));
        }

    }

    if (!\function_exists('get_debug_type')) {

        /**
         * Returns the type of a variable. The new function works in quite a similar way as the gettype function, but get_debug_type returns native type names and resolves class names.
         *
         * @link https://kinsta.com/blog/php-8/#get_debug_type
         * @param \__PHP_Incomplete_Class $value The variable to get the type of.
         * @return string Native type of class name.
         */
        function get_debug_type($value): string {
            switch (true) {
                case null == $value: return 'null';
                case \is_bool($value): return 'bool';
                case \is_string($value): return 'string';
                case \is_array($value): return 'array';
                case \is_int($value): return 'int';
                case \is_float($value): return 'float';
                case \is_object($value): break;
                case $value instanceof \__PHP_Incomplete_Class: return '__PHP_Incomplete_Class';
                default:
                    if (null == $type = @\get_resource_type($value)) {
                        return 'unknown';
                    }

                    if ('Unknown' === $type) {
                        $type = 'closed';
                    }

                    return "resource ($type)";
            }

            $class = \get_class($value);

            if (false === \mb_strpos($class, '@')) {
                return $class;
            }

            return (\get_parent_class($class) ?: \key(\class_implements($class)) ?: 'class') . '@anonymous';
        }

    }
}