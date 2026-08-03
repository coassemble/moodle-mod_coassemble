<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_coassemble\privacy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/coassemble/lib.php');

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the privacy provider.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Create an activity with tracked progress for a user.
     *
     * @return array [$instance, $user, $context]
     */
    private function setup_tracked_activity(): array {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('coassemble', [
            'course' => $course->id,
            'coassemblecourseid' => 123,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        coassemble_record_progress($instance, $user->id, 66, false, true, 80.0, true);

        $context = \context_module::instance($instance->cmid);
        return [$instance, $user, $context];
    }

    /**
     * Contexts with user data are reported.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        [, $user, $context] = $this->setup_tracked_activity();

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertContainsEquals($context->id, $contextlist->get_contextids());
    }

    /**
     * Export includes progress, score, passed and commenced.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        [, $user, $context] = $this->setup_tracked_activity();

        $approved = new approved_contextlist($user, 'mod_coassemble', [$context->id]);
        provider::export_user_data($approved);

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('pluginname', 'mod_coassemble')]);
        $this->assertEquals(66.0, (float) $data->progress);
        $this->assertEquals(80.0, (float) $data->score);
        $this->assertNotEmpty($data->passed);
        $this->assertNotEmpty($data->commenced);
    }

    /**
     * Users in context are enumerated.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        [, $user, $context] = $this->setup_tracked_activity();

        $userlist = new userlist($context, 'mod_coassemble');
        provider::get_users_in_context($userlist);
        $this->assertContainsEquals((int) $user->id, $userlist->get_userids());
    }

    /**
     * Per-user deletion removes only that user's tracking.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        [$instance, $user, $context] = $this->setup_tracked_activity();

        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $instance->course, 'student');
        coassemble_record_progress($instance, $other->id, 10);

        $approved = new approved_contextlist($user, 'mod_coassemble', [$context->id]);
        provider::delete_data_for_user($approved);

        $this->assertEquals(0, $DB->count_records('coassemble_track', ['userid' => $user->id]));
        $this->assertEquals(1, $DB->count_records('coassemble_track', ['userid' => $other->id]));
    }

    /**
     * Context-wide deletion removes all tracking.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        [$instance, , $context] = $this->setup_tracked_activity();

        provider::delete_data_for_all_users_in_context($context);
        $this->assertEquals(0, $DB->count_records('coassemble_track', ['coassembleid' => $instance->id]));
    }

    /**
     * Approved-userlist deletion removes the listed users only.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        [$instance, $user, $context] = $this->setup_tracked_activity();

        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $instance->course, 'student');
        coassemble_record_progress($instance, $other->id, 10);

        $approved = new approved_userlist($context, 'mod_coassemble', [(int) $user->id]);
        provider::delete_data_for_users($approved);

        $this->assertEquals(0, $DB->count_records('coassemble_track', ['userid' => $user->id]));
        $this->assertEquals(1, $DB->count_records('coassemble_track', ['userid' => $other->id]));
    }
}
