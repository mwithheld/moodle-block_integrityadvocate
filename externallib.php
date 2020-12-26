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
 * IntegrityAdvocate external functions.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
\defined('MOODLE_INTERNAL') || die;
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/externallib.php');

/**
 * Answers AJAX calls for this block.
 */
class block_integrityadvocate_external extends \external_api {

    use \block_integrityadvocate\external_ia_session_tracking;

    /**
     * Describes a return value that just returns submitted = true.
     *
     * @return external_single_structure
     */
    protected static function returns_boolean_submitted(): \external_single_structure {
        return new \external_single_structure(
                [
            // Usage: external_value($type, $desc, $required, $default, $allownull).
            'submitted' => new \external_value(PARAM_BOOL, 'submitted', true, false, false),
            'warnings' => new \external_warnings()
                ]
        );
    }

}
