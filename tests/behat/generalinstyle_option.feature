@format @format_smartcards @format_smartcards_generalinstyle
Feature: SmartCards "General" section can opt into the active navigation style
  In order to keep the General section consistent with the rest of the course
  As a teacher
  I need the generalinstyle option to make section 0 join the active style instead of always rendering plain

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
    And the following "courses" exist:
      | fullname | shortname | format     | numsections | navstyle  | generalinstyle |
      | Course 1 | C1        | smartcards | 1           | accordion | 1              |
      | Course 2 | C2        | smartcards | 1           | tabs      | 1              |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student1 | C2     | student |
    And the following "activities" exist:
      | activity | name   | course | idnumber | section |
      | page     | Page 1 | C1     | page1    | 0       |
      | page     | Page 2 | C2     | page2    | 0       |

  Scenario: With generalinstyle on, General becomes collapsible in the accordion
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then I should see "General" in the ".sc-accordion-title" "css_element"

  @javascript
  Scenario: With generalinstyle on, General gets its own tab
    When I am on the "Course 2" "Course" page logged in as "student1"
    Then "button.nav-link" "css_element" should exist
    And I should see "General" in the ".nav-tabs" "css_element"
