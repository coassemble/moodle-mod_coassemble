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
     * @param string|null $linkmode Explicit origin, or preserve the existing origin for the same course
     * @return \stdClass Updated instance
     */
    public static function persist(\stdClass $instance, $courseid, $title = '', ?string $linkmode = null) {
        global $DB;

        $courseid = (int) $courseid;
        if ($courseid <= 0) {
            return $instance;
        }

        $changed = empty($instance->coassemblecourseid) || (int) $instance->coassemblecourseid !== $courseid;
        if ($linkmode !== null && !in_array($linkmode, ['created', 'linked', 'copied'], true)) {
            throw new \coding_exception('Invalid Coassemble link mode');
        }
        // A browser event or recovery cannot establish ownership of an arbitrary course.
        $instance->linkmode = $linkmode ?? ($changed ? 'linked' : ($instance->linkmode ?? 'linked'));
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
     * Count other activities using this remote course, across the Moodle site.
     *
     * @param \stdClass $instance Activity
     * @return int
     */
    public static function other_uses(\stdClass $instance): int {
        global $DB;
        if (empty($instance->coassemblecourseid)) {
            return 0;
        }
        return $DB->count_records_select(
            'coassemble',
            'coassemblecourseid = ? AND id <> ?',
            [(int) $instance->coassemblecourseid, (int) $instance->id]
        );
    }

    /**
     * Detach content and clear local results belonging to that content.
     *
     * @param \stdClass $instance Activity
     * @return \stdClass
     */
    public static function unlink(\stdClass $instance): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/coassemble/lib.php');
        $transaction = $DB->start_delegated_transaction();
        $instance->coassemblecourseid = null;
        $instance->timeauthored = null;
        $instance->linkmode = 'created';
        // Revisiting the empty activity should offer a choice, not create a stub.
        $instance->flow = 'existing';
        $instance->timemodified = time();
        $DB->update_record('coassemble', $instance);
        $DB->delete_records('coassemble_track', ['coassembleid' => $instance->id]);
        coassemble_grade_item_update($instance, 'reset');
        $cm = get_coursemodule_from_instance('coassemble', $instance->id);
        if ($cm) {
            $completion = new \completion_info(get_course($instance->course));
            $completion->delete_all_state($cm);
        }
        $transaction->allow_commit();
        rebuild_course_cache((int) $instance->course, true);
        return $instance;
    }

    /**
     * Delete only an owned course which no other activity currently uses.
     *
     * @param \stdClass $instance Activity
     * @param \mod_coassemble\api\client $client API client
     * @return \stdClass
     */
    public static function delete_remote(\stdClass $instance, \mod_coassemble\api\client $client): \stdClass {
        global $DB;
        $courseid = (int) $instance->coassemblecourseid;
        $factory = \core\lock\lock_config::get_lock_factory('mod_coassemble');
        $lock = $factory->get_lock('course:' . $courseid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $instance = $DB->get_record('coassemble', ['id' => $instance->id], '*', MUST_EXIST);
            if (empty($courseid) || (int) $instance->coassemblecourseid !== $courseid) {
                throw new \moodle_exception('error_coursechanged', 'mod_coassemble');
            }
            if (!in_array($instance->linkmode, ['created', 'copied'], true)) {
                throw new \moodle_exception('manage_delete_linked', 'mod_coassemble');
            }
            $others = self::other_uses($instance);
            if ($others) {
                throw new \moodle_exception('manage_delete_shared', 'mod_coassemble', '', $others);
            }
            $client->delete_course($courseid);
            return self::unlink($instance);
        } finally {
            $lock->release();
        }
    }
}
