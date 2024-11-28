@block @block_integrityadvocate @block_integrityadvocate_activity_completion
Feature: Activity completion on quiz1 should prevent access to quiz2
  In order to use IntegrityAdvocate acivity restriction
  As a teacher or as a student
  I need to be able to create, configure and change IntegrityAdvocate activity restriction, and view it as a student

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
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext | defaultmark |
      | Test questions   | truefalse | TF1  | Question 1   | 10          |
      | Test questions   | truefalse | TF2  | Question 2   | 10          |
    And the following "activities" exist:
      | activity | name   | intro              | course | idnumber | grade | type    | completion | completionview |
      | quiz     | Quiz 1 | Quiz 1 description | C1     | quiz1    | 20    | general | 2          | 1              |
      | page     | Page1  | Page 1 description | C1     | page1    | 1     | general | 1          | 1              |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I am on the "Quiz 1" "quiz activity" page
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    And I should see "2 IA block(s) in this course" in the "block_integrityadvocate" "block"

  @javascript @block @block_integrityadvocate @block_integrityadvocate_activity_completion_blocks_second_quiz
  Scenario: Activity completion on quiz1 should prevent access to page1
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on

    # Restrict page1 to require quiz1 IA completion.
    When I open "Page1" actions menu
    And I click on "Edit settings" "link" in the "Page1" activity
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Integrity Advocate" "button" in the "Add restriction..." "dialogue"
    # And I click on "Displayed if student doesn't meet this condition • Click to hide" "link"
    # In newer versions this field is named "Activity or resource"
    And I set the field "Module" to "Quiz 1"
    And I set the field "Required IA validation status" to "must have IA status valid"
    And I press "Save and return to course"
    Then I should see "Not available unless: The IA result for module Quiz 1 is valid" in the "region-main" "region"
    When I turn editing mode off
    Then I should see "Not available unless: The IA result for module Quiz 1 is valid" in the "region-main" "region"

# Todo: Test this
  # And I set the field "Module" to "Quiz1"
  # And I set the field "Required IA validation status" to "Must have IA status invalid"

# Todo: Test this maybe?
  # On Page1 require IA completion status=invalid in Quiz 1
  # As a student I can access Page1
