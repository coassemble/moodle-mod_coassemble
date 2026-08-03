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
 * Data generator for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Coassemble module data generator.
 */
class mod_coassemble_generator extends testing_module_generator {
    /**
     * Create a coassemble activity instance.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $defaults = [
            'name' => 'Coassemble activity',
            'intro' => 'Test intro',
            'introformat' => FORMAT_HTML,
            'coassemblecourseid' => null,
            'collectionid' => null,
            'flow' => null,
            'themeid' => null,
            'language' => null,
            'grade' => 100,
            'grademethod' => 0,
            'completioncourse' => 1,
        ];
        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }
}
