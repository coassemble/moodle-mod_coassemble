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
 * Custom completion rules for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_coassemble\completion;

use core_completion\activity_custom_completion;

/**
 * Completion is driven by the mirrored Coassemble tracking record.
 */
class custom_completion extends activity_custom_completion {
    /**
     * Fetch the state of the given completion rule.
     *
     * @param string $rule
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $track = $DB->get_record('coassemble_track', [
            'coassembleid' => $this->cm->instance,
            'userid' => $this->userid,
        ]);

        return ($track && !empty($track->completed)) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Custom rules defined by this module.
     *
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return ['completioncourse'];
    }

    /**
     * Descriptions shown in the activity completion details.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completioncourse' => get_string('completiondetail:course', 'mod_coassemble'),
        ];
    }

    /**
     * Display order of completion rules.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return ['completionview', 'completioncourse', 'completionusegrade'];
    }
}
