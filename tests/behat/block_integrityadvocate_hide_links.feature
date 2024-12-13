@block @block_integrityadvocate @block_integrityadvocate_hide_links
Feature: IntegrityAdvocate Hide Privacy and Support Links
  In order to use the IntegrityAdvocate block
  As a student
  I should see or not see the Privacy and Support links when configured

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
    # And the following "questions" exist:
    #   | questioncategory | qtype     | name | questiontext | defaultmark |
    #   | Test questions   | truefalse | TF1  | Question 1   | 10          |
    #   | Test questions   | truefalse | TF2  | Question 2   | 10          |
    And the following "activities" exist:
      | activity | name   | intro              | course | idnumber | grade | type    | completion | completionview |
      | quiz     | Quiz 1 | Quiz 1 description | C1     | quiz1    | 20    | general | 2          | 1              |
    # And quiz "Quiz 1" contains the following questions:
    #   | question | page |
    #   | TF1      | 1    |
    #   | TF2      | 1    |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I am on the "Quiz 1" "quiz activity" page
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"

  @javascript @@block_integrityadvocate @block_integrityadvocate_hide_links_quiz
  Scenario: Student should see the right things on the user overview page when they have no IA data
    When I log in as "student1"
    And I am on "Course 1" course homepage with editing mode on
    When I am on the "Quiz 1" "quiz activity" page
    And block_integrityadvocate I add test output "Test module block content -----"
    Then "block_integrityadvocate" "block" should exist
    And I should see "This page uses the Integrity Advocate proctoring service" in the "block_integrityadvocate" "block"
    And "Privacy" "link" should exist in the "block_integrityadvocate" "block"
    And "Support" "link" should exist in the "block_integrityadvocate" "block"
    And I log out
    And block_integrityadvocate I add test output "Configure block to hide Privacy/Support links -----"
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I am on the "Quiz 1" "quiz activity" page
    When I configure the "block_integrityadvocate" block
    And I set the field "Hide Privacy and Support links in the block when proctoring" to "Yes"
    And I press "Save changes"
    And I log out
    And block_integrityadvocate I add test output "Test the Privacy/Support links are hidden -----"
    When I log in as "student1"
    And I am on "Course 1" course homepage with editing mode on
    When I am on the "Quiz 1" "quiz activity" page
    Then "block_integrityadvocate" "block" should exist
    And I should see "This page uses the Integrity Advocate proctoring service" in the "block_integrityadvocate" "block"
    And "Privacy" "link" should not exist in the "block_integrityadvocate" "block"
    And "Support" "link" should not exist in the "block_integrityadvocate" "block"

