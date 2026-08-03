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
 * Teacher progress report for a Coassemble activity.
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

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/coassemble:manage', $context);

$PAGE->set_url('/mod/coassemble/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('nav_report', 'mod_coassemble'));
$PAGE->set_heading(format_string($course->fullname));

$tracks = $DB->get_records('coassemble_track', ['coassembleid' => $instance->id], 'timemodified DESC');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('nav_report', 'mod_coassemble'));

echo html_writer::div(
    $OUTPUT->single_button(
        new moodle_url('/mod/coassemble/refresh_progress.php', ['id' => $cm->id, 'sesskey' => sesskey()]),
        get_string('refresh_progress', 'mod_coassemble'),
        'post'
    ),
    'mb-3'
);

$table = new html_table();
$table->head = [
    get_string('fullname'),
    get_string('progress', 'mod_coassemble'),
    get_string('score', 'mod_coassemble'),
    get_string('commenced', 'mod_coassemble'),
    get_string('completed', 'mod_coassemble'),
    get_string('lastmodified'),
    get_string('nav_analytics', 'mod_coassemble'),
];
$table->data = [];

foreach ($tracks as $track) {
    $user = $DB->get_record('user', ['id' => $track->userid, 'deleted' => 0]);
    if (!$user) {
        continue;
    }
    $analyticsurl = new moodle_url('/mod/coassemble/analytics.php', [
        'id' => $cm->id,
        'kind' => 'user',
        'userid' => $user->id,
    ]);
    $scorecell = '—';
    if ($track->score !== null) {
        $scorecell = format_float((float) $track->score, 1) . '%';
        if ($track->passed !== null) {
            $scorecell .= ' (' . ($track->passed ? get_string('yes') : get_string('no')) . ')';
        }
    }
    $table->data[] = [
        fullname($user),
        format_float((float) $track->progress, 1) . '%',
        $scorecell,
        $track->commenced ? userdate($track->commenced) : '—',
        $track->completed ? userdate($track->completed) : '—',
        userdate($track->timemodified),
        html_writer::link($analyticsurl, get_string('analytics_user', 'mod_coassemble')),
    ];
}

if (!$table->data) {
    echo $OUTPUT->notification(get_string('report_empty', 'mod_coassemble'), 'info');
} else {
    echo html_writer::table($table);
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/mod/coassemble/view.php', ['id' => $cm->id]),
        get_string('backtoactivity', 'mod_coassemble'),
        ['class' => 'btn btn-link']
    ),
    'mt-3'
);

echo $OUTPUT->footer();
