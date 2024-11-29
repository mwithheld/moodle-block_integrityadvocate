@block @block_integrityadvocate @block_integrityadvocate_diagnostics
Feature: IntegrityAdvocate Diagnostics page
  In order to use the IntegrityAdvocate block diagnostics
  As a teacher
  I need to be able to view the page and see some results

  Background:
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 1 | C1        | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam1      | Student1 | student1@example.com |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |

  @javascript @block_integrityadvocate_diagnostics_link_and_ping
  Scenario: Teacher should see the diagnostics link on the block config and ping should work
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    Then I configure the "block_integrityadvocate" block
    When I click on "Diagnostics" "link"
    And block_integrityadvocate I add test output "Test header -----"
    Then I should see "Course 1: Integrity Advocate Diagnostics" in the ".page-context-header" "css_element"
    And block_integrityadvocate I add test output "Test footer -----"
    And I should see "Version 20" in the "#page-content" "css_element"
    And I should see "Application id " in the "#page-content" "css_element"
    And I should see "Block id " in the "#page-content" "css_element"
    Then "Back to course" "button" should be visible
    And block_integrityadvocate I add test output "Test diagnostics results -----"
    Then "The IA API endpoint: /ping" row "Summary" column of "block_integrityadvocate_diagnostics" table should contain "200 Success"

  @javascript @block_integrityadvocate_diagnostics_link_and_ping
  Scenario: Student should not be able to access the diagnostics page
    When I log in as "student1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    Then I configure the "block_integrityadvocate" block
    When I click on "Diagnostics" "link"
    And block_integrityadvocate I add test output "Test header -----"
    Then I should see "Course 1: Integrity Advocate Diagnostics" in the ".page-context-header" "css_element"
    And block_integrityadvocate I add test output "Test footer -----"
    And I should see "Version 20" in the "#page-content" "css_element"
    And I should see "Application id " in the "#page-content" "css_element"
    And I should see "Block id " in the "#page-content" "css_element"
    Then "Back to course" "button" should be visible
    And block_integrityadvocate I add test output "Test diagnostics results -----"
    Then "The IA API endpoint: /ping" row "Summary" column of "block_integrityadvocate_diagnostics" table should contain "200 Success"
