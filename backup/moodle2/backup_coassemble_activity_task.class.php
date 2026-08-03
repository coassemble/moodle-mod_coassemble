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
 * Backup task for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/coassemble/backup/moodle2/backup_coassemble_stepslib.php');

/**
 * Provides the steps to perform one complete backup of a coassemble instance.
 */
class backup_coassemble_activity_task extends backup_activity_task {
    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Define the backup steps.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_coassemble_activity_structure_step('coassemble_structure', 'coassemble.xml'));
    }

    /**
     * Encode links to this activity's scripts in course content.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = '/(' . $base . '\/mod\/coassemble\/index.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@COASSEMBLEINDEX*$2@$', $content);

        $search = '/(' . $base . '\/mod\/coassemble\/view.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@COASSEMBLEVIEWBYID*$2@$', $content);

        return $content;
    }
}
