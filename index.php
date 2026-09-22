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
 * List all Coassemble activities in a course.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/coassemble/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_coassemble'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_coassemble'));

$modinfo = get_fast_modinfo($course);
$cms = $modinfo->get_instances_of('coassemble');
if (!$cms) {
    notice(
        get_string('thereareno', 'moodle', get_string('modulenameplural', 'mod_coassemble')),
        new moodle_url('/course/view.php', ['id' => $course->id])
    );
    exit;
}

$instances = coassemble_get_instances($course->id);
$managed = [];
$courseids = [];
foreach ($cms as $cm) {
    if ($cm->uservisible && has_capability('mod/coassemble:manage', context_module::instance($cm->id))) {
        $managed[$cm->id] = true;
        $courseids[] = $instances[$cm->instance]->coassemblecourseid ?? null;
    }
}
$metadata = $managed ? \mod_coassemble\local\course_metadata::get_many($courseids, new \mod_coassemble\api\client()) : [];
$table = new html_table();
$table->head = [get_string('name')];
if ($managed) {
    $table->head = array_merge($table->head, [get_string('manage_remote_title', 'mod_coassemble'),
        get_string('library_status', 'mod_coassemble'), get_string('linkmode', 'mod_coassemble'), get_string('actions')]);
}
$table->data = [];
foreach ($cms as $cm) {
    if (!$cm->uservisible) {
        continue;
    }
    $instance = $instances[$cm->instance] ?? null;
    if (!$instance) {
        continue;
    }
    $row = [
        html_writer::link(new moodle_url('/mod/coassemble/view.php', ['id' => $cm->id]), format_string($instance->name)),
    ];
    if ($managed) {
        if (isset($managed[$cm->id])) {
            $remote = $metadata[$instance->coassemblecourseid] ?? [];
            $title = get_string('notyetlinked', 'mod_coassemble');
            $status = '';
            $origin = '';
            if (!empty($instance->coassemblecourseid)) {
                $title = !empty($remote['available']) ? s($remote['title']) : get_string('metadata_unavailable', 'mod_coassemble');
                $status = !empty($remote['available'])
                    ? get_string($remote['published'] ? 'library_published' : 'library_draft', 'mod_coassemble') : '';
                $origin = get_string('origin_' . $instance->linkmode, 'mod_coassemble');
            }
            $row = array_merge($row, [$title, $status, $origin, html_writer::link(
                new moodle_url('/mod/coassemble/report.php', ['id' => $cm->id]),
                get_string('nav_report', 'mod_coassemble')
            )]);
        } else {
            $row = array_merge($row, ['', '', '', '']);
        }
    }
    $table->data[] = $row;
}
echo html_writer::table($table);
if ($managed) {
    echo html_writer::tag('p', get_string('metadata_cache_help', 'mod_coassemble'), ['class' => 'small']);
}
echo $OUTPUT->footer();
