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

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_coassemble\local\diagnostics::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_coassemble\event\api_request_failed::class)]
/**
 * Ensure service diagnostics cannot disclose upstream payloads or credentials.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\local\diagnostics
 * @covers \mod_coassemble\event\api_request_failed
 */
final class diagnostics_test extends \advanced_testcase {
    /**
     * Log the failure without the untrusted message or trace, even with debugging enabled.
     */
    public function test_exception_details_are_not_disclosed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEvents();
        ob_start();
        diagnostics::log(new \RuntimeException('secret-token <script>alert(1)</script>'), 'resolve');
        $output = ob_get_clean();
        $this->assertSame('', $output);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(\mod_coassemble\event\api_request_failed::class, $event);
        $this->assertSame('resolve', $event->other['operation']);
        $this->assertSame('RuntimeException', $event->other['exception']);
        $this->assertStringNotContainsString('secret-token', json_encode($event->get_data()));
        $this->assertStringNotContainsString('<script>', $event->get_description());
    }

    /**
     * HTTP status and transport codes remain available to administrators.
     */
    public function test_http_diagnostics_are_recorded(): void {
        $this->resetAfterTest();
        $sink = $this->redirectEvents();
        diagnostics::record('POST /api/v1/headless/embed/course', '', 403, 0);
        $events = $sink->get_events();
        $this->assertSame(403, $events[0]->other['httpstatus']);
        $this->assertStringContainsString('HTTP status: 403', $events[0]->get_description());
    }
}
