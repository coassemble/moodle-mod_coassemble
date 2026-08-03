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
 * Teacher analytics embed for a linked Coassemble course.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$kind = optional_param('kind', 'course', PARAM_ALPHA); // One of: course or user.
$userid = optional_param('userid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'coassemble');
$instance = $DB->get_record('coassemble', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/coassemble:viewanalytics', $context);

$PAGE->set_url('/mod/coassemble/analytics.php', ['id' => $cm->id, 'kind' => $kind, 'userid' => $userid]);
$PAGE->set_title(get_string('nav_analytics', 'mod_coassemble'));
$PAGE->set_heading(format_string($course->fullname));

if (empty($instance->coassemblecourseid)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('error_nocourseyet', 'mod_coassemble'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$client = new \mod_coassemble\api\client();
$identifier = \mod_coassemble\local\identity::for_user($USER);
$clientidentifier = \mod_coassemble\local\identity::client_identifier();

// User picker (enrolled students + anyone already tracked).
if ($kind === 'user' && !$userid) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('analytics_pick_user', 'mod_coassemble'));

    $contextcourse = context_course::instance($course->id);
    $users = get_enrolled_users($contextcourse, 'mod/coassemble:view', 0, 'u.*', null, 0, 200);
    $table = new html_table();
    $table->head = [get_string('fullname'), get_string('email'), ''];
    $table->data = [];
    foreach ($users as $user) {
        $url = new moodle_url('/mod/coassemble/analytics.php', [
            'id' => $cm->id,
            'kind' => 'user',
            'userid' => $user->id,
        ]);
        $table->data[] = [
            fullname($user),
            $user->email,
            html_writer::link($url, get_string('analytics_user', 'mod_coassemble'), ['class' => 'btn btn-secondary btn-sm']),
        ];
    }
    if (!$table->data) {
        echo $OUTPUT->notification(get_string('analytics_nousers', 'mod_coassemble'), 'info');
    } else {
        echo html_writer::table($table);
    }
    echo $OUTPUT->footer();
    exit;
}

try {
    if ($kind === 'user') {
        $target = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        if (!is_enrolled($context, $target, '', true)) {
            throw new moodle_exception('usernotincourse');
        }
        $url = $client->issue_analytics_embed('user', [
            'identifier' => \mod_coassemble\local\identity::for_user($target),
            'clientIdentifier' => $clientidentifier,
            'name' => \mod_coassemble\local\identity::display_name($target),
            'options' => ['stats' => true, 'table' => true],
        ]);
    } else {
        $url = $client->issue_analytics_embed('course', [
            'courseId' => (int) $instance->coassemblecourseid,
            'identifier' => $identifier,
            'clientIdentifier' => $clientidentifier,
            'options' => ['stats' => true, 'chart' => true, 'table' => true],
        ]);
    }
} catch (Throwable $e) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo $OUTPUT->footer();
    exit;
}

$PAGE->requires->js_call_amd('mod_coassemble/embed', 'init', [[
    'iframeId' => 'coassemble-embed-frame',
    'expectedOrigin' => coassemble_embed_origin($url),
    'mode' => 'analytics',
    'cmid' => (int) $cm->id,
    'sesskey' => sesskey(),
    'statusElId' => 'coassemble-session-status',
]]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('nav_analytics', 'mod_coassemble'));
echo html_writer::div(
    html_writer::link(
        new moodle_url('/mod/coassemble/analytics.php', ['id' => $cm->id, 'kind' => 'course']),
        get_string('analytics_course', 'mod_coassemble'),
        ['class' => 'btn btn-secondary mr-1']
    ) . ' ' .
    html_writer::link(
        new moodle_url('/mod/coassemble/analytics.php', ['id' => $cm->id, 'kind' => 'user']),
        get_string('analytics_pick_user', 'mod_coassemble'),
        ['class' => 'btn btn-secondary']
    ),
    'mb-2'
);
echo html_writer::div('', 'coassemble-session-status', ['id' => 'coassemble-session-status']);
echo html_writer::start_div('coassemble-embed-shell');
echo html_writer::tag('iframe', '', [
    'id' => 'coassemble-embed-frame',
    'src' => $url,
    'class' => 'coassemble-embed-iframe',
    'allow' => 'clipboard-write; fullscreen',
    'title' => get_string('analytics_iframe_title', 'mod_coassemble'),
]);
echo html_writer::end_div();
echo $OUTPUT->footer();
