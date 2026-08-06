@mod @mod_quiz @quiz_timer @quiz_timer_notifications @javascript
Feature: Quiz timer network notifications
  In order to see timer sync feedback without layout disruption
  As a student
  I need notifications as floating overlay toasts

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username |
      | student  |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | student | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext   |
      | Test questions   | truefalse | TF1  | First question |

  Scenario: Attempt page uses toast overlay and no inline sync badge
    Given the following "activities" exist:
      | activity | name       | course | idnumber | timelimit |
      | quiz     | Timed quiz | C1     | quiz1    | 120       |
    And quiz "Timed quiz" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    |         |

    When I am on the "Timed quiz" "mod_quiz > View" page logged in as "student"
    And I press "Attempt quiz"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    Then I should see "Time left"
    And "[data-region='sync-status']" "css_element" should not exist
    And ".toast-wrapper" "css_element" should exist

  Scenario: Staged timer attempt has no inline sync badge
    Given the following "activities" exist:
      | activity | name        | course | idnumber | timerstagesetting                                              |
      | quiz     | Staged quiz | C1     | quiz2    | 00:00:15 Main time/-/00:00:10 Upload time/-/00:00:05 Review time |
    And quiz "Staged quiz" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    |         |

    When I am on the "Staged quiz" "mod_quiz > View" page logged in as "student"
    And I press "Attempt quiz"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    Then "ul.stagetimer li.current" "css_element" should exist
    And "[data-region='sync-status']" "css_element" should not exist
    And ".toast-wrapper" "css_element" should exist

  Scenario: Preview attempt has toast region without inline sync badge
    Given the following "activities" exist:
      | activity | name       | course | idnumber | timelimit |
      | quiz     | Timed quiz | C1     | quiz3    | 120       |
    And quiz "Timed quiz" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    |         |

    When I am on the "Timed quiz" "mod_quiz > View" page logged in as "admin"
    And I press "Preview quiz"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    Then I should see "Time left"
    And "[data-region='sync-status']" "css_element" should not exist
    And ".toast-wrapper" "css_element" should exist
