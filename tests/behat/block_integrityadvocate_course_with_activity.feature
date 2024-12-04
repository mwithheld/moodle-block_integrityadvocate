# These are course-level tests that require an activity for the test to work.
# To run on pre-Moodle 4.3:
# bin/moodle-docker-compose exec -u www-data webserver php admin/tool/behat/cli/run.php --format=moodle_screenshot --format-settings '{"formats": "image"}' --format=pretty --colors  --tags="@block_integrityadvocate_course_with_quiz&&~@moodle403"

@block @block_integrityadvocate @block_integrityadvocate_course @block_integrityadvocate_activity @block_integrityadvocate_course_with_activity @block_integrityadvocate_course_with_quiz
Feature: Add IntegrityAdvocate block to a course and an activity
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
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    Given block_integrityadvocate I add a quiz activity to course "Course 1" section "1" and I fill the form with:
      | Name          | Quiz 1                |
      | Description   | Test quiz description |
      | Grade to pass | 1.00                  |
    And I add a "True/False" question to the "Quiz 1" quiz with:
      | Question name  | Question 1            |
      | Question text  | Answer the Question 1 |
      | Correct answer | False                 |
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Integrity Advocate" block

  @javascript @block_integrityadvocate_course_with_quiz_no_completion
  Scenario: When add to course and no config the block shows a warning
    # Then I should see "No API key is set" in the "block_integrityadvocate" "block"
    # And I should see "No Application id is set" in the "block_integrityadvocate" "block"
    Then I should see "There are no activities that are visible" in the "block_integrityadvocate" "block"
    And "Course overview" "button" should not be visible

  @javascript @block_integrityadvocate_course_with_quiz_config_missing @moodle39
  Scenario: When add to a quiz and no config the block shows a warning
    And I am on "Course 1" course homepage with editing mode on
    And I am on the "Quiz 1" "quiz activity" page
    And block_integrityadvocate I navigate to settings in current page administration
    And I set the field "Completion tracking" to "1"
    And I press "Save and display"
    And I am on "Course 1" course homepage
    Then I should see "No API key is set" in the "block_integrityadvocate" "block"
    And I should see "No Application id is set" in the "block_integrityadvocate" "block"
    And "Course overview" "button" should not be visible

  @javascript @block_integrityadvocate_course_with_quiz_config_missing @moodle403
  Scenario: When add to a quiz and no config the block shows a warning
    And I am on "Course 1" course homepage with editing mode on
    And I am on the "Quiz 1" "quiz activity" page
    And block_integrityadvocate I navigate to settings in current page administration
    And I set the following fields to these values:
      | Add requirements  | 1 |
      | View the activity | 1 |
    And I press "Save and display"
    And I am on "Course 1" course homepage
    Then I should see "No API key is set" in the "block_integrityadvocate" "block"
    And I should see "No Application id is set" in the "block_integrityadvocate" "block"
    And "Course overview" "button" should not be visible

  # @javascript @block_integrityadvocate_course_no_activities
  # Scenario: When add to course and no applicable activities the block shows a warning
  #   When I configure the "block_integrityadvocate" block
  #   And block_integrityadvocate I set the fields from CFG:
  #     # Subsequent steps require a valid appid and apikey.
  #     | Application id | block_integrityadvocate_appid  |
  #     | API key        | block_integrityadvocate_apikey |
  #   And I press "Save changes"
  #   And I follow "Quiz 1"
  #   And I navigate to "Edit settings" in current page administration
  #   And I expand all fieldsets
  #   And I set the field "Availability" to "Hide from students"
  #   And I click on "Save and display" "button"
  #   And I am on "Course 1" course homepage
  #   And I wait "3" seconds
  #   And I reload the page
  #   Then I should see "There are no activities that are visible" in the "block_integrityadvocate" "block"
  #   And "Course overview" "button" should not be visible

  @javascript @block_integrityadvocate_course_with_quiz_config_replicates_from_activity_to_course @moodle39
  Scenario: When add to a quiz and configured the course block shows to students
    And I am on "Course 1" course homepage with editing mode on
    And I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Edit settings" in current page administration
    And I set the field "Completion tracking" to "1"
    And I press "Save and display"
    And I am on "Course 1" course homepage
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
    And I should see "Version 20" in the "block_integrityadvocate" "block"
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
    And I should not see "Version 20" in the "block_integrityadvocate" "block"
    And I should not see "Application id " in the "block_integrityadvocate" "block"
    And I should not see "Block id " in the "block_integrityadvocate" "block"
    And I should see "This page uses the Integrity Advocate proctoring service"
    And "Privacy" "link" should exist in the "block_integrityadvocate" "block"
    And "Support" "link" should exist in the "block_integrityadvocate" "block"

  @javascript @block_integrityadvocate_course_with_quiz_config_replicates_from_activity_to_course @moodle403
  Scenario: When add to a quiz and configured the course block shows to students
    And I am on "Course 1" course homepage with editing mode on
    And I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Add requirements  | 1 |
      | View the activity | 1 |
    And I press "Save and display"
    And I am on "Course 1" course homepage
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
    And I should see "Version 20" in the "block_integrityadvocate" "block"
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
    And I should not see "Version 20" in the "block_integrityadvocate" "block"
    And I should not see "Application id " in the "block_integrityadvocate" "block"
    And I should not see "Block id " in the "block_integrityadvocate" "block"
    And I should see "This page uses the Integrity Advocate proctoring service"
    And "Privacy" "link" should exist in the "block_integrityadvocate" "block"
    And "Support" "link" should exist in the "block_integrityadvocate" "block"

  @javascript @block_integrityadvocate_course_with_quiz_config_replicates_from_course_to_activity @moodle39
  Scenario: When add block to activity it picks up the apikey and appid from the course level block
    And I am on "Course 1" course homepage with editing mode on
    And I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Edit settings" in current page administration
    And I set the field "Completion tracking" to "1"
    And I press "Save and display"
    And I am on "Course 1" course homepage
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      # Subsequent steps require a valid appid and apikey.
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    And I am on "Course 1" course homepage with editing mode on
    When I am on the "Quiz 1" "quiz activity" page
    And I add the "Integrity Advocate" block
    # Course-level block for teacher should show these things
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    Then "Course" "link" should exist in the "block_integrityadvocate" "block"
    And "Quiz 1" "link" should exist in the "block_integrityadvocate" "block"
    And I should see "2 IA block(s) in this course" in the "block_integrityadvocate" "block"

  @javascript @block_integrityadvocate_course_with_quiz_config_replicates_from_course_to_activity @moodle403
  Scenario: When add block to activity it picks up the apikey and appid from the course level block
    And I am on "Course 1" course homepage with editing mode on
    And I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Add requirements  | 1 |
      | View the activity | 1 |
    And I press "Save and display"
    And I am on "Course 1" course homepage
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      # Subsequent steps require a valid appid and apikey.
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    And I am on "Course 1" course homepage with editing mode on
    When I am on the "Quiz 1" "quiz activity" page
    And I add the "Integrity Advocate" block
    # Course-level block for teacher should show these things
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    Then "Course" "link" should exist in the "block_integrityadvocate" "block"
    And "Quiz 1" "link" should exist in the "block_integrityadvocate" "block"
    And I should see "2 IA block(s) in this course" in the "block_integrityadvocate" "block"

  @javascript @block_integrityadvocate_course_with_quiz_course_overview_button @moodle39
  Scenario: When click course overview button I go to the course overview page
    And I am on "Course 1" course homepage with editing mode on
    And I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Edit settings" in current page administration
    And I set the field "Completion tracking" to "1"
    And I press "Save and display"
    And I am on "Course 1" course homepage
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      # Subsequent steps require a valid appid and apikey.
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    When I am on the "Quiz 1" "quiz activity" page
    And I add the "Integrity Advocate" block
    # Course-level block for teacher should show these things
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    Then "Course overview" "button" should be visible

  @javascript @block_integrityadvocate_course_with_quiz_course_overview_button @moodle403
  Scenario: When click course overview button I go to the course overview page
    And I am on "Course 1" course homepage with editing mode on
    And I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Add requirements  | 1 |
      | View the activity | 1 |
    And I press "Save and display"
    And I am on "Course 1" course homepage
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      # Subsequent steps require a valid appid and apikey.
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    When I am on the "Quiz 1" "quiz activity" page
    And I add the "Integrity Advocate" block
    # Course-level block for teacher should show these things
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    Then "Course overview" "button" should be visible
