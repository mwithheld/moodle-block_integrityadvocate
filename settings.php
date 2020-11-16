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
use block_integrityadvocate\Output as ia_output;
use block_integrityadvocate\MoodleUtility as ia_mu;

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $setting = new admin_setting_heading(INTEGRITYADVOCATE_BLOCK_NAME . '/config_loggingnote_heading', get_string('config_loggingnote', INTEGRITYADVOCATE_BLOCK_NAME), get_string('config_loggingnote_help', INTEGRITYADVOCATE_BLOCK_NAME));
    $settings->add($setting);

    // Debug logging: Log destination.
    $logdestinationoptions = Logger::get_log_destinations();
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
            time(), '/^0|([1-9][0-9]{9})$/');
    $settings->add($setting);

    $setting = new admin_setting_heading(INTEGRITYADVOCATE_BLOCK_NAME . '/config_siteinfo_heading', get_string('config_siteinfo', INTEGRITYADVOCATE_BLOCK_NAME), get_string('config_siteinfo_help', INTEGRITYADVOCATE_BLOCK_NAME));
    $settings->add($setting);

    if (!function_exists('block_integrityadvocate_get_siteinfo')) {

        /**
         * Get site info.  Use caching b/c Moodle will call this function twice per page display.
         *
         * @return string HTML site info
         */
        function block_integrityadvocate_get_siteinfo(): string {
            $debug = false;
            $fxn = __FILE__ . '::' . __FUNCTION__;

            // Cache so multiple calls don't repeat the same work.
            $cache = \cache::make(INTEGRITYADVOCATE_BLOCK_NAME, 'perrequest');
            $cachekey = ia_mu::get_cache_key(implode('_', [__FILE__, __FUNCTION__]));
            if (block_integrityadvocate\FeatureControl::CACHE && $cachedvalue = $cache->get($cachekey)) {
                $debug && Logger::log($fxn . '::Found a cached value, so return that');
                return $cachedvalue;
            }

            list($remote_ip, $http_reponsecode, $http_responsebody, $total_time) = \block_integrityadvocate\Api::ping();

            $micro_date = microtime();
            $date_array = explode(" ", $micro_date);
            $date = date("Y-m-d H:i:s", $date_array[1]);

            $badfolders = ['/vendor/bin', '/.git'];
            foreach ($badfolders as $key => $folder) {
                $debug && error_log('Looking at file_exists(' . __DIR__ . $folder . ')=' . file_exists(__DIR__ . $folder));
                if (!file_exists(__DIR__ . $folder)) {
                    unset($badfolders[$key]);
                }
            }

            $badplugins = ['block_massactions'];
            foreach ($badplugins as $key => $plugin) {
                if (empty(core_plugin_manager::instance()->get_plugin_info($plugin))) {
                    unset($badplugins[$key]);
                }
            }

            $siteinfo = [
                'Timestamp' => "{$date}:{$date_array[0]}",
                'Server IP' => cleanremoteaddr($_SERVER['REMOTE_ADDR']),
                'PHP version' => phpversion(),
                'Moodle version' => moodle_major_version(),
                'IA ping' => implode(ia_output::BRNL, ["ip=$remote_ip", "total time={$total_time}s", "response code={$http_reponsecode}", 'body=' . htmlentities(strip_tags($http_responsebody))]),
                INTEGRITYADVOCATE_BLOCK_NAME . ' config' => '',
                'Bad folders' => implode(ia_output::BRNL, $badfolders),
                'Bad plugins' => implode(ia_output::BRNL, $badplugins),
            ];
            foreach (get_config(INTEGRITYADVOCATE_BLOCK_NAME) as $key => $val) {
                switch (true) {
                    case str_ends_with($key, '_locked'):
                        // Do not bother outputting these - they are not useful.
                        continue 2;
                    case ($key === 'config_logforfunction'):
                        $val = str_replace(',', ia_output::NL, $val);
                        break;
                    case ($key === 'config_logfromtime'):
                        $val = "$val (" . date("Y-m-d H:i:s", $val) . ')';
                        break;
                }

                $siteinfo[INTEGRITYADVOCATE_BLOCK_NAME . ' config'] .= preg_replace('/^config_/', '', $key) . '=>' . ia_output::pre($val);
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

            $returnThis = html_writer::table($table);
            if (block_integrityadvocate\FeatureControl::CACHE && !$cache->set($cachekey, $returnThis)) {
                throw new \Exception('Failed to set value in the cache');
            }


            return $returnThis;
        }

    }

    $setting = new admin_setting_description(INTEGRITYADVOCATE_BLOCK_NAME . '/config_siteinfo', get_string('config_debuginfo', INTEGRITYADVOCATE_BLOCK_NAME), block_integrityadvocate_get_siteinfo());
    $settings->add($setting);
}
