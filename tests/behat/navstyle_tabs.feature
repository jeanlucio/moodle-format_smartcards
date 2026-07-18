@format @format_smartcards @format_smartcards_navstyle_tabs @javascript
Feature: SmartCards tabs navigation style
  In order to switch between topics without scrolling
  As a student
  I need each section to render as its own tab, opening the one I have pending work in

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
    And the following "courses" exist:
      | fullname | shortname | format     | numsections | enablecompletion | navstyle |
      | Course 1 | C1        | smartcards | 2           | 1                | tabs     |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | name   | course | idnumber | section | completion |
      | page     | Done 1 | C1     | page1    | 1       | 0          |
      | page     | Todo 1 | C1     | page2    | 2       | 1          |

  Scenario: The tab with a pending activity is active by default
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then I should see "Todo 1" in the ".tab-pane.active" "css_element"
    And I should not see "Done 1" in the ".tab-pane.active" "css_element"

  Scenario: Clicking another tab switches the visible section
    Given I am on the "Course 1" "Course" page logged in as "student1"
    When I click on "Topic 1" "button"
    Then I should see "Done 1" in the ".tab-pane.active" "css_element"
    And I should not see "Todo 1" in the ".tab-pane.active" "css_element"
