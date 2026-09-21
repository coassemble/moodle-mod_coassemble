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
 * View / launch page for a Coassemble activity.
 *
 * Teachers with author capability land in the builder when creating/editing.
 * Learners only get the player once a Coassemble course id is linked.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$mode = optional_param('mode', '', PARAM_ALPHA); // One of: edit, view, create or collection.
$flow = optional_param('flow', '', PARAM_ALPHANUMEXT);
$resolve = optional_param('resolve', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'coassemble');
$instance = $DB->get_record('coassemble', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/coassemble:view', $context);

$canauthor = has_capability('mod/coassemble:author', $context);
$hascourse = !empty($instance->coassemblecourseid);
$hascollection = !empty($instance->collectionid);

if ($resolve) {
    require_capability('mod/coassemble:author', $context);
    \mod_coassemble\local\action::require_post();
}

if ($mode === '') {
    if ($canauthor && !$hascourse && !$hascollection) {
        $mode = 'edit';
    } else if ($canauthor && optional_param('edit', 0, PARAM_BOOL)) {
        $mode = 'edit';
    } else if ($hascollection && !$hascourse) {
        $mode = 'collection';
    } else {
        $mode = 'view';
    }
}

if ($mode === 'create') {
    $mode = 'edit';
}

$isedit = ($mode === 'edit');
$PAGE->set_url('/mod/coassemble/view.php', ['id' => $cm->id, 'mode' => $mode]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

if (!$isedit) {
    $event = \mod_coassemble\event\course_module_viewed::create([
        'objectid' => $instance->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('coassemble', $instance);
    $event->trigger();
}

$completion = new completion_info($course);
if ($completion->is_enabled($cm) && !$isedit) {
    $completion->set_module_viewed($cm);
}

$client = new \mod_coassemble\api\client();
if (!$client->is_configured()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('error_notconfigured', 'mod_coassemble'), 'error');
    if (has_capability('moodle/site:config', context_system::instance())) {
        echo $OUTPUT->single_button(
            new moodle_url('/admin/settings.php', ['section' => 'modsettingcoassemble']),
            get_string('settings_testconnection_link', 'mod_coassemble'),
            'get'
        );
    }
    echo $OUTPUT->footer();
    exit;
}

$identifier = \mod_coassemble\local\identity::for_user($USER);
$clientidentifier = \mod_coassemble\local\identity::client_identifier();
$displayname = \mod_coassemble\local\identity::display_name($USER);
$avatar = \mod_coassemble\local\identity::avatar_url($USER);

if ($resolve && $canauthor && !$hascourse) {
    try {
        $instance = \mod_coassemble\local\course_link::resolve_from_api(
            $instance,
            $client,
            $identifier,
            $clientidentifier
        );
    } catch (Throwable $e) {
        \mod_coassemble\local\diagnostics::log($e, 'resolve');
        redirect(
            new moodle_url('/mod/coassemble/manage.php', ['id' => $cm->id]),
            get_string('error_apirequest', 'mod_coassemble'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $hascourse = !empty($instance->coassemblecourseid);
    if ($hascourse) {
        redirect(new moodle_url('/mod/coassemble/view.php', ['id' => $cm->id, 'mode' => 'view']));
    }
}


// In singleactivity format the course page is this activity itself, so
// "back" must leave the course entirely.
if ($course->format === 'singleactivity') {
    $exiturl = new moodle_url('/my/');
    $exitlabel = get_string('myhome');
} else {
    $exiturl = new moodle_url('/course/view.php', ['id' => $course->id]);
    $exitlabel = get_string('backtocourse', 'mod_coassemble');
}

// Collection player path (optional activity-level collection).
if ($mode === 'collection') {
    if (!$hascollection) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('error_nocollection', 'mod_coassemble'), 'info');
        echo $OUTPUT->footer();
        exit;
    }
    $action = ($canauthor && optional_param('edit', 0, PARAM_BOOL)) ? 'edit' : 'view';
    if ($action === 'edit') {
        require_capability('mod/coassemble:author', $context);
    }
    try {
        $url = $client->issue_collection_embed([
            'action' => $action,
            'collectionId' => (int) $instance->collectionid,
            'identifier' => $identifier,
            'clientIdentifier' => $clientidentifier,
            'name' => $displayname,
            'options' => ['back' => 'event'],
        ]);
    } catch (Throwable $e) {
        \mod_coassemble\local\diagnostics::log($e, 'view');
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('error_apirequest', 'mod_coassemble'), 'error');
        echo $OUTPUT->footer();
        exit;
    }
    $PAGE->requires->js_call_amd('mod_coassemble/embed', 'init', [[
        'iframeId' => 'coassemble-embed-frame',
        'expectedOrigin' => coassemble_embed_origin($url),
        'mode' => $action === 'edit' ? 'edit' : 'view',
        'cmid' => (int) $cm->id,
        'backUrl' => $exiturl->out(false),
        'statusElId' => 'coassemble-session-status',
        'strings' => [
            'ready' => get_string('session_ready', 'mod_coassemble'),
            'error' => get_string('session_error', 'mod_coassemble'),
            'expired' => get_string('session_expired', 'mod_coassemble'),
        ],
    ]]);
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_coassemble/toolbar', [
        'label' => get_string('modulename', 'mod_coassemble'),
        'links' => [['url' => $exiturl->out(false), 'label' => $exitlabel, 'exit' => true]],
    ]);
    echo $OUTPUT->render_from_template('mod_coassemble/embed', [
        'url' => $url,
        'title' => get_string('collection_iframe_title', 'mod_coassemble'),
        'allow' => 'clipboard-write; fullscreen; microphone; camera',
        'shellclass' => 'coassemble-embed-shell--view',
    ]);
    echo $OUTPUT->footer();
    exit;
}

if ($mode === 'edit') {
    require_capability('mod/coassemble:author', $context);

    $options = [
        'back' => 'event',
        'legacy' => false,
        'ai' => true,
        'googleDrive' => true,
        'oneDrive' => true,
        'feedback' => true,
        // No in-builder publishing: Moodle's Manage content page owns the
        // publish/revert lifecycle (API default is already false).
    ];

    $selectedflow = $flow !== '' ? $flow : (string) $instance->flow;
    if ($selectedflow !== '' && empty($instance->coassemblecourseid)) {
        $options['flow'] = $selectedflow;
    }

    $body = [
        'action' => 'edit',
        'identifier' => $identifier,
        'clientIdentifier' => $clientidentifier,
        'name' => $displayname,
        'options' => $options,
    ];
    if (!empty($instance->coassemblecourseid)) {
        $body['courseId'] = (int) $instance->coassemblecourseid;
    }
    if (!empty($instance->themeid)) {
        $body['themeId'] = (int) $instance->themeid;
    }
    if (!empty($instance->language)) {
        $body['options']['language'] = $instance->language;
    }

    try {
        $embed = $client->issue_course_embed($body);
    } catch (Throwable $e) {
        \mod_coassemble\local\diagnostics::log($e, 'view');
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('error_apirequest', 'mod_coassemble'), 'error');
        echo $OUTPUT->footer();
        exit;
    }

    if (empty($instance->coassemblecourseid) && !empty($embed['courseid'])) {
        $instance = \mod_coassemble\local\course_link::persist($instance, (int) $embed['courseid'], '', 'created');
        $hascourse = true;
    }

    $backurl = new moodle_url('/mod/coassemble/view.php', ['id' => $cm->id, 'mode' => 'view']);
    $PAGE->requires->js_call_amd('mod_coassemble/embed', 'init', [[
        'iframeId' => 'coassemble-embed-frame',
        'expectedOrigin' => coassemble_embed_origin($embed['url']),
        'mode' => 'edit',
        'cmid' => (int) $cm->id,
        'backUrl' => $backurl->out(false),
        'resolveFormId' => !$hascourse ? 'coassemble-resolve-form' : '',
        'persistCourse' => true,
        'statusElId' => 'coassemble-session-status',
        'strings' => [
            'ready' => get_string('session_ready', 'mod_coassemble'),
            'error' => get_string('session_error', 'mod_coassemble'),
            'expired' => get_string('session_expired', 'mod_coassemble'),
        ],
    ]]);

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_coassemble/toolbar', [
        'label' => get_string('modulename', 'mod_coassemble'),
        'links' => [[
            'url' => $hascourse ? $backurl->out(false) : $exiturl->out(false),
            'label' => $hascourse ? get_string('backtoactivity', 'mod_coassemble') : $exitlabel,
            'exit' => true,
        ]],
    ]);

    $createflows = [
        '' => get_string('flow_scratch', 'mod_coassemble'),
        'ai' => get_string('flow_ai', 'mod_coassemble'),
    ];
    if (empty($instance->coassemblecourseid)) {
        $flowlinks = [];
        foreach ($createflows as $fkey => $flabel) {
            $furl = new moodle_url('/mod/coassemble/view.php', [
                'id' => $cm->id,
                'mode' => 'edit',
                'flow' => $fkey,
            ]);
            $flowlinks[] = [
                'url' => $furl->out(false),
                'label' => $flabel,
                'primary' => $selectedflow === $fkey,
                'current' => $selectedflow === $fkey,
            ];
        }
        echo $OUTPUT->render_from_template('mod_coassemble/toolbar', [
            'label' => get_string('flow', 'mod_coassemble'),
            'class' => 'coassemble-flow-bar',
            'links' => $flowlinks,
        ]);
    }

    echo $OUTPUT->render_from_template('mod_coassemble/embed', [
        'url' => $embed['url'],
        'title' => get_string('builder_iframe_title', 'mod_coassemble'),
        'allow' => 'clipboard-write; fullscreen; microphone; camera',
        'shellclass' => 'coassemble-embed-shell--edit',
        'resolve' => !$hascourse,
        'resolveurl' => (new moodle_url('/mod/coassemble/view.php'))->out(false),
        'cmid' => (int) $cm->id,
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->footer();
    exit;
}

// Learner / view mode.
if (!$hascourse) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('error_nocourseyet', 'mod_coassemble'), 'info');
    if ($canauthor) {
        echo $OUTPUT->single_button(
            new moodle_url('/mod/coassemble/view.php', ['id' => $cm->id, 'mode' => 'edit']),
            get_string('nav_editcontent', 'mod_coassemble'),
            'get'
        );
        echo $OUTPUT->single_button(
            new moodle_url('/mod/coassemble/view.php', ['id' => $cm->id, 'resolve' => 1]),
            get_string('resolve_course', 'mod_coassemble'),
            'post'
        );
    }
    echo $OUTPUT->footer();
    exit;
}

