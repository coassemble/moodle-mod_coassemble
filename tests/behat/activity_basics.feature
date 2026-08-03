@mod @mod_coassemble
Feature: Coassemble activity basics
  In order to deliver Coassemble content inside Moodle
  As a user
  I need Coassemble activities to render sensibly even before the API is configured

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity   | name            | course | idnumber |
      | coassemble | Test Coassemble | C1     | ca1      |

  Scenario: The activity appears on the course page
    When I am on the "Course 1" course page logged in as student1
    Then I should see "Test Coassemble"

  Scenario: A student sees a clear notice while the API is unconfigured
    When I am on the "Test Coassemble" "coassemble activity" page logged in as student1
    Then I should see "Coassemble API credentials are not configured"

  Scenario: A teacher sees the same notice with a link to settings context
    When I am on the "Test Coassemble" "coassemble activity" page logged in as teacher1
    Then I should see "Coassemble API credentials are not configured"
