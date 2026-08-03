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
 * Optional collection player / builder expansion.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$collectionid = required_param('collectionid', PARAM_INT);
$action = optional_param('action', 'view', PARAM_ALPHA); // One of: view or edit.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'coassemble');
$instance = $DB->get_record('coassemble', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/coassemble:view', $context);

if ($action === 'edit') {
    require_capability('mod/coassemble:author', $context);
}

$PAGE->set_url('/mod/coassemble/collection.php', [
    'id' => $cm->id,
    'collectionid' => $collectionid,
    'action' => $action,
]);
$PAGE->set_title(get_string('collection_heading', 'mod_coassemble'));
$PAGE->set_heading(format_string($course->fullname));

$client = new \mod_coassemble\api\client();
$identifier = \mod_coassemble\local\identity::for_user($USER);
$clientidentifier = \mod_coassemble\local\identity::client_identifier();

$body = [
    'action' => $action === 'edit' ? 'edit' : 'view',
    'collectionId' => $collectionid,
    'identifier' => $identifier,
    'clientIdentifier' => $clientidentifier,
    'name' => \mod_coassemble\local\identity::display_name($USER),
    'options' => ['back' => 'event'],
];

try {
    $url = $client->issue_collection_embed($body);
} catch (Throwable $e) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo $OUTPUT->footer();
    exit;
}

$PAGE->requires->js_call_amd('mod_coassemble/embed', 'init', [[
    'iframeId' => 'coassemble-embed-frame',
    'expectedOrigin' => coassemble_embed_origin($url),
    'mode' => $action === 'edit' ? 'edit' : 'view',
    'cmid' => (int) $cm->id,
    'sesskey' => sesskey(),
    'backUrl' => (new moodle_url('/mod/coassemble/view.php', ['id' => $cm->id]))->out(false),
]]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('collection_heading', 'mod_coassemble'));
echo html_writer::tag('p', get_string('collection_help', 'mod_coassemble'));
echo html_writer::start_div('coassemble-embed-shell');
echo html_writer::tag('iframe', '', [
    'id' => 'coassemble-embed-frame',
    'src' => $url,
    'class' => 'coassemble-embed-iframe',
    'allow' => 'clipboard-write; fullscreen; microphone; camera',
    'title' => get_string('collection_iframe_title', 'mod_coassemble'),
]);
echo html_writer::end_div();
echo $OUTPUT->footer();
