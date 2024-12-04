@block @block_integrityadvocate @block_integrityadvocate_overview
Feature: IntegrityAdvocate Course and Module Overview pages
  In order to use the IntegrityAdvocate block overview pages
  As a teacher
  I need to be able to create, configure and change IntegrityAdvocate blocks, and view it as a student

  Background:
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion |
      #| Course 0 | C0        | 0 |
      | Course 1 | C1        | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      # | student1 | Sam1      | Student1 | student1@example.com |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      #| teacher1 | C0     | editingteacher |
      # | student1 | C1     | student        |
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

  @javascript @block_integrityadvocate_overview_course @block_integrityadvocate_overview_course_from_course
  Scenario: Teacher should see the right things on the course overview page
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
    And block_integrityadvocate I add test output "Test header -----"
    Then I should see "Course 1: Integrity Advocate course overview" in the "#page-header" "css_element"
    And I should see "Course 1" in the "div[role='main'] h2" "css_element"
    And I should not see "Quiz 1"
    And block_integrityadvocate I add test output "Test footer -----"
    And I should see "Version 20" in the "#page-content" "css_element"
    And I should see "Application id " in the "#page-content" "css_element"
    And I should see "Block id " in the "#page-content" "css_element"
    Then "Back to course" "button" should be visible
    When I switch to "iframelaunch" class iframe
    And block_integrityadvocate I add test output "Test IA-created items -----"
    And I wait until the page is ready
    And I should see "Participants" in the ".integrity-tabs" "css_element"
    And I should see "Activities" in the ".integrity-tabs" "css_element"
    And I should see "Admin" in the ".integrity-tabs" "css_element"
    And I should see "Search Participant Sessions"

  @javascript @block_integrityadvocate_overview_course @block_integrityadvocate_overview_course_from_quiz @_switch_iframe
  Scenario: Teacher should see the right things on the course overview page iframe
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
    And block_integrityadvocate I add test output "Test IA-created items -----"
    When I switch to "iframelaunch" class iframe
    And I wait until the page is ready
    And I should see "Participants" in the ".integrity-tabs" "css_element"

  @javascript @block_integrityadvocate_overview_module @_switch_iframe
  Scenario: Teacher should see the right things on the module overview page
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I am on the "Quiz 1" "quiz activity" page
    And I add the "Integrity Advocate" block
    When I configure the "block_integrityadvocate" block
    And block_integrityadvocate I set the fields from CFG:
      | Application id | block_integrityadvocate_appid  |
      | API key        | block_integrityadvocate_apikey |
    And I press "Save changes"
    When I click on "Module overview" "button"
    And block_integrityadvocate I add test output "Test header -----"
    # 3.9 shows "Course1: Integrity Advocate module overview" but the course name is omitted in 4.5.
    Then I should see "Integrity Advocate module overview" in the "#page-header" "css_element"
    And I should not see "Course 1" in the "div[role='main'] h2" "css_element"
    And I should see "Quiz 1" in the "div[role='main'] h2" "css_element"
    And block_integrityadvocate I add test output "Test footer -----"
    And I should see "Version 20" in the "#page-content" "css_element"
    And I should see "Application id " in the "#page-content" "css_element"
    And I should see "Block id " in the "#page-content" "css_element"
    Then "Back to course" "button" should be visible
    When I switch to "iframelaunch" class iframe
    And block_integrityadvocate I add test output "Test IA-created items -----"
    And I wait until the page is ready
    And I should see "Participants" in the ".integrity-tabs" "css_element"
    And I should see "Activities" in the ".integrity-tabs" "css_element"
    And I should see "Admin" in the ".integrity-tabs" "css_element"
    And I should see "Search Participant Sessions"
    And block_integrityadvocate I add test output "Test footer buttons -----"
    When I switch to the main frame
    When I press "Back to course"
    Then I should see "Course 1" in the ".page-context-header" "css_element"
