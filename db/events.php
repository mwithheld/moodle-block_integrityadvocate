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
 * Event observer.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$blockintegrityadvocateprocessevent = 'block_integrityadvocate_observer::process_event';
$observers = [
    [
        'eventname' => '\\mod_assign\\event\\assessable_submitted',
        'callback' => $blockintegrityadvocateprocessevent,
    ],
    [
        'eventname' => '\\mod_choice\\event\\answer_created',
        'callback' => $blockintegrityadvocateprocessevent,
    ],
    [
        'eventname' => '\\mod_feedback\\event\\response_submitted',
        'callback' => $blockintegrityadvocateprocessevent,
    ],
    [
        'eventname' => '\\mod_scorm\\event\\scoreraw_submitted',
        'callback' => $blockintegrityadvocateprocessevent,
    ],
    [
        'eventname' => '\\mod_quiz\\event\\attempt_abandoned',
        'callback' => $blockintegrityadvocateprocessevent,
    ],
    [
        'eventname' => '\\mod_quiz\\event\\attempt_reviewed',
        'callback' => $blockintegrityadvocateprocessevent,
    ],
    [
        'eventname' => '\\mod_quiz\\event\\attempt_submitted',
        'callback' => $blockintegrityadvocateprocessevent,
    ],
];
