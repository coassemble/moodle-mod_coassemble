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
}
