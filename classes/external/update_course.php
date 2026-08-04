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
 * External function: persist the Coassemble course link from builder events.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Moodle 4.1 compatibility: the external API classes moved to the
// core_external namespace in 4.2 and the legacy names were removed in 5.0.
if (!class_exists('\core_external\external_api')) {
    require_once($CFG->libdir . '/externallib.php');
    class_alias('external_api', 'core_external\external_api');
    class_alias('external_function_parameters', 'core_external\external_function_parameters');
    class_alias('external_single_structure', 'core_external\external_single_structure');
    class_alias('external_value', 'core_external\external_value');
}

/**
 * Persist Coassemble course id / title reported by builder postMessage events.
 */
class update_course extends \core_external\external_api {
    /**
     * Parameter definition.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'cmid' => new \core_external\external_value(PARAM_INT, 'Course module id'),
            'courseid' => new \core_external\external_value(PARAM_INT, 'Coassemble course id reported by the builder'),
            'title' => new \core_external\external_value(PARAM_TEXT, 'Coassemble course title', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Store the Coassemble course id (and optional title) on the activity.
     *
     * @param int $cmid Course module id
     * @param int $courseid Coassemble course id
     * @param string $title Coassemble course title
     * @return array {courseid: int}
     */
    public static function execute(int $cmid, int $courseid, string $title = ''): array {
        global $DB;

        [
            'cmid' => $cmid,
            'courseid' => $courseid,
            'title' => $title,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'courseid' => $courseid,
            'title' => $title,
        ]);

        $cm = get_coursemodule_from_id('coassemble', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/coassemble:author', $context);

        $instance = $DB->get_record('coassemble', ['id' => $cm->instance], '*', MUST_EXIST);
        $instance = \mod_coassemble\local\course_link::persist($instance, $courseid, $title);

        return ['courseid' => (int) $instance->coassemblecourseid];
    }

    /**
     * Return definition.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'courseid' => new \core_external\external_value(PARAM_INT, 'Linked Coassemble course id'),
        ]);
    }
}
