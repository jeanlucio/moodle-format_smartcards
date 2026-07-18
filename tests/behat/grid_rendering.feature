@format @format_smartcards @format_smartcards_grid
Feature: SmartCards renders activities as icon and title cards
  In order to navigate a course visually
  As a student
  I need every activity to render as an icon and title card instead of a plain list link

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | 1        |
      | student1 | Student   | 1        |
    And the following "courses" exist:
      | fullname | shortname | format     | numsections |
      | Course 1 | C1        | smartcards | 1           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: A student sees an activity rendered as an icon and title card
    Given the following "activities" exist:
      | activity | name   | course | idnumber | section |
      | page     | Page 1 | C1     | page1    | 1       |
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then "[data-region='smartcards-content']" "css_element" should exist
    And "a.sc-card[data-cmid]" "css_element" should exist
    And I should see "Page 1" in ".sc-card-title" "css_element"

  Scenario: The General section always renders plain unless it opts into the active style
    Given the following "courses" exist:
      | fullname | shortname | format     | numsections | navstyle  |
      | Course 2 | C2        | smartcards | 1           | accordion |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C2     | student |
    And the following "activities" exist:
      | activity | name   | course | idnumber | section |
      | page     | Page 1 | C2     | page1    | 1       |
    When I am on the "Course 2" "Course" page logged in as "student1"
    Then I should see "General" in "h3.sc-section-title:not(.sc-accordion-title)" "css_element"
    And I should see "Topic 1" in ".sc-accordion-title" "css_element"

  Scenario: The card grid does not render while editing mode is on
    Given the following "activities" exist:
      | activity | name   | course | idnumber | section |
      | page     | Page 1 | C1     | page1    | 1       |
    And I log in as "teacher1"
    When I am on "Course 1" course homepage with editing mode on
    Then "[data-region='smartcards-content']" "css_element" should not exist
