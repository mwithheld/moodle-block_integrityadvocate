<?php
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