@block @block_integrityadvocate @block_integrityadvocate_quiz
Feature: Add and configure IntegrityAdvocate block to a quiz
  In order to have an IntegrityAdvocate block on a quiz page
  As a teacher or as a student
  I need to be able to create, configure and change IntegrityAdvocate blocks, and view it as a student

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
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 1    |

  # Is this still a thing even?
  # Then I should see "This quiz is configured with" in the "block_integrityadvocate" "block"

  # When I hide the quiz-level block the IA block should not show to students
  # Quiz-level block should show Course Overview and Module Overview

  # As a student on the quiz intro page I should see the student overview button in the IA block http://localhost:8000/mod/quiz/view.php?id=1

  # Quiz timer reset when proctoring starts

  @javascript @block_integrityadvocate_quiz_warn_if_no_config
  Scenario: Teacher should be warned if quiz configured to not show blocks
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I am on the "Quiz 1" "quiz activity" page
    And I add the "Integrity Advocate" block
    Then I should see "No API key is set" in the "block_integrityadvocate" "block"
    And I should see "No Application id is set" in the "block_integrityadvocate" "block"

  @javascript @block_integrityadvocate_quiz_teacher_view
  Scenario: Teacher can configure the IA block and see the overview buttons
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I am on the "Quiz 1" "quiz activity" page
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    And "Course overview" "button" should be visible
    And "Module overview" "button" should be visible

  @javascript @block_integrityadvocate_quiz_course_overview_ia_enable_proctoring @_switch_iframe
  Scenario: Student can do proctoring
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I am on the "Quiz 1" "quiz activity" page
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    When I click on "Course overview" "button"
    And I change window size to "1366x968"
    When I switch to "iframelaunch" class iframe
    And I wait until the page is ready
    And block_integrityadvocate I add test output "Enable IA for this module -----"
    And I change window size to "large"
    And I click on "Activities" "link"
    # And I click on ".chkEnableIA" "css_element"
    # And I set the field "#chkEnableIA_3" to "1"
    And I ensure ".chkEnableIA" "css_element" is "checked"
    And I wait until the page is ready
    # Then I should see "DEMO"
    Then block_integrityadvocate I select "DEMO" from the ".ddactivationname" selectbox
    And I wait until the page is ready
    Then I should see "DEMO" in the "#gridActivities" "css_element"
    # Then block_integrityadvocate I select "Level 2" from the ".ddactivationname" selectbox
    # Then I should see "Level 2" in the "#gridActivities" "css_element"
    # # Sometimes the button does not show up.
    # And I reload the page
    # -- BEGIN: We do not need these now but they might be useful later.
    When I click on "Select Rules" "button"
    And I wait until the page is ready
    Then I should see "Stay in view of the camera"
    And block_integrityadvocate I "uncheck" all checkboxes in "#popIntegrityActivityRulesContent"
    # Remain engaged
    And I ensure "#chkRules_9" "css_element" is "checked"
    # Stay in view of the camera
    And I ensure "#chkRules_1" "css_element" is "checked"
    And I click on "Submit" "button"
    # -- END: We do not need these now but they might be useful later.
    # Allow the AJAX POST to finish.
    And I wait "1" seconds
    And I log out
    And block_integrityadvocate I add test output "Test as student -----"
    # And block_integrityadvocate I set the browser useragent to "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36"
    Given I log in as "student1"
    When I am on the "Quiz 1" "quiz activity" page logged in as student1
    Then "block_integrityadvocate" "block" should exist
    And I should see "This page uses the Integrity Advocate proctoring service" in the "block_integrityadvocate" "block"
    When I press "Attempt quiz"
    And I wait until the page is ready
    And I wait "2" seconds
    Then I should see "DEMO mode"
    # Then I should see "monitor your participation" in the "#integrityadvocate_container" "css_element"
    And I click on "#integrityadvocate_btnContinue" "css_element"
    Then I should see "privacy policy" in the "#integrityadvocate_container" "css_element"
    And I click on "#integrityadvocate_btnContinue" "css_element"
    Then I should see "Take a picture" in the "#integrityadvocate_container" "css_element"
    # 2024Nov17 This fails until we can bypass or emulate the camera and microphone.
    #And I click on "#integrityadvocate_btnContinue" "css_element"
    #Then I should see "Not yet answered" in the "#responseform" "css_element"
