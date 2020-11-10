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
use block_integrityadvocate\Utility as ia_u;

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
    $logforfunctionoptions = [];
    foreach (Logger::get_functions_for_logging() as $method) {
        $logforfunctionoptions[$method] = $method;
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

    if (!function_exists('block_integrityadvocate_get_siteinfo')) {

        function block_integrityadvocate_get_siteinfo(): string {
            // [$responseinfo['primary_ip'], intval($responsecode), $response, $responseinfo['total_time']]
            list($remote_ip, $http_reponsecode, $http_responsebody, $total_time) = \block_integrityadvocate\Api::ping();

            $siteinfo = [
                'Server IP' => cleanremoteaddr($_SERVER['REMOTE_ADDR']),
                'PHP version' => phpversion(),
                'Moodle version' => moodle_major_version(),
                //'block_integrityadvocate version' => get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version')
                $siteinfo['IA ping'] = "IA IP={$remote_ip}; Response={$http_reponsecode} <PRE>{$http_responsebody}</PRE> {$total_time}s",
                INTEGRITYADVOCATE_BLOCK_NAME . ' config' => ''
            ];
            foreach (get_config(INTEGRITYADVOCATE_BLOCK_NAME) as $key => &$val) {
                if (str_ends_with($key, '_locked')) {
                    continue;
                }
                $siteinfo[INTEGRITYADVOCATE_BLOCK_NAME . ' config'] .= str_replace('[config_', '', $key) . '=>' . '<PRE>' . $val . '</PRE>';
            }

            // Format the site info into a pretty table.
            $table = new html_table();
            $table->head = ['Item', 'Value'];
            //$table->colclasses = array ('leftalign', 'leftalign', 'centeralign', 'leftalign', 'leftalign', 'leftalign');
            $table->attributes['class'] = 'admintable generaltable';
            $table->id = INTEGRITYADVOCATE_BLOCK_NAME . '_siteinfo';
            $table->data = array(); //array_values($siteinfo);
            foreach ($siteinfo as $key => &$val) {
                $table->data[] = [$key, $val];
            }
            return html_writer::table($table);
        }

    }

    $setting = new admin_setting_description(INTEGRITYADVOCATE_BLOCK_NAME . '/config_siteinfo', 'Site info', block_integrityadvocate_get_siteinfo());
    $settings->add($setting);
}
