@block @block_integrityadvocate @block_integrityadvocate_activity_completion
Feature: Activity completion on quiz1 should prevent access to quiz2
  In order to use IntegrityAdvocate acivity restriction
  A a teacher or as a student
  I need to be able to create, configure and change IntegrityAdvocate activity restriction, and view it as a student

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
      | quiz     | Quiz 2 | Quiz 2 description | C1     | quiz2    | 20    | general | 2          | 1              |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    And quiz "Quiz 2" contains the following questions:
      | question | page |
      | TF2      | 1    |

# @javascript @block_integrityadvocate_config_replicates_from_course_to_activity
# Scenario: When add block to activity it picks up the apikey and appid from the course level block
#   Given I log in as "teacher1"
#   And I am on "Course 1" course homepage with editing mode on
#   And I add a "Quiz" to section "1" and I fill the form with:
#     | Name                | Quiz 1                |
#     | Description         | Test quiz description |
#     | Grade to pass       | 1.00                  |
#     | Completion tracking | 2                     |
#   And I add a "True/False" question to the "Quiz 1" quiz with:
#     | Question name  | Question 1            |
#     | Question text  | Answer the Question 1 |
#     | Correct answer | False                     |
#   And I am on "Course 1" course homepage with editing mode on
#   And I add the "Integrity Advocate" block
#   When I configure the "block_integrityadvocate" block
#   And block_integrityadvocate I set the fields from CFG:
#     # Subsequent steps require a valid appid and apikey.
#     | Application id | block_integrityadvocate_appid  |
#     | API key        | block_integrityadvocate_apikey |
#   And I press "Save changes"
#   When I am on the "Quiz 1" "quiz activity" page logged in as "teacher1"
#   And I turn editing mode on
#   And I add the "Integrity Advocate" block
#   # Course-level block for teacher should show these things
#   Given I log in as "teacher1"
#   And I am on "Course 1" course homepage
#   Then "Course" "link" should exist in the "block_integrityadvocate" "block"
#   And "Quiz 1" "link" should exist in the "block_integrityadvocate" "block"
#   And I should see "2 IA block(s) in this course" in the "block_integrityadvocate" "block"

# Activity completion on quiz1 should prevent access to quiz2
# Requires availability_integrityadvocate.
# | Require view        | 1                     |
# | Require grade       | 1                     |
# | completionpassgrade | 1                     |
#   | completionentriesenabled | 1                                                 |
#   | completionentries        | 2
