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
 * IntegrityAdvocate class to enable/disable features easily.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

// Needed so we can choose to log from methods in this class.
require_once(\dirname(__DIR__) . '/externallib.php');

//require_once(__DIR__ . '/ParticipantsTable.php');

use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

\defined('MOODLE_INTERNAL') || die;

/**
 * Determines where to send error logs.
 *
 * @author markv
 */
class Logger {

    /** @var string Do not store logged messages. */
    public const NONE = 'NONE';

    /** @var string Store logged messages to the standard PHP error log. */
    public const ERRORLOG = 'ERRORLOG';

    /** @var string Send logged messages to standard HTML output, adding a <br> tag and a newline. */
    public const HTML = 'HTML';

    /** @var string Store logged messages to STDOUT through htmlentities. */
    public const LOGGLY = 'LOGGLY';

    /** @var string Required for Loggly logging */
    public const LOGGLY_TOKEN = 'fab8d2aa-69a0-4b03-8063-b41b215f2e32';

    /** @var string Store logged messages to the moodle log handler plain-textified. */
    public const MLOG = 'MLOG';

    /** @var string Store logged messages to STDOUT through htmlentities. */
    public const STDOUT = 'STDOUT';

    /** @var string Prefix to use when a function has no namespace */
    public const NONAMESPACE_FUNCTION_PREFIX = \INTEGRITYADVOCATE_BLOCK_NAME . '\\';

    /** @var string Even if the local debug flag is false, this enables debug logging for these classnames (including namespace).
     * Names come from __CLASS__.
     * Examples:
     *   - non-namespaced class method: classname
     *   - namespaced class method: namespace\classname, e.g. 'block_integrityadvocate\Api'
     */
    // Unused: public static $logForClass = [];.

    public static function get_log_destinations(): array {
        return [Logger::NONE, Logger::ERRORLOG, Logger::HTML, Logger::LOGGLY, Logger::MLOG, Logger::STDOUT];
    }

    /** @var string Even if the local debug flag is false, this enables debug logging for these functions.
     * Names come from __METHOD__.
     * Examples:
     *   - non-namespaced class method: classname::functionname
     *   - non-namespaced standalone function: functionname
     *   - namespaced class method: namespace\classname::functionname
     *   - namespaced standalone function: namespace\functionname
     * This is overridden by $logForClass and is only used if $blockconfig->config_logforfunction is empty.
     */
    public static $logForFunction = [
//        INTEGRITYADVOCATE_BLOCK_NAME.'\Api::get_participant',
//        INTEGRITYADVOCATE_BLOCK_NAME.'\Api::get_participants_data',
//        INTEGRITYADVOCATE_BLOCK_NAME . '\Api::get_participantsessions',
//        INTEGRITYADVOCATE_BLOCK_NAME . '\Api::get_participantsessions_data',
    ];

    /** @var string Determines where to send error logs. For values, see self::log()'s switch statement. */
    public static $default = self::NONE;

    /**
     * Return true if the namespaced classname is in the self::$logForClass array,
     * indicating we should debug log for this class.
     * Names come from __CLASS__.
     * Examples:
     *   - non-namespaced class method: classname
     *   - namespaced class method: namespace\classname
     *
     * @param string $classname Namespaced classname, e.g. block_integrityadvocate\Api.
     * @return bool True if the namespaced classname is in the self::$logForClass array,
      public static function doLogForClass(string $classname): bool {
      if (empty($classname)) {
      return false;
      }
      return \in_array($classname, self::$logForClass, true);
      }
     */

