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
 * IntegrityAdvocate behat helpers.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;

/**
 * Behat helper functions.
 */
class behat_block_integrityadvocate extends behat_base {

    /** @var string String to put at the end of behat debug output */
    private const OUTPUT_EOL = '; ';

    /**
     * Output a string to the behat test output.
     * @Then /^block_integrityadvocate I add test output "([^"]*)"$/
     *
     * @param string $string The string to output.
     */
    public function block_integrityadvocate_add_test_output($string) {
        print($string);
    }

    /**
     * Fills a form field with a given value.
     *
     * This function locates a form field by its name and sets its value. If the field
     * is not found, it throws an exception.
     *
     * @param string $fieldname The name of the form field to be filled.
     * @param mixed $value The value to set for the form field.
     */
    private function fill_field($fieldname, $value) {
        $session = $this->getSession();
        $page = $session->getPage();
        $field = $page->findField($fieldname);

        if (null === $field) {
            throw new Exception("The field '{$fieldname}' was not found on the page.");
        }

        $field->setValue($value);
    }

    /**
     * Set fields dynamically using values from $CFG.
     * Example usage in feature file:
     *   And I set the fields from $CFG:
     *     | Application id | block_integrityadvocate_appid |
     *     | API key        | block_integrityadvocate_apikey |
     *
     * @Then /^block_integrityadvocate I set the fields from CFG:$/
     *
     * @param TableNode $table A Behat TableNode object containing rows of field names and corresponding $CFG variables.
     */
    public function block_integrityadvocate_i_set_fields_from_cfg(TableNode $table) {
        global $CFG;
        $debug = false;
        $fxn = __CLASS__ . '::' . __FUNCTION__;
        $debug && print($fxn . '::Started with $CFG->wwwroot=' . $CFG->wwwroot . self::OUTPUT_EOL);

        foreach ($table->getRows() as $row) {
            $fieldname = $row[0];
            $cfgname = $row[1];
            $debug && print($fxn . '::Looking at $cfgname=' . $cfgname . self::OUTPUT_EOL);
            // Disabled on purpose: $debug && print($fxn . "::Looking at \$CFG=" . print_r($CFG, true) . self::OUTPUT_EOL); .

            if (isset($CFG->{$cfgname})) {
                $fieldvalue = $CFG->{$cfgname};
                $debug && print($fxn . '::Looking at $cfgname=' . $cfgname . ' with value=' . $fieldvalue . self::OUTPUT_EOL);
                $this->fill_field($fieldname, $fieldvalue);
            } else {
                throw new Exception("The \$CFG->{$cfgname} is not set.");
            }
        }
    }

     * Click on the element of the specified type which is located inside the second element.
     *
     * @When /^I ensure "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" is checked$/
     * @param string $element Element we look for
     * @param string $selectortype The type of what we look for
     */
    public function i_ensure_is_checked(string $element, string $selectortype) {
        // $checkboxField = $this->getSession()->getPage()->find('css', 'input[type="checkbox"].' . $classname);
        $checkboxField = $this->get_selected_node($selectortype, $element);

        if (null === $checkboxField) {
            throw new \Exception("The checkbox with class '{$classname}' was not found on the page.");
        }

        // Only check if it's not already checked.
        if (!$checkboxField->isChecked()) {
            $checkboxField->check();
        }
    }
}
