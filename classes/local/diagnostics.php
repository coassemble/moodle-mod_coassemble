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
 * Server-only diagnostics for external service failures.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\local;

/**
 * Keep upstream payloads, credentials and personal data out of error output.
 */
class diagnostics {
    /**
     * Record the operation and exception type without its untrusted message or trace.
     *
     * @param \Throwable $exception Failure to record
     * @param string $operation Internal operation name
     */
    public static function log(\Throwable $exception, string $operation): void {
        self::record($operation, get_class($exception));
    }

    /**
     * Write a safe diagnostic to Moodle's configured event log stores.
     *
     * @param string $operation Internal operation name (never a signed URL)
     * @param string $exception Exception class, without its message or trace
     * @param int $httpstatus HTTP response status
     * @param int $curlerrno cURL error number
     */
    public static function record(string $operation, string $exception = '', int $httpstatus = 0, int $curlerrno = 0): void {
        $event = \mod_coassemble\event\api_request_failed::create([
            'context' => \context_system::instance(),
            'other' => [
                'operation' => $operation,
                'exception' => $exception,
                'httpstatus' => $httpstatus,
                'curlerrno' => $curlerrno,
            ],
        ]);
        $event->trigger();
    }
}
