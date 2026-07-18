@format @format_smartcards @format_smartcards_appearance_defaults
Feature: SmartCards site-wide appearance defaults with a per-course override
  In order to set a consistent look across every course
  As an administrator
  I need a site-wide default card size that an individual course can still override

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
    And the following config values are set as admin:
      | cardsize | large | format_smartcards |

  Scenario: A course without its own card size uses the site default
    Given the following "courses" exist:
      | fullname | shortname | format     | numsections |
      | Course 1 | C1        | smartcards | 1           |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then "[data-region='smartcards-content'].sc-size-large" "css_element" should exist

  Scenario: A course's own card size overrides the site default
    Given the following "courses" exist:
      | fullname | shortname | format     | numsections | cardsize |
      | Course 2 | C2        | smartcards | 1           | small    |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C2     | student |
    When I am on the "Course 2" "Course" page logged in as "student1"
    Then "[data-region='smartcards-content'].sc-size-small" "css_element" should exist
    And "[data-region='smartcards-content'].sc-size-large" "css_element" should not exist
