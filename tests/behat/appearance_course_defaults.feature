@format @format_smartcards @format_smartcards_appearance_defaults
Feature: SmartCards per-course card size overrides the site-wide default
  In order to give one course a different visual density than the rest of the site
  As a teacher
  I need the course's own card size option to take priority over the site default

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
    And the following config values are set as admin:
      | cardsize | large | format_smartcards |

  Scenario: A course's own card size overrides the site default
    Given the following "courses" exist:
      | fullname | shortname | format     | numsections | cardsize |
      | Course 1 | C1        | smartcards | 1           | small    |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then "[data-region='smartcards-content'].sc-size-small" "css_element" should exist
    And "[data-region='smartcards-content'].sc-size-large" "css_element" should not exist
