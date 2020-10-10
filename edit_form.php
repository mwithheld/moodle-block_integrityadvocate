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
 * IntegrityAdvocate block per-instance configuration form definition.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use block_integrityadvocate\Logger as Logger;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/blocks/integrityadvocate/lib.php');

/**
 * IntegrityAdvocate per-instance block config form class.
 *
 * @copyright IntegrityAdvocate.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_integrityadvocate_edit_form extends block_edit_form {

    /**
     * Overridden to create any form fields specific to this type of block.
     * We can't add a type check here without causing a warning b/c the parent class does not have the type check.
     *
     * @param \stdClass|MoodleQuickForm $mform the form being built.
     */
    protected function specific_definition(MoodleQuickForm $mform) {
        // Start block specific section in config form.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $this->specific_definition_ia($mform);
    }

    /**
     * Build form fields for this block's settings.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function specific_definition_ia(MoodleQuickForm $mform) {
        $mform->addElement('text', 'config_appid', get_string('config_appid', INTEGRITYADVOCATE_BLOCK_NAME), array('size' => 39));
        $mform->setType('config_appid', PARAM_ALPHANUMEXT);

        $mform->addElement('text', 'config_apikey', get_string('config_apikey', INTEGRITYADVOCATE_BLOCK_NAME), array('size' => 52));
        $mform->setType('config_apikey', PARAM_BASE64);

        if (str_starts_with($this->page->pagetype, 'mod-quiz-')) {
            $mform->addElement('selectyesno', 'config_proctorquizinfopage', get_string('config_proctorquizinfopage', INTEGRITYADVOCATE_BLOCK_NAME));
            $mform->setDefault('config_proctorquizinfopage', 0);
        }

        $mform->addElement('static', 'blockversion', get_string('config_blockversion', INTEGRITYADVOCATE_BLOCK_NAME), get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version'));
    }

    /**
     * Overridden to perform some extra validation.
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * Server side rules do not work for uploaded files, implement serverside rules here if needed.
     *
     * @param object[] $data array of ("fieldname"=>value) of submitted data
     * @param object[] $unused Unused array of uploaded files "element_name"=>tmp_file_path
     * @return object[] of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation(array $data, $unused): array {
        $debug = false || Logger::do_log_for_function(__CLASS__ . '::' . __FUNCTION__);
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && Logger::log($fxn . '::Started with $data=' . ia_u::var_dump($data, true));

        $errors = [];

        if (!empty($data['config_appid']) && !ia_u::is_guid($data['config_appid'])) {
            $data['config_appid'] = rtrim(ltrim(trim($data['config_appid']), '{'), '}');
            $errors['config_appid'] = get_string('error_invalidappid', \INTEGRITYADVOCATE_BLOCK_NAME);
        }
        return $errors;
    }

}
