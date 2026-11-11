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

#[\PHPUnit\Framework\Attributes\CoversClass(connect::class)]
/**
 * Connect Coassemble handshake: state, PKCE, access and key handling.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_coassemble\local\connect
 */
final class connect_test extends \advanced_testcase {
    /** @var string Key the fake token endpoint issues. */
    const ISSUED_KEY = 'COASSEMBLE:42:0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    /** @var array Authorization server metadata served by the fake API. */
    const METADATA = [
        'issuer' => 'https://api.example.com',
        'authorization_endpoint' => 'https://api.example.com/oauth/authorize',
        'token_endpoint' => 'https://api.example.com/oauth/token',
        'coassemble_connect_resource' => 'https://app.example.com/api/connect',
    ];

    /**
     * A \curl double.
     *
     * @return \curl|\PHPUnit\Framework\MockObject\MockObject
     */
    private function mock_curl() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = $this->getMockBuilder(\curl::class)->disableOriginalConstructor()
            ->onlyMethods(['setHeader', 'setopt', 'get', 'post', 'get_info', 'get_errno'])->getMock();
        $curl->method('get_errno')->willReturn(0);
        return $curl;
    }

    /**
     * A connect instance whose HTTP transport is the given double.
     *
     * @param \curl $curl
     * @param string $apiurl
     * @return connect
     */
    private function connect_with(\curl $curl, string $apiurl = 'https://api.example.com'): connect {
        $connect = $this->getMockBuilder(connect::class)->setConstructorArgs([$apiurl])
            ->onlyMethods(['create_curl'])->getMock();
        $connect->method('create_curl')->willReturn($curl);
        return $connect;
    }

    /**
     * Start an attempt against the fake discovery document, as the admin unless someone is logged in.
     *
     * @return \moodle_url Authorization URL
     */
    private function start(): \moodle_url {
        if (!isloggedin()) {
            $this->setAdminUser();
        }
        $curl = $this->mock_curl();
        $curl->expects($this->once())->method('get')
            ->with('https://api.example.com/.well-known/oauth-authorization-server')
            ->willReturn(json_encode(self::METADATA));
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        return $this->connect_with($curl)->start();
    }

    /**
     * PKCE S256 matches the RFC 7636 appendix B example.
     */
    public function test_pkce_challenge(): void {
        $this->assertSame(
            'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            connect::pkce_challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk')
        );
    }

    /**
     * The client ID metadata document names its own URL and an https return on the same host.
     */
    public function test_client_metadata(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->wwwroot = 'https://moodle.example.com/lms';

        $doc = connect::client_metadata();
        $this->assertSame('https://moodle.example.com/lms/mod/coassemble/connect/client.php', $doc['client_id']);
        $this->assertSame(connect::client_id(), $doc['client_id']);
        $this->assertSame(['https://moodle.example.com/lms/mod/coassemble/connect/return.php'], $doc['redirect_uris']);
        $this->assertSame(parse_url($doc['client_id'], PHP_URL_HOST), parse_url($doc['redirect_uris'][0], PHP_URL_HOST));
        $this->assertSame('https', parse_url($doc['redirect_uris'][0], PHP_URL_SCHEME));
        $this->assertSame(['authorization_code'], $doc['grant_types']);
        $this->assertSame(['code'], $doc['response_types']);
        $this->assertSame('none', $doc['token_endpoint_auth_method']);
        $this->assertSame('https://moodle.example.com/lms', $doc['client_uri']);
        $this->assertNotSame('', $doc['client_name']);
        $this->assertSame(
            ['client_id', 'client_name', 'redirect_uris', 'grant_types', 'response_types', 'token_endpoint_auth_method',
                'client_uri'],
            array_keys($doc)
        );
    }

    /**
     * Connect refuses to start on a site that is not served over https.
     */
    public function test_start_requires_https(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->wwwroot = 'http://moodle.example.com';
        $curl = $this->mock_curl();
        $curl->expects($this->never())->method('get');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('connect_error_https', 'mod_coassemble'));
        $this->connect_with($curl)->start();
    }

    /**
     * Discovery without the Connect resource is a clear admin-facing error.
     */
    public function test_start_requires_connect_resource(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $metadata = self::METADATA;
        unset($metadata['coassemble_connect_resource']);
        $curl = $this->mock_curl();
        $curl->method('get')->willReturn(json_encode($metadata));
        $curl->method('get_info')->willReturn(['http_code' => 200]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('connect_error_discovery', 'mod_coassemble', 'https://api.example.com'));
        $this->connect_with($curl)->start();
    }

    /**
     * Connect refuses to start when the API base URL is not https.
     */
    public function test_start_requires_https_apiurl(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $curl = $this->mock_curl();
        $curl->expects($this->never())->method('get');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('connect_error_apiurl_https', 'mod_coassemble'));
        $this->connect_with($curl, 'http://api.example.com')->start();
    }

    /**
     * Metadata that would send the code or verifier anywhere but the configured API.
     *
     * @return array
     */
    public static function untrusted_metadata_provider(): array {
        $other = function (array $changes): array {
            return [array_filter(array_merge(self::METADATA, $changes), fn($value) => $value !== null), 200];
        };
        return [
            'missing issuer' => $other(['issuer' => null]),
            'issuer on another host' => $other(['issuer' => 'https://evil.example.com']),
            'issuer on another port' => $other(['issuer' => 'https://api.example.com:8443']),
            'issuer over http' => $other(['issuer' => 'http://api.example.com']),
            'issuer with a path' => $other(['issuer' => 'https://api.example.com/tenant']),
            'authorization endpoint on another host' => $other([
                'authorization_endpoint' => 'https://evil.example.com/oauth/authorize',
            ]),
            'token endpoint on another host' => $other(['token_endpoint' => 'https://evil.example.com/oauth/token']),
            'token endpoint on another port' => $other(['token_endpoint' => 'https://api.example.com:8443/oauth/token']),
            'token endpoint lookalike host' => $other(['token_endpoint' => 'https://api.example.com.evil.example/oauth/token']),
            'token endpoint with user info' => $other(['token_endpoint' => 'https://api.example.com@evil.example.com/oauth/token']),
            'token endpoint over http' => $other(['token_endpoint' => 'http://api.example.com/oauth/token']),
            'connect resource over http' => $other(['coassemble_connect_resource' => 'http://app.example.com/api/connect']),
            'redirect response' => [[], 302],
        ];
    }

    /**
     * Untrusted discovery is refused before any attempt is stored.
     *
     * @dataProvider untrusted_metadata_provider
     * @param array $metadata
     * @param int $httpcode
     */
    public function test_start_rejects_untrusted_metadata(array $metadata, int $httpcode): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->redirectEvents();
        $curl = $this->mock_curl();
        $curl->method('get')->willReturn($metadata ? json_encode($metadata) : '');
        $curl->method('get_info')->willReturn(['http_code' => $httpcode]);

        try {
            $this->connect_with($curl)->start();
            $this->fail('Untrusted metadata must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('connect_error_discovery', $e->errorcode);
        }
        $this->assertFalse(isset($SESSION->{connect::SESSION_KEY}));
    }

    /**
     * The issuer may differ from the API URL only by a trailing slash or an explicit default port.
     */
    public function test_start_accepts_equivalent_issuer(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        foreach (['https://api.example.com/', 'https://API.example.com:443'] as $issuer) {
            $curl = $this->mock_curl();
            $curl->method('get')->willReturn(json_encode(['issuer' => $issuer] + self::METADATA));
            $curl->method('get_info')->willReturn(['http_code' => 200]);
            $url = $this->connect_with($curl, 'https://api.example.com/')->start();
            $this->assertSame('https://api.example.com/oauth/authorize', $url->out_omit_querystring());
        }
    }

    /**
     * Discovery and the token exchange verify TLS and never follow redirects.
     */
    public function test_transport_verifies_tls_without_redirects(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        $expected = [
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_MAXREDIRS' => 0,
        ];

        $options = [];
        $curl = $this->mock_curl();
        $curl->method('setopt')->willReturnCallback(function ($set) use (&$options) {
            $options = array_merge($options, $set);
        });
        $curl->method('get')->willReturn(json_encode(self::METADATA));
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $this->connect_with($curl)->start();
        foreach ($expected as $name => $value) {
            $this->assertSame($value, $options[$name], 'discovery ' . $name);
        }

        $pending = connect::consume_state($SESSION->{connect::SESSION_KEY}['state']);
        $options = [];
        $curl = $this->mock_curl();
        $curl->method('setopt')->willReturnCallback(function ($set) use (&$options) {
            $options = array_merge($options, $set);
        });
        $curl->method('post')->willReturn(json_encode(['api_key' => self::ISSUED_KEY]));
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $this->connect_with($curl)->complete($pending, 'one-time-code');
        foreach ($expected as $name => $value) {
            $this->assertSame($value, $options[$name], 'token ' . $name);
        }

        // On Moodle's own \curl, the CA bundle Moodle chose is kept.
        $method = new \ReflectionMethod(connect::class, 'transport');
        $method->setAccessible(true);
        $real = $method->invoke(new connect('https://api.example.com'));
        $property = new \ReflectionProperty(\curl::class, 'options');
        $property->setAccessible(true);
        $options = $property->getValue($real);
        foreach ($expected as $name => $value) {
            $this->assertSame($value, $options[$name], 'real ' . $name);
        }
        $cacert = \curl::get_cacert();
        if ($cacert) {
            $this->assertSame($cacert, $options['CURLOPT_CAINFO']);
        }
    }

    /**
     * A redirect from the token endpoint is a failure, not something to follow.
     */
    public function test_complete_rejects_redirect(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->redirectEvents();
        $this->start();
        $pending = connect::consume_state($SESSION->{connect::SESSION_KEY}['state']);

        $curl = $this->mock_curl();
        $curl->expects($this->once())->method('post')->willReturn('');
        $curl->method('get_info')->willReturn(['http_code' => 307]);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('connect_error_failed', 'mod_coassemble'));
        $this->connect_with($curl)->complete($pending, 'one-time-code');
    }

    /**
     * The authorization URL carries the PKCE challenge and no API key.
     */
    public function test_start_authorization_url(): void {
        global $SESSION;
        $this->resetAfterTest();
        set_config('apikey', 'COASSEMBLE:7:existing-secret-key', 'mod_coassemble');

        $url = $this->start();
        $pending = $SESSION->{connect::SESSION_KEY};

        $this->assertSame('https://api.example.com/oauth/authorize', $url->out_omit_querystring());
        $this->assertSame('code', $url->param('response_type'));
        $this->assertSame(connect::client_id(), $url->param('client_id'));
        $this->assertSame(connect::redirect_uri(), $url->param('redirect_uri'));
        $this->assertSame($pending['state'], $url->param('state'));
        $this->assertSame(connect::pkce_challenge($pending['verifier']), $url->param('code_challenge'));
        $this->assertSame('S256', $url->param('code_challenge_method'));
        $this->assertSame('https://app.example.com/api/connect', $url->param('resource'));
        $this->assertSame('moodle', $url->param('channel'));
        $this->assertNull($url->param('code_verifier'));

        $this->assertGreaterThanOrEqual(43, strlen($pending['verifier']));
        $this->assertLessThanOrEqual(128, strlen($pending['verifier']));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $pending['verifier']);
        $this->assertGreaterThanOrEqual(32, strlen($pending['state']));
        $this->assertStringNotContainsString('existing-secret-key', $url->out(false));
    }

    /**
     * Only the latest attempt's state is accepted, and only once.
     */
    public function test_state_is_single_use(): void {
        global $SESSION;
        $this->resetAfterTest();

        // Missing or wrong state is refused, and leaves the genuine attempt usable.
        $this->start();
        $state = $SESSION->{connect::SESSION_KEY}['state'];
        $this->assertNull(connect::consume_state(''));
        $this->assertNull(connect::consume_state('not-the-state'));
        $this->assertNull(connect::consume_state(substr($state, 0, -1)));
        $this->assertIsArray(connect::consume_state($state));

        // An earlier attempt is replaced by a later one.
        $this->start();
        $first = $SESSION->{connect::SESSION_KEY}['state'];
        $this->start();
        $second = $SESSION->{connect::SESSION_KEY}['state'];
        $this->assertNotSame($first, $second);
        $this->assertNull(connect::consume_state($first));

        // Correct state, once.
        $this->start();
        $state = $SESSION->{connect::SESSION_KEY}['state'];
        $pending = connect::consume_state($state);
        $this->assertIsArray($pending);
        $this->assertSame('https://api.example.com/oauth/token', $pending['tokenendpoint']);
        $this->assertNull(connect::consume_state($state));

        // Nothing started in this session.
        $this->assertNull(connect::consume_state($state));
    }

    /**
     * An attempt left too long is refused, and cleared.
     */
    public function test_state_expires(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->start();
        $state = $SESSION->{connect::SESSION_KEY}['state'];
        $SESSION->{connect::SESSION_KEY}['time'] = time() - connect::ATTEMPT_TTL - 1;
        $this->assertNull(connect::consume_state($state));
        $this->assertFalse(isset($SESSION->{connect::SESSION_KEY}));
    }

    /**
     * Only the administrator who started an attempt can complete it.
     */
    public function test_state_bound_to_user(): void {
        global $SESSION;
        $this->resetAfterTest();
        $starter = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $this->setUser($starter);
        $this->start();
        $state = $SESSION->{connect::SESSION_KEY}['state'];
        $this->assertSame((int) $starter->id, $SESSION->{connect::SESSION_KEY}['userid']);

        // Same session data, different user: refused, and the attempt survives.
        $this->setUser($other);
        $this->assertNull(connect::consume_state($state));
        $this->setUser(null);
        $this->assertNull(connect::consume_state($state));

        $this->setUser($starter);
        $this->assertIsArray(connect::consume_state($state));
    }

    /**
     * The code is swapped by POST body; the key is saved and never put in a URL.
     */
    public function test_complete_exchanges_code_by_post(): void {
        global $SESSION;
        $this->resetAfterTest();
        $authorizeurl = $this->start();
        $pending = connect::consume_state($SESSION->{connect::SESSION_KEY}['state']);

        $posted = null;
        $curl = $this->mock_curl();
        $curl->expects($this->never())->method('get');
        $curl->expects($this->once())->method('post')
            ->willReturnCallback(function ($url, $body) use (&$posted) {
                $posted = ['url' => $url, 'body' => $body];
                return json_encode([
                    'access_token' => self::ISSUED_KEY,
                    'token_type' => 'Bearer',
                    'api_key' => self::ISSUED_KEY,
                    'workspace' => ['id' => 42, 'name' => 'Acme Learning'],
                    'key_mode' => 'test',
                ]);
            });
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $this->connect_with($curl)->complete($pending, 'one-time-code');

        // Sent to the discovered token endpoint, with everything in the form body.
        $this->assertSame('https://api.example.com/oauth/token', $posted['url']);
        parse_str($posted['body'], $form);
        $this->assertSame([
            'grant_type' => 'authorization_code',
            'code' => 'one-time-code',
            'code_verifier' => $pending['verifier'],
            'client_id' => connect::client_id(),
            'redirect_uri' => connect::redirect_uri(),
        ], $form);

        $this->assertSame(self::ISSUED_KEY, get_config('mod_coassemble', 'apikey'));
        $this->assertTrue(connect::is_connected());
        $this->assertSame('Acme Learning', get_config('mod_coassemble', 'connectworkspace'));
        $this->assertStringNotContainsString(self::ISSUED_KEY, (string) get_config('mod_coassemble', 'connectkeyhash'));

        // Neither the start redirect nor the final redirect carries the key.
        foreach ([$authorizeurl, connect::settings_url()] as $url) {
            $this->assertStringNotContainsString('COASSEMBLE:', urldecode($url->out(false)));
        }
    }

    /**
     * A refused exchange leaves the existing key alone and reports nothing upstream.
     */
    public function test_complete_failure_keeps_key(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->redirectEvents();
        set_config('apikey', 'COASSEMBLE:7:existing-secret-key', 'mod_coassemble');
        $this->start();
        $pending = connect::consume_state($SESSION->{connect::SESSION_KEY}['state']);

        $curl = $this->mock_curl();
        $curl->method('post')->willReturn('{"error":"invalid_grant","error_description":"upstream-detail"}');
        $curl->method('get_info')->willReturn(['http_code' => 400]);
        try {
            $this->connect_with($curl)->complete($pending, 'used-code');
            $this->fail('Exchange should fail');
        } catch (\moodle_exception $e) {
            $this->assertSame('connect_error_failed', $e->errorcode);
            $this->assertStringNotContainsString('upstream-detail', $e->getMessage());
        }
        $this->assertSame('COASSEMBLE:7:existing-secret-key', get_config('mod_coassemble', 'apikey'));
        $this->assertFalse(connect::is_connected());
    }

    /**
     * Pasting a different key forgets that Connect set the key.
     */
    public function test_pasted_key_replaces_connect_marker(): void {
        $this->resetAfterTest();
        set_config('apikey', self::ISSUED_KEY, 'mod_coassemble');
        set_config('connectkeyhash', hash('sha256', self::ISSUED_KEY), 'mod_coassemble');
        $this->assertTrue(connect::is_connected());

        // Saving the same key keeps the marker.
        connect::forget_if_key_replaced();
        $this->assertTrue(connect::is_connected());

        set_config('apikey', 'COASSEMBLE:42:' . str_repeat('f', 64), 'mod_coassemble');
        $this->assertFalse(connect::is_connected());
        connect::forget_if_key_replaced();
        $this->assertFalse(get_config('mod_coassemble', 'connectkeyhash'));
    }

    /**
     * The start and return pages are for site administrators only.
     */
    public function test_require_admin(): void {
        $this->resetAfterTest();

        $this->setAdminUser();
        connect::require_admin();

        $this->setUser($this->getDataGenerator()->create_user());
        try {
            connect::require_admin();
            $this->fail('A non-admin must not pass');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        $this->setUser(null);
        $this->expectException(\moodle_exception::class);
        connect::require_admin();
    }

    /**
     * The manage link goes to the developer console API keys list.
     */
    public function test_manage_url(): void {
        $this->resetAfterTest();
        $this->assertSame('https://coassemble.com/dashboard/developer?tab=api-keys', connect::manage_url()->out(false));
        set_config('appurl', 'https://learn.example.com/', 'mod_coassemble');
        $this->assertSame('https://learn.example.com/dashboard/developer?tab=api-keys', connect::manage_url()->out(false));
    }
}
