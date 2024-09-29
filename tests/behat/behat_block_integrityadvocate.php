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
     * Helper to fill fields in the form.
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
                $this->fill_field($fieldname, $fieldvalue);
            } else {
                throw new Exception("The \$CFG->{$cfgname} is not set.");
            }
        }
    }
}
