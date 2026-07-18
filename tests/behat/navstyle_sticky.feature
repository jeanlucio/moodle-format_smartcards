@format @format_smartcards @format_smartcards_navstyle_sticky
Feature: SmartCards sticky navigation style
  In order to keep track of which section I am in while scrolling
  As a student
  I need each section's heading to stay pinned to the top while I scroll through its cards

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
    And the following "courses" exist:
      | fullname | shortname | format     | numsections | navstyle |
      | Course 1 | C1        | smartcards | 1           | sticky   |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | name   | course | idnumber | section |
      | page     | Page 1 | C1     | page1    | 1       |

  Scenario: The course container carries the sticky modifier and sections render as usual
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then "[data-region='smartcards-content'].sc-sticky" "css_element" should exist
    And I should see "Page 1" in the ".sc-card-title" "css_element"
