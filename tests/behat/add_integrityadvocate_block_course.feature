@block @block_integrityadvocate @block_integrityadvocate_course @block_integrityadvocate_config
Feature: Add and configure IntegrityAdvocate block to a course
  In order to have an IntegrityAdvocate block on a page
  A a teacher
  I need to be able to create, configure and change IntegrityAdvocate blocks

  Background:
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 0 | C0        | 0                |
      | Course 1 | C1        | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
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
    Then I should see "There are no activities that are visible" in the "block_integrityadvocate" "block"
    And I log out
    #Scenario: Students should not see the course-level block
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then "block_integrityadvocate" "block" should not exist
    And I log out

  @javascript @block_integrityadvocate_config_application_id_empty
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

  @javascript @block_integrityadvocate_config_application_id_invalid
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

  @javascript @block_integrityadvocate_config_api_key_empty
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

  @javascript @block_integrityadvocate_config_api_key_invalid
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

  # This is for module-level blocks.
  # And I set the following fields to these values:
  #   | Application id | invalid-application-id        |
  #   | API key        | invalid-api-key== |
  # Then I should see "No API key is set" in the "block_integrityadvocate" "block"
  # And I should see "No Application id is set" in the "block_integrityadvocate" "block"
  # And I log out

  @javascript @block_integrityadvocate_with_activity_no_config
  Scenario: When an applicable activity and no config the block shows a warning
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Quiz" to section "1" and I fill the form with:
      | Name                | Test quiz 1           |
      | Description         | Test quiz description |
      | Grade to pass       | 1.00                  |
      | Completion tracking | 2                     |
      | Require view        | 1                     |
      | Require grade       | 1                     |
      | completionpassgrade | 1                     |
    #   | completionentriesenabled | 1                                                 |
    #   | completionentries        | 2                                                 |
    And I add a "True/False" question to the "Test quiz 1" quiz with:
      | Question name                      | First question                          |
      | Question text                      | Answer the first question               |
      # | General feedback                   | Thank you, this is the general feedback |
      | Correct answer                     | False                                   |
      # | Feedback for the response 'True'.  | So you think it is true                 |
      # | Feedback for the response 'False'. | So you think it is false                |
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    Then I should see "No API key is set" in the "block_integrityadvocate" "block"
    And I should see "No Application id is set" in the "block_integrityadvocate" "block"
    And "Course overview" "button" should not be visible
    And I log out

  @javascript @block_integrityadvocate_with_activity_and_config
  Scenario: When an applicable activity and configured the course block shows to students
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Quiz" to section "1" and I fill the form with:
      | Name                | Test quiz 1           |
      | Description         | Test quiz description |
      | Grade to pass       | 1.00                  |
      | Completion tracking | 2                     |
      | Require view        | 1                     |
      | Require grade       | 1                     |
      | completionpassgrade | 1                     |
    #   | completionentriesenabled | 1                                                 |
    #   | completionentries        | 2                                                 |
    And I add a "True/False" question to the "Test quiz 1" quiz with:
      | Question name                      | First question                          |
      | Question text                      | Answer the first question               |
      # | General feedback                   | Thank you, this is the general feedback |
      | Correct answer                     | False                                   |
      # | Feedback for the response 'True'.  | So you think it is true                 |
      # | Feedback for the response 'False'. | So you think it is false                |
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      # Subsequent steps require a valid appid and apikey.
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    # Course-level block for teacher should show these things
    Then I should not see "No API key is set" in the "block_integrityadvocate" "block"
    And I should not see "No Application id is set" in the "block_integrityadvocate" "block"
    And "Course overview" "button" should be visible
    And I should see "1 IA block(s) in this course" in the "block_integrityadvocate" "block"
    And "Course" "link" should exist in the "block_integrityadvocate" "block"
    And I should see "Version " in the "block_integrityadvocate" "block"
    And I should see "Application id " in the "block_integrityadvocate" "block"
    And I should see "Block id " in the "block_integrityadvocate" "block"
    And I log out
    #Scenario: Now students should see the course-level block
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then "block_integrityadvocate" "block" should exist
    And "Course overview" "button" should not be visible
    And I should not see "IA block(s) in this course" in the "block_integrityadvocate" "block"
    And "Course" "link" should not exist in the "block_integrityadvocate" "block"
    And I should not see "Version " in the "block_integrityadvocate" "block"
    And I should not see "Application id " in the "block_integrityadvocate" "block"
    And I should not see "Block id " in the "block_integrityadvocate" "block"
    And I should see "This page uses the Integrity Advocate proctoring service"
    And "Privacy" "link" should exist in the "block_integrityadvocate" "block"
    And "Support" "link" should exist in the "block_integrityadvocate" "block"
    And I log out

# @javascript @block_integrityadvocate_with_activity_and_config
# Scenario: When add block to activity it picks up the apikey and appid from the course level block
# Teacher 
# And "Course" "link" should exist in the "block_integrityadvocate" "block"
# And "Test quiz 1" should exist in the "block_integrityadvocate" "block"

# @javascript @block_integrityadvocate_with_activity_and_config
# Scenario: When add block to course it picks up the apikey and appid from the activity level block
# Teacher 
# And "Course" "link" should exist in the "block_integrityadvocate" "block"
# And "Test quiz 1" should exist in the "block_integrityadvocate" "block"
# And I should see "1 IA block(s) in this course" in the "block_integrityadvocate" "block"
# Student should se...

