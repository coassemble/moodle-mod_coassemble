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
 * Backup structure step for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the complete coassemble structure for backup, with user data.
 */
class backup_coassemble_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the backup structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $coassemble = new backup_nested_element('coassemble', ['id'], [
            'name', 'intro', 'introformat', 'coassemblecourseid', 'collectionid',
            'flow', 'themeid', 'language', 'grade', 'grademethod', 'completioncourse',
            'timecreated', 'timemodified', 'timeauthored',
        ]);

        $tracks = new backup_nested_element('tracks');
        $track = new backup_nested_element('track', ['id'], [
            'userid', 'progress', 'score', 'passed', 'completed', 'commenced', 'timemodified',
        ]);

        $coassemble->add_child($tracks);
        $tracks->add_child($track);

        $coassemble->set_source_table('coassemble', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $track->set_source_table('coassemble_track', ['coassembleid' => backup::VAR_PARENTID]);
        }

        $track->annotate_ids('user', 'userid');

        $coassemble->annotate_files('mod_coassemble', 'intro', null);

        return $this->prepare_activity_structure($coassemble);
    }
}
