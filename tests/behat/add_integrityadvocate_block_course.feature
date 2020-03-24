@block @block_integrityadvocate @block_integrityadvocate_course
Feature: Add and configure IntegrityAdvocate block to a course
  In order to have an IntegrityAdvocate block on a page
  A a teacher
  I need to be able to create, configure and change IntegrityAdvocate blocks

  Background:
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 0 | C0        | 0 |
      | Course 1 | C1        | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Sam1      | Student1 | student1@example.com |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C0     | editingteacher |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |

  Scenario: Teacher should be warned if completion is disabled at the site level
    When I log in as "admin"
    And I navigate to "Advanced features" in site administration
    And the following config values are set as admin:
      | enablecompletion | 0 |
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    Then I should see "Completion tracking is not enabled on this site"
    And I log out

  Scenario: Teacher should be warned if completion is disabled at the course level
    When I log in as "teacher1"
    And I am on "Course 0" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    Then I should see "Completion tracking is not enabled in this course"
    And I log out
  #Scenario: Students should not see the course-level block
    When I log in as "student1"
    And I am on "Course 0" course homepage
    Then "block_integrityadvocate" "block" should not exist
    And I log out

  Scenario: Teacher should be warned if no activities with completion
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    Then I should see "There are no activities found with activity completion enabled" in the "block_integrityadvocate" "block"
    And I log out
  #Scenario: Students should not see the course-level block
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then "block_integrityadvocate" "block" should not exist
    And I log out

  @javascript
  Scenario: Teacher should be warned if block configuration is not set
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Quiz" to section "1" and I fill the form with:
      | Name        | Test quiz name        |
      | Description | Test quiz description |
      | Grade to pass | 1.00                |
      | Completion tracking | 2             |
      | Student must view this activity to complete it | true                 |
      | Student must receive a grade to complete this activity | true                |
      | Require passing grade | true        |
    And I add a "True/False" question to the "Test quiz name" quiz with:
      | Question name                      | First question                          |
      | Question text                      | Answer the first question               |
      | General feedback                   | Thank you, this is the general feedback |
      | Correct answer                     | False                                   |
      | Feedback for the response 'True'.  | So you think it is true                 |
      | Feedback for the response 'False'. | So you think it is false                |
    And I am on "Course 1" course homepage
    And I add the "Integrity Advocate" block
    Then I should see "No Api Key is set" in the "block_integrityadvocate" "block"
    And I should see "No Application Id is set" in the "block_integrityadvocate" "block"
    And I log out
  #Scenario: Students should not see the course-level block
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    Then "block_integrityadvocate" "block" should not exist
    And I log out
  #Scenario: Teachers can configure the block
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I configure the "block_integrityadvocate" block
    And I set the following fields to these values:
      | Application Id | 123e4567-e89b-12d3-a456-426655440000         |
      | Api Key        | YTM0NZomIzI2OTsmIzM0NTueYQ== |
    And I press "Save changes"
    Then I should not see "No Api Key is set" in the "block_integrityadvocate" "block"
    And I should not see "No Application Id is set" in the "block_integrityadvocate" "block"
    And "Overview" "button" should be visible
    And I log out
  #Scenario: Now students should see the course-level block
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    Then "block_integrityadvocate" "block" should exist
    And "Overview" "button" should not be visible
    And I should see "Privacy Policy"
    And I log out
