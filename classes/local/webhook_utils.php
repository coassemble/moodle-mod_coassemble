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

/**
 * Pure helpers for webhook signature verification and payload extraction.
 *
 * Kept side-effect free so they are unit-testable without HTTP plumbing.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\local;

/**
 * Webhook verification and payload parsing.
 */
class webhook_utils {
    /** @var int Maximum allowed clock skew for deliveries, in seconds. */
    const MAX_SKEW = 300;

    /**
     * Whether a delivery timestamp is within the accepted window.
     *
     * @param string $timestamp Unix seconds, as sent in X-Coassemble-Timestamp
     * @param int|null $now Current time (defaults to time())
     * @return bool
     */
    public static function timestamp_fresh(string $timestamp, ?int $now = null): bool {
        $now = $now ?? time();
        return abs($now - (int) $timestamp) <= self::MAX_SKEW;
    }

    /**
     * Verify an HMAC signature against a set of candidate secrets.
     *
     * Signature scheme: "sha256=" . HMAC-SHA256("{timestamp}.{raw_body}", secret).
     *
     * @param string $timestamp
     * @param string $raw Raw request body
     * @param string $signature Value of X-Coassemble-Signature
     * @param string[] $secrets Candidate signing secrets
     * @return bool
     */
    public static function verify_signature(string $timestamp, string $raw, string $signature, array $secrets): bool {
        foreach ($secrets as $secret) {
            if (!is_string($secret) || $secret === '') {
                continue;
            }
            $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract the fields the plugin acts on from a decoded delivery payload.
     *
     * Tolerates both flat and data.tracking-nested layouts, and both camelCase
     * and snake_case progress keys.
     *
     * @param array $payload Decoded JSON payload
     * @param string $eventname Value of X-Coassemble-Event
     * @return \stdClass {courseid, identifier, progress, completed, commenced}
     */
    public static function extract_event(array $payload, string $eventname): \stdClass {
        $data = $payload['data'] ?? $payload;
        if (!is_array($data)) {
            $data = [];
        }
        $tracking = (isset($data['tracking']) && is_array($data['tracking'])) ? $data['tracking'] : [];

        $courseid = isset($data['courseId']) ? (int) $data['courseId'] : (int) ($data['course_id'] ?? 0);
        if (!$courseid && isset($data['course']['id'])) {
            $courseid = (int) $data['course']['id'];
        }

        // Delivery payloads nest the learner under data.tracking.identifier.
        $identifier = (string) ($data['identifier'] ?? ($tracking['identifier'] ?? ''));

        $progress = null;
        foreach ([$data, $tracking] as $source) {
            foreach (['progressPercent', 'progress_percent', 'progress'] as $key) {
                if (isset($source[$key]) && is_numeric($source[$key])) {
                    $progress = (float) $source[$key];
                    break 2;
                }
            }
        }

        $completed = ($eventname === 'course.completed');
        $commenced = ($eventname === 'course.commenced');
        if ($progress === null) {
            $progress = $completed ? 100.0 : 0.0;
        }

        return (object) [
            'courseid' => $courseid,
            'identifier' => $identifier,
            'progress' => $progress,
            'completed' => $completed,
            'commenced' => $commenced,
        ];
    }
}