$body = [
    'action' => 'view',
    'courseId' => (int) $instance->coassemblecourseid,
    'identifier' => $identifier,
    'clientIdentifier' => $clientidentifier,
    'name' => $displayname,
    'options' => ['legacy' => false],
];
if ($canauthor) {
    // Authors get a builder preview instead of the learner player, so
    // opening the activity never creates a learner tracking for them.
    $body['action'] = 'edit';
    $body['options']['flow'] = 'preview';
    // The Moodle chrome bar owns navigation; the embed's own back arrow
    // would otherwise dead-end in place.
    $body['options']['back'] = 'hidden';
}
if ($avatar) {
    $body['avatar'] = $avatar;
}
if (!empty($instance->themeid)) {
    $body['themeId'] = (int) $instance->themeid;
}
if (!empty($instance->language)) {
    $body['options']['language'] = $instance->language;
}

try {
    $embed = $client->issue_course_embed($body);
} catch (Throwable $e) {
    \mod_coassemble\local\diagnostics::log($e, 'view');
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('error_apirequest', 'mod_coassemble'), 'error');
    echo $OUTPUT->footer();
    exit;
}

$jsconfig = [
    'iframeId' => 'coassemble-embed-frame',
    'expectedOrigin' => coassemble_embed_origin($embed['url']),
    'mode' => $canauthor ? 'edit' : 'view',
    'cmid' => (int) $cm->id,
    'statusElId' => 'coassemble-session-status',
    'strings' => [
        'ready' => get_string('session_ready', 'mod_coassemble'),
        'error' => get_string('session_error', 'mod_coassemble'),
        'expired' => get_string('session_expired', 'mod_coassemble'),
    ],
];
if ($canauthor) {
    $jsconfig['persistCourse'] = true;
} else {
    $jsconfig['syncProgress'] = true;
}
$PAGE->requires->js_call_amd('mod_coassemble/embed', 'init', [$jsconfig]);

