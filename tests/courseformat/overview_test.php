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

namespace mod_coassemble\courseformat;

use core_courseformat\local\overview\overviewfactory;
use mod_coassemble\api\client;

#[\PHPUnit\Framework\Attributes\CoversClass(overview::class)]
/**
 * Activity overview metadata is cached and restricted to activity managers.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\courseformat\overview
 */
final class overview_test extends \advanced_testcase {
    /**
     * The overview API was introduced in Moodle 5.1.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists(\core_courseformat\activityoverviewbase::class)) {
            $this->markTestSkipped('Activities overview requires Moodle 5.1 or later.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Repeated activities and columns fetch each remote course only once.
     */
    public function test_metadata_is_cached_and_escaped(): void {
        $course = $this->getDataGenerator()->create_course();
        $client = $this->createMock(client::class);
        $client->method('is_configured')->willReturn(true);
        $client->expects($this->once())->method('get_course')->with(123)->willReturn([
            'id' => 123, 'title' => '<b>Remote title</b>', 'published' => '2026-09-22',
        ]);
        foreach (['created', 'copied', 'linked'] as $mode) {
            $activity = $this->getDataGenerator()->create_module('coassemble', [
                'course' => $course->id, 'coassemblecourseid' => 123, 'linkmode' => $mode,
            ]);
            $cm = get_fast_modinfo($course)->get_cm($activity->cmid);
            $overview = new overview($cm, $client);
            foreach ([1, 2] as $iteration) {
                $items = $overview->get_extra_overview_items();
                $this->assertSame('<b>Remote title</b>', $items['title']->get_value());
                $this->assertSame('&lt;b&gt;Remote title&lt;/b&gt;', $items['title']->get_content());
                $this->assertSame(get_string('library_published', 'mod_coassemble'), $items['status']->get_value());
                $this->assertSame(get_string('origin_' . $mode, 'mod_coassemble'), $items['relationship']->get_value());
            }
            $this->assertStringContainsString(
                '/mod/coassemble/report.php?id=' . $cm->id,
                $overview->get_actions_overview()->get_content()
            );
        }
    }

    /**
     * A learner or author without manage permission never requests remote data.
     */
    public function test_metadata_requires_manage_in_the_activity(): void {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('coassemble', [
            'course' => $course->id, 'coassemblecourseid' => 123,
        ]);
        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('get_course');
        foreach (['student', 'editingteacher'] as $rolename) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id, $rolename);
            if ($rolename === 'editingteacher') {
                $roles = get_archetype_roles($rolename);
                $role = reset($roles);
                assign_capability(
                    'mod/coassemble:manage',
                    CAP_PROHIBIT,
                    $role->id,
                    \context_module::instance($activity->cmid)->id
                );
            }
            $this->setUser($user);
            $cm = get_fast_modinfo($course)->get_cm($activity->cmid);
            $overview = new overview($cm, $client);
            $this->assertSame([], $overview->get_extra_overview_items());
            $this->assertNull($overview->get_actions_overview());
        }
    }

    /**
     * Moodle can construct the integration and render an unlinked activity.
     */
    public function test_factory_and_unlinked_activity(): void {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('coassemble', ['course' => $course->id]);
        $overview = overviewfactory::create(get_fast_modinfo($course)->get_cm($activity->cmid));
        $this->assertInstanceOf(overview::class, $overview);
        $items = $overview->get_extra_overview_items();
        $this->assertSame(get_string('notyetlinked', 'mod_coassemble'), $items['title']->get_value());
        $this->assertSame('', $items['status']->get_value());
        $this->assertSame('', $items['relationship']->get_value());
    }

    /**
     * Drafts and unavailable metadata are distinguishable without leaking API errors.
     */
    public function test_draft_and_unavailable_metadata(): void {
        $course = $this->getDataGenerator()->create_course();
        $client = $this->createMock(client::class);
        $client->method('is_configured')->willReturn(true);
        $client->expects($this->exactly(2))->method('get_course')->willReturnCallback(function ($id) {
            if ($id === 123) {
                return ['id' => 123, 'title' => 'Draft course'];
            }
            throw new \RuntimeException('Private API error');
        });
        $this->redirectEvents();
        foreach ([123, 456, 456] as $id) {
            $activity = $this->getDataGenerator()->create_module('coassemble', [
                'course' => $course->id, 'coassemblecourseid' => $id, 'linkmode' => 'linked',
            ]);
            $overview = new overview(get_fast_modinfo($course)->get_cm($activity->cmid), $client);
            $items = $overview->get_extra_overview_items();
            $this->assertSame(
                $id === 123 ? 'Draft course' : get_string('metadata_unavailable', 'mod_coassemble'),
                $items['title']->get_value()
            );
            $this->assertSame($id === 123 ? get_string('library_draft', 'mod_coassemble') : '', $items['status']->get_value());
        }
    }
}
