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

/**
 * Tests for webhook signature verification and payload extraction.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\local\webhook_utils
 */
final class webhook_utils_test extends \basic_testcase {
    /**
     * Sign a body the way Coassemble does.
     *
     * @param string $timestamp
     * @param string $raw
     * @param string $secret
     * @return string
     */
    private function sign(string $timestamp, string $raw, string $secret): string {
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
    }

    /**
     * Valid signatures verify; tampered bodies and wrong secrets do not.
     */
    public function test_verify_signature(): void {
        $timestamp = '1700000000';
        $raw = '{"id":"abc","data":{}}';
        $secret = str_repeat('s', 64);

        $signature = $this->sign($timestamp, $raw, $secret);

        $this->assertTrue(webhook_utils::verify_signature($timestamp, $raw, $signature, [$secret]));
        $this->assertFalse(webhook_utils::verify_signature($timestamp, $raw . ' ', $signature, [$secret]));
        $this->assertFalse(webhook_utils::verify_signature($timestamp, $raw, $signature, ['othersecret']));
        $this->assertFalse(webhook_utils::verify_signature('1700000001', $raw, $signature, [$secret]));
        $this->assertFalse(webhook_utils::verify_signature($timestamp, $raw, $signature, []));
        $this->assertFalse(webhook_utils::verify_signature($timestamp, $raw, $signature, ['']));
    }

    /**
     * Any matching secret in the candidate set verifies (e.g. during rotation).
     */
    public function test_verify_signature_multiple_secrets(): void {
        $timestamp = '1700000000';
        $raw = '{"id":"abc"}';
        $signature = $this->sign($timestamp, $raw, 'new-secret');

        $this->assertTrue(webhook_utils::verify_signature(
            $timestamp,
            $raw,
            $signature,
            ['old-secret', 'new-secret']
        ));
    }

    /**
     * Timestamp freshness window.
     */
    public function test_timestamp_fresh(): void {
        $now = 1700000000;
        $this->assertTrue(webhook_utils::timestamp_fresh((string) $now, $now));
        $this->assertTrue(webhook_utils::timestamp_fresh((string) ($now - 299), $now));
        $this->assertTrue(webhook_utils::timestamp_fresh((string) ($now + 299), $now));
        $this->assertFalse(webhook_utils::timestamp_fresh((string) ($now - 301), $now));
        $this->assertFalse(webhook_utils::timestamp_fresh((string) ($now + 301), $now));
        $this->assertFalse(webhook_utils::timestamp_fresh('', $now));
    }

    /**
     * The real delivery payload shape: course + learner nested under data.
     */
    public function test_extract_event_real_payload_shape(): void {
        $payload = [
            'id' => '3f2f5f1e-0000-0000-0000-000000000000',
            'type' => 'course.completed',
            'occurredAt' => '2026-07-22T00:00:00.000Z',
            'workspaceId' => 42,
            'data' => [
                'course' => ['id' => 123, 'title' => 'Safety 101', 'key' => 'abc', 'clientIdentifier' => 'moodle-course:2'],
                'tracking' => [
                    'id' => 555,
                    'identifier' => 'moodle:abcdef123456:7',
                    'email' => null,
                    'commenced' => '2026-07-21T00:00:00.000Z',
                    'completed' => '2026-07-22T00:00:00.000Z',
                    'totalTime' => 120,
                ],
            ],
        ];

        $event = webhook_utils::extract_event($payload, 'course.completed');

        $this->assertSame(123, $event->courseid);
        $this->assertSame('moodle:abcdef123456:7', $event->identifier);
        $this->assertTrue($event->completed);
        $this->assertFalse($event->commenced);
        // No progress in the payload: completed implies 100.
        $this->assertSame(100.0, $event->progress);
    }

    /**
     * Flat and snake_case layouts are tolerated.
     */
    public function test_extract_event_variants(): void {
        $event = webhook_utils::extract_event([
            'courseId' => 9,
            'identifier' => 'moodle:abcdef123456:3',
            'progress_percent' => '55.5',
        ], 'course.commenced');
        $this->assertSame(9, $event->courseid);
        $this->assertSame(55.5, $event->progress);
        $this->assertTrue($event->commenced);
        $this->assertFalse($event->completed);

        $event = webhook_utils::extract_event([
            'data' => [
                'course_id' => 10,
                'tracking' => ['identifier' => 'x', 'progressPercent' => 12],
            ],
        ], 'course.commenced');
        $this->assertSame(10, $event->courseid);
        $this->assertSame(12.0, $event->progress);

        // Unmapped event with nothing useful.
        $event = webhook_utils::extract_event(['data' => []], 'course.created');
        $this->assertSame(0, $event->courseid);
        $this->assertSame('', $event->identifier);
        $this->assertSame(0.0, $event->progress);
    }
}
