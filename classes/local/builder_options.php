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
 * Course builder features configured for the current author.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\local;

/**
 * Combine site feature settings with the activity's publishing permission.
 */
class builder_options {
    /**
     * Build the server-side options for an authoring embed.
     *
     * @param \context $context Activity context
     * @return array
     */
    public static function for_context(\context $context): array {
        $config = get_config('mod_coassemble');
        return [
            'back' => 'event',
            'legacy' => false,
            'ai' => true,
            'googleDrive' => true,
            'oneDrive' => true,
            'feedback' => true,
            'narrations' => (bool) ($config->narrations ?? true),
            'translations' => (bool) ($config->translations ?? true),
            'brandVoice' => (bool) ($config->brandvoice ?? true),
            'publishing' => has_capability('mod/coassemble:manage', $context),
        ];
    }
}
