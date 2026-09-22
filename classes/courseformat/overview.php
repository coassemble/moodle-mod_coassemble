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
 * Coassemble information on Moodle's Activities overview.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\courseformat;

use core_courseformat\local\overview\overviewitem;
use mod_coassemble\api\client;
use mod_coassemble\local\course_metadata;

/**
 * Show author metadata while retaining Moodle's completion and grade columns.
 */
class overview extends \core_courseformat\activityoverviewbase {
    /**
     * Create an activity overview with the shared API client.
     *
     * @param \cm_info $cm Course module
     * @param client $client API client
     */
    public function __construct(
        \cm_info $cm,
        /** @var client API client */
        protected readonly client $client
    ) {
        parent::__construct($cm);
    }

    /**
     * Whether the current user may see the activity's remote metadata.
     *
     * @return bool
     */
    private function can_manage(): bool {
        return $this->cm->uservisible && has_capability('mod/coassemble:manage', $this->context);
    }

    /**
     * Remote title, publication state and relationship for managers.
     *
     * @return overviewitem[]
     */
    public function get_extra_overview_items(): array {
        global $DB;
        if (!$this->can_manage()) {
            return [];
        }
        $instance = $DB->get_record('coassemble', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $title = get_string('notyetlinked', 'mod_coassemble');
        $status = '';
        $origin = '';
        if (!empty($instance->coassemblecourseid)) {
            // Shared activities and the legacy index reuse the same short-lived metadata cache.
            $metadata = course_metadata::get_many([(int) $instance->coassemblecourseid], $this->client);
            $remote = $metadata[$instance->coassemblecourseid] ?? [];
            $title = !empty($remote['available']) ? $remote['title'] : get_string('metadata_unavailable', 'mod_coassemble');
            $status = !empty($remote['available'])
                ? get_string($remote['published'] ? 'library_published' : 'library_draft', 'mod_coassemble') : '';
            $origin = get_string('origin_' . $instance->linkmode, 'mod_coassemble');
        }
        return [
            'title' => new overviewitem(get_string('manage_remote_title', 'mod_coassemble'), $title, s($title)),
            'status' => new overviewitem(get_string('library_status', 'mod_coassemble'), $status, s($status)),
            'relationship' => new overviewitem(get_string('linkmode', 'mod_coassemble'), $origin, s($origin)),
        ];
    }

    /**
     * Link managers to this activity's learner progress report.
     *
     * @return overviewitem|null
     */
    public function get_actions_overview(): ?overviewitem {
        if (!$this->can_manage()) {
            return null;
        }
        return new overviewitem(
            get_string('actions'),
            '',
            \html_writer::link(
                new \moodle_url('/mod/coassemble/report.php', ['id' => $this->cm->id]),
                get_string('nav_report', 'mod_coassemble')
            )
        );
    }
}
