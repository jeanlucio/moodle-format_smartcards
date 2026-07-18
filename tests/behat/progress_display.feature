@format @format_smartcards @format_smartcards_progress_display
Feature: SmartCards section progress display
  In order to see how much of a topic I have left
  As a student
  I need an optional progress indicator next to the section title

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
    And the following "courses" exist:
      | fullname | shortname | format     | numsections | enablecompletion | progressdisplay |
      | Course 1 | C1        | smartcards | 1           | 1                | count           |
      | Course 2 | C2        | smartcards | 1           | 1                | percent         |
      | Course 3 | C3        | smartcards | 1           | 1                | none            |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student1 | C2     | student |
      | student1 | C3     | student |
    And the following "activities" exist:
      | activity | name   | course | idnumber | section | completion |
      | page     | Page 1 | C1     | page1    | 1       | 1          |
      | page     | Page 2 | C1     | page2    | 1       | 1          |
      | page     | Page 3 | C2     | page3    | 1       | 1          |
      | page     | Page 4 | C2     | page4    | 1       | 1          |
      | page     | Page 5 | C3     | page5    | 1       | 1          |
      | page     | Page 6 | C3     | page6    | 1       | 1          |

  Scenario: A count progress display shows how many activities are complete
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then I should see "Progress: 0 / 2" in the ".sc-section-title" "css_element"

  Scenario: A percent progress display shows the completion percentage
    When I am on the "Course 2" "Course" page logged in as "student1"
    Then I should see "0%" in the ".sc-section-title" "css_element"

  Scenario: No progress display shows nothing next to the section title
    When I am on the "Course 3" "Course" page logged in as "student1"
    Then ".sc-progress-label" "css_element" should not exist
