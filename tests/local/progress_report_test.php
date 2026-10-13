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

#[\PHPUnit\Framework\Attributes\CoversClass(progress_report::class)]
/**
 * Progress reports must not disclose results across separate activity groups.
 *
 * @package mod_coassemble
 * @copyright 2026 Coassemble
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\local\progress_report
 */
final class progress_report_test extends \advanced_testcase {
    /**
     * Create two groups with results and a teacher allowed to report on group one.
     *
     * @param int $groupmode Activity group mode
     * @return array
     */
    private function setup_report(int $groupmode = SEPARATEGROUPS): array {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('coassemble', ['course' => $course->id, 'groupmode' => $groupmode]);
        $cm = get_coursemodule_from_instance('coassemble', $instance->id);
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $context = \context_module::instance($cm->id);
        $roles = get_archetype_roles('teacher');
        $role = reset($roles);
        assign_capability('mod/coassemble:manage', CAP_ALLOW, $role->id, $context->id);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $role->id, $context->id);
        $groups = [];
        $users = [];
        for ($i = 0; $i < 2; $i++) {
            $groups[] = $generator->create_group(['courseid' => $course->id]);
            $users[] = $generator->create_user();
            $generator->enrol_user($users[$i]->id, $course->id, 'student');
            groups_add_member($groups[$i], $users[$i]);
            $DB->insert_record('coassemble_track', (object) [
                'coassembleid' => $instance->id, 'userid' => $users[$i]->id, 'progress' => 50, 'timemodified' => time(),
            ]);
        }
        groups_add_member($groups[0], $teacher);
        $this->setUser($teacher);
        return [$course, $cm, $teacher, $groups, $users];
    }

    /**
     * All-groups requests remain limited to the teacher's own separate groups.
     */
    public function test_separate_groups_restrict_results(): void {
        [, $cm, , $groups, $users] = $this->setup_report();
        foreach ([0, (int) $groups[0]->id] as $selection) {
            $tracks = progress_report::get_tracks($cm, $selection);
            $this->assertCount(1, $tracks);
            $this->assertEquals($users[0]->id, reset($tracks)->userid);
        }
        $this->expectException(\moodle_exception::class);
        progress_report::get_tracks($cm, (int) $groups[1]->id);
    }

    /**
     * Report pages use cm_info objects rather than raw course-module records.
     */
    public function test_course_module_info_is_supported(): void {
        [, $cm] = $this->setup_report();
        $info = get_fast_modinfo($cm->course)->get_cm($cm->id);
        $this->assertCount(1, progress_report::get_tracks($info, 0));
    }

    /**
     * A teacher with no allowed groups sees no learner results.
     */
    public function test_no_group_membership_does_not_mean_all_groups(): void {
        [, $cm, $teacher, $groups] = $this->setup_report();
        groups_remove_member($groups[0], $teacher);
        $this->assertSame([], progress_report::get_tracks($cm, 0));
    }

    /**
     * Grouping restrictions apply even when the teacher belongs to another group.
     */
    public function test_grouping_limits_allowed_groups(): void {
        global $DB;
        [$course, $cm] = $this->setup_report();
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        $cm->groupingid = $grouping->id;
        $DB->set_field('course_modules', 'groupingid', $grouping->id, ['id' => $cm->id]);
        $this->assertSame([], progress_report::get_tracks($cm, 0));
    }

    /**
     * Site-wide reporters can see all groups and explicitly filter one group.
     */
    public function test_access_all_groups_can_view_and_filter_results(): void {
        [, $cm, , $groups, $users] = $this->setup_report();
        $this->setAdminUser();
        $this->assertCount(2, progress_report::get_tracks($cm, 0));
        $tracks = progress_report::get_tracks($cm, (int) $groups[1]->id);
        $this->assertCount(1, $tracks);
        $this->assertEquals($users[1]->id, reset($tracks)->userid);
    }

    /**
     * Visible groups allow cross-group reporting and an optional group filter.
     */
    public function test_visible_groups_can_view_and_filter_results(): void {
        [, $cm, , $groups, $users] = $this->setup_report(VISIBLEGROUPS);
        $this->assertCount(2, progress_report::get_tracks($cm, 0));
        $tracks = progress_report::get_tracks($cm, (int) $groups[1]->id);
        $this->assertEquals($users[1]->id, reset($tracks)->userid);
    }

    /**
     * Course-level forced separate groups override the activity's own setting.
     */
    public function test_forced_course_group_mode_restricts_results(): void {
        global $DB;
        [$course, $cm] = $this->setup_report(NOGROUPS);
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $course->id]);
        $DB->set_field('course', 'groupmodeforce', 1, ['id' => $course->id]);
        rebuild_course_cache($course->id, true);
        $this->assertCount(1, progress_report::get_tracks($cm, 0));
    }

    /**
     * Activities without groups preserve the existing complete report.
     */
    public function test_no_groups_shows_all_results(): void {
        [, $cm] = $this->setup_report(NOGROUPS);
        $this->assertCount(2, progress_report::get_tracks($cm, 0));
    }

    /**
     * Learners cannot access report data directly.
     */
    public function test_report_requires_manage_capability(): void {
        [, $cm, , , $users] = $this->setup_report();
        $this->setUser($users[0]);
        $this->expectException(\required_capability_exception::class);
        progress_report::get_tracks($cm, 0);
    }
}
