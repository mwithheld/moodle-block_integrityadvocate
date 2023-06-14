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
use block_integrityadvocate as ia;
use block_integrityadvocate\MoodleUtility as ia_mu;
use block_integrityadvocate\Utility as ia_u;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/blocks/integrityadvocate/lib.php');

/**
 * IntegrityAdvocate per-instance block config form class.
 *
 * @copyright IntegrityAdvocate.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_integrityadvocate_edit_form extends block_edit_form
{

    /**
     * Overridden to create any form fields specific to this type of block.
     * We can't add a type check here without causing a warning b/c the parent class does not have the type check.
     *
     * Note: Do not add a type declaration MoodleQuickForm $mform b/c it causes a...
     *       "Warning: Declaration of block_integrityadvocate_edit_form::specific_definition(MoodleQuickForm $mform) should be compatible with block_edit_form::specific_definition($mform)"
     *
     * @param \stdClass|MoodleQuickForm $mform the form being built.
     */
    protected function specific_definition($mform)
    {
        if (!($mform instanceof MoodleQuickForm)) {
            throw new InvalidArgumentException('$mform must be an instance of MoodleQuickForm and it appears to be a ' . \gettype($mform));
        }

        // Start block specific section in config form.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $this->specific_definition_ia($mform);
    }

    /**
     * Build form fields for this block instance's settings.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function specific_definition_ia(MoodleQuickForm $mform)
    {
        $mform->addElement('static', 'topnote', get_string('config_topnote', INTEGRITYADVOCATE_BLOCK_NAME), get_string('config_topnote_help', INTEGRITYADVOCATE_BLOCK_NAME), ['hidden' => true]);

        $mform->addElement('text', 'config_appid', get_string('config_appid', INTEGRITYADVOCATE_BLOCK_NAME), ['size' => 39]);
        $mform->setType('config_appid', PARAM_ALPHANUMEXT);
        $mform->addRule('config_appid', null, 'required', null, 'client');

        // Accept ALPHANUMEXT even though we could use BASE64 b/c the former will show a nice error, which the latter will simply ignore the input value on submit.
        $mform->addElement('text', 'config_apikey', get_string('config_apikey', INTEGRITYADVOCATE_BLOCK_NAME), ['size' => 52]);
        $mform->setType('config_apikey', PARAM_BASE64);

        $invalidstr = get_string('error_invalidapikey', \INTEGRITYADVOCATE_BLOCK_NAME);
        $mform->addRule('config_apikey', null, 'required', null, 'client');
        $mform->addRule('config_apikey', $invalidstr, 'minlength', 41, 'client');
        $mform->addRule('config_apikey', get_string('maximumchars', '', 50), 'maxlength', 50, 'client');
        $mform->addRule('config_apikey', $invalidstr, 'regex', '/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{4}|[A-Za-z0-9+\/]{3}=|[A-Za-z0-9+\/]{2}={2})$/', 'client');

        if (str_starts_with($this->page->pagetype, 'mod-quiz-')) {
            $mform->addElement('selectyesno', 'config_proctorquizinfopage', get_string('config_proctorquizinfopage', INTEGRITYADVOCATE_BLOCK_NAME));
            $mform->setDefault('config_proctorquizinfopage', 0);
        }

        $mform->addElement('static', 'blockversion', get_string('config_blockversion', INTEGRITYADVOCATE_BLOCK_NAME), get_config(INTEGRITYADVOCATE_BLOCK_NAME, 'version'));
    }

    /**
     * Setup the form depending on current
     * values. This method is called after definition(), data submission and set_data().
     * All form setup that is dependent on form values should go in here.
     */
    public function definition_after_data()
    {
        parent::definition_after_data();
        $mform = & $this->_form;

        $appid = $mform->getElementValue('config_appid');
        $apikey = $mform->getElementValue('config_apikey');

        // Hide the text above the form inputs that says "Use of this plugin requires purchasing a paid service".
        if (!empty($apikey) && ia::is_valid_apikey($apikey) && !empty($appid) && ia_u::is_guid($appid)) {
            $mform->getElement('topnote')->setAttributes(['class' => 'hidden']);
        }
    }

    /**
     * Overridden to perform some extra validation.
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * Server side rules do not work for uploaded files, implement serverside rules here if needed.
     *
     * Note: Do not add a type declaration MoodleQuickForm $mform b/c it causes a...
     *       "Warning: Declaration of block_integrityadvocate_edit_form::validation(array $data, $unused): array should be compatible with moodleform::validation($data, $files)
     *
     * @param object[] $data array of ("fieldname"=>value) of submitted data
     * @param object[] $unused Unused array of uploaded files "element_name"=>tmp_file_path
     * @return object[] of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $unused): array
    {
        if (!\is_array($data)) {
            throw new InvalidArgumentException('$data must be an array and it appears to be a ' . \gettype($data));
        }

        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && error_log($fxn . '::Started with $data=' . ia_u::var_dump($data, true));

        $errors = [];

        // Since we accept ALPHANUMEXT as input, we need to custom validate it is a GUID.
        if (!empty($data['config_appid']) && !ia_u::is_guid($data['config_appid'])) {
            $data['config_appid'] = \rtrim(\ltrim(\trim($data['config_appid']), '{'), '}');
            $errors['config_appid'] = get_string('error_invalidappid', \INTEGRITYADVOCATE_BLOCK_NAME);
        }

        if (!empty($data['config_apikey']) && !ia::is_valid_apikey($data['config_apikey'])) {
            $data['config_apikey'] = \rtrim(\ltrim(\trim($data['config_apikey']), '{'), '}');
            $errors['config_apikey'] = get_string('error_invalidapikey', \INTEGRITYADVOCATE_BLOCK_NAME);
        }

        return $errors;
    }
}
