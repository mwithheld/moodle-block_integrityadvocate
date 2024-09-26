<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;

class behat_block_integrityadvocate extends behat_base {

    /** @var string String to put at the end of behat debug output */
    private const OUTPUT_EOL = '; ';

    /**
     * Set fields dynamically using values from $CFG.
     * Example usage in feature file:
     *   And I set the fields from $CFG:
     *     | Application id | block_integrityadvocate_appid |
     *     | API key        | block_integrityadvocate_apikey |
     *
     * @Then /^integrityadvocate I set the fields from CFG:$/
     */
    public function integrityadvocate_i_set_fields_from_cfg(TableNode $table) {
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
                $this->fillField($fieldname, $fieldvalue);
            } else {
                throw new Exception("The \$CFG->{$cfgname} is not set.");
            }
        }
    }

    /**
     * Helper to fill fields in the form.
     */
    private function fillField($fieldname, $value) {
        $session = $this->getSession();
        $page = $session->getPage();
        $field = $page->findField($fieldname);

        if (null === $field) {
            throw new Exception("The field '{$fieldname}' was not found on the page.");
        }

        $field->setValue($value);
    }
}
