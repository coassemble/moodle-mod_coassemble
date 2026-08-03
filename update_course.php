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
 * Persist Coassemble course id / title from builder postMessage events.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$cmid = required_param('cmid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$title = optional_param('title', '', PARAM_TEXT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'coassemble');
$instance = $DB->get_record('coassemble', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();
require_capability('mod/coassemble:author', context_module::instance($cm->id));

$instance = \mod_coassemble\local\course_link::persist($instance, $courseid, $title);

echo json_encode(['ok' => true, 'courseid' => (int) $instance->coassemblecourseid]);
