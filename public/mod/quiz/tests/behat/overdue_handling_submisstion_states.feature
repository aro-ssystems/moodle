@mod @mod_quiz @quiz_timer
Feature: Overdue quizzes are either auto-submitted or abandoned based on quiz setting.
  In order to be fair to all students
  As a teacher
  I want to see which students completed some questions and have not submitted at the dealine

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | idnumber  |
      | marker   | Mark      | Alwright | 012345678 |
      | student1 | Student   | One      | S11111111 |
      | student2 | Student   | Two      | S22222222 |
      | student3 | Student   | three    | S33333333 |
      | student4 | Student   | Four     | S44444444 |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
      | student3 | C1     | student |
      | student4 | C1     | student |
    And the following "activities" exist:
      | activity | name   | intro              | course | idnumber | timelimit | overduehandling | graceperiod | timerstagesetting                                                          |
      | quiz     | Quiz 1 | Quiz 1 description | C1     | quiz1    | 30        | autosubmit      | 0           | 00:00:15 Main time/-/00:00:10 Upload time/-/00:00:05 Late penalty period |
      | quiz     | Quiz 2 | Quiz 2 description | C1     | quiz2    | 30        | graceperiod     | 61          | 00:00:15 Main time/-/00:00:10 Upload time/-/00:00:05 Late penalty period |
      | quiz     | Quiz 3 | Quiz 3 description | C1     | quiz3    | 25        | autoabandon     | 0           | 00:00:03 Main time/-/00:00:02 Upload time/-/00:00:20 Late penalty period |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext         |
      | Test questions   | truefalse | TF1  | First question true  |
      | Test questions   | truefalse | TF2  | Second question false|
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    And quiz "Quiz 2" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 2    |
    And quiz "Quiz 3" contains the following questions:
      | question | page |
      | TF1      | 1    |

  @javascript
  Scenario: Student1 attempts Quiz 1 with autosubmit setting, finishes the attempt and submit.
    Given I am on the "Quiz 1" "quiz activity" page logged in as "student1"
    And I click on "Attempt quiz" "button"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Main time: 15 secs"
    And I should see "Upload time: 10 secs"
    And I should see "Late penalty period: 5 secs"
    And I set the field "True" to "1"
    And I press "Finish attempt ..."
    And I press "Submit all and finish"
    When I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    Then I should see "Finished" in the "Status" "table_row"

  @javascript
  Scenario: Student2 attempts Quiz 1 with autosubmit settings, finishes the attempt and submit, but does not confirm.
    Given I am on the "Quiz 1" "quiz activity" page logged in as "student2"
    And I click on "Attempt quiz" "button"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Main time: 15 secs"
    And I should see "Upload time: 10 secs"
    And I should see "Late penalty period: 5 secs"
    And I set the field "True" to "1"
    And I press "Finish attempt ..."
    When I press "Submit all and finish"
    # Do not click on 'Submit all and finish' 'button' in the 'Confirmation' 'dialogue'
    # The attempt is submitted automatically.
    And I wait "25" seconds
    Then I should see "Finished" in the "Status" "table_row"

  @javascript
  Scenario: Student3 attempts Quiz 1 with autosubmit setting, finishes the attempt and does not submit.
    Given I am on the "Quiz 1" "quiz activity" page logged in as "student3"
    And I click on "Attempt quiz" "button"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Main time: 15 secs"
    And I should see "Upload time: 10 secs"
    And I should see "Late penalty period: 5 secs"
    And I set the field "True" to "1"
    When I press "Finish attempt ..."
    # Do not press 'Submit all and finish'.
    # The attempt is submitted automatically.
    And I wait "25" seconds
    Then I should see "Finished" in the "Status" "table_row"

  @javascript
  Scenario: Student4 attempts Quiz 1 with autosubmit setting and does not finish.
    Given I am on the "Quiz 1" "quiz activity" page logged in as "student4"
    And I click on "Attempt quiz" "button"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Main time: 15 secs"
    And I should see "Upload time: 10 secs"
    And I should see "Late penalty period: 5 secs"
    And I set the field "True" to "1"
    # Do not press 'Finish attempt ...'.
    # The attempt is submitted automatically.
    When I wait "25" seconds
    Then I should see "Finished" in the "Status" "table_row"

  @javascript
  Scenario: Student1 attempts Quiz 2 with graceperiod settings, finishes the attempt and submit.
    Given I am on the "Quiz 2" "quiz activity" page logged in as "student1"
    And I click on "Attempt quiz" "button"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Main time: 15 secs"
    And I should see "Upload time: 10 secs"
    And I should see "Late penalty period: 5 secs"
    And I set the field "True" to "1"
    And I press "Next"
    And I set the field "True" to "1"
    And I press "Finish attempt ..."
    And I press "Submit all and finish"
    When I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    Then I should see "Finished" in the "Status" "table_row"

  @javascript
  Scenario: Student2 attempts Quiz 2 with graceperiod settings, does not finish, but submit within the graceperiod.
    Given I am on the "Quiz 2" "quiz activity" page logged in as "student2"
    And I click on "Attempt quiz" "button"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Main time: 15 secs"
    And I should see "Upload time: 10 secs"
    And I should see "Late penalty period: 5 secs"
    And I set the field "True" to "1"
    And I press "Next"
    And I wait "25" seconds
    And I should see "Answer saved" in the "1" "table_row"
    And I should see "Not yet answered" in the "2" "table_row"
    And I should see "This attempt is now overdue. It should already have been submitted."
    And I should see "If you would like this quiz to be graded, you must submit it by"
    When I press "Submit all and finish"
    Then I should see "Finished" in the "Status" "table_row"
    And I should not see "Overdue"

  @javascript
  Scenario: Student3 attempts Quiz 2 with graceperiod settings, does not finish and submit after the graceperiod.
    Given I am on the "Quiz 2" "quiz activity" page logged in as "student3"
    And I click on "Attempt quiz" "button"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Main time: 15 secs"
    And I should see "Upload time: 10 secs"
    And I should see "Late penalty period: 5 secs"
    And I set the field "True" to "1"
    And I press "Next"
    And I wait "30" seconds
    And I should see "Answer saved" in the "1" "table_row"
    And I should see "Not yet answered" in the "2" "table_row"
    And I should see "This attempt is now overdue. It should already have been submitted."
    And I should see "If you would like this quiz to be graded, you must submit it by"
    When I wait "60" seconds
    And I press "Submit all and finish"
    Then I should see "Finished" in the "Status" "table_row"
    And I should see "1 min " in the "Duration" "table_row"
    And I should see "secs" in the "Duration" "table_row"
    And I should see "1 min " in the "Overdue" "table_row"
    And I should see "sec" in the "Overdue" "table_row"

  @javascript
  Scenario: Student1 attempts the quiz and submit in Late penalty period stage.
    Given I am on the "Quiz 3" "quiz activity" page logged in as "student1"
    And I click on "Attempt quiz" "button"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Main time: 3 secs"
    And I should see "Upload time: 2 secs"
    And I should see "Late penalty period: 20 secs"
    And I set the field "True" to "1"
    And I press "Finish attempt ..."
    And I wait "5" seconds
    And I press "Submit all and finish"
    When I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    Then I should see "Finished" in the "Status" "table_row"

  @javascript
  Scenario: Student2 attempts the quiz, does not finish and does not submit.
    Given I am on the "Quiz 3" "quiz activity" page logged in as "student2"
    And I click on "Attempt quiz" "button"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Main time: 3 secs"
    And I should see "Upload time: 2 secs"
    And I should see "Late penalty period: 20 secs"
    And I set the field "True" to "1"
    # Do not press 'Finish attempt ...'
    # Do not press 'Submit all and finish'.
    When I wait "25" seconds
    Then I should see "Never submitted" in the "Status" "table_row"

  @javascript
  Scenario: Student3 attempts the quiz, finished the attempt and does not submit.
    Given I am on the "Quiz 3" "quiz activity" page logged in as "student3"
    And I click on "Attempt quiz" "button"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Main time: 3 secs"
    And I should see "Upload time: 2 secs"
    And I should see "Late penalty period: 20 secs"
    And I set the field "True" to "1"
    And I press "Finish attempt ..."
    # Do not press 'Submit all and finish'.
    When I wait "25" seconds
    Then I should see "Never submitted" in the "Status" "table_row"

  @javascript
  Scenario: Student2 attempts the quiz and submit, but does not confirm.
    Given I am on the "Quiz 3" "quiz activity" page logged in as "student4"
    And I click on "Attempt quiz" "button"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Main time: 3 secs"
    And I should see "Upload time: 2 secs"
    And I should see "Late penalty period: 20 secs"
    And I set the field "True" to "1"
    And I press "Finish attempt ..."
    And I press "Submit all and finish"
    # Do not click on 'Submit all and finish' 'button' in the 'Confirmation' 'dialogue'
    When I wait "25" seconds
    Then I should see "Never submitted" in the "Status" "table_row"
