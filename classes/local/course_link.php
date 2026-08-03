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
 * Helpers for linking / resolving Coassemble courses on activity instances.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\local;

/**
 * Persist and recover Coassemble course links for Moodle activities.
 */
class course_link {
    /**
     * Store a Coassemble course id (and optional title) on the activity.
     *
     * @param \stdClass $instance
     * @param int $courseid
     * @param string $title
     * @return \stdClass Updated instance
     */
    public static function persist(\stdClass $instance, $courseid, $title = '') {
        global $DB;

        $courseid = (int) $courseid;
        if ($courseid <= 0) {
            return $instance;
        }

        $changed = empty($instance->coassemblecourseid) || (int) $instance->coassemblecourseid !== $courseid;
        $instance->coassemblecourseid = $courseid;
        $instance->timemodified = time();
        if (empty($instance->timeauthored)) {
            $instance->timeauthored = $instance->timemodified;
        }
        if ($title !== '') {
            $instance->name = $title;
        }
        $DB->update_record('coassemble', $instance);

        if ($changed || $title !== '') {
            rebuild_course_cache((int) $instance->course, true);
        }

        return $instance;
    }

    /**
     * Fallback: find the newest Headless course for this author + tenant and link it.
     *
     * Used when JWT decode failed and no course.updated event arrived.
     *
     * @param \stdClass $instance
     * @param \mod_coassemble\api\client $client
     * @param string $identifier
     * @param string $clientidentifier
     * @return \stdClass
     */
    public static function resolve_from_api(\stdClass $instance, $client, $identifier, $clientidentifier) {
        if (!empty($instance->coassemblecourseid)) {
            return $instance;
        }

        $courses = $client->list_courses([
            'identifier' => $identifier,
            'clientIdentifier' => $clientidentifier,
            'length' => 25,
        ]);

        if (!$courses) {
            return $instance;
        }

        // Prefer the most recently updated / created course.
        usort($courses, function ($a, $b) {
            $at = strtotime((string) ($a['updated'] ?? $a['created'] ?? '')) ?: 0;
            $bt = strtotime((string) ($b['updated'] ?? $b['created'] ?? '')) ?: 0;
            return $bt <=> $at;
        });

        $best = $courses[0];
        if (empty($best['id'])) {
            return $instance;
        }

        return self::persist($instance, (int) $best['id'], (string) ($best['title'] ?? ''));
    }
}
