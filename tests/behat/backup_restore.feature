@format @format_smartcards @format_smartcards_backup_restore @javascript @_file_upload
Feature: SmartCards custom appearance survives backup and restore
  In order to keep my customised cards when duplicating a course
  As a teacher
  I need an activity's custom emoji appearance to survive a full backup and restore

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
    And the following config values are set as admin:
      | enableasyncbackup | 0 |

  Scenario: A custom emoji survives a full backup and restore into a new course
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I open "Page 1" actions menu
    And I choose "Card appearance" in the open action menu
    And I click on "//input[@id='sc-appearance-type-emoji']" "xpath_element"
    And I set the field with xpath "//input[@id='sc-appearance-emoji-input']" to "⭐"
    And I click on "Save changes" "button"
    And I log out
    # Restoring into a new course needs moodle/course:create, which editingteacher
    # does not have by default — the rest of this scenario runs as admin, matching
    # every core feature that exercises this exact restore destination.
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I backup "Course 1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name       | Course 2 |
      | Schema | Course short name | C2       |
    When I am on the "Course 2" "Course" page logged in as "admin"
    Then I should see "⭐" in the ".sc-card-emoji" "css_element"
