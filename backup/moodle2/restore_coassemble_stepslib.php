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
 * Restore structure step for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores one coassemble activity (and user tracking when included).
 */
class restore_coassemble_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the restore structure.
     *
     * @return array of restore_path_element
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('coassemble', '/activity/coassemble');
        if ($userinfo) {
            $paths[] = new restore_path_element('coassemble_track', '/activity/coassemble/tracks/track');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore a coassemble record.
     *
     * @param array $data
     */
    protected function process_coassemble($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        // The linked Coassemble course id stays valid only within the same
        // workspace; it is kept so same-site restores keep working.
        $newitemid = $DB->insert_record('coassemble', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore a tracking record.
     *
     * @param array $data
     */
    protected function process_coassemble_track($data) {
        global $DB;

        $data = (object) $data;
        $data->coassembleid = $this->get_new_parentid('coassemble');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }

        $DB->insert_record('coassemble_track', $data);
    }

    /**
     * Post-restore: nothing extra; grades are restored by core.
     */
    protected function after_execute() {
        // No file areas beyond intro (handled by core).
        $this->add_related_files('mod_coassemble', 'intro', null);
    }
}
