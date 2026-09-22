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
 * Teacher content management: publish, revert, duplicate, delete, restore, SCORM export.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$confirmed = optional_param('confirmed', 0, PARAM_BOOL);
$expectedcourseid = optional_param('remoteid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'coassemble');
$instance = $DB->get_record('coassemble', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/coassemble:manage', $context);

$PAGE->set_url('/mod/coassemble/manage.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('nav_manage', 'mod_coassemble'));
$PAGE->set_heading(format_string($course->fullname));

$client = new \mod_coassemble\api\client();
$identifier = \mod_coassemble\local\identity::for_user($USER);
$clientidentifier = \mod_coassemble\local\identity::client_identifier();

if ($action !== '') {
    \mod_coassemble\local\action::require_post();
    if (empty($instance->coassemblecourseid) && $action !== 'refresh') {
        redirect($PAGE->url, get_string('error_nocourseyet', 'mod_coassemble'), null, \core\output\notification::NOTIFY_ERROR);
    }

    if ((int) $instance->coassemblecourseid !== $expectedcourseid) {
        redirect($PAGE->url, get_string('error_coursechanged', 'mod_coassemble'), null, \core\output\notification::NOTIFY_ERROR);
    }
    if (in_array($action, ['delete', 'unlink', 'duplicate'], true) && !$confirmed) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('manage_' . $action . '_confirm', 'mod_coassemble'),
            new single_button(
                new moodle_url('/mod/coassemble/manage.php', [
                'id' => $id, 'action' => $action, 'confirmed' => 1, 'remoteid' => $expectedcourseid,
                ]),
                get_string('continue'),
                'post'
            ),
            $PAGE->url
        );
        echo $OUTPUT->footer();
        exit;
    }
    $courseid = (int) $instance->coassemblecourseid;
    \mod_coassemble\local\course_metadata::invalidate($courseid);
    try {
        switch ($action) {
            case 'duplicate':
                $dup = $client->duplicate_course($courseid, [
                    'identifier' => $identifier,
                    'clientIdentifier' => $clientidentifier,
                ]);
                if (empty($dup['id']) || (int) $dup['id'] === $courseid) {
                    throw new moodle_exception('error_apirequest', 'mod_coassemble');
                }
                $instance = \mod_coassemble\local\course_link::unlink($instance);
                $instance = \mod_coassemble\local\course_link::persist($instance, (int) $dup['id'], '', 'copied');
                redirect($PAGE->url, get_string('manage_duplicate_ok', 'mod_coassemble'));
                break;
            case 'delete':
                \mod_coassemble\local\course_link::delete_remote($instance, $client);
                redirect($PAGE->url, get_string('manage_delete_ok', 'mod_coassemble'));
                break;
            case 'unlink':
                \mod_coassemble\local\course_link::unlink($instance);
                redirect($PAGE->url, get_string('manage_unlink_ok', 'mod_coassemble'));
                break;
            case 'scorm':
                $binary = $client->export_scorm($courseid);
                require_once($CFG->libdir . '/filelib.php');
                send_temp_file($binary, 'coassemble-' . $courseid . '.zip', true);
                break;
            case 'refresh':
                // Pull latest title from Headless when linked.
                if (!empty($instance->coassemblecourseid)) {
                    $remote = $client->get_course((int) $instance->coassemblecourseid);
                    if (!empty($remote['title'])) {
                        $instance->name = $remote['title'];
                        $instance->timemodified = time();
                        $DB->update_record('coassemble', $instance);
                    }
                }
                redirect($PAGE->url, get_string('manage_refresh_ok', 'mod_coassemble'));
                break;
            default:
                redirect($PAGE->url);
        }
    } catch (Throwable $e) {
        \mod_coassemble\local\diagnostics::log($e, 'manage');
        $message = get_string('error_apirequest', 'mod_coassemble');
        if (
            $e instanceof moodle_exception && in_array(
                $e->errorcode,
                ['manage_delete_linked', 'manage_delete_shared', 'manage_delete_shared_singular', 'error_coursechanged'],
                true
            )
        ) {
            $message = get_string($e->errorcode, 'mod_coassemble', $e->a);
        }
        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_ERROR);
    }
}

