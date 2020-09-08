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
 * IntegrityAdvocate block services setup
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

// Ref https://docs.moodle.org/dev/Web_services_API.
// Ref https://docs.moodle.org/dev/Adding_a_web_service_to_a_plugin.
$services = array(
    'block_integrityadvocate_set_override' => array(
        'functions' => array('block_integrityadvocate_set_override'),
        'requiredcapability' => 'block/integrityadvocate:override',
        // If 1, the administrator must manually select which user can use this service.
        // Ref (Administration > Plugins > Web services > Manage services > Authorised users).
        'restrictedusers' => 0,
        // If 0, then token linked to this service won't work.
        'enabled' => 1,
        // The short name used to refer to this service from elsewhere including when fetching a token.
        // Optional – but needed if restrictedusers is set so as to allow logins.
        'shortname' => 'block_integrityadvocate_set_override',
    )
);

$functions = array(
    'block_integrityadvocate_set_override' => array(
        // Class containing the external function OR namespaced class in classes/external/XXXX.php.
        'classname' => 'block_integrityadvocate_external',
        // External function name.
        'methodname' => 'set_override',
        // File containing the class/external function - not required if using namespaced auto-loading classes.
        // Defaults to the service's externalib.php.
        'classpath' => 'blocks/integrityadvocate/externallib.php',
        // Human-readable description of the web service function.
        'description' => 'Override IntegrityAdvocate session status',
        // Database rights of the web service function (read, write).
        'type' => 'write',
        // Is the service available to 'internal' ajax calls.
        'ajax' => true,
        // Optional, only available for Moodle 3.1 onwards.
        // List of built-in services (by shortname) where the function will be included.
        // Services created manually via the Moodle interface are not supported.
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        // Capabilities required by the function.
        'capabilities' => array('block/integrityadvocate:override'),
    ),
);
