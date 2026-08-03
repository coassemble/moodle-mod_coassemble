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
 * Identity helpers for Headless identifier / clientIdentifier mapping.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\local;

/**
 * Maps Moodle users/courses onto Coassemble Headless identity fields.
 */
class identity {
    /**
     * Stable learner/author identifier for a Moodle user.
     *
     * @param \stdClass $user
     * @return string
     */
    public static function for_user(\stdClass $user): string {
        return 'moodle:' . self::site_hash() . ':' . (int) $user->id;
    }

    /**
     * Short stable hash identifying this Moodle site.
     *
     * @return string
     */
    public static function site_hash(): string {
        global $CFG;
        return !empty($CFG->siteidentifier) ? substr(sha1($CFG->siteidentifier), 0, 12) : 'site';
    }

    /**
     * The tenant clientIdentifier for this Moodle site.
     *
     * One Coassemble headless client per Moodle site (1:1 with the workspace).
     *
     * @return string
     */
    public static function client_identifier(): string {
        return 'moodle-site:' . self::site_hash();
    }

    /**
     * Display name for embed profile fields.
     *
     * @param \stdClass $user
     * @return string
     */
    public static function display_name(\stdClass $user): string {
        return fullname($user);
    }

    /**
     * Avatar URL for embed profile fields.
     *
     * @param \stdClass $user
     * @return string|null
     */
    public static function avatar_url(\stdClass $user): ?string {
        global $PAGE;
        $userpicture = new \user_picture($user);
        $userpicture->size = 1;
        $url = $userpicture->get_url($PAGE);
        return $url ? $url->out(false) : null;
    }

    /**
     * Resolve Moodle user id from a Headless identifier minted by this plugin.
     *
     * @param string $identifier
     * @return int|null
     */
    public static function moodle_user_id_from_identifier(string $identifier): ?int {
        if (!preg_match('/^moodle:([^:]+):(\d+)$/', $identifier, $matches)) {
            return null;
        }
        // Identifiers minted by another Moodle site sharing this workspace
        // must never map onto local user ids.
        if ($matches[1] !== self::site_hash()) {
            return null;
        }
        return (int) $matches[2];
    }
}
