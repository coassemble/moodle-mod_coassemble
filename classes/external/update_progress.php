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
 * External function: sync learner progress from Coassemble trackings.
 *
 * Client-supplied progress/completed values are never accepted. Grades and
 * completion are taken from authoritative Coassemble trackings for the
 * current user.
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
 * Record learner progress for the current user from player events.
 */
class update_progress extends \core_external\external_api {
    /**
     * Parameter definition.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'cmid' => new \core_external\external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Pull the current user's authoritative tracking and mirror it into Moodle.
     *
     * @param int $cmid Course module id
     * @return array {synced: bool}
     */
    public static function execute(int $cmid): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/coassemble/lib.php');

        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id('coassemble', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/coassemble:view', $context);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        $instance = $DB->get_record('coassemble', ['id' => $cm->instance], '*', MUST_EXIST);
        if (empty($instance->coassemblecourseid)) {
            throw new \moodle_exception('error_nocourseyet', 'mod_coassemble');
        }

        $client = new \mod_coassemble\api\client();
        $identifier = \mod_coassemble\local\identity::for_user($USER);
        $clientidentifier = \mod_coassemble\local\identity::client_identifier();

        $trackings = $client->list_trackings([
            'id' => (int) $instance->coassemblecourseid,
            'identifier' => $identifier,
            'clientIdentifier' => $clientidentifier,
            'length' => 1,
        ]);

        $tracking = null;
        foreach ($trackings as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((string) ($row['identifier'] ?? '') === $identifier) {
                $tracking = $row;
                break;
            }
        }

        if (!$tracking) {
            // No authoritative progress yet — do not accept client-forged values.
            return ['synced' => false];
        }

        $progress = 0.0;
        if (isset($tracking['progress_percent'])) {
            $progress = (float) $tracking['progress_percent'];
        } else if (isset($tracking['progressPercent'])) {
            $progress = (float) $tracking['progressPercent'];
        }
        $completed = !empty($tracking['completed']);
        $commenced = !empty($tracking['commenced']) || $progress > 0 || $completed;
        $score = isset($tracking['score']) && is_numeric($tracking['score']) ? (float) $tracking['score'] : null;
        $passed = array_key_exists('passed', $tracking) && $tracking['passed'] !== null ? (bool) $tracking['passed'] : null;

        coassemble_record_progress($instance, (int) $USER->id, $progress, $completed, $commenced, $score, $passed);

        return ['synced' => true];
    }

    /**
     * Return definition.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'synced' => new \core_external\external_value(PARAM_BOOL, 'Whether authoritative progress was found and mirrored'),
        ]);
    }
}
