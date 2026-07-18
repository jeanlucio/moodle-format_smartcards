@format @format_smartcards @format_smartcards_navstyle_sectioncards @javascript
Feature: SmartCards section-cards navigation style
  In order to browse a course as topic cards instead of a long page
  As a student
  I need each section to become its own card, opening a full-screen grid when tapped

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
    And the following "courses" exist:
      | fullname | shortname | format     | numsections | navstyle     |
      | Course 1 | C1        | smartcards | 1           | sectioncards |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | name   | course | idnumber | section |
      | page     | Page 1 | C1     | page1    | 1       |

  Scenario: Each section renders as its own card instead of an inline grid
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then "button.sc-section-card" "css_element" should exist
    And I should see "Topic 1" in the ".sc-section-card" "css_element"
    And "[data-region='smartcards-section-modal-source']" "css_element" should not be visible

  Scenario: Tapping the section card opens its nested activity grid in a modal
    Given I am on the "Course 1" "Course" page logged in as "student1"
    When I click on "Topic 1" "button"
    Then I should see "Page 1" in the ".modal-body" "css_element"
