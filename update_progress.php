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
 * Record learner progress from player postMessage events.
 *
 * Client-supplied progress/completed values are ignored. Grades and completion
 * are taken from authoritative Coassemble trackings for the current user.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$cmid = required_param('cmid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'coassemble');
$instance = $DB->get_record('coassemble', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();
require_capability('mod/coassemble:view', context_module::instance($cm->id));

if (isguestuser()) {
    throw new moodle_exception('noguest');
}

if (empty($instance->coassemblecourseid)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Activity is not linked to a Coassemble course']);
    exit;
}

$client = new \mod_coassemble\api\client();
$identifier = \mod_coassemble\local\identity::for_user($USER);
$clientidentifier = \mod_coassemble\local\identity::client_identifier();

try {
    $trackings = $client->list_trackings([
        'id' => (int) $instance->coassemblecourseid,
        'identifier' => $identifier,
        'clientIdentifier' => $clientidentifier,
        'length' => 1,
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

$tracking = null;
foreach ($trackings as $row) {
    if (!is_array($row)) {
        continue;
    }
    if ((string) ($row['identifier'] ?? '') === $identifier) {
        $tracking = $row;
        break;
    }
}

if (!$tracking) {
    // No authoritative progress yet — do not accept client-forged values.
    echo json_encode(['ok' => true, 'synced' => false]);
    exit;
}

$progress = 0.0;
if (isset($tracking['progress_percent'])) {
    $progress = (float) $tracking['progress_percent'];
} else if (isset($tracking['progressPercent'])) {
    $progress = (float) $tracking['progressPercent'];
}
$completed = !empty($tracking['completed']);
$commenced = !empty($tracking['commenced']) || $progress > 0 || $completed;
$score = isset($tracking['score']) && is_numeric($tracking['score']) ? (float) $tracking['score'] : null;
$passed = array_key_exists('passed', $tracking) && $tracking['passed'] !== null ? (bool) $tracking['passed'] : null;

coassemble_record_progress($instance, (int) $USER->id, $progress, $completed, $commenced, $score, $passed);

echo json_encode(['ok' => true, 'synced' => true]);
