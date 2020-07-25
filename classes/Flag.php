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
 * IntegrityAdvocate class to represent a single IA participant session flag.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

defined('MOODLE_INTERNAL') || die;

/**
 * Class to represent a single IA participant session flag.
 */
class Flag {

    /** @var string The data captured */
    public $capturedata;

    /** @var string Date captured */
    public $capturedate = -1;

    /** @var string Comments added to this capture */
    public $comment;

    /** @var int Unix timestamp when this was created. */
    public $created = -1;

    /** @var string IA-assigned code assigned to this flag */
    public $flagtypeid;

    /** @var string IA-assigned name assigned to this flag */
    public $flagtypename;

    /** @var string Unique ID assigned to this flag */
    public $id;

}
