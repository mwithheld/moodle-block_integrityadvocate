@block @block_integrityadvocate @block_integrityadvocate_quiz
Feature: Add and configure IntegrityAdvocate block to a quiz
  In order to have an IntegrityAdvocate block on a page
  A a teacher
  I need to be able to create, configure and change IntegrityAdvocate blocks

  Background:
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion |
      #| Course 0 | C0        | 0 |
      | Course 1 | C1        | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Sam1      | Student1 | student1@example.com |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      #| teacher1 | C0     | editingteacher |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |

  Scenario: Teacher should be warned if quiz configured to not show blocks
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Quiz" to section "1" and I fill the form with:
      | Name        | Test quiz 1               |
      | Description | Test quiz 1 description   |
      | Show blocks during quiz attempts | 0    |
    And I add a "True/False" question to the "Test quiz 1" quiz with:
      | Question name                      | Quiz Question 1                          |
      | Question text                      | Answer to Quiz Question 1               |
      | General feedback                   | Thank you, this is the general feedback |
      | Correct answer                     | False                                   |
      | Feedback for the response 'True'.  | So you think it is true                 |
      | Feedback for the response 'False'. | So you think it is false                |
    #Should still be in course editing mode here
    And I am on "Course 1" course homepage
    When I follow "Test quiz 1"
    And I add the "Integrity Advocate" block
    Then I should see "No Api Key is set" in the "block_integrityadvocate" "block"
    And I should see "No Application Id is set" in the "block_integrityadvocate" "block"
    When I configure the "block_integrityadvocate" block
    And I set the following fields to these values:
      | Application Id | 123e4567-e89b-12d3-a456-426655440000         |
      | Api Key        | YTM0NZomIzI2OTsmIzM0NTueYQ== |
    And I press "Save changes"
    Then I should see "This quiz is configured with" in the "block_integrityadvocate" "block"

  @javascript
  Scenario: Teacher should see the overview button once the block is configured
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Quiz" to section "1" and I fill the form with:
      | Name        | Test quiz 2               |
      | Description | Test quiz 2 description   |
      | Show blocks during quiz attempts | 1    |
    And I add a "True/False" question to the "Test quiz 2" quiz with:
      | Question name                      | Quiz Question 2                          |
      | Question text                      | Answer to Quiz Question 2               |
      | General feedback                   | Thank you, this is the general feedback |
      | Correct answer                     | False                                   |
      | Feedback for the response 'True'.  | So you think it is true                 |
      | Feedback for the response 'False'. | So you think it is false                |
    #Should still be in course editing mode here
    And I am on "Course 1" course homepage
    When I follow "Test quiz 2"
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And I set the following fields to these values:
      | Application Id | 123e4567-e89b-12d3-a456-426655440000         |
      | Api Key        | YTM0NZomIzI2OTsmIzM0NTueYQ== |
    And I press "Save changes"
    Then I should not see "No Api Key is set" in the "block_integrityadvocate" "block"
    And I should not see "No Application Id is set" in the "block_integrityadvocate" "block"
    And "Overview" "button" should be visible


