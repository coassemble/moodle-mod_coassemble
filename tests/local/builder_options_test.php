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

#[\PHPUnit\Framework\Attributes\CoversClass(builder_options::class)]
/**
 * Builder features honour site settings and Moodle publishing permissions.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\local\builder_options
 */
final class builder_options_test extends \advanced_testcase {
    /**
     * Existing sites get enabled features before the new settings are saved.
     */
    public function test_features_default_on_when_settings_are_missing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        foreach (['ai', 'googledrive', 'onedrive', 'narrations', 'translations', 'brandvoice'] as $feature) {
            unset_config($feature, 'mod_coassemble');
        }
        $options = builder_options::for_context(\context_system::instance());
        foreach (['ai', 'googleDrive', 'oneDrive', 'narrations', 'translations', 'brandVoice', 'publishing'] as $feature) {
            $this->assertTrue($options[$feature]);
        }
    }

    /**
     * An explicit disabled setting must survive the default-on fallback.
     */
    public function test_features_can_be_disabled_independently(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $features = [
            'ai' => 'ai', 'googledrive' => 'googleDrive', 'onedrive' => 'oneDrive',
            'narrations' => 'narrations', 'translations' => 'translations', 'brandvoice' => 'brandVoice',
        ];
        foreach (array_keys($features) as $disabled) {
            foreach (array_keys($features) as $setting) {
                set_config($setting, $setting === $disabled ? '0' : '1', 'mod_coassemble');
            }
            $options = builder_options::for_context(\context_system::instance());
            foreach ($features as $setting => $feature) {
                $this->assertSame($setting !== $disabled, $options[$feature]);
            }
            $this->assertTrue($options['publishing']);
        }
    }

    /**
     * Disabling AI removes the creation choice and blocks saved or forged requests.
     */
    public function test_ai_creation_obeys_current_settings(): void {
        $this->resetAfterTest();
        unset_config('ai', 'mod_coassemble');
        $this->assertArrayHasKey('ai', builder_options::creation_flows());
        builder_options::require_creation_flow('ai');
        set_config('ai', 0, 'mod_coassemble');
        $this->assertSame(['', 'existing'], array_keys(builder_options::creation_flows()));
        builder_options::require_creation_flow('');
        builder_options::require_creation_flow('existing');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_creationflow', 'mod_coassemble'));
        builder_options::require_creation_flow('ai');
    }

    /**
     * Author-only roles cannot publish, including when manage is allowed elsewhere.
     */
    public function test_publishing_uses_the_current_activity_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('coassemble', ['course' => $course->id]);
        $context = \context_module::instance($activity->cmid);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $this->assertTrue(builder_options::for_context($context)['publishing']);

        $role = get_archetype_roles('editingteacher');
        $role = reset($role);
        assign_capability('mod/coassemble:manage', CAP_PROHIBIT, $role->id, $context->id);
        $this->assertTrue(has_capability('mod/coassemble:author', $context));
        $this->assertFalse(builder_options::for_context($context)['publishing']);
    }
}
