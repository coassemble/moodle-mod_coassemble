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

namespace mod_coassemble\external;

/**
 * Tests for the update_course external function.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_coassemble\external\update_course
 */
final class update_course_test extends \advanced_testcase {
    /**
     * Create a course, an unlinked activity and return the cm record.
     *
     * @return array [$course, $instance, $cm]
     */
    private function setup_activity(): array {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('coassemble', [
            'course' => $course->id,
            'coassemblecourseid' => null,
        ]);
        $cm = get_coursemodule_from_instance('coassemble', $instance->id, $course->id, false, MUST_EXIST);
        return [$course, $instance, $cm];
    }

    /**
     * Authors can persist the Coassemble course link and title.
     */
    public function test_author_can_persist_course_link(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $instance, $cm] = $this->setup_activity();

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $result = update_course::execute((int) $cm->id, 456, 'Linked title');
        $result = update_course::clean_returnvalue(update_course::execute_returns(), $result);

        $this->assertSame(456, $result['courseid']);
        $record = $DB->get_record('coassemble', ['id' => $instance->id], '*', MUST_EXIST);
        $this->assertEquals(456, (int) $record->coassemblecourseid);
        $this->assertSame('Linked title', $record->name);
        $this->assertNotEmpty($record->timeauthored);
    }

    /**
     * Users without the author capability are rejected.
     */
    public function test_student_cannot_persist_course_link(): void {
        $this->resetAfterTest();
        [$course, , $cm] = $this->setup_activity();

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        update_course::execute((int) $cm->id, 456, 'Nope');
    }
}
