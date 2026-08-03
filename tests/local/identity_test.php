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
 * Tests for identity mapping.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\local\identity
 */
final class identity_test extends \advanced_testcase {
    /**
     * A minted identifier maps back to the same user id.
     */
    public function test_identifier_round_trip(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $identifier = identity::for_user($user);

        $this->assertMatchesRegularExpression('/^moodle:[0-9a-f]{12}:\d+$/', $identifier);
        $this->assertSame((int) $user->id, identity::moodle_user_id_from_identifier($identifier));
    }

    /**
     * Identifiers minted by a different Moodle site must not map to local users.
     */
    public function test_foreign_site_identifier_rejected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $foreign = 'moodle:aaaaaaaaaaaa:' . $user->id;
        // Guard against the astronomically unlikely case the real hash is all a's.
        if (strpos(identity::for_user($user), ':aaaaaaaaaaaa:') !== false) {
            $foreign = 'moodle:bbbbbbbbbbbb:' . $user->id;
        }

        $this->assertNull(identity::moodle_user_id_from_identifier($foreign));
    }

    /**
     * Malformed identifiers are rejected.
     */
    public function test_malformed_identifiers_rejected(): void {
        $this->assertNull(identity::moodle_user_id_from_identifier(''));
        $this->assertNull(identity::moodle_user_id_from_identifier('someone@example.com'));
        $this->assertNull(identity::moodle_user_id_from_identifier('moodle:5'));
        $this->assertNull(identity::moodle_user_id_from_identifier('moodle:abc:def'));
    }

    /**
     * The tenant identifier is fixed to this Moodle site.
     */
    public function test_client_identifier(): void {
        $this->assertSame('moodle-site:' . identity::site_hash(), identity::client_identifier());
    }
}
