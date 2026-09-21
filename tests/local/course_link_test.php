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

namespace mod_coassemble\local;

#[\PHPUnit\Framework\Attributes\CoversClass(course_link::class)]
/**
 * Course ownership survives updates and cannot be inferred from browser events.
 *
 * @package mod_coassemble
 * @copyright 2026 Coassemble
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\local\course_link
 */
final class course_link_test extends \advanced_testcase {
    /**
     * Ownership is retained when the builder updates a linked course's title.
     */
    public function test_updates_preserve_origin(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('coassemble', ['course' => $course->id]);
        foreach (['created', 'linked', 'copied'] as $mode) {
            $instance = course_link::persist($instance, 123, '', $mode);
            $instance = course_link::persist($instance, 123, 'Updated title');
            $this->assertSame($mode, $DB->get_field('coassemble', 'linkmode', ['id' => $instance->id]));
        }
        $instance = course_link::persist($instance, 456);
        $this->assertSame('linked', $instance->linkmode);
    }

    /**

     * Invalid origin values fail before changing the activity.

     */
    public function test_invalid_origin_is_rejected(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('coassemble', ['course' => $course->id]);
        $this->expectException(\coding_exception::class);
        course_link::persist($instance, 123, '', 'unknown');
    }
    /**
     * Linked originals cannot be deleted even when they are the only local use.
     */
    public function test_linked_course_cannot_be_deleted(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('coassemble', [
            'course' => $course->id, 'coassemblecourseid' => 123, 'linkmode' => 'linked',
        ]);
        $client = $this->createMock(\mod_coassemble\api\client::class);
        $client->expects($this->never())->method('delete_course');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('manage_delete_linked', 'mod_coassemble'));
        course_link::delete_remote($instance, $client);
    }

    /**
     * Uses in a different Moodle course also prevent remote deletion.
     */
    public function test_shared_owned_course_cannot_be_deleted(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('coassemble', [
            'course' => $course->id, 'coassemblecourseid' => 123, 'linkmode' => 'copied',
        ]);
        $this->getDataGenerator()->create_module('coassemble', [
            'course' => $othercourse->id, 'coassemblecourseid' => 123, 'linkmode' => 'linked',
        ]);
        $this->assertSame(1, course_link::other_uses($instance));
        $client = $this->createMock(\mod_coassemble\api\client::class);
        $client->expects($this->never())->method('delete_course');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('manage_delete_shared', 'mod_coassemble', 1));
        course_link::delete_remote($instance, $client);
    }

    /**
     * Deleting an exclusively owned course clears its link and local results.
     */
    public function test_owned_delete_clears_results(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/coassemble/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $instance = $this->getDataGenerator()->create_module('coassemble', [
            'course' => $course->id, 'coassemblecourseid' => 123, 'linkmode' => 'created',
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completioncourse' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        coassemble_record_progress($instance, $user->id, 100, true);
        $client = $this->createMock(\mod_coassemble\api\client::class);
        $client->expects($this->once())->method('delete_course')->with(123)->willReturn([]);
        $result = course_link::delete_remote($instance, $client);
        $this->assertNull($result->coassemblecourseid);
        $this->assertSame('existing', $result->flow);
        $this->assertFalse($DB->record_exists('coassemble_track', ['coassembleid' => $instance->id]));
        $grades = grade_get_grades($course->id, 'mod', 'coassemble', $instance->id, $user->id);
        $item = reset($grades->items);
        $this->assertNull($item->grades[$user->id]->grade);
        $cm = get_coursemodule_from_instance('coassemble', $instance->id);
        $this->assertFalse($DB->record_exists('course_modules_completion', ['coursemoduleid' => $cm->id]));
    }

    /**
     * An upstream delete failure must retain the local link and ownership.
     */
    public function test_failed_delete_keeps_link(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('coassemble', [
            'course' => $course->id, 'coassemblecourseid' => 123, 'linkmode' => 'copied',
        ]);
        $client = $this->createMock(\mod_coassemble\api\client::class);
        $client->method('delete_course')->willThrowException(new \RuntimeException('Failed'));
        try {
            course_link::delete_remote($instance, $client);
            $this->fail('Expected deletion failure');
        } catch (\RuntimeException $e) {
            $this->assertEquals(123, $DB->get_field('coassemble', 'coassemblecourseid', ['id' => $instance->id]));
        }
    }
}