echo $OUTPUT->header();

// Keep course navigation available alongside the embedded activity.
$chromelinks = [['url' => $exiturl->out(false), 'label' => $exitlabel, 'exit' => true]];
if ($canauthor) {
    $remote = null;
    try {
        $remote = $client->get_course((int) $instance->coassemblecourseid);
    } catch (Throwable $e) {
        \mod_coassemble\local\diagnostics::log($e, 'view');
        // Non-fatal for the player chrome.
        $remote = null;
    }
    $actions = [
        ['view.php', 'nav_editcontent', ['mode' => 'edit']],
        ['manage.php', 'nav_manage', []],
        ['report.php', 'nav_report', []],
        ['analytics.php', 'nav_analytics', []],
    ];
    foreach ($actions as [$page, $label, $params]) {
        $chromelinks[] = [
            'url' => (new moodle_url('/mod/coassemble/' . $page, ['id' => $cm->id] + $params))->out(false),
            'label' => get_string($label, 'mod_coassemble'),
        ];
    }
}
echo $OUTPUT->render_from_template('mod_coassemble/toolbar', [
    'label' => get_string('modulename', 'mod_coassemble'),
    'class' => 'coassemble-player-chrome',
    'links' => $chromelinks,
]);
if ($canauthor && !empty($remote) && empty($remote['published'])) {
    echo $OUTPUT->notification(get_string('status_unpublished', 'mod_coassemble'), 'warning');
}
echo $OUTPUT->render_from_template('mod_coassemble/embed', [
    'url' => $embed['url'],
    'title' => get_string('player_iframe_title', 'mod_coassemble'),
    'allow' => 'clipboard-write; fullscreen; microphone; camera',
    'shellclass' => 'coassemble-embed-shell--view',
]);
echo $OUTPUT->footer();
