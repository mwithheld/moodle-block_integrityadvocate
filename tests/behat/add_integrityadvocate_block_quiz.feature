@block @block_integrityadvocate @block_integrityadvocate_quiz
Feature: Add and configure IntegrityAdvocate block to a quiz
  In order to have an IntegrityAdvocate block on a page
  A a teacher
  I need to be able to create, configure and change IntegrityAdvocate blocks

  Background:
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion |
      #| Course 0 | C0        | 0 |
      | Course 1 | C1        | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam1      | Student1 | student1@example.com |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      #| teacher1 | C0     | editingteacher |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
  # And the following "question categories" exist:
  #   | contextlevel | reference | name           |
  #   | Course       | C1        | Test questions |
  # And the following "questions" exist:
  #   | questioncategory | qtype       | name  | questiontext    | defaultmark |
  #   | Test questions   | essay       | TF1   | First question  | 20          |
  #   | Test questions   | truefalse   | TF2   | Second question |
  # And the following "activities" exist:
  #   | activity   | name   | intro              | course | idnumber | grade |
  #   | quiz       | Quiz 1 | Quiz 1 description | C1     | quiz1    | 20    |
  # And quiz "Quiz 1" contains the following questions:
  #   | question | page |
  #   | TF1      | 1    |
  #   | TF2      | 1    |
  # And the following "blocks" exist:
  #   | blockname     | contextlevel | reference | pagetypepattern | defaultregion |
  #   | private_files | System       | 1         | my-index        | side-post     |

  # This is for module-level blocks.
  # And I set the following fields to these values:
  #   | Application id | invalid-application-id        |
  #   | API key        | invalid-api-key== |
  # Then I should see "No API key is set" in the "block_integrityadvocate" "block"
  # And I should see "No Application id is set" in the "block_integrityadvocate" "block"
  # And I log out

  @javascript
  Scenario: Teacher should be warned if quiz configured to not show blocks
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Quiz" to section "1" and I fill the form with:
      | Name                             | Quiz 1             |
      | Description                      | Quiz 1 description |
      # This next line is not the standard setup for using this plugin.
      | Show blocks during quiz attempts | 0                  |
    And I add a "True/False" question to the "Quiz 1" quiz with:
      | Question name  | Quiz Question 1           |
      | Question text  | Answer to Quiz Question 1 |
      | Correct answer | False                     |
    # Should still be in course editing mode here
    And I am on "Course 1" course homepage with editing mode on
    When I follow "Quiz 1"
    And I add the "Integrity Advocate" block
    Then I should see "No API key is set" in the "block_integrityadvocate" "block"
    And I should see "No Application id is set" in the "block_integrityadvocate" "block"
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    Then I should see "This quiz is configured with" in the "block_integrityadvocate" "block"

  @javascript
  Scenario: Teacher should see the overview button once the block is configured
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Quiz" to section "1" and I fill the form with:
      | Name                             | Test quiz 2             |
      | Description                      | Test quiz 2 description |
      | Show blocks during quiz attempts | 1                       |
    And I add a "True/False" question to the "Test quiz 2" quiz with:
      | Question name  | Quiz Question 2           |
      | Question text  | Answer to Quiz Question 2 |
      | Correct answer | False                     |
    # Should still be in course editing mode here
    And I am on "Course 1" course homepage with editing mode on
    When I follow "Test quiz 2"
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    Then I should not see "No Api Key is set" in the "block_integrityadvocate" "block"
    And I should not see "No Application Id is set" in the "block_integrityadvocate" "block"
    And "Overview" "button" should be visible

# When I hide the quiz-level block the IA block should not show to students
# Quiz-level block should show Course Overview and Module Overview
