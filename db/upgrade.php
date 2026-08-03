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
 * Upgrade steps for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Apply schema upgrades between plugin versions.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_coassemble_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026072201) {
        $table = new xmldb_table('coassemble');

        $field = new xmldb_field('timeauthored', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timemodified');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('collectionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'coassemblecourseid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('completioncourse', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'grade');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $track = new xmldb_table('coassemble_track');
        $field = new xmldb_field('commenced', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'completed');
        if (!$dbman->field_exists($track, $field)) {
            $dbman->add_field($track, $field);
        }

        upgrade_mod_savepoint(true, 2026072201, 'coassemble');
    }

    if ($oldversion < 2026072203) {
        $table = new xmldb_table('coassemble');
        $field = new xmldb_field('grademethod', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'grade');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $track = new xmldb_table('coassemble_track');
        $field = new xmldb_field('score', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null, 'progress');
        if (!$dbman->field_exists($track, $field)) {
            $dbman->add_field($track, $field);
        }
        $field = new xmldb_field('passed', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'score');
        if (!$dbman->field_exists($track, $field)) {
            $dbman->add_field($track, $field);
        }

        upgrade_mod_savepoint(true, 2026072203, 'coassemble');
    }

    if ($oldversion < 2026072204) {
        // The workspace id is embedded in the API key; merge legacy split
        // credentials into the full COASSEMBLE:{workspace}:{secret} form.
        $workspaceid = (string) get_config('mod_coassemble', 'workspaceid');
        $apikey = trim((string) get_config('mod_coassemble', 'apikey'));
        if ($apikey !== '' && $workspaceid !== '' && strpos($apikey, ':') === false) {
            set_config('apikey', 'COASSEMBLE:' . $workspaceid . ':' . $apikey, 'mod_coassemble');
        }
        unset_config('workspaceid', 'mod_coassemble');

        // The tenant identifier is now always derived from the Moodle site.
        unset_config('clientidentifierstrategy', 'mod_coassemble');
        unset_config('clientidentifiercustom', 'mod_coassemble');

        upgrade_mod_savepoint(true, 2026072204, 'coassemble');
    }

    return true;
}
