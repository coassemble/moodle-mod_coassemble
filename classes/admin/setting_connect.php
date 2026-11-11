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
 * Connect to Coassemble section of the plugin settings page.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\admin;

use mod_coassemble\local\connect;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Display-only setting: the Connect button, or the current connection.
 *
 * Connection details are fetched only when the section is rendered, never
 * while the admin tree is built for other pages.
 */
class setting_connect extends \admin_setting {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->nosave = true;
        parent::__construct(
            'mod_coassemble/connect',
            get_string('connect_heading', 'mod_coassemble'),
            get_string('connect_intro', 'mod_coassemble'),
            ''
        );
    }

    /**
     * Nothing is stored.
     *
     * @return bool
     */
    public function get_setting() {
        return true;
    }

    /**
     * Nothing is stored.
     *
     * @param mixed $data
     * @return string
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Render the section.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;
        return $OUTPUT->render_from_template('mod_coassemble/connect_settings', self::export_context());
    }

    /**
     * Template context for the current connection state.
     *
     * @param \mod_coassemble\api\client|null $client
     * @return array
     */
    public static function export_context(?\mod_coassemble\api\client $client = null): array {
        $context = [
            'starturl' => (new \moodle_url('/mod/coassemble/connect/start.php'))->out(false),
            'canconnect' => connect::site_is_https(),
            'connected' => false,
        ];

        if (!connect::is_connected()) {
            $context['heading'] = get_string('connect_heading', 'mod_coassemble');
            $context['intro'] = get_string('connect_intro', 'mod_coassemble');
            $context['button'] = get_string('connect_button', 'mod_coassemble');
            $context['hint'] = get_string('connect_button_hint', 'mod_coassemble');
            return $context;
        }

        $context['connected'] = true;
        $context['button'] = get_string('connect_reconnect', 'mod_coassemble');
        $context['hint'] = get_string('connect_reconnect_hint', 'mod_coassemble');
        $context['manageurl'] = connect::manage_url()->out(false);

        $details = connect::connection_details($client);
        $workspace = (string) ($details['workspace']['name'] ?? get_config('mod_coassemble', 'connectworkspace'));
        $context['heading'] = get_string(
            'connect_heading_connected',
            'mod_coassemble',
            $workspace !== '' ? $workspace : get_string('connect_workspace_unnamed', 'mod_coassemble')
        );

        if ($details === null) {
            $context['unavailable'] = get_string('connect_details_unavailable', 'mod_coassemble');
            return $context;
        }

        $plan = (string) ($details['plan']['name'] ?? '');
        $mode = ($details['key']['mode'] ?? '') === 'test' ? 'test' : 'live';
        $context['summary'] = get_string('connect_summary_' . $mode, 'mod_coassemble', $plan !== '' ? $plan : 'Coassemble');

        $connection = $details['connection'] ?? null;
        if (is_array($connection)) {
            $connectedat = strtotime((string) ($connection['connectedAt'] ?? ''));
            $context['connectedby'] = get_string('connect_connectedby', 'mod_coassemble', (object) [
                'name' => (string) ($connection['connectedBy']['name'] ?? ''),
                'date' => $connectedat ? userdate($connectedat, get_string('strftimedate', 'langconfig')) : '',
                'key' => (string) ($details['key']['name'] ?? ''),
            ]);
        }
        return $context;
    }
}
