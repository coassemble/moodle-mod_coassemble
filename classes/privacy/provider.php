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
 * Privacy API implementation for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data this plugin stores or sends.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('coassemble_track', [
            'userid' => 'privacy:metadata:coassemble_track:userid',
            'progress' => 'privacy:metadata:coassemble_track:progress',
            'score' => 'privacy:metadata:coassemble_track:score',
            'passed' => 'privacy:metadata:coassemble_track:passed',
            'completed' => 'privacy:metadata:coassemble_track:completed',
            'commenced' => 'privacy:metadata:coassemble_track:commenced',
            'timemodified' => 'privacy:metadata:coassemble_track:timemodified',
        ], 'privacy:metadata:coassemble_track');

        $collection->add_database_table('coassemble_webhook', [], 'privacy:metadata:coassemble_webhook');

        $collection->add_external_location_link('coassemble', [
            'identifier' => 'privacy:metadata:external:identifier',
            'clientidentifier' => 'privacy:metadata:external:clientidentifier',
            'name' => 'privacy:metadata:external:name',
            'avatar' => 'privacy:metadata:external:avatar',
        ], 'privacy:metadata:external');

        return $collection;
    }

    /**
     * Find module contexts holding data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {coassemble_track} t
                  JOIN {course_modules} cm ON cm.instance = t.coassembleid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE t.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'modname' => 'coassemble',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * List users with data in a module context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT t.userid
                  FROM {coassemble_track} t
                  JOIN {course_modules} cm ON cm.instance = t.coassembleid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'coassemble',
            'cmid' => $context->instanceid,
        ]);
    }

    /**
     * Export mirrored tracking data for the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('coassemble', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $track = $DB->get_record('coassemble_track', [
                'coassembleid' => $cm->instance,
                'userid' => $userid,
            ]);
            if ($track) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'mod_coassemble')],
                    (object) [
                        'progress' => $track->progress,
                        'score' => $track->score,
                        'passed' => $track->passed === null ? null : transform::yesno($track->passed),
                        'commenced' => $track->commenced ? transform::datetime($track->commenced) : null,
                        'completed' => $track->completed ? transform::datetime($track->completed) : null,
                        'timemodified' => transform::datetime($track->timemodified),
                    ]
                );
            }
        }
    }

    /**
     * Delete all tracking data in a module context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('coassemble', $context->instanceid);
        if ($cm) {
            $DB->delete_records('coassemble_track', ['coassembleid' => $cm->instance]);
        }
    }

    /**
     * Delete one user's tracking data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('coassemble', $context->instanceid);
            if ($cm) {
                $DB->delete_records('coassemble_track', [
                    'coassembleid' => $cm->instance,
                    'userid' => $userid,
                ]);
            }
        }
    }

    /**
     * Delete tracking data for the approved users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('coassemble', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['coassembleid'] = $cm->instance;
        $DB->delete_records_select('coassemble_track', "coassembleid = :coassembleid AND userid $insql", $params);
    }
}
