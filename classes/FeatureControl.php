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
 * Feature control: Enable/disable features easily.
 */
class FeatureControl {

    /** @var bool True to allow caching using MUC. */
    const CACHE = true;

    /** @var bool True to show a list of IA-enabled modules on the course-level block. */
    const MODULE_LIST = true;

    /** @var bool True to allow showing the overview_module content. */
    const OVERVIEW_MODULE = true;

    /** @var bool True to allow instructors to override the IA session status. */
    const SESSION_STATUS_OVERRIDE = true;

    /** @var bool True to keep track of when session are started. */
    const SESSION_STARTED_TRACKING = true;

}