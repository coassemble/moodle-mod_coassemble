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
 * Coassemble webhook receiver (course.commenced / course.completed).
 *
 * Configure this URL in the Coassemble workspace webhook settings.
 * Signature: HMAC SHA-256 over "{timestamp}.{raw_body}" using the webhook secret.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$raw = file_get_contents('php://input');
$eventname = $_SERVER['HTTP_X_COASSEMBLE_EVENT'] ?? '';
$deliveryid = $_SERVER['HTTP_X_COASSEMBLE_DELIVERY'] ?? '';
$timestamp = $_SERVER['HTTP_X_COASSEMBLE_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_COASSEMBLE_SIGNATURE'] ?? '';

header('Content-Type: application/json');

if ($deliveryid === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing delivery id header']);
    exit;
}

// Deliveries are verified when a signing secret is configured. Without one
// (e.g. before "Register webhook automatically" has run) deliveries are
// accepted unauthenticated so the integration still works out of the box.
$secret = (string) get_config('mod_coassemble', 'webhooksecret');
if ($secret !== '') {
    if ($timestamp === '' || $signature === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing signature headers']);
        exit;
    }

    // Reject stale timestamps (> 5 minutes).
    if (!\mod_coassemble\local\webhook_utils::timestamp_fresh($timestamp)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Stale timestamp']);
        exit;
    }

    if (!\mod_coassemble\local\webhook_utils::verify_signature($timestamp, $raw, $signature, [$secret])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Invalid signature']);
        exit;
    }
}

if ($DB->record_exists('coassemble_webhook', ['deliveryid' => $deliveryid])) {
    echo json_encode(['ok' => true, 'duplicate' => true]);
    exit;
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$event = \mod_coassemble\local\webhook_utils::extract_event($payload, $eventname);

$userid = \mod_coassemble\local\identity::moodle_user_id_from_identifier($event->identifier);
if (!$event->courseid || !$userid) {
    // Acknowledged but not mapped — record delivery so retries stop.
    $DB->insert_record('coassemble_webhook', (object) [
        'deliveryid' => $deliveryid,
        'eventname' => $eventname,
        'timecreated' => time(),
    ]);
    echo json_encode(['ok' => true, 'mapped' => false]);
    exit;
}

$instances = $DB->get_records('coassemble', ['coassemblecourseid' => $event->courseid]);

$applied = 0;
foreach ($instances as $instance) {
    // Only mirror progress into Moodle courses the learner is enrolled in.
    $coursecontext = context_course::instance($instance->course, IGNORE_MISSING);
    if (!$coursecontext || !is_enrolled($coursecontext, $userid)) {
        continue;
    }
    coassemble_record_progress($instance, $userid, $event->progress, $event->completed, $event->commenced);
    $applied++;
}

// Record delivery only after progress was applied so failed attempts can retry.
try {
    $DB->insert_record('coassemble_webhook', (object) [
        'deliveryid' => $deliveryid,
        'eventname' => $eventname,
        'timecreated' => time(),
    ]);
} catch (dml_exception $e) {
    // Concurrent delivery of the same id already recorded after applying progress.
    debugging('Duplicate webhook delivery insert: ' . $e->getMessage(), DEBUG_DEVELOPER);
}

echo json_encode(['ok' => true, 'mapped' => true, 'instances' => $applied]);
