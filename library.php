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
echo html_writer::link($backurl, get_string($instance ? 'backtoactivity' : 'backtocourse', 'mod_coassemble'));
if ($error !== '') {
    echo $OUTPUT->notification($error, 'error');
}
if (!$client->is_configured()) {
    echo $OUTPUT->notification(get_string('error_notconfigured', 'mod_coassemble'), 'error');
    echo $OUTPUT->footer();
    exit;
}
$canselect = $instance && empty($instance->coassemblecourseid);
echo html_writer::tag('p', get_string($canselect ? 'library_select_intro' : 'library_browse_intro', 'mod_coassemble'));

if ($remoteid && $canselect) {
    try {
        $remote = $client->get_course($remoteid);
        $reason = course_library::unavailable_reason($remote);
        if ($reason !== '') {
            echo $OUTPUT->notification(get_string($reason, 'mod_coassemble'), 'warning');
        } else {
            echo $OUTPUT->heading(s($remote['title'] ?? get_string('library_untitled', 'mod_coassemble')), 3);
            echo html_writer::start_div('coassemble-library-choice');
            echo html_writer::tag('p', get_string('library_link_help', 'mod_coassemble'));
            echo $OUTPUT->single_button(new moodle_url('/mod/coassemble/library.php', $params + [
                'remoteid' => $remoteid, 'action' => 'link',
            ]), get_string('library_link', 'mod_coassemble'), 'post');
            echo html_writer::end_div();
            echo html_writer::start_div('coassemble-library-choice');
            echo html_writer::tag('p', get_string('library_copy_help', 'mod_coassemble'));
            echo $OUTPUT->single_button(new moodle_url('/mod/coassemble/library.php', $params + [
                'remoteid' => $remoteid, 'action' => 'copy',
            ]), get_string('library_copy', 'mod_coassemble'), 'post');
            echo html_writer::end_div();
        }
    } catch (Throwable $e) {
        \mod_coassemble\local\diagnostics::log($e, 'library_course');
        echo $OUTPUT->notification(get_string('error_apirequest', 'mod_coassemble'), 'error');
    }
    echo html_writer::tag(
        'p',
        html_writer::link($PAGE->url, get_string('library_back', 'mod_coassemble')),
        ['class' => 'coassemble-library-back']
    );
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
$table = new html_table();
$table->head = [get_string('library_thumbnail', 'mod_coassemble'), get_string('name'),
    get_string('library_screens', 'mod_coassemble'), get_string('library_status', 'mod_coassemble'),
    get_string('lastmodified'), get_string('actions')];
$table->data = [];
foreach ($courses as $remote) {
    $reason = course_library::unavailable_reason($remote);
    $thumbnail = clean_param($remote['thumbnail'] ?? '', PARAM_URL);
    $image = $thumbnail ? html_writer::empty_tag('img', [
        'src' => $thumbnail, 'alt' => '', 'width' => 96, 'height' => 54, 'loading' => 'lazy',
        'class' => 'coassemble-library-thumbnail',
    ]) : '';
    $updated = strtotime($remote['updated'] ?? '') ?: 0;
    $screens = array_filter($remote['screens'] ?? [], static function ($screen) {
        return empty($screen['deleted']);
    });
    $selection = '';
    if ($reason) {
        $selection = html_writer::span(
            get_string($reason . '_badge', 'mod_coassemble'),
            'badge coassemble-library-badge'
        ) . ' ' . get_string($reason, 'mod_coassemble');
    }
    if (!$reason && $canselect) {
        $selection = html_writer::link(new moodle_url('/mod/coassemble/library.php', $params + [
            'remoteid' => (int) $remote['id'], 'title' => $title, 'page' => $page,
        ]), get_string('choose'), ['class' => 'btn btn-secondary',
            'aria-label' => get_string('library_choose', 'mod_coassemble', $remote['title'] ?? '')]);
    }
    $row = new html_table_row([$image, s($remote['title'] ?? get_string('library_untitled', 'mod_coassemble')),
        count($screens), get_string(!empty($remote['published']) ? 'library_published' : 'library_draft', 'mod_coassemble'),
        $updated ? userdate($updated) : get_string('unknown'), $selection]);
    if ($reason) {
        $row->attributes['class'] = 'coassemble-library-unavailable';
    }
    $table->data[] = $row;
}
if ($courses) {
    echo html_writer::table($table);
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
