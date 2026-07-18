@format @format_smartcards @format_smartcards_availability
Feature: SmartCards shows native availability and deadline badges
  In order to know what I can access and what is coming up
  As a student
  I need cards to show a lock badge when restricted and a clock badge when timed

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
  Scenario: A restricted activity shows the lock badge
    Given the following "activities" exist:
      | activity | name         | course | idnumber | section |
      | page     | Restricted 1 | C1     | page1    | 1       |
    And I am on the "Restricted 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Date" "button" in the "Add restriction..." "dialogue"
    And I set the field "year" to "2099"
    And I press "Save and return to course"
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then I should see "🔒" in ".sc-card-badgeicon" "css_element"

  Scenario: An activity with an expected completion date shows the clock badge
    Given the following "activities" exist:
      | activity | name    | course | idnumber | section | completion | completionexpected |
      | page     | Timed 1 | C1     | page1    | 1       | 1          | ##tomorrow noon##  |
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then I should see "🕒" in ".sc-card-badgeicon" "css_element"

  Scenario: A freely accessible activity shows no badge at all
    Given the following "activities" exist:
      | activity | name   | course | idnumber | section |
      | page     | Free 1 | C1     | page1    | 1       |
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then "a.sc-card[data-cmid]" "css_element" should exist
    And ".sc-card-badgeicon" "css_element" should not exist

  @javascript
  Scenario: A stealth activity renders dimmed with a hidden label instead of disappearing
    Given the following config values are set as admin:
      | allowstealth | 1 |
    And the following "activities" exist:
      | activity | name     | course | idnumber | section |
      | page     | Stealth1 | C1     | page1    | 1       |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I click on "Hide" "link" in the "Stealth1" activity
    And I click on "Make available" "link" in the "Stealth1" activity
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then "a.sc-card.sc-card-dimmed[data-cmid]" "css_element" should exist
    And I should see "Hidden" in ".sc-card-dimmedlabel" "css_element"
