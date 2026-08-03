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
 * Restore task for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/coassemble/backup/moodle2/restore_coassemble_stepslib.php');

/**
 * Provides the steps to perform one complete restore of a coassemble instance.
 */
class restore_coassemble_activity_task extends restore_activity_task {
    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Define the restore steps.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_coassemble_activity_structure_step('coassemble_structure', 'coassemble.xml'));
    }

    /**
     * Define decoding contents.
     *
     * @return array of restore_decode_content
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('coassemble', ['intro'], 'coassemble'),
        ];
    }

    /**
     * Define decoding rules.
     *
     * @return array of restore_decode_rule
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('COASSEMBLEINDEX', '/mod/coassemble/index.php?id=$1', 'course'),
            new restore_decode_rule('COASSEMBLEVIEWBYID', '/mod/coassemble/view.php?id=$1', 'course_module'),
        ];
    }

    /**
     * Define restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [];
    }
}
