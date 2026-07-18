@format @format_smartcards @format_smartcards_status_sheet
Feature: SmartCards status sheet explains what a badge alone cannot show
  In order to understand why an activity is restricted, when it is due, or what it is about
  As a student
  I need tapping a badged card to open a sheet with the full details

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

  @javascript
  Scenario: The sheet shows the real restriction reason computed by core_availability
    Given the following "activities" exist:
      | activity | name         | course | idnumber | section |
      | page     | Restricted 1 | C1     | page1    | 1       |
    And I am on the "Restricted 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Date" "button" in the "Add restriction..." "dialogue"
    And I set the field "year" to "2099"
    And I press "Save and return to course"
    And I am on the "Course 1" "Course" page logged in as "student1"
    When I click on "Restricted 1" "button"
    Then I should see "Reason" in ".modal-body" "css_element"
    And I should see "Not available" in ".sc-sheet-reason" "css_element"

  @javascript
  Scenario: The sheet shows the due date row for a timed activity
    Given the following "activities" exist:
      | activity | name    | course | idnumber | section | completion | completionexpected |
      | page     | Timed 1 | C1     | page1    | 1       | 1          | ##tomorrow noon##  |
    And I am on the "Course 1" "Course" page logged in as "student1"
    When I click on "Timed 1" "button"
    Then I should see "Due date" in ".modal-body" "css_element"

  @javascript
  Scenario: The sheet shows the activity description when the teacher enabled it
    Given the following "activities" exist:
      | activity | name    | intro                               | course | idnumber | section | showdescription |
      | page     | Intro 1 | Read chapters 1 to 3 before class.  | C1     | page1    | 1       | 1               |
    And I am on the "Course 1" "Course" page logged in as "student1"
    When I click on "Intro 1" "button"
    Then I should see "Description" in ".modal-body" "css_element"
    And I should see "Read chapters 1 to 3" in ".sc-sheet-description" "css_element"

  @javascript
  Scenario: The sheet offers a way to access the activity directly
    Given the following "activities" exist:
      | activity | name    | course | idnumber | section | completion | completionexpected |
      | page     | Timed 1 | C1     | page1    | 1       | 1          | ##tomorrow noon##  |
    And I am on the "Course 1" "Course" page logged in as "student1"
    When I click on "Timed 1" "button"
    Then I should see "Access activity" in ".sc-sheet-access" "css_element"
