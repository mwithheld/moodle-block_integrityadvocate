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
 * IntegrityAdvocate block configuration form definition
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/blocks/integrityadvocate/lib.php');

/**
 * IntegrityAdvocate block config form class
 *
 * @copyright IntegrityAdvocate.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_integrityadvocate_edit_form extends block_edit_form {

    /**
     * Overridden to create any form fields specific to this type of block.
     * @param stdClass $mform the form being built.
     */
    protected function specific_definition($mform) {
        // Start block specific section in config form.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $this->specific_definition_ia($mform);

        /* IA: The rest of what was in this function is removed on purpose */
    }

    /**
     * Overridden to create any form fields specific to this type of block.
     * @param stdClass $mform the form being built.
     */
    protected function specific_definition_ia(MoodleQuickForm $mform) {
        $mform->addElement('text', 'config_appid', get_string('config_appid', INTEGRITYADVOCATE_BLOCKNAME), array('size' => 39));
        $mform->setType('config_appid', PARAM_ALPHANUMEXT);

        $mform->addElement('text', 'config_apikey', get_string('config_apikey', INTEGRITYADVOCATE_BLOCKNAME), array('size' => 52));
        $mform->setType('config_apikey', PARAM_BASE64);
    }

    /**
     * Overridden to perform some extra validation.
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * Server side rules do not work for uploaded files, implement serverside rules here if needed.
     *
     * @param object[] $data array of ("fieldname"=>value) of submitted data
     * @param object[] $files array of uploaded files "element_name"=>tmp_file_path
     * @return object[] of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $errors = array();

        if (!empty($data['config_appid']) && !IntegrityAdvocate_Utility::is_guid($data['config_appid'])) {
            $data['config_appid'] = rtrim(ltrim(trim($data['config_appid']), '{'), '}');
            $errors['config_appid'] = get_string('error_invalidappid', INTEGRITYADVOCATE_BLOCKNAME);
        }
        return $errors;
    }

}
