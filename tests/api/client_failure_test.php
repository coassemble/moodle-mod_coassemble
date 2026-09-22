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

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_coassemble\api\client::class)]
/**
 * Failed connection tests must not expose upstream exception text.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\api\client
 */
final class client_failure_test extends \advanced_testcase {
    /**
     * List failures are generic in the admin connection-test result.
     */
    public function test_connection_list_failure_is_generic(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $client = $this->getMockBuilder(client::class)
            ->setConstructorArgs(['https://example.com', 'test-key'])
            ->onlyMethods(['list_courses'])->getMock();
        $client->method('list_courses')->willThrowException(new \RuntimeException('secret-token'));
        $result = $client->test_connection();
        $this->assertFalse($result['ok']);
        $this->assertSame(get_string('error_apirequest', 'mod_coassemble'), $result['message']);
        $this->assertStringNotContainsString('secret-token', json_encode($result));
    }

    /**
     * Authoring failures must not return raw diagnostics after list access succeeds.
     */
    public function test_connection_authoring_failure_is_generic(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $client = $this->getMockBuilder(client::class)
            ->setConstructorArgs(['https://example.com', 'test-key'])
            ->onlyMethods(['list_courses', 'issue_course_embed'])->getMock();
        $client->method('list_courses')->willReturn([]);
        $client->method('issue_course_embed')->willThrowException(new \RuntimeException('secret-token'));
        $result = $client->test_connection();
        $this->assertFalse($result['ok']);
        $this->assertTrue($result['list_ok']);
        $this->assertStringNotContainsString('secret-token', json_encode($result));
    }
    /**
     * Moodle's HTML query separator must never leak into server-side API requests.
     */
    public function test_query_parameters_use_http_separators(): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $this->resetAfterTest();
        $separator = ini_get('arg_separator.output');
        ini_set('arg_separator.output', '&amp;');
        try {
            $curl = $this->getMockBuilder(\curl::class)->disableOriginalConstructor()
                ->onlyMethods(['setHeader', 'setopt', 'get', 'get_info', 'get_errno'])->getMock();
            $curl->expects($this->once())->method('get')->with(
                'https://example.com/api/v1/headless/courses?title=Health+%26+safety&page=2&length=25'
            )->willReturn('[]');
            $curl->method('get_info')->willReturn(['http_code' => 200]);
            $curl->method('get_errno')->willReturn(0);
            $client = $this->getMockBuilder(client::class)->setConstructorArgs(['https://example.com', 'fixture'])
                ->onlyMethods(['create_curl'])->getMock();
            $client->method('create_curl')->willReturn($curl);
            $this->assertSame([], $client->list_courses(['title' => 'Health & safety', 'page' => 2, 'length' => 25]));
        } finally {
            ini_set('arg_separator.output', $separator);
        }
    }
}
