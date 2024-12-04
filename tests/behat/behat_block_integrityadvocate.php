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

// use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;

/**
 * Behat helper functions.
 */
class behat_block_integrityadvocate extends behat_base {

    /** @var string String to put at the end of behat debug output */
    private const OUTPUT_EOL = '; ';

    /**
     * Output a string to the behat test output.
     *
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
    private function fill_field(string $fieldname, $value) {
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

    /**
     * Click on the element of the specified type which is located inside the second element.
     *
     * @When /^I ensure "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" is "(?P<checkedunchecked_string>[^"]*)"$/
     *
     * @param string $element Element we look for
     * @param string $selectortype The type of what we look for
     * @param string $checkedunchecked The string "checked" or "unchecked", whichever you want the box to be.
     */
    public function i_ensure_is_checked(string $element, string $selectortype, string $checkedunchecked = 'checked') {
        $checkbox = $this->get_selected_node($selectortype, $element);

        if (null === $checkbox) {
            throw new \Exception("The checkbox with class '{$classname}' was not found on the page.");
        }

        if (!in_array($checkedunchecked, ['checked', 'unchecked'])) {
            throw new \InvalidArgumentException("Invalid input param checkedunchecked=$checkedunchecked");
        }

        // Only change the checkbox if needed.
        if ($checkedunchecked === 'checked' && !$checkbox->ischecked()) {
            $checkbox->check();
        }
        if ($checkedunchecked === 'unchecked' && $checkbox->ischecked()) {
            $checkbox->uncheck();
        }
    }

    /**
     * Checks or unchecks all checkboxes within a specific CSS element.
     *
     * @When /^block_integrityadvocate I "(?P<checkuncheck_string>[^"]*)" all checkboxes in "(?P<element_string>(?:[^"]|\\")*)"$/
     *
     * @param string $checkuncheck Whether to "check" or "uncheck" the checkboxes.
     * @param string $csselement The CSS element containing the checkboxes.
     */
    public function i_check_or_uncheck_all_checkboxes(string $checkuncheck, string $csselement) {
        // Validate the input.
        if (!in_array($checkuncheck, ['check', 'uncheck'])) {
            throw new InvalidArgumentException("Invalid action: $checkuncheck. Use 'check' or 'uncheck'.");
        }

        $session = $this->getSession();
        $page = $session->getPage();

        // Find the element containing the checkboxes.
        $container = $page->find('css', $csselement);
        if (null === $container) {
            throw new Exception("The CSS element '{$csselement}' was not found on the page.");
        }

        // Find all checkboxes within the container.
        $checkboxes = $container->findAll('css', 'input[type="checkbox"]');
        if (empty($checkboxes)) {
            throw new Exception("No checkboxes found within the element '{$csselement}'.");
        }

        // Check or uncheck each checkbox as per the $checkuncheck parameter.
        foreach ($checkboxes as $checkbox) {
            $ischecked = $checkbox->ischecked();

            if ($checkuncheck === 'check' && !$ischecked) {
                $checkbox->check();
            } else if ($checkuncheck === 'uncheck' && $ischecked) {
                $checkbox->uncheck();
            }
        }
    }

    /**
     * Set the value of a simple Select element by its selector and not its name.
     *
     * @Then /^block_integrityadvocate I select "(?P<value_string>(?:[^"]|\\")*)" from the "(?P<csselement_string>(?:[^"]|\\")*)" selectbox$/
     *
     * @param string $value The value to set.
     * @param string $csselement The CSS element containing the checkboxes.
     */
    public function i_select_value_from_element(string $value, string $csselement) {
        $session = $this->getSession();
        $page = $session->getPage();

        // Find the select element by id or class.
        $select = $page->find('css', $csselement);
        if (null === $select) {
            throw new Exception("The select element '{$csselement}' was not found on the page.");
        }

        // Select the value.
        $select->selectOption($value);
    }

    /**
     * Add a quiz activity to a course section, using the correct step for the Moodle version.
     *
     * @Given /^block_integrityadvocate I add a quiz activity to course "(?P<coursename>[^"]+)" section "(?P<section>\d+)" and I fill the form with:$/
     *
     * @param string $coursefullname The name of the course.
     * @param int $section The section number.
     * @param TableNode $tablenode The data for the quiz form.
     */
    public function block_integrityadvocate_i_add_a_quiz_activity_to_course_section_and_fill_form(string $coursefullname, int $section, TableNode $tablenode) {
        global $CFG;

        // Get the Moodle version as a numeric value, e.g., 4.4 becomes 40400.
        $moodleversion = (int)$CFG->version;

        // Check if Moodle version is 4.4 or above (version 2023101300 corresponds to 4.4).
        if ($moodleversion >= 2023101300) {
            $this->execute('behat_course::i_add_to_course_section_and_i_fill_the_form_with', ['quiz', $coursefullname, $section, $tablenode]);
        } else {
            $this->execute('And I add a "Quiz" to section "' . $section . '" and I fill the form with:', $tablenode);
        }
    }
}
