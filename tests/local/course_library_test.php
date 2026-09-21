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

#[\PHPUnit\Framework\Attributes\CoversClass(course_library::class)]
/**
 * Workspace browsing and explicit selection tests.
 *
 * @package mod_coassemble
 * @copyright 2026 Coassemble
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\local\course_library
 */
final class course_library_test extends \advanced_testcase {
    /**
     * Search and paging are passed to the API without narrowing to a Moodle author.
     */
    public function test_workspace_query(): void {
        $client = $this->createMock(\mod_coassemble\api\client::class);
        $client->expects($this->once())->method('list_courses')->with([
            'title' => 'Safety', 'page' => 2, 'length' => 25,
        ])->willReturn([['id' => 77]]);
        $this->assertSame([['id' => 77]], course_library::page($client, ' Safety ', 2));
    }

    /**
     * Missing compatibility data fails closed; legacy and SCORM stay distinguishable.
     */
    public function test_eligibility(): void {
        $remote = ['id' => 123, 'type' => 'standard', 'legacy' => false];
        $this->assertSame('', course_library::unavailable_reason($remote));
        $this->assertSame('library_legacy', course_library::unavailable_reason(array_replace($remote, ['legacy' => true])));
        $this->assertSame('library_scorm', course_library::unavailable_reason(array_replace($remote, ['type' => 'scorm'])));
        $this->assertSame('library_deleted', course_library::unavailable_reason(array_replace($remote, ['deleted' => 'today'])));
        $this->assertSame('library_unavailable', course_library::unavailable_reason(['id' => 123]));
    }

    /**
     * Linking preserves the activity name and records that the original is shared.
     */
    public function test_select_original(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('coassemble', ['course' => $course->id, 'name' => 'Moodle title']);
        $client = $this->createMock(\mod_coassemble\api\client::class);
        $client->expects($this->once())->method('get_course')->with(123)->willReturn([
            'id' => 123, 'type' => 'standard', 'legacy' => false, 'title' => 'Workspace title',
        ]);
        $client->expects($this->never())->method('duplicate_course');
        course_library::select($instance, $client, 123, false);
        $record = $DB->get_record('coassemble', ['id' => $instance->id]);
        $this->assertSame('linked', $record->linkmode);
        $this->assertEquals(123, $record->coassemblecourseid);
        $this->assertSame('Moodle title', $record->name);
        $this->expectException(\moodle_exception::class);
        course_library::select($instance, $client, 123, false);
    }

    /**
     * A copy receives this site's author and tenant identifiers and its own origin.
     */
    public function test_copy_stamps_identity(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('coassemble', ['course' => $course->id]);
        $client = $this->createMock(\mod_coassemble\api\client::class);
        $client->method('get_course')->willReturn(['id' => 123, 'type' => 'standard', 'legacy' => false]);
        $client->expects($this->once())->method('duplicate_course')->with(123, [
            'identifier' => identity::for_user($USER), 'clientIdentifier' => identity::client_identifier(),
        ])->willReturn(['id' => 456]);
        $result = course_library::select($instance, $client, 123, true);
        $this->assertSame('copied', $result->linkmode);
        $this->assertSame(456, $result->coassemblecourseid);
    }

    /**
     * A forged request cannot copy an unavailable course.
     */
    public function test_unavailable_course_is_rechecked_on_submit(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('coassemble', ['course' => $course->id]);
        $client = $this->createMock(\mod_coassemble\api\client::class);
        $client->method('get_course')->willReturn(['id' => 123, 'type' => 'scorm', 'legacy' => false]);
        $client->expects($this->never())->method('duplicate_course');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('library_scorm', 'mod_coassemble'));
        course_library::select($instance, $client, 123, true);
    }

    /**
     * Invalid duplicate responses must not attach the original as an owned copy.
     */
    public function test_failed_copy_leaves_activity_unlinked(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('coassemble', ['course' => $course->id]);
        $client = $this->createMock(\mod_coassemble\api\client::class);
        $client->method('get_course')->willReturn(['id' => 123, 'type' => 'standard', 'legacy' => false]);
        $client->method('duplicate_course')->willReturn(['id' => 123]);
        try {
            course_library::select($instance, $client, 123, true);
            $this->fail('Expected invalid duplicate response to be rejected');
        } catch (\moodle_exception $e) {
            $this->assertEmpty($DB->get_field('coassemble', 'coassemblecourseid', ['id' => $instance->id]));
        }
    }
}
