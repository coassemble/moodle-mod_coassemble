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
 * Scheduled cleanup of the webhook idempotency log.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\task;

/**
 * Deletes webhook delivery records older than the retention window.
 *
 * Webhook retries stop well within the retention window, so expired rows can
 * never be needed for duplicate suppression again.
 */
class cleanup_webhook_log extends \core\task\scheduled_task {
    /** Retention window in seconds (7 days). */
    const RETENTION = 7 * DAYSECS;

    /**
     * Task name shown in the scheduled task list.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup_webhook_log', 'mod_coassemble');
    }

    /**
     * Delete expired rows.
     */
    public function execute() {
        global $DB;
        $DB->delete_records_select('coassemble_webhook', 'timecreated < :cutoff', [
            'cutoff' => time() - self::RETENTION,
        ]);
    }
}
