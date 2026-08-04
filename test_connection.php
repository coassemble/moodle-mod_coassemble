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
 * Admin helper to verify Headless API credentials and authoring entitlement.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/mod/coassemble/test_connection.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('settings_testconnection', 'mod_coassemble'));
$PAGE->set_heading(get_string('settings_testconnection', 'mod_coassemble'));

$client = new \mod_coassemble\api\client();
$result = $client->test_connection();

echo $OUTPUT->header();
if (!empty($result['ok'])) {
    echo $OUTPUT->notification($result['message'], 'success');
    if (!empty($result['authoring_message'])) {
        echo html_writer::tag('p', s($result['authoring_message']));
    }
    echo html_writer::tag('p', get_string('connection_authoring_note', 'mod_coassemble'));
} else {
    echo $OUTPUT->notification($result['message'], 'error');
    if (!empty($result['list_ok']) && empty($result['authoring_ok'])) {
        echo html_writer::tag('p', get_string('connection_authoring_note', 'mod_coassemble'));
    }
}
if (isset($result['coursecount'])) {
    echo html_writer::tag('p', get_string('connection_coursecount', 'mod_coassemble', (int) $result['coursecount']));
}

// Webhooks are managed automatically — report (and repair) registration here.
if (!empty($result['ok'])) {
    $webhook = \mod_coassemble\local\webhook_registration::ensure();
    echo $OUTPUT->heading(get_string('webhook_status', 'mod_coassemble'), 3);
    switch ($webhook['status']) {
        case \mod_coassemble\local\webhook_registration::STATUS_REGISTERED:
            echo $OUTPUT->notification(get_string('webhook_auto_ok', 'mod_coassemble'), 'success');
            break;
        case \mod_coassemble\local\webhook_registration::STATUS_OK:
            echo $OUTPUT->notification(get_string('webhook_status_ok', 'mod_coassemble'), 'success');
            break;
        case \mod_coassemble\local\webhook_registration::STATUS_NEEDSHTTPS:
            echo $OUTPUT->notification(get_string('webhook_auto_needshttps', 'mod_coassemble'), 'info');
            break;
        default:
            echo $OUTPUT->notification(
                get_string('webhook_auto_failed', 'mod_coassemble', s($webhook['message'])),
                'warning'
            );
            break;
    }
}

echo $OUTPUT->single_button(
    new moodle_url('/admin/settings.php', ['section' => 'modsettingcoassemble']),
    get_string('back'),
    'get'
);
echo $OUTPUT->footer();
