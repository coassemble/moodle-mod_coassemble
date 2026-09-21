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
 * Form submission security tests.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\local\action
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_coassemble\local\action::class)]
final class action_test extends \advanced_testcase {
    /**
     * Restore request globals after each test.
     */
    protected function tearDown(): void {
        $_POST = [];
        $_GET = [];
        parent::tearDown();
    }

    /**
     * A valid sesskey in a GET request must not authorise a state change.
     */
    public function test_get_with_valid_sesskey_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST = [];
        $_GET = ['sesskey' => sesskey()];
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_postrequired', 'mod_coassemble'));
        action::require_post();
    }

    /**
     * A forged POST must fail before an API call or a database write.
     */
    public function test_post_with_invalid_sesskey_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST = ['sesskey' => 'forged'];
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidsesskey', 'error'));
        action::require_post();
    }

    /**
     * A submitted Moodle form is accepted.
     */
    public function test_post_with_valid_sesskey_is_accepted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST = ['sesskey' => sesskey()];
        action::require_post();
        $this->assertTrue(true);
    }
}
