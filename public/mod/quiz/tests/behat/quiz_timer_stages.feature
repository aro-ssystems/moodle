@mod @mod_quiz @quiz_timer @javascript
Feature: Core quiz timer with staged timings
  In order to see accurate countdown during staged exams
  As a student
  I need the unified core timer to show stage list and current phase

  Scenario: Staged timer on attempt page
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username |
      | student  |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | student | C1     | student |
    And the following "activities" exist:
      | activity | name      | course | idnumber | timerstagesetting                                                          |
      | quiz     | Test quiz | C1     | quiz1    | 01:00:00 Main time/-/00:10:00 Upload time/-/00:05:00 Late penalty period |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext   |
      | Test questions   | truefalse | TF1  | First question |
    And quiz "Test quiz" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    |         |

    When I am on the "Test quiz" "mod_quiz > View" page logged in as "student"
    And I press "Attempt quiz"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    Then I should see "First question"
    And I should see "Stages"
    And I should see "Main time"
    And I should see "Time left"
    And "ul.stagetimer li.current" "css_element" should exist

  Scenario: Simple timed quiz shows single-line timer
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username |
      | student  |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | student | C1     | student |
    And the following "activities" exist:
      | activity | name      | course | idnumber | timelimit |
      | quiz     | Test quiz | C1     | quiz1    | 120       |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext   |
      | Test questions   | truefalse | TF1  | First question |
    And quiz "Test quiz" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    |         |

    When I am on the "Test quiz" "mod_quiz > View" page logged in as "student"
    And I press "Attempt quiz"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    Then I should see "Time left"
    And "#quiz-time-left" "css_element" should exist
    And "ul.stagetimer" "css_element" should not exist
