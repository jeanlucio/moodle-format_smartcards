@format @format_smartcards @format_smartcards_navstyle_trail
Feature: SmartCards trail navigation style
  In order to follow a course as a single guided path
  As a student
  I need every section's cards to render as one winding column instead of a wrapping grid

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
    And the following "courses" exist:
      | fullname | shortname | format     | numsections | navstyle |
      | Course 1 | C1        | smartcards | 1           | trail    |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | name   | course | idnumber | section |
      | page     | Page 1 | C1     | page1    | 1       |
      | page     | Page 2 | C1     | page2    | 1       |

  Scenario: Cards render inside a single trail column instead of a wrapping grid
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then ".sc-trail" "css_element" should exist
    And ".sc-grid" "css_element" should not exist
    And I should see "Page 1" in the ".sc-trail" "css_element"
    And I should see "Page 2" in the ".sc-trail" "css_element"
