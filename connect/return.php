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
 * Connect Coassemble: Coassemble returns the administrator here after approval.
 *
 * The one-time code is swapped for the API key server to server; the key is
 * never shown, logged or put in a URL.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/mod/coassemble/lib.php');

use mod_coassemble\local\connect;

connect::require_admin();

$PAGE->set_url('/mod/coassemble/connect/return.php');
$PAGE->set_context(context_system::instance());

$state = optional_param('state', '', PARAM_RAW_TRIMMED);
$code = optional_param('code', '', PARAM_RAW_TRIMMED);
$error = optional_param('error', '', PARAM_ALPHANUMEXT);

$pending = connect::consume_state($state);
$settingsurl = connect::settings_url();

// State first, so a forged cancellation can't end a genuine attempt.
if ($pending === null) {
    redirect($settingsurl, get_string('connect_error_state', 'mod_coassemble'), null, \core\output\notification::NOTIFY_ERROR);
}
if ($error === 'access_denied') {
    redirect($settingsurl, get_string('connect_cancelled', 'mod_coassemble'), null, \core\output\notification::NOTIFY_INFO);
}
if ($error !== '') {
    redirect($settingsurl, get_string('connect_error_failed', 'mod_coassemble'), null, \core\output\notification::NOTIFY_ERROR);
}

try {
    (new connect())->complete($pending, $code);
} catch (moodle_exception $e) {
    redirect($settingsurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}

// Registers progress webhooks for the new key and reports the outcome.
mod_coassemble_credentials_updated('', true);
redirect($settingsurl);
