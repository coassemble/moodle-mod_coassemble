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
 * Cached workspace course summaries for the activity index.
 *
 * @package mod_coassemble
 * @copyright 2026 Coassemble
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_coassemble\local;

/**
 * Cache remote titles and publish state without storing user or signed embed data.
 */
class course_metadata {
    /**
     * Isolate cached metadata when the configured workspace or API host changes.
     *
     * @param int $courseid Remote course id
     * @return string
     */
    private static function key(int $courseid): string {
        return hash('sha256', (string) get_config('mod_coassemble', 'apiurl') . ':' .
            (string) get_config('mod_coassemble', 'apikey')) . '_' . $courseid;
    }

    /**
     * Retrieve each distinct remote course at most once per five-minute cache period.
     *
     * Failures are cached too, so an unavailable workspace is not retried per row.
     *
     * @param array $courseids Remote course ids
     * @param \mod_coassemble\api\client $client API client
     * @return array Summaries keyed by remote course id
     */
    public static function get_many(array $courseids, \mod_coassemble\api\client $client): array {
        $cache = \cache::make('mod_coassemble', 'coursemetadata');
        $result = [];
        foreach (array_unique(array_filter(array_map('intval', $courseids))) as $id) {
            $key = self::key($id);
            $metadata = $cache->get($key);
            if ($metadata === false) {
                $metadata = ['available' => false];
                if ($client->is_configured()) {
                    try {
                        $remote = $client->get_course($id);
                        if ((int) ($remote['id'] ?? 0) === $id && empty($remote['deleted'])) {
                            $metadata = ['available' => true, 'title' => (string) ($remote['title'] ?? ''),
                                'published' => !empty($remote['published'])];
                        }
                    } catch (\Throwable $e) {
                        diagnostics::log($e, 'course_metadata');
                    }
                }
                $cache->set($key, $metadata);
            }
            $result[$id] = $metadata;
        }
        return $result;
    }

    /**
     * Expire a course summary after a local content update.
     *
     * @param int $courseid Remote course id
     */
    public static function invalidate(int $courseid): void {
        \cache::make('mod_coassemble', 'coursemetadata')->delete(self::key($courseid));
    }
}
