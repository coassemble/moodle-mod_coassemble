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
 * Teacher reconciliation: pull trackings from Headless into Moodle grades.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'coassemble');
$instance = $DB->get_record('coassemble', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/coassemble:manage', $context);

if (empty($instance->coassemblecourseid)) {
    redirect(
        new moodle_url('/mod/coassemble/manage.php', ['id' => $cm->id]),
        get_string('error_nocourseyet', 'mod_coassemble'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$client = new \mod_coassemble\api\client();
$clientidentifier = \mod_coassemble\local\identity::client_identifier();

$updated = 0;
try {
    $trackings = $client->list_trackings([
        'id' => (int) $instance->coassemblecourseid,
        'clientIdentifier' => $clientidentifier,
        'length' => 500,
    ]);
    foreach ($trackings as $tracking) {
        if (!is_array($tracking)) {
            continue;
        }
        $identifier = (string) ($tracking['identifier'] ?? '');
        $userid = \mod_coassemble\local\identity::moodle_user_id_from_identifier($identifier);
        if (!$userid) {
            continue;
        }
        if (!is_enrolled($context, $userid, '', true)) {
            continue;
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
        coassemble_record_progress($instance, $userid, $progress, $completed, $commenced, $score, $passed);
        $updated++;
    }
} catch (Throwable $e) {
    redirect(
        new moodle_url('/mod/coassemble/manage.php', ['id' => $cm->id]),
        $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

redirect(
    new moodle_url('/mod/coassemble/manage.php', ['id' => $cm->id]),
    get_string('refresh_progress_ok', 'mod_coassemble', $updated)
);
