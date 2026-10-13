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
 * Native workspace library, optionally selecting content for an activity.
 *
 * @package mod_coassemble
 * @copyright 2026 Coassemble
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_coassemble\local\course_library;

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$title = optional_param('title', '', PARAM_TEXT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$remoteid = optional_param('remoteid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$instance = null;
if ($id) {
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'coassemble');
    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);
    require_capability('mod/coassemble:author', $context);
    $instance = $DB->get_record('coassemble', ['id' => $cm->instance], '*', MUST_EXIST);
    $params = ['id' => $id];
} else {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($course->id);
    require_capability('mod/coassemble:addinstance', $context);
    require_capability('mod/coassemble:author', $context);
    $params = ['courseid' => $course->id];
}
$PAGE->set_context($context);
$PAGE->set_url('/mod/coassemble/library.php', $params + ['title' => $title, 'page' => $page]);
$PAGE->set_title(get_string('library', 'mod_coassemble'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');
$client = new \mod_coassemble\api\client();
$error = '';

if ($action !== '') {
    \mod_coassemble\local\action::require_post();
    if (!$instance || !in_array($action, ['link', 'copy'], true)) {
        throw new invalid_parameter_exception('Invalid course selection');
    }
    try {
        course_library::select($instance, $client, $remoteid, $action === 'copy');
        $destination = has_capability('mod/coassemble:manage', $context) ? 'manage.php' : 'view.php';
        redirect(
            new moodle_url('/mod/coassemble/' . $destination, ['id' => $id]),
            get_string('library_selected', 'mod_coassemble')
        );
    } catch (Throwable $e) {
        \mod_coassemble\local\diagnostics::log($e, 'library_select');
        $allowed = ['error_coursechanged', 'library_legacy', 'library_scorm', 'library_deleted', 'library_unavailable'];
        $error = $e instanceof moodle_exception && in_array($e->errorcode, $allowed, true)
            ? get_string($e->errorcode, 'mod_coassemble') : get_string('error_apirequest', 'mod_coassemble');
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('library', 'mod_coassemble'));
$backurl = $instance ? new moodle_url('/mod/coassemble/view.php', ['id' => $id])
    : new moodle_url('/course/view.php', ['id' => $course->id]);
echo $OUTPUT->render_from_template('mod_coassemble/back_link', [
    'url' => $backurl->out(false),
    'label' => get_string($instance ? 'backtoactivity' : 'backtocourse', 'mod_coassemble'),
]);
if ($error !== '') {
    echo $OUTPUT->notification($error, 'error');
}
if (!$client->is_configured()) {
    echo $OUTPUT->notification(get_string('error_notconfigured', 'mod_coassemble'), 'error');
    echo $OUTPUT->footer();
    exit;
}
$canselect = $instance && empty($instance->coassemblecourseid);
echo $OUTPUT->render_from_template('mod_coassemble/library_intro', [
    'intro' => get_string($canselect ? 'library_select_intro' : 'library_browse_intro', 'mod_coassemble'),
]);

if ($remoteid && $canselect) {
    try {
        $remote = $client->get_course($remoteid);
        $reason = course_library::unavailable_reason($remote);
        if ($reason !== '') {
            echo $OUTPUT->notification(get_string($reason, 'mod_coassemble'), 'warning');
        } else {
            echo $OUTPUT->render_from_template('mod_coassemble/library_choice', [
                'title' => $remote['title'] ?? get_string('library_untitled', 'mod_coassemble'),
                'url' => (new moodle_url('/mod/coassemble/library.php'))->out(false),
                'id' => $id,
                'remoteid' => $remoteid,
                'sesskey' => sesskey(),
            ]);
        }
    } catch (Throwable $e) {
        \mod_coassemble\local\diagnostics::log($e, 'library_course');
        echo $OUTPUT->notification(get_string('error_apirequest', 'mod_coassemble'), 'error');
    }
    echo $OUTPUT->render_from_template('mod_coassemble/back_link', [
        'url' => $PAGE->url->out(false), 'label' => get_string('library_back', 'mod_coassemble'),
    ]);
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->render_from_template('mod_coassemble/library_search', [
    'url' => (new moodle_url('/mod/coassemble/library.php'))->out(false),
    'id' => $id, 'courseid' => $course->id, 'title' => $title,
]);
try {
    $courses = course_library::page($client, $title, $page);
} catch (Throwable $e) {
    \mod_coassemble\local\diagnostics::log($e, 'library_list');
    echo $OUTPUT->notification(get_string('error_apirequest', 'mod_coassemble'), 'error');
    echo $OUTPUT->footer();
    exit;
}
$rows = [];
foreach ($courses as $remote) {
    $reason = course_library::unavailable_reason($remote);
    $updated = strtotime($remote['updated'] ?? '') ?: 0;
    $screens = array_filter($remote['screens'] ?? [], static function ($screen) {
        return empty($screen['deleted']);
    });
    $row = [
        'title' => $remote['title'] ?? get_string('library_untitled', 'mod_coassemble'),
        'thumbnail' => clean_param($remote['thumbnail'] ?? '', PARAM_URL),
        'screens' => count($screens),
        'status' => get_string(!empty($remote['published']) ? 'library_published' : 'library_draft', 'mod_coassemble'),
        'updated' => $updated ? userdate($updated) : get_string('unknown'),
    ];
    if ($reason) {
        $row['reason'] = get_string($reason, 'mod_coassemble');
        $row['badge'] = get_string($reason . '_badge', 'mod_coassemble');
    } else if ($canselect) {
        $row['chooseurl'] = (new moodle_url('/mod/coassemble/library.php', $params + [
            'remoteid' => (int) $remote['id'], 'title' => $title, 'page' => $page,
        ]))->out(false);
        $row['chooselabel'] = get_string('library_choose', 'mod_coassemble', $remote['title'] ?? '');
    }
    $rows[] = $row;
}
if ($rows) {
    echo $OUTPUT->render_from_template('mod_coassemble/library_courses', ['courses' => $rows]);
} else {
    echo $OUTPUT->notification(get_string('library_empty', 'mod_coassemble'), 'info');
}
$links = [];
if ($page > 0) {
    $url = new moodle_url('/mod/coassemble/library.php', $params + ['title' => $title, 'page' => $page - 1]);
    $links[] = ['url' => $url->out(false), 'label' => get_string('previous')];
}
if (count($courses) === course_library::PAGE_SIZE) {
    $url = new moodle_url('/mod/coassemble/library.php', $params + ['title' => $title, 'page' => $page + 1]);
    $links[] = ['url' => $url->out(false), 'label' => get_string('next')];
}
echo $OUTPUT->render_from_template('mod_coassemble/toolbar', [
    'class' => 'coassemble-library-pagination',
    'label' => get_string('library_pages', 'mod_coassemble'), 'links' => $links,
]);
echo $OUTPUT->footer();
