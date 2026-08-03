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

namespace mod_coassemble\completion;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/coassemble/lib.php');

/**
 * Tests for the custom completion rule.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\completion\custom_completion
 */
final class custom_completion_test extends \advanced_testcase {
    /**
     * The rule flips from incomplete to complete when mirrored tracking completes.
     */
    public function test_get_state(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $instance = $this->getDataGenerator()->create_module('coassemble', [
            'course' => $course->id,
            'coassemblecourseid' => 123,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completioncourse' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $cm = get_fast_modinfo($course, $user->id)->get_cm($instance->cmid);
        $completion = new custom_completion($cm, (int) $user->id);

        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completioncourse'));

        coassemble_record_progress($instance, $user->id, 100, true);

        // Fresh modinfo to avoid cached completion state.
        $cm = get_fast_modinfo($course, $user->id)->get_cm($instance->cmid);
        $completion = new custom_completion($cm, (int) $user->id);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_state('completioncourse'));

        // The activity-level completion state reflects it too.
        $completioninfo = new \completion_info($course);
        $data = $completioninfo->get_data($cm, false, (int) $user->id);
        $this->assertEquals(COMPLETION_COMPLETE, (int) $data->completionstate);
    }

    /**
     * Rule metadata is declared correctly.
     */
    public function test_rule_definitions(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $instance = $this->getDataGenerator()->create_module('coassemble', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completioncourse' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $cm = get_fast_modinfo($course, $user->id)->get_cm($instance->cmid);
        $completion = new custom_completion($cm, (int) $user->id);

        $this->assertSame(['completioncourse'], custom_completion::get_defined_custom_rules());
        $this->assertArrayHasKey('completioncourse', $completion->get_custom_rule_descriptions());
        $this->assertContains('completioncourse', $completion->get_sort_order());
    }
}
