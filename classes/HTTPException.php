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
 * IntegrityAdvocate block Overview page showing course participants with a summary of their IntegrityAdvocate data.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_integrityadvocate;

/**
 *  This class represents an HTTP transport error.
 * Copied from GeoIp2\Exception
 */
class HttpException extends \Exception {

    /**
     * The URI queried.
     */
    public $uri;

    public function __construct(
            $message,
            $httpstatus,
            $uri,
            \Exception $previous = null
    ) {
        $this->uri = $uri;
        parent::__construct($message, $httpstatus, $previous);
    }

}
