@block @block_integrityadvocate @block_integrityadvocate_course @block_integrityadvocate_course_config
Feature: Add IntegrityAdvocate block to a course and configure it
  In order to have an IntegrityAdvocate block on a course
  As a teacher or as a student
  I need to be able to create, configure and change IntegrityAdvocate blocks, and view it as a student

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
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  @block_integrityadvocate_course_config_warn_completion_disabled_site
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

  @block_integrityadvocate_course_config_warn_completion_disabled_course
  Scenario: Teacher should be warned if completion is disabled at the course level
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 0 | C0        | 0                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C0     | editingteacher |
      | student1 | C0     | student        |
    When I log in as "teacher1"
    And I am on "Course 0" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    Then I should see "Completion tracking is not enabled in this course"
    And I log out
    #Scenario: Students should not see the course-level block
    When I log in as "student1"
    And I am on "Course 0" course homepage
    Then "block_integrityadvocate" "block" should not exist

  @block_integrityadvocate_course_config_warn_no_completion
  Scenario: Teacher should be warned if no activities with completion
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    Then I should see "There are no activities that are visible" in the "block_integrityadvocate" "block"
    And I log out
    #Scenario: Students should not see the course-level block
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then "block_integrityadvocate" "block" should not exist

  @javascript @block_integrityadvocate_course_config_application_id_empty
  Scenario: Teacher should be warned if block config application id is empty
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And I set the following fields to these values:
      | Application id | invalid-value |
    #   | API key        | invalid-value |
    And I press "Save changes"
    Then I should see "You must supply a value here."

  @javascript @block_integrityadvocate_course_config_application_id_invalid
  Scenario: Teacher should be warned if block config application id is invalid
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And I set the following fields to these values:
      | Application id | invalid-value                                |
      # This will pass Moodle validation, but won't work on calls to the IA api.
      | API key        | c5oMspfrqaUuYX+3/Pem/7/8VnxS385tlmqoV2/bVcA= |
    And I press "Save changes"
    Then I should see "Invalid Application id"

  @javascript @block_integrityadvocate_course_config_api_key_empty
  Scenario: Teacher should be warned if block config api key is empty
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And I set the following fields to these values:
      #   | Application id | invalid-value         |
      | API key | invalid-value |
    And I press "Save changes"
    Then I should see "You must supply a value here."

  @javascript @block_integrityadvocate_course_config_api_key_invalid
  Scenario: Teacher should be warned if block config api key is invalid
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And I set the following fields to these values:
      | Application id | invalid-value |
      | API key        | invalid-value |
    And I press "Save changes"
    Then I should see "Invalid API key"
