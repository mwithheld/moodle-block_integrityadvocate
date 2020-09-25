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

/**
 * Determines where to send error logs.
 *
 * @author markv
 */
class LogDestination {

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

    /** @var string Determines where to send error logs.
     * For values, see MoodleUtility::log()'s switch statement.
     */
    public static $default = self::ERRORLOG;

}
