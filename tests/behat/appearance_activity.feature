@format @format_smartcards @format_smartcards_appearance_activity
Feature: SmartCards custom activity card appearance
  In order to make an activity easier to recognise at a glance
  As a teacher
  I need to replace its default icon with an emoji, from the per-activity edit menu

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | 1        |
      | student1 | Student   | 1        |
    And the following "courses" exist:
      | fullname | shortname | format     | numsections |
      | Course 1 | C1        | smartcards | 1           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name   | course | idnumber | section |
      | page     | Page 1 | C1     | page1    | 1       |

  @javascript
  Scenario: A teacher sets a custom emoji and it replaces the card's default icon
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I open "Page 1" actions menu
    And I choose "Card appearance" in the open action menu
    And I click on "Emoji" "radio"
    And I set the field with xpath "//input[@id='sc-appearance-emoji-input']" to "⭐"
    And I click on "Save changes" "button"
    And I am on "Course 1" course homepage with editing mode off
    Then I should see "⭐" in the ".sc-card-emoji" "css_element"

  Scenario: A teacher without the manageappearance capability does not see the action
    Given the following "permission overrides" exist:
      | capability                          | permission | role           | contextlevel | reference |
      | format/smartcards:manageappearance   | Prevent    | editingteacher | Course       | C1        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    Then "Card appearance" "link" should not exist in the "Page 1" "activity"
