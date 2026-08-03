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

namespace mod_coassemble;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/coassemble/lib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Tests for progress recording, grading and course reset.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::coassemble_record_progress
 * @covers ::coassemble_update_grades
 * @covers ::coassemble_reset_userdata
 */
final class lib_test extends \advanced_testcase {
    /**
     * Create course, activity and enrolled user.
     *
     * @param array $instancerecord Extra fields for the activity
     * @return array [$course, $instance, $user]
     */
    private function setup_activity(array $instancerecord = []): array {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $instance = $this->getDataGenerator()->create_module('coassemble', array_merge([
            'course' => $course->id,
            'coassemblecourseid' => 123,
        ], $instancerecord));
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        return [$course, $instance, $user];
    }

    /**
     * Fetch the single grade for a user on an instance.
     *
     * @param \stdClass $instance
     * @param int $userid
     * @return float|null
     */
    private function get_grade(\stdClass $instance, int $userid): ?float {
        $grades = grade_get_grades($instance->course, 'mod', 'coassemble', $instance->id, $userid);
        $item = reset($grades->items);
        if (!$item || !isset($item->grades[$userid]) || $item->grades[$userid]->grade === null) {
            return null;
        }
        return (float) $item->grades[$userid]->grade;
    }

    /**
     * Progress is monotonic and completion timestamps are set once.
     */
    public function test_record_progress_monotonic(): void {
        global $DB;
        $this->resetAfterTest();
        [, $instance, $user] = $this->setup_activity();

        coassemble_record_progress($instance, $user->id, 40, false, true);
        $track = $DB->get_record('coassemble_track', ['coassembleid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(40.0, (float) $track->progress);
        $this->assertNotEmpty($track->commenced);
        $this->assertEmpty($track->completed);

        // Lower progress must not regress the mirror.
        coassemble_record_progress($instance, $user->id, 10);
        $track = $DB->get_record('coassemble_track', ['coassembleid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(40.0, (float) $track->progress);

        coassemble_record_progress($instance, $user->id, 100, true);
        $track = $DB->get_record('coassemble_track', ['coassembleid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(100.0, (float) $track->progress);
        $this->assertNotEmpty($track->completed);

        $firstcompleted = $track->completed;
        coassemble_record_progress($instance, $user->id, 100, true);
        $track = $DB->get_record('coassemble_track', ['coassembleid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals($firstcompleted, $track->completed);
    }

    /**
     * Score mirrors the best attempt and passed is sticky-improving.
     */
    public function test_record_progress_score(): void {
        global $DB;
        $this->resetAfterTest();
        [, $instance, $user] = $this->setup_activity();

        coassemble_record_progress($instance, $user->id, 50, false, true, 60.0, false);
        $track = $DB->get_record('coassemble_track', ['coassembleid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(60.0, (float) $track->score);
        $this->assertEquals(0, (int) $track->passed);

        // Better attempt improves score and passes.
        coassemble_record_progress($instance, $user->id, 80, false, false, 85.0, true);
        $track = $DB->get_record('coassemble_track', ['coassembleid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(85.0, (float) $track->score);
        $this->assertEquals(1, (int) $track->passed);

        // Worse attempt never downgrades.
        coassemble_record_progress($instance, $user->id, 80, false, false, 30.0, false);
        $track = $DB->get_record('coassemble_track', ['coassembleid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(85.0, (float) $track->score);
        $this->assertEquals(1, (int) $track->passed);
    }

    /**
     * Progress-based grading pushes progress percent of grademax.
     */
    public function test_update_grades_progress_method(): void {
        $this->resetAfterTest();
        [, $instance, $user] = $this->setup_activity(['grade' => 80, 'grademethod' => 0]);

        coassemble_record_progress($instance, $user->id, 50);
        $this->assertEqualsWithDelta(40.0, $this->get_grade($instance, (int) $user->id), 0.001);

        // Completion pushes full marks regardless of mirrored percent.
        coassemble_record_progress($instance, $user->id, 50, true);
        $this->assertEqualsWithDelta(80.0, $this->get_grade($instance, (int) $user->id), 0.001);
    }

    /**
     * Score-based grading only grades once a score exists.
     */
    public function test_update_grades_score_method(): void {
        $this->resetAfterTest();
        [, $instance, $user] = $this->setup_activity(['grade' => 100, 'grademethod' => 1]);

        // Progress alone yields no grade in score mode.
        coassemble_record_progress($instance, $user->id, 90, true);
        $this->assertNull($this->get_grade($instance, (int) $user->id));

        coassemble_record_progress($instance, $user->id, 100, true, false, 72.5, true);
        $this->assertEqualsWithDelta(72.5, $this->get_grade($instance, (int) $user->id), 0.001);
    }

    /**
     * The gradebook regrade callback signature works with a single user and all users.
     */
    public function test_update_grades_core_callback(): void {
        $this->resetAfterTest();
        [$course, $instance, $user] = $this->setup_activity();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        coassemble_record_progress($instance, $user->id, 25);
        coassemble_record_progress($instance, $user2->id, 75);

        // Core regrade path: all users, then one user.
        coassemble_update_grades($instance);
        coassemble_update_grades($instance, (int) $user->id);

        $this->assertEqualsWithDelta(25.0, $this->get_grade($instance, (int) $user->id), 0.001);
        $this->assertEqualsWithDelta(75.0, $this->get_grade($instance, (int) $user2->id), 0.001);
    }

    /**
     * Course reset removes mirrored tracking.
     */
    public function test_reset_userdata(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $instance, $user] = $this->setup_activity();

        coassemble_record_progress($instance, $user->id, 50);
        $this->assertEquals(1, $DB->count_records('coassemble_track', ['coassembleid' => $instance->id]));

        $status = coassemble_reset_userdata((object) [
            'courseid' => $course->id,
            'reset_coassemble_track' => 1,
        ]);

        $this->assertEquals(0, $DB->count_records('coassemble_track', ['coassembleid' => $instance->id]));
        $this->assertFalse($status[0]['error']);
    }
}
