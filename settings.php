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
 * Admin settings for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // The apikey updated-callback (webhook auto-registration) lives in lib.php.
    require_once($CFG->dirroot . '/mod/coassemble/lib.php');

    // Connect Coassemble sets the API key below through a sign-in handshake.
    $settings->add(new \mod_coassemble\admin\setting_connect());

    $settings->add(new admin_setting_heading(
        'mod_coassemble/credentials',
        get_string('settings_credentials', 'mod_coassemble'),
        get_string('settings_credentials_desc', 'mod_coassemble')
    ));

    $settings->add(new admin_setting_configtext(
        'mod_coassemble/apiurl',
        get_string('settings_apiurl', 'mod_coassemble'),
        get_string('settings_apiurl_desc', 'mod_coassemble'),
        'https://api.coassemble.com',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'mod_coassemble/appurl',
        get_string('settings_appurl', 'mod_coassemble'),
        get_string('settings_appurl_desc', 'mod_coassemble'),
        \mod_coassemble\local\connect::DEFAULT_APPURL,
        PARAM_URL
    ));

    // Saving credentials auto-registers this site's webhook receiver with
    // Coassemble (no manual webhook configuration).
    $apikeysetting = new admin_setting_configpasswordunmask(
        'mod_coassemble/apikey',
        get_string('settings_apikey', 'mod_coassemble'),
        get_string(
            \mod_coassemble\local\connect::is_connected() ? 'settings_apikey_desc_connect' : 'settings_apikey_desc',
            'mod_coassemble'
        ),
        ''
    );
    $apikeysetting->set_updatedcallback('mod_coassemble_credentials_updated');
    $settings->add($apikeysetting);

    $testurl = new moodle_url('/mod/coassemble/test_connection.php');
    $settings->add(new admin_setting_description(
        'mod_coassemble/testconnection',
        get_string('settings_testconnection', 'mod_coassemble'),
        html_writer::link($testurl, get_string('settings_testconnection_link', 'mod_coassemble'))
    ));

    $settings->add(new admin_setting_heading(
        'mod_coassemble/builderfeatures',
        get_string('settings_builderfeatures', 'mod_coassemble'),
        get_string('settings_builderfeatures_desc', 'mod_coassemble')
    ));
    foreach (['ai', 'googledrive', 'onedrive', 'narrations', 'translations', 'brandvoice'] as $feature) {
        $settings->add(new admin_setting_configcheckbox(
            'mod_coassemble/' . $feature,
            get_string('settings_' . $feature, 'mod_coassemble'),
            get_string('settings_' . $feature . '_desc', 'mod_coassemble'),
            1
        ));
    }
}
