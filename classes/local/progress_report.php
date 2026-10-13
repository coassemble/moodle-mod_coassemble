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

namespace mod_coassemble\local;

/**
 * Apply Moodle activity group permissions before retrieving learner results.
 *
 * @package mod_coassemble
 * @copyright 2026 Coassemble
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_report {
    /**
     * Retrieve results for the selected group, enforcing separate groups access.
     *
     * @param \stdClass|\cm_info $cm Course module
     * @param int $groupid Selected activity group, or zero for all allowed groups
     * @return array
     */
    public static function get_tracks($cm, int $groupid): array {
        global $DB;

        $context = \context_module::instance($cm->id);
        require_capability('mod/coassemble:manage', $context);
        $where = 'coassembleid = :instanceid';
        $params = ['instanceid' => $cm->instance];
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode) {
            $allowedgroups = groups_get_activity_allowed_groups($cm);
            if ($groupid && !isset($allowedgroups[$groupid])) {
                throw new \moodle_exception('notingroup');
            }
            $restricted = $groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context);
            if ($groupid || $restricted) {
                $groupids = $groupid ? [$groupid] : array_keys($allowedgroups);
                if (!$groupids) {
                    return [];
                }
                [$insql, $groupparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'reportgroup');
                $where .= " AND userid IN (SELECT userid FROM {groups_members} WHERE groupid $insql)";
                $params += $groupparams;
            }
        }
        return $DB->get_records_select('coassemble_track', $where, $params, 'timemodified DESC');
    }
}
