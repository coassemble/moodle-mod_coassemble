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
    $rows = [];
    foreach ($users as $user) {
        $url = new moodle_url('/mod/coassemble/analytics.php', [
            'id' => $cm->id,
            'kind' => 'user',
            'userid' => $user->id,
        ]);
        $rows[] = [
            'name' => fullname($user),
            'email' => $user->email,
            'url' => $url->out(false),
        ];
    }
    if (!$rows) {
        echo $OUTPUT->notification(get_string('analytics_nousers', 'mod_coassemble'), 'info');
    } else {
        echo $OUTPUT->render_from_template('mod_coassemble/analytics_users', ['users' => $rows]);
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
    \mod_coassemble\local\diagnostics::log($e, 'analytics');
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('error_apirequest', 'mod_coassemble'), 'error');
    echo $OUTPUT->footer();
    exit;
}

$PAGE->requires->js_call_amd('mod_coassemble/embed', 'init', [[
    'iframeId' => 'coassemble-embed-frame',
    'expectedOrigin' => coassemble_embed_origin($url),
    'mode' => 'analytics',
    'cmid' => (int) $cm->id,
    'statusElId' => 'coassemble-session-status',
    'strings' => [
        'error' => get_string('session_error', 'mod_coassemble'),
        'expired' => get_string('session_expired', 'mod_coassemble'),
    ],
    'sesskey' => sesskey(),
]]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('nav_analytics', 'mod_coassemble'));
echo $OUTPUT->render_from_template('mod_coassemble/toolbar', [
    'label' => get_string('nav_analytics', 'mod_coassemble'),
    'links' => [
        [
            'url' => (new moodle_url('/mod/coassemble/view.php', ['id' => $cm->id]))->out(false),
            'label' => get_string('backtoactivity', 'mod_coassemble'),
            'exit' => true,
        ],
        [
            'url' => (new moodle_url('/mod/coassemble/analytics.php', ['id' => $cm->id, 'kind' => 'course']))->out(false),
            'label' => get_string('analytics_course', 'mod_coassemble'),
            'current' => $kind === 'course',
        ],
        [
            'url' => (new moodle_url('/mod/coassemble/analytics.php', ['id' => $cm->id, 'kind' => 'user']))->out(false),
            'label' => get_string('analytics_pick_user', 'mod_coassemble'),
            'current' => $kind === 'user',
        ],
    ],
]);
echo $OUTPUT->render_from_template('mod_coassemble/embed', [
    'url' => $url,
    'title' => get_string('analytics_iframe_title', 'mod_coassemble'),
    'allow' => 'clipboard-write; fullscreen',
    'shellclass' => '',
]);
echo $OUTPUT->footer();
