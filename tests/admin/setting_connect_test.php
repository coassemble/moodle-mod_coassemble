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

namespace mod_coassemble\admin;

#[\PHPUnit\Framework\Attributes\CoversClass(setting_connect::class)]
/**
 * Connect to Coassemble section of the settings page.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\admin\setting_connect
 */
final class setting_connect_test extends \advanced_testcase {
    /** @var string A key set by Connect. */
    const KEY = 'COASSEMBLE:42:0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    /**
     * Mark the current key as set by Connect.
     */
    private function connect_key(): void {
        set_config('apikey', self::KEY, 'mod_coassemble');
        set_config('connectkeyhash', hash('sha256', self::KEY), 'mod_coassemble');
        set_config('connectworkspace', 'Acme Learning', 'mod_coassemble');
    }

    /**
     * An API client double.
     *
     * @return \mod_coassemble\api\client|\PHPUnit\Framework\MockObject\MockObject
     */
    private function client() {
        return $this->getMockBuilder(\mod_coassemble\api\client::class)
            ->setConstructorArgs(['https://api.example.com', self::KEY])
            ->onlyMethods(['get_connection'])->getMock();
    }

    /**
     * A pasted key keeps offering Connect, without calling Coassemble.
     */
    public function test_not_connected(): void {
        $this->resetAfterTest();
        set_config('apikey', self::KEY, 'mod_coassemble');
        $client = $this->client();
        $client->expects($this->never())->method('get_connection');

        $context = setting_connect::export_context($client);
        $this->assertFalse($context['connected']);
        $this->assertSame(get_string('connect_button', 'mod_coassemble'), $context['button']);
        $this->assertSame(get_string('connect_intro', 'mod_coassemble'), $context['intro']);
        $this->assertArrayNotHasKey('manageurl', $context);
    }

    /**
     * A test key on a connected site shows the Test mode badge note.
     */
    public function test_connected_test_key(): void {
        $this->resetAfterTest();
        $this->connect_key();
        $client = $this->client();
        $client->expects($this->once())->method('get_connection')->willReturn([
            'workspace' => ['id' => 42, 'name' => 'Acme Learning'],
            'plan' => ['name' => 'Sandbox', 'badge' => 'sandbox'],
            'key' => ['name' => 'Moodle: moodle.example.com', 'mode' => 'test', 'prefix' => 'COASSEMBLE:42:0123'],
            'connection' => [
                'siteHost' => 'moodle.example.com',
                'connectedBy' => ['name' => 'Sam Lee'],
                'connectedAt' => '2026-11-11T02:00:00.000Z',
            ],
        ]);

        $context = setting_connect::export_context($client);
        $this->assertTrue($context['connected']);
        $this->assertSame('Connected to Acme Learning', $context['heading']);
        $this->assertSame('Sandbox workspace · test key · learners see a Test mode badge', $context['summary']);
        $this->assertStringStartsWith('Connected by Sam Lee on ', $context['connectedby']);
        $this->assertStringEndsWith('· key “Moodle: moodle.example.com”', $context['connectedby']);
        $this->assertSame(get_string('connect_reconnect', 'mod_coassemble'), $context['button']);
        $this->assertSame('https://coassemble.com/dashboard/developer?tab=api-keys', $context['manageurl']);
        $this->assertStringNotContainsString(self::KEY, json_encode($context));

        // Reused for the next page view.
        setting_connect::export_context($client);
    }

    /**
     * A live key has no badge note.
     */
    public function test_connected_live_key(): void {
        $this->resetAfterTest();
        $this->connect_key();
        $client = $this->client();
        $client->method('get_connection')->willReturn([
            'workspace' => ['id' => 42, 'name' => 'Acme Learning'],
            'plan' => ['name' => 'Business', 'badge' => 'business'],
            'key' => ['name' => 'Moodle: moodle.example.com', 'mode' => 'production', 'prefix' => 'COASSEMBLE:42:0123'],
            'connection' => null,
        ]);

        $context = setting_connect::export_context($client);
        $this->assertSame('Business workspace · live key', $context['summary']);
        $this->assertArrayNotHasKey('connectedby', $context);
    }

    /**
     * An unreachable API still renders the section from what Connect stored.
     */
    public function test_connected_details_unavailable(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $this->connect_key();
        $client = $this->client();
        $client->method('get_connection')->willThrowException(new \moodle_exception('error_apirequest', 'mod_coassemble'));

        $context = setting_connect::export_context($client);
        $this->assertTrue($context['connected']);
        $this->assertSame('Connected to Acme Learning', $context['heading']);
        $this->assertSame(get_string('connect_details_unavailable', 'mod_coassemble'), $context['unavailable']);
        $this->assertArrayNotHasKey('summary', $context);
    }
}
