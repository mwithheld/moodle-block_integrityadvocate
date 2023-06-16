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

defined('MOODLE_INTERNAL') || die;

/**
 * Feature control: Enable/disable features easily.
 */
class FeatureControl {

    /** @var bool True to allow caching using MUC. */
    public const CACHE = true;

    /** @var bool True to show a list of IA-enabled modules on the course-level block. */
    public const MODULE_LIST = true;

    /** @var bool True to show the gear icon to configure the module-level blocks. This feature is not ready yet!! */
    public const MODULE_LIST_CONFIGLINK = false;

    /** @var bool True to showing the overview_course content using the IA LTI endpoint. */
    public const OVERVIEW_COURSE_LTI = true;

    /** @var bool True to allow showing the overview_module content using the IA LTI endpoint. */
    public const OVERVIEW_MODULE_LTI = true;

    /** @var bool True to allow showing the overview_user content using the IA LTI endpoint. */
    public const OVERVIEW_USER_LTI = true;

    /** @var bool True to keep track of when session are started. */
    public const SESSION_STARTED_TRACKING = true;
}
