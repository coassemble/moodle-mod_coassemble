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
 * Workspace course browsing and selection.
 *
 * @package mod_coassemble
 * @copyright 2026 Coassemble
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_coassemble\local;

/**

 * Native library queries and server-side eligibility checks.

 */
class course_library {
    /** @var int Number of workspace courses per page. */
    public const PAGE_SIZE = 25;

    /**
     * Fetch one workspace-wide page, without author or tenant filters.
     *
     * @param \mod_coassemble\api\client $client API client
     * @param string $title Title search
     * @param int $page Zero-based page
     * @return array
     */
    public static function page(\mod_coassemble\api\client $client, string $title, int $page): array {
        return $client->list_courses(['title' => trim($title), 'page' => max(0, $page), 'length' => self::PAGE_SIZE]);
    }

    /**
     * Return the language key explaining why a course cannot be selected.
     *
     * @param array $course API course
     * @return string Empty for available courses
     */
    public static function unavailable_reason(array $course): string {
        if (!empty($course['deleted'])) {
            return 'library_deleted';
        }
        if (($course['type'] ?? '') === 'scorm') {
            return 'library_scorm';
        }
        if (!empty($course['legacy'])) {
            return 'library_legacy';
        }
        if (empty($course['id']) || ($course['legacy'] ?? null) !== false || ($course['type'] ?? '') !== 'standard') {
            return 'library_unavailable';
        }
        return '';
    }

    /**
     * Link or duplicate a freshly validated course into an unlinked activity.
     *
     * @param \stdClass $instance Activity
     * @param \mod_coassemble\api\client $client API client
     * @param int $courseid Selected remote course
     * @param bool $copy Whether to duplicate
     * @return \stdClass
     */
    public static function select(\stdClass $instance, \mod_coassemble\api\client $client, int $courseid, bool $copy): \stdClass {
        global $DB, $USER;
        $factory = \core\lock\lock_config::get_lock_factory('mod_coassemble');
        $lock = $factory->get_lock('activity:' . $instance->id, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        $courselock = null;
        try {
            $courselock = $factory->get_lock('course:' . $courseid, 10);
            if (!$courselock) {
                throw new \moodle_exception('locktimeout');
            }
            $instance = $DB->get_record('coassemble', ['id' => $instance->id], '*', MUST_EXIST);
            if (!empty($instance->coassemblecourseid)) {
                throw new \moodle_exception('error_coursechanged', 'mod_coassemble');
            }
            $remote = $client->get_course($courseid);
            $reason = self::unavailable_reason($remote);
            if ($reason !== '') {
                throw new \moodle_exception($reason, 'mod_coassemble');
            }
            if ((int) $remote['id'] !== $courseid) {
                throw new \moodle_exception('error_apirequest', 'mod_coassemble');
            }
            if ($copy) {
                $remote = $client->duplicate_course($courseid, [
                    'identifier' => identity::for_user($USER),
                    'clientIdentifier' => identity::client_identifier(),
                ]);
                if (empty($remote['id']) || (int) $remote['id'] === $courseid) {
                    throw new \moodle_exception('error_apirequest', 'mod_coassemble');
                }
            }
            // Keep the teacher's Moodle activity name; the remote title is displayed separately.
            return course_link::persist($instance, (int) $remote['id'], '', $copy ? 'copied' : 'linked');
        } finally {
            if ($courselock) {
                $courselock->release();
            }
            $lock->release();
        }
    }
}
