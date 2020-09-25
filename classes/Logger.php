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

use block_integrityadvocate\Utility as ia_u;

/**
 * Determines where to send error logs.
 *
 * @author markv
 */
class Logger {

    /** @var string Store logged messaged to the standard PHP error log. */
    const ERRORLOG = 'ERRORLOG';

    /** @var string Send logged messages to standard HTML output, adding a <br> tag and a newline. */
    const HTML = 'HTML';

    /** @var string Store logged messaged to STDOUT through htmlentities. */
    const LOGGLY = 'LOGGLY';

    /** @var string Required for Loggly logging */
    const LOGGLY_TOKEN = 'fab8d2aa-69a0-4b03-8063-b41b215f2e32';

    /** @var string Store logged messaged to the moodle log handler plain-textified. */
    const MLOG = 'MLOG';

    /** @var string Store logged messaged to STDOUT through htmlentities. */
    const STDOUT = 'STDOUT';

    /** @var string Even if the local debug flag is false, this enables debug logging for these classnames (including namespace).
     * Names come from __CLASS__.
     * Examples:
     *   - non-namespaced class method: classname
     *   - namespaced class method: namespace\classname, e.g. 'block_integrityadvocate\Api'
     */
    public static $logForClass = [];

    /** @var string Even if the local debug flag is false, this enables debug logging for these functions.
     * Names come from __METHOD__.
     * Examples:
     *   - non-namespaced class method: classname::functionname
     *   - non-namespaced standalone function: functionname
     *   - namespaced class method: namespace\classname::functionname
     *   - namespaced standalone function: namespace\functionname
     * This is overridden by $logForClass.
     */
    public static $logForFunction = [
//        'block_integrityadvocate\Api::get_participant',
//        'block_integrityadvocate\Api::get_participants_data',
        'block_integrityadvocate\Api::get_participantsessions',
        'block_integrityadvocate\Api::get_participantsessions_data',
    ];

    /** @var string Determines where to send error logs. For values, see self::log()'s switch statement. */
    public static $default = self::LOGGLY;

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
     */
    public static function doLogForClass(string $classname): bool {
        if (empty($classname)) {
            return false;
        }
        return in_array($classname, self::$logForClass, true);
    }

    /**
     * Return true if the namespaced functionname is in the self::$logForClass array, indicating we should debug log for this class.
     * Names come from __METHOD__.
     * Examples:
     *   - non-namespaced class method: classname::functionname
     *   - non-namespaced standalone function: functionname
     *   - namespaced class method: namespace\classname::functionname
     *   - namespaced standalone function: namespace\functionname
     * @param string $classname Namespaced functionname.
     * @return bool True if the namespaced functionname is in the self::$logForFunction array,
     */
    public static function doLogForFunction(string $functionname): bool {
        if (empty($functionname)) {
            return false;
        }
        return in_array($functionname, self::$logForFunction, true);
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
            $dest = Logger::$default;
        }
        $debug && error_log($fxn . '::After cleanup, $dest=' . $dest . "\n");

        // If the file path is included, strip it.
        $cleanedmsg = str_replace(realpath($CFG->dirroot), '', $message);
        // Remove base64-encoded images.
        $cleanedmsg = preg_replace(INTEGRITYADVOCATE_REGEX_DATAURI, 'redacted_base64_image', $cleanedmsg);
        // Trim and remove blank lines.
        $cleanedmsg = trim(preg_replace('/^[ \t]*[\r\n]+/m', '', $cleanedmsg));

        switch ($dest) {
            case Logger::HTML:
                print($cleanedmsg) . "<br />\n";
                break;
            case Logger::MLOG:
                mtrace(html_to_text($cleanedmsg, 0, false));
                break;
            case Logger::STDOUT:
                print(htmlentities($cleanedmsg, 0, false)) . "\n";
                break;
            case Logger::LOGGLY:
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
                // Ref https://www-staging.loggly.com/docs/tags/.
                $tag = self::clean_loggly_tag(str_replace(INTEGRITYADVOCATE_SHORTNAME, '', "{$siteslug}-{$classorfile}-{$functionorline}"));

                // Usage https://github.com/Seldaek/monolog/blob/1.x/doc/01-usage.md.
                // Usage https://dzone.com/articles/php-monolog-tutorial-a-step-by-step-guide.
                $log = new \Monolog\Logger("$tag,$siteslug,$classorfile");
                $log->pushHandler(new \Monolog\Handler\LogglyHandler(Logger::LOGGLY_TOKEN, \Monolog\Logger::DEBUG));
                $log->debug($cleanedmsg);
                break;
            case Logger::ERRORLOG:
            default:
                error_log($cleanedmsg);
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
        return \core_text::substr(trim(preg_replace('/[^0-9a-z_\-.]+/i', '-', clean_param($key, PARAM_TEXT))), 0, $maxlength);
    }

}
