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

#[\PHPUnit\Framework\Attributes\CoversClass(course_metadata::class)]
/**
 * Metadata requests are cached across rows and page loads.
 *
 * @package mod_coassemble
 * @copyright 2026 Coassemble
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\local\course_metadata
 */
final class course_metadata_test extends \advanced_testcase {
    /**
     * Repeated ids and page loads reuse the same summary until invalidated.
     */
    public function test_cache_and_invalidation(): void {
        $this->resetAfterTest();
        $client = $this->createMock(\mod_coassemble\api\client::class);
        $client->method('is_configured')->willReturn(true);
        $client->expects($this->exactly(2))->method('get_course')->with(123)->willReturn([
            'id' => 123, 'title' => 'A course', 'published' => '2026-09-22',
        ]);
        $first = course_metadata::get_many([123, 123, 0, null], $client);
        $this->assertCount(1, $first);
        $this->assertTrue($first[123]['published']);
        $this->assertSame($first, course_metadata::get_many([123], $client));
        course_metadata::invalidate(123);
        $this->assertSame($first, course_metadata::get_many([123], $client));
    }

    /**
     * Changing the configured workspace must not show cached titles from the old one.
     */
    public function test_workspace_isolation(): void {
        $this->resetAfterTest();
        $client = $this->createMock(\mod_coassemble\api\client::class);
        $client->method('is_configured')->willReturn(true);
        $client->expects($this->exactly(2))->method('get_course')->with(123)->willReturnOnConsecutiveCalls(
            ['id' => 123, 'title' => 'Workspace A'],
            ['id' => 123, 'title' => 'Workspace B']
        );
        set_config('apikey', 'workspace-a', 'mod_coassemble');
        $this->assertSame('Workspace A', course_metadata::get_many([123], $client)[123]['title']);
        set_config('apikey', 'workspace-b', 'mod_coassemble');
        $this->assertSame('Workspace B', course_metadata::get_many([123], $client)[123]['title']);
    }

    /**
     * Failures produce an unavailable summary and are not retried for repeated rows.
     */
    public function test_failure_is_cached_without_upstream_details(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $client = $this->createMock(\mod_coassemble\api\client::class);
        $client->method('is_configured')->willReturn(true);
        $client->expects($this->once())->method('get_course')
            ->willThrowException(new \RuntimeException('private upstream details'));
        $this->assertSame([123 => ['available' => false]], course_metadata::get_many([123, 123], $client));
        $this->assertSame([123 => ['available' => false]], course_metadata::get_many([123], $client));
    }
}
