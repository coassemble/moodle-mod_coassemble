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
 * Safe diagnostics for failed Coassemble operations.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\event;

/**
 * Record failures without upstream payloads, signed URLs or credentials.
 */
class api_request_failed extends \core\event\base {
    /**
     * Initialise event metadata.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Human-readable event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_api_request_failed', 'mod_coassemble');
    }

    /**
     * Safe diagnostic fields for administrators inspecting the Moodle logs.
     *
     * @return string
     */
    public function get_description() {
        return 'Coassemble operation ' . $this->other['operation'] . ' failed. HTTP status: ' .
            $this->other['httpstatus'] . '; cURL error: ' . $this->other['curlerrno'] .
            '; exception type: ' . $this->other['exception'] . '.';
    }
}
