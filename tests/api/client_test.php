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

namespace mod_coassemble\api;

/**
 * Tests for API key handling.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\api\client
 */
final class client_test extends \basic_testcase {
    /**
     * Pasted keys are normalised to the full COASSEMBLE:{workspace}:{secret} form.
     */
    public function test_normalise_apikey(): void {
        $hex = str_repeat('a', 64);

        // Full key is kept as-is.
        $this->assertSame("COASSEMBLE:123:$hex", client::normalise_apikey("COASSEMBLE:123:$hex"));

        // Key pasted without the scheme prefix gains it.
        $this->assertSame("COASSEMBLE:123:$hex", client::normalise_apikey("123:$hex"));

        // Whitespace is trimmed.
        $this->assertSame("COASSEMBLE:123:$hex", client::normalise_apikey("  COASSEMBLE:123:$hex\n"));

        // Anything else is left untouched (and will fail auth server-side).
        $this->assertSame('not-a-key', client::normalise_apikey('not-a-key'));
        $this->assertSame('', client::normalise_apikey(''));
    }

    /**
     * Empty options must be omitted: PHP encodes [] as a JSON array, but the
     * embed API requires options to be an object.
     */
    public function test_strip_empty_options(): void {
        $this->assertSame(
            ['action' => 'view', 'courseId' => 1],
            client::strip_empty_options(['action' => 'view', 'courseId' => 1, 'options' => []])
        );

        // Non-empty options are preserved.
        $body = ['action' => 'edit', 'options' => ['back' => 'event']];
        $this->assertSame($body, client::strip_empty_options($body));

        // Absent options are untouched.
        $this->assertSame(['action' => 'view'], client::strip_empty_options(['action' => 'view']));
    }
}
