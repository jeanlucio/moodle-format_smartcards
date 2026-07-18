@format @format_smartcards @format_smartcards_navstyle_accordion @javascript
Feature: SmartCards accordion navigation style
  In order to focus on one topic at a time
  As a student
  I need sections to collapse and expand, opening the one I have pending work in by default

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
    And the following "courses" exist:
      | fullname | shortname | format     | numsections | enablecompletion | navstyle  |
      | Course 1 | C1        | smartcards | 2           | 1                | accordion |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | name   | course | idnumber | section | completion |
      | page     | Done 1 | C1     | page1    | 1       | 0          |
      | page     | Todo 1 | C1     | page2    | 2       | 1          |

  Scenario: The section with a pending activity opens by default
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then "//section[.//span[contains(@class,'sc-accordion-name')][normalize-space(.)='Topic 2']]/div[contains(concat(' ', normalize-space(@class), ' '), ' show ')]" "xpath_element" should exist
    And "//section[.//span[contains(@class,'sc-accordion-name')][normalize-space(.)='Topic 1']]/div[contains(concat(' ', normalize-space(@class), ' '), ' show ')]" "xpath_element" should not exist

  Scenario: Clicking the toggle expands a collapsed section
    Given I am on the "Course 1" "Course" page logged in as "student1"
    When I click on "Topic 1" "button"
    Then "//section[.//span[contains(@class,'sc-accordion-name')][normalize-space(.)='Topic 1']]/div[contains(concat(' ', normalize-space(@class), ' '), ' show ')]" "xpath_element" should exist

  Scenario: A manual collapse survives a page reload
    Given I am on the "Course 1" "Course" page logged in as "student1"
    And I click on "Topic 2" "button"
    And "//section[.//span[contains(@class,'sc-accordion-name')][normalize-space(.)='Topic 2']]/div[contains(concat(' ', normalize-space(@class), ' '), ' show ')]" "xpath_element" should not exist
    When I reload the page
    Then "//section[.//span[contains(@class,'sc-accordion-name')][normalize-space(.)='Topic 2']]/div[contains(concat(' ', normalize-space(@class), ' '), ' show ')]" "xpath_element" should not exist