$remote = null;
$remoteerror = '';
if (!empty($instance->coassemblecourseid) && $client->is_configured()) {
    try {
        $remote = $client->get_course((int) $instance->coassemblecourseid);
    } catch (Throwable $e) {
        \mod_coassemble\local\diagnostics::log($e, 'manage');
        $remoteerror = get_string('error_apirequest', 'mod_coassemble');
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('nav_manage', 'mod_coassemble'));

echo html_writer::tag('p', get_string('manage_intro', 'mod_coassemble'));
if (!empty($instance->coassemblecourseid) && $instance->linkmode === 'linked') {
    echo $OUTPUT->notification(get_string('library_link_help', 'mod_coassemble'), 'warning');
}

if (!empty($remoteerror)) {
    echo $OUTPUT->notification($remoteerror, 'error');
} else if ($remote) {
    if (empty($remote['published'])) {
        echo $OUTPUT->notification(get_string('status_unpublished', 'mod_coassemble'), 'warning');
    } else if (!empty($remote['revision'])) {
        echo $OUTPUT->notification(get_string('status_unpublishedchanges', 'mod_coassemble'), 'warning');
    } else {
        echo $OUTPUT->notification(get_string('status_published', 'mod_coassemble'), 'success');
    }
}

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->data = [];
$table->data[] = [get_string('name'), format_string($instance->name)];
$otheruses = \mod_coassemble\local\course_link::other_uses($instance);
if (!empty($instance->coassemblecourseid)) {
    $table->data[] = [get_string('linkmode', 'mod_coassemble'),
        get_string(
            'linkmode_' . $instance->linkmode . ($instance->linkmode === 'linked' && $otheruses === 1 ? '_singular' : ''),
            'mod_coassemble',
            $otheruses
        )];
    if ($instance->linkmode !== 'linked' && $otheruses) {
        $table->data[] = [get_string('manage_shared', 'mod_coassemble'),
            get_string($otheruses === 1 ? 'manage_delete_shared_singular' : 'manage_delete_shared', 'mod_coassemble', $otheruses)];
    }
}
$table->data[] = [
    get_string('coassemblecourseid', 'mod_coassemble'),
    $instance->coassemblecourseid ? (int) $instance->coassemblecourseid : get_string('notyetlinked', 'mod_coassemble'),
];
if ($remote) {
    $table->data[] = [get_string('manage_remote_title', 'mod_coassemble'), s($remote['title'] ?? '')];
    $published = get_string('manage_notpublished', 'mod_coassemble');
    if (!empty($remote['published'])) {
        $published = get_string(
            !empty($remote['revision']) ? 'manage_unpublishedchanges' : 'library_published',
            'mod_coassemble'
        );
    }
    $table->data[] = [get_string('manage_remote_published', 'mod_coassemble'), $published];
}
if (!empty($instance->timeauthored)) {
    $table->data[] = [get_string('timeauthored', 'mod_coassemble'), userdate($instance->timeauthored)];
}
if (!empty($instance->collectionid)) {
    $table->data[] = [
        get_string('collectionid', 'mod_coassemble'),
        html_writer::link(
            new moodle_url('/mod/coassemble/view.php', ['id' => $cm->id, 'mode' => 'collection']),
            (int) $instance->collectionid
        ),
    ];
}
echo html_writer::table($table);

if (!empty($instance->coassemblecourseid)) {
    echo html_writer::start_div('coassemble-manage-actions');
    $actions = ['duplicate', 'unlink', 'refresh'];
    if (in_array($instance->linkmode, ['created', 'copied'], true) && !$otheruses) {
        $actions[] = 'delete';
    }
    foreach ($actions as $act) {
        $url = new moodle_url('/mod/coassemble/manage.php', [
            'id' => $cm->id,
            'action' => $act,
            'remoteid' => (int) $instance->coassemblecourseid,
            'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->single_button($url, get_string('manage_' . $act, 'mod_coassemble'), 'post');
    }
    $scormurl = new moodle_url('/mod/coassemble/manage.php', [
        'id' => $cm->id,
        'action' => 'scorm',
        'remoteid' => (int) $instance->coassemblecourseid,
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->single_button($scormurl, get_string('manage_scorm', 'mod_coassemble'), 'post');
    $refreshprogress = new moodle_url('/mod/coassemble/refresh_progress.php', [
        'id' => $cm->id,
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->single_button($refreshprogress, get_string('refresh_progress', 'mod_coassemble'), 'post');
    echo html_writer::end_div();
} else {
    echo html_writer::div(
        $OUTPUT->notification(get_string('error_nocourseyet', 'mod_coassemble'), 'info'),
        'coassemble-manage-notice'
    );
    echo html_writer::start_div('coassemble-manage-actions');
    $createflows = \mod_coassemble\local\builder_options::creation_flows();
    foreach ($createflows as $fkey => $flabel) {
        echo $OUTPUT->single_button(
            new moodle_url('/mod/coassemble/view.php', [
                'id' => $cm->id,
                'mode' => 'edit',
                'flow' => $fkey,
            ]),
            $flabel,
            $fkey === 'existing' ? 'get' : 'post'
        );
    }
    echo html_writer::end_div();
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
