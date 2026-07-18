@format @format_smartcards @format_smartcards_completion @javascript
Feature: SmartCards shows an independent completion badge on each card
  In order to see at a glance what I still have to do
  As a student
  I need a pending/complete badge that updates without leaving the course page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | 1        |
      | student1 | Student   | 1        |
    And the following "courses" exist:
      | fullname | shortname | format     | numsections | enablecompletion |
      | Course 1 | C1        | smartcards | 1           | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: Manually marking an activity done flips the badge and the sheet button
    Given the following "activities" exist:
      | activity | name    | course | idnumber | section | completion |
      | page     | Manual1 | C1     | page1    | 1       | 1          |
    And I am on the "Course 1" "Course" page logged in as "student1"
    And I should see "⚪" in ".sc-card-completionicon" "css_element"
    When I click on "Manual1" "button"
    And I click on "Mark as done" "button" in the ".modal-body" "css_element"
    Then I should see "Mark as not done" in ".modal-body" "css_element"
    And I click on ".btn-close" "css_element" in the ".modal-header" "css_element"
    And I should see "✅" in ".sc-card-completionicon" "css_element"

  Scenario: Viewing an activity satisfies an automatic completion condition
    Given the following "activities" exist:
      | activity | name    | course | idnumber | section | completion | completionview |
      | page     | Auto1   | C1     | page1    | 1       | 2          | 1              |
    And I am on the "Course 1" "Course" page logged in as "student1"
    And I should see "⚪" in ".sc-card-completionicon" "css_element"
    When I click on "Auto1" "button"
    And I click on "Access activity" "link" in the ".modal-body" "css_element"
    And I am on "Course 1" course homepage
    Then I should see "✅" in ".sc-card-completionicon" "css_element"
    And "button.sc-card-opensheet" "css_element" should not exist