    /**
     * Return true if the namespaced functionname is in the self::$logForClass array, indicating we should debug log for this class.
     * Names come from __METHOD__.
     * Examples:
     *   - non-namespaced class method: classname::functionname
     *   - non-namespaced standalone function: functionname
     *   - namespaced class method: namespace\classname::functionname
     *   - namespaced standalone function: namespace\functionname.
     * @param string $classname Namespaced functionname.
     * @return bool True if the namespaced functionname is in the self::$logForFunction array.
     */
    public static function do_log_for_function(string $functionname): bool {
        $debug = /* Do not make this true except in unusual circumstances */ false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \error_log($fxn . '::Started with $functionname=' . $functionname);

        if (empty($functionname)) {
            $debug && \error_log($fxn . '::$functionname is empty so return false');
            return false;
        }

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'perrequest');
        $cachekey = ia_mu::get_cache_key(__CLASS__ . '_' . __FUNCTION__ . $functionname);
        if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            $debug && \error_log($fxn . '::Got $cachedvalue=' . $cachedvalue);
            return ($cachedvalue === 'y') ? 1 : 0;
        }

        $blockconfig = \get_config(INTEGRITYADVOCATE_BLOCK_NAME);
        if (!isset($blockconfig->config_logforfunction)) {
            return false;
        }
        $debug && \error_log($fxn . '::Got $blockconfig->config_logforfunction=' . ia_u::var_dump($blockconfig->config_logforfunction));
        $result = \in_array($functionname, \explode(',', $blockconfig->config_logforfunction), true);
        $debug && \error_log($fxn . "::About to return \$result={$result}");

        if (FeatureControl::CACHE && !$cache->set($cachekey, $result ? 'y' : 'n')) {
            throw new \Exception('Failed to set value in the cache');
        }
        return $result;
    }

    private static function is_within_log_time(): bool {
        $debug = /* Do not make this true except in unusual circumstances */ false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \error_log($fxn . '::Started');

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'perrequest');
        $cachekey = ia_mu::get_cache_key(__CLASS__ . '_' . __FUNCTION__);
        if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            $debug && \error_log($fxn . '::Got $cachedvalue=' . $cachedvalue);
            return ($cachedvalue === 'y') ? 1 : 0;
        }

        $now = \time();
        $blockconfig = \get_config(INTEGRITYADVOCATE_BLOCK_NAME);

        // Log for 24 hours from the this time.
        $result = $now < $blockconfig->config_logfromtime + 86400;
        $debug && \error_log($fxn . '::Got $result=' . $result);
        if (FeatureControl::CACHE && !$cache->set($cachekey, $result ? 'y' : 'n')) {
            throw new \Exception('Failed to set value in the cache');
        }
        return $result;
    }

    /**
     * Should we log for this IP?
     *
     * @return bool True if we should log for this IP.
     */
    public static function do_log_for_ip(): bool {
        $debug = /* Do not make this true except in unusual circumstances */ false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $blockconfig = \get_config(INTEGRITYADVOCATE_BLOCK_NAME);
        $debug && \error_log($fxn . "::Started with \$blockconfig->config_logforip={$blockconfig->config_logforip}; remoteip_in_list(\$blockconfig->config_logforip)=" . \remoteip_in_list($blockconfig->config_logforip));

        $result = isset($blockconfig->config_logforip) && !empty($blockconfig->config_logforip) && remoteip_in_list($blockconfig->config_logforip);
        $debug && \error_log($fxn . "::About to return result={$result}");
        return $result;
    }

    /**
     * Log $message to HTML output, mlog, stdout, or error log.
     *
     * @param string $message Message to log.
     * @param string $dest One of the LogDestination::* constants.
     * @param bool $force Ignore IP and time restrictions and log anyway, but only if destination=ERRORLOG.  This is meant to be used for errors that should be logged.
     * @return bool True on completion.
     */
    public static function log(string $message, string $dest = '', bool $force = false): bool {
        $debug = /* Do not make this true except in unusual circumstances */ false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \error_log($fxn . '::Started with $dest=' . $dest);

        $blockconfig = \get_config(INTEGRITYADVOCATE_BLOCK_NAME);
        if (ia_u::is_empty($dest)) {
            if (!ia_u::is_empty($blockconfig) && isset($blockconfig->config_logdestination) && !ia_u::is_empty($blockconfig->config_logdestination)) {
                $dest = $blockconfig->config_logdestination;
            } else {
                $dest = Logger::$default;
            }
        }
        $debug && \error_log($fxn . '::After cleanup, $dest=' . $dest);

        // Short circuit without logging anything in these cases.
        if ($dest === Logger::NONE) {
            $debug && \error_log($fxn . '::Skipping - $dest=NONE');
            return false;
        }
        if (!($force && $dest === Logger::ERRORLOG)) {

            $debug && \error_log($fxn . '::About to check IP vs logforip; $CFG->blockedip=');
            if (!self::do_log_for_ip()) {
                $debug && \error_log($fxn . '::Skipping - logforip');
                return false;
            }
            if (!self::is_within_log_time()) {
                $debug && \error_log($fxn . '::Skipping - not isWithinLogTime()');
                return false;
            }
        }

        global $CFG;
        // If the file path is included, strip it.
        $cleanedmsg = \str_replace(\realpath($CFG->dirroot), '', $message);
        // Remove base64-encoded images.
        $cleanedmsg = \preg_replace(INTEGRITYADVOCATE_REGEX_DATAURI, 'redacted_base64_image', $cleanedmsg);
        // Trim and remove blank lines.
        $cleanedmsg = \trim(\preg_replace('/^[ \t]*[\r\n]+/m', '', $cleanedmsg));

        switch ($dest) {
            case Logger::HTML:
                print($cleanedmsg) . "<br />\n";
                break;
            case Logger::MLOG:
                \mtrace(html_to_text($cleanedmsg, 0, false));
                break;
            case Logger::STDOUT:
                print(\htmlentities($cleanedmsg, 0, 'UTF-8')) . "\n";
                break;
            case Logger::LOGGLY:
                if (isset(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1])) {
                    $debugbacktrace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
                } else if (isset(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0])) {
                    $debugbacktrace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0];
                }
                $classorfile = $debugbacktrace['class'] ?? '';
                if (empty($classorfile)) {
                    $classorfile = \basename(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['file']);
                }

                $functionorline = $debugbacktrace['function'] ?? '';
                if (empty($functionorline)) {
                    $functionorline = (int) ($debugbacktrace['line']);
                } else {
                    $functionorline .= '-' . (int) ($debugbacktrace['line']);
                }

                $siteslug = \preg_replace('/^www\./', '', \str_replace(['http://', 'https://'], '', \trim($CFG->wwwroot, '/')));
                // Ref https://www-staging.loggly.com/docs/tags/.
                $tag = self::clean_loggly_tag(\str_replace(INTEGRITYADVOCATE_SHORTNAME, '', "{$siteslug}-{$classorfile}-{$functionorline}"));

                // Usage https://github.com/Seldaek/monolog/blob/1.x/doc/01-usage.md.
                // Usage https://dzone.com/articles/php-monolog-tutorial-a-step-by-step-guide.
                $log = new \Monolog\Logger("$tag,$siteslug,$classorfile");
                $log->pushHandler(new \Monolog\Handler\LogglyHandler(Logger::LOGGLY_TOKEN, \Monolog\Logger::DEBUG));
                $log->debug($cleanedmsg);
                break;
            case Logger::ERRORLOG:
            default:
                \error_log($cleanedmsg);
                break;
        }

        return true;
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
        return \core_text::substr(\trim(\preg_replace('/[^0-9a-z_\-.]+/i', '-', clean_param($key, PARAM_TEXT))), 0, $maxlength);
    }

    /**
     * Return true if a file has been included.
     * @url https://stackoverflow.com/a/52467334
     * @param String $f file path.
     * @param String $f file path.
     * @return bool true if a file has been included.
     */
    private static function file_has_been_included(string $filepath): bool {
        $fixpaths = function(string $f): string {
            return \str_replace(['\\'], '/', $f);
        };
        return \in_array($fixpaths($filepath), \array_map($fixpaths, \get_included_files()), true);
    }

    /**
     * Get list of functions defined in a PHP file.
     * @param string $filePath File path.
     * @param bool $sort True to sort.
     * @return array<string> List of functions defined in a PHP file.
     */
    private static function get_defined_functions_in_file(string $filePath, bool $sort = false): array {
        $file = \file(\str_replace(['\\'], '/', $filePath));
        $functions = [];

        foreach ($file as $line) {
            $line = \trim($line);
            if (\mb_strpos($line, '//')) {
                continue;
            }

            if (\str_contains($line, 'function ')) {
                $function_name = \trim(\str_ireplace([
                    'public',
                    'private',
                    'protected',
                    'static'
                                ], '', $line));

                $function_name = \trim(\mb_substr($function_name, 9, \mb_strpos($function_name, '(') - 9));

                if (!\in_array($function_name, ['__construct', '__destruct', '__get', '__set', '__isset', '__unset'], true)) {
                    $functions[] = $function_name;
                }
            }
        }

        if ($sort) {
            \asort($functions);
            $functions = \array_values($functions);
        }

        return $functions;
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
     * Build an array of namespaced functionnames to log for.
     * Names come from __METHOD__.
     * Examples:
     *   - non-namespaced class method: classname::functionname
     *   - non-namespaced standalone function: functionname
     *   - namespaced class method: namespace\classname::functionname
     *   - namespaced standalone function: namespace\functionname.
     * @return <String> Array of namespaced functionnames to log for.
     */
    public static function get_functions_for_logging(): array {
        $debug = /* Do not make this true except in unusual circumstances */ false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && \error_log($fxn . '::Started');

        // Cache so multiple calls don't repeat the same work.
        $cache = \cache::make(\INTEGRITYADVOCATE_BLOCK_NAME, 'persession');
        $cachekey = ia_mu::get_cache_key(__CLASS__ . '_' . __FUNCTION__);
        if (FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
            $debug && \error_log($fxn . '::Got $cachedvalue=' . $cachedvalue);
            return $cachedvalue;
        }

        $classestolog = [
            '\block_integrityadvocate_external',
            '\block_integrityadvocate',
            INTEGRITYADVOCATE_BLOCK_NAME . '\Api',
            INTEGRITYADVOCATE_BLOCK_NAME . '\Output',
            INTEGRITYADVOCATE_BLOCK_NAME . '\MoodleUtility',
                // This one causes OOM errors in session.
//            INTEGRITYADVOCATE_BLOCK_NAME . '\ParticipantsTable',
        ];

        // These ones are not classes but files with functions.
        $fileswithfunctionstolog = [
            \dirname(__DIR__) . '\lib.php',
        ];

        // These ones are not classes and don't have functions but we want to be able to log them anyway.
        $thingstolog = [
            INTEGRITYADVOCATE_BLOCK_NAME . '\overview.php',
            INTEGRITYADVOCATE_BLOCK_NAME . '\overview-course.php',
            INTEGRITYADVOCATE_BLOCK_NAME . '\overview-user.php',
            INTEGRITYADVOCATE_BLOCK_NAME . '\overview-module.php',
        ];

        \core_php_time_limit::raise();
        foreach ($classestolog as $classname) {
            if (!\class_exists($classname)) {
                continue;
            }
            $reflection = new \ReflectionClass($classname);
            foreach ($reflection->getMethods() as $method) {
                // Remove methods from parent etc.
                $debug && \error_log($fxn . "::Looking at \$method->class={$method->class} vs \$classname={$classname}");
                if (\trim($method->class, '\\') !== \trim($classname, '\\')) {
                    continue;
                }
                $val = $method->class . '::' . $method->name;
                $thingstolog[] = $val;
            }
        }

        foreach ($fileswithfunctionstolog as $filename) {
            if (self::file_has_been_included($filename) && !ia_u::is_empty($functionsarr = self::get_defined_functions_in_file($filename))) {
                foreach ($functionsarr as $functionname) {
                    $functionreflection = new \ReflectionFunction($functionname);
                    $val = self::NONAMESPACE_FUNCTION_PREFIX . self::filepath_relative_to_plugin($functionreflection->getFileName()) . '::' . $functionreflection->getName();
                    $thingstolog[] = $val;
                }
            }
        }

        \sort($thingstolog);

        if (FeatureControl::CACHE && !$cache->set($cachekey, $thingstolog)) {
            throw new \Exception('Failed to set value in the cache');
        }

        return $thingstolog;
    }

}
