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

  @javascript
  Scenario: Selecting an initial create flow preserves unsaved activity settings
    Given I am on the "Test Coassemble" "coassemble activity editing" page logged in as teacher1
    When I set the field "Name" to "Unsaved course title"
    And I set the field "Initial create flow" to "Generate with AI"
    Then the field "Name" matches value "Unsaved course title"
    And the field "Initial create flow" matches value "Generate with AI"
    And I should see "C1"

  Scenario: Learners retain Moodle navigation on an activity page
    When I am on the "Test Coassemble" "coassemble activity" page logged in as student1
    Then ".navbar" "css_element" should exist
    And I should see "C1"

  Scenario: A teacher can save the existing-course path without losing the activity name
    Given I am on the "Test Coassemble" "coassemble activity editing" page logged in as teacher1
    When I set the field "Name" to "Existing course activity"
    And I set the field "Initial create flow" to "Use an existing course"
    And I press "Save and display"
    Then I should see "Coassemble course library"
    And I should see "Coassemble API credentials are not configured"

  Scenario: A teacher can browse the library before adding an activity
    Given the following "courses" exist:
      | fullname     | shortname |
      | Empty course | EMPTY     |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | EMPTY  | editingteacher |
    When I am on the "Empty course" course page logged in as teacher1
    And I navigate to "Coassemble course library" in current page administration
    Then I should see "Coassemble course library"
    And I should see "Coassemble API credentials are not configured"
