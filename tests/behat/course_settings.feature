@format @format_smartcards @format_smartcards_course_settings
Feature: SmartCards format options on the course settings form
  In order to configure a course's own look without touching the site defaults
  As a teacher
  I need the format's options available on the course edit settings form

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | 1        |
    And the following "courses" exist:
      | fullname | shortname | format     | numsections |
      | Course 1 | C1        | smartcards | 1           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name   | course | idnumber | section |
      | page     | Page 1 | C1     | page1    | 1       |

  Scenario: Changing the navigation style from the settings form changes how the course renders
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I navigate to "Settings" in current page administration
    And I set the field "Navigation style" to "Trail"
    And I press "Save and display"
    Then ".sc-trail" "css_element" should exist

  Scenario: Changing the card size from the settings form changes the grid's modifier class
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I navigate to "Settings" in current page administration
    And I set the field "Card size" to "Large"
    And I press "Save and display"
    Then "[data-region='smartcards-content'].sc-size-large" "css_element" should exist
