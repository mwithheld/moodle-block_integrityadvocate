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
 * IntegrityAdvocate block sitewide configuration form definition.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use block_integrityadvocate\Logger as Logger;

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Debug logging: Log destination.
    $logdestinationoptions = array(Logger::NONE, Logger::ERRORLOG, Logger::HTML, Logger::LOGGLY, Logger::MLOG, Logger::STDOUT);
    foreach ($logdestinationoptions as $key => $val) {
        unset($logdestinationoptions[$key]);
        $logdestinationoptions[$val] = $val;
    }
    $setting = new admin_setting_configselect(INTEGRITYADVOCATE_BLOCK_NAME . '/config_logdestination',
            get_string('config_logdestination', INTEGRITYADVOCATE_BLOCK_NAME),
            get_string('config_logdestination_help', INTEGRITYADVOCATE_BLOCK_NAME),
            Logger::NONE, $logdestinationoptions);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    // Debug logging: Log only if user is from these IPs.
    $setting = new admin_setting_configiplist(INTEGRITYADVOCATE_BLOCK_NAME . '/config_logforip',
            new lang_string('config_logforip', INTEGRITYADVOCATE_BLOCK_NAME),
            new lang_string('config_logforip_help', INTEGRITYADVOCATE_BLOCK_NAME), '', PARAM_FILE);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    // Debug logging: function-level.
    $reflection = new \ReflectionClass(INTEGRITYADVOCATE_BLOCK_NAME . '\Api');
    $logforfunctionoptions = [];
    foreach ($reflection->getMethods() as $method) {
        $val = $method->class . '::' . $method->name;
        $logforfunctionoptions[$val] = $val;
    }
    $setting = new admin_setting_configmultiselect(INTEGRITYADVOCATE_BLOCK_NAME . '/config_logforfunction',
            get_string('config_logforfunction', INTEGRITYADVOCATE_BLOCK_NAME),
            get_string('config_logforfunction_help', INTEGRITYADVOCATE_BLOCK_NAME),
            Logger::$logForFunction, $logforfunctionoptions);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $setting = new admin_setting_configtext(INTEGRITYADVOCATE_BLOCK_NAME . '/config_logfromtime',
            get_string('config_logfromtime', INTEGRITYADVOCATE_BLOCK_NAME),
            get_string('config_logfromtime_help', INTEGRITYADVOCATE_BLOCK_NAME),
            time(), '/^[1-9][0-9]{9}$/');
    $settings->add($setting);
}
