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

/**
 * Connect Coassemble: OAuth handshake that issues this site's API key.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\local;

/**
 * Authorization code + PKCE handshake against Coassemble's sign-in server.
 *
 * The site registers itself with a public client ID metadata document
 * (connect/client.php). The admin approves the site in Coassemble, and the
 * returned one-time code is swapped server to server for an API key. The key
 * never travels through the browser, a URL, a notification or a log.
 */
class connect {
    /** @var string $SESSION property holding the pending attempt. */
    const SESSION_KEY = 'mod_coassemble_connect';

    /** @var int Seconds a started attempt stays valid. */
    const ATTEMPT_TTL = 1800;

    /** @var int Seconds connection details are reused on the settings page. */
    const DETAILS_TTL = 300;

    /** @var string Default Coassemble app origin (developer console links). */
    const DEFAULT_APPURL = 'https://coassemble.com';

    /** @var string */
    private $apiurl;

    /**
     * Constructor.
     *
     * @param string|null $apiurl Coassemble API base URL; defaults to the plugin setting
     */
    public function __construct(?string $apiurl = null) {
        $apiurl = $apiurl !== null ? $apiurl : (string) get_config('mod_coassemble', 'apiurl');
        $this->apiurl = rtrim($apiurl !== '' ? $apiurl : 'https://api.coassemble.com', '/');
    }

    /**
     * Require a logged-in site administrator.
     *
     * @return void
     */
    public static function require_admin(): void {
        require_login();
        require_capability('moodle/site:config', \context_system::instance());
    }

    /**
     * The client ID metadata document URL, which is also the OAuth client_id.
     *
     * @return string
     */
    public static function client_id(): string {
        return (new \moodle_url('/mod/coassemble/connect/client.php'))->out(false);
    }

    /**
     * The registered return address.
     *
     * @return string
     */
    public static function redirect_uri(): string {
        return (new \moodle_url('/mod/coassemble/connect/return.php'))->out(false);
    }

    /**
     * Plugin settings page.
     *
     * @return \moodle_url
     */
    public static function settings_url(): \moodle_url {
        return new \moodle_url('/admin/settings.php', ['section' => 'modsettingcoassemble']);
    }

    /**
     * Coassemble only returns to https addresses on the metadata document's host.
     *
     * @return bool
     */
    public static function site_is_https(): bool {
        global $CFG;
        return strpos($CFG->wwwroot, 'https://') === 0;
    }

    /**
     * The API key travels from the token endpoint, so the API URL must be https.
     *
     * @return bool
     */
    public function api_is_https(): bool {
        return self::https_origin($this->apiurl) !== null;
    }

    /**
     * The public client ID metadata document.
     *
     * @return array
     */
    public static function client_metadata(): array {
        global $CFG, $SITE;
        $name = format_string($SITE->fullname, true, ['context' => \context_system::instance(), 'escape' => false]);
        return [
            'client_id' => self::client_id(),
            'client_name' => trim(strip_tags($name)) !== '' ? trim(strip_tags($name)) : 'Moodle',
            'redirect_uris' => [self::redirect_uri()],
            'grant_types' => ['authorization_code'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'client_uri' => $CFG->wwwroot,
        ];
    }

    /**
     * PKCE S256 challenge for a verifier.
     *
     * @param string $verifier
     * @return string base64url(sha256(verifier)) without padding
     */
    public static function pkce_challenge(string $verifier): string {
        return self::base64url(hash('sha256', $verifier, true));
    }

    /**
     * Begin an attempt and return the Coassemble authorization URL.
     *
     * Starting again replaces any earlier attempt in this session, so only the
     * latest one can complete, and only for the administrator who started it.
     *
     * @return \moodle_url
     */
    public function start(): \moodle_url {
        global $SESSION, $USER;

        if (!self::site_is_https()) {
            throw new \moodle_exception('connect_error_https', 'mod_coassemble');
        }
        if (!$this->api_is_https()) {
            throw new \moodle_exception('connect_error_apiurl_https', 'mod_coassemble');
        }
        $metadata = $this->discover();

        $state = self::base64url(random_bytes(32));
        $verifier = self::base64url(random_bytes(48));
        $SESSION->{self::SESSION_KEY} = [
            'state' => $state,
            'verifier' => $verifier,
            'tokenendpoint' => $metadata['token_endpoint'],
            'userid' => (int) $USER->id,
            'time' => time(),
        ];

        return new \moodle_url($metadata['authorization_endpoint'], [
            'response_type' => 'code',
            'client_id' => self::client_id(),
            'redirect_uri' => self::redirect_uri(),
            'state' => $state,
            'code_challenge' => self::pkce_challenge($verifier),
            'code_challenge_method' => 'S256',
            'resource' => $metadata['coassemble_connect_resource'],
            'channel' => 'moodle',
        ]);
    }

    /**
     * Take the pending attempt if the returned state matches it.
     *
     * The attempt is cleared only once the state matches, so a forged return
     * can't cancel a genuine attempt. A matched state is usable once.
     *
     * @param string $state State returned by Coassemble
     * @return array|null The attempt, or null when the state is wrong, reused, expired
     *     or was started by another user
     */
    public static function consume_state(string $state): ?array {
        global $SESSION, $USER;

        $pending = $SESSION->{self::SESSION_KEY} ?? null;
        if (!is_array($pending) || empty($pending['state']) || $state === '') {
            return null;
        }
        if (!hash_equals((string) $pending['state'], $state)) {
            return null;
        }
        if (empty($USER->id) || (int) ($pending['userid'] ?? 0) !== (int) $USER->id) {
            return null;
        }

        unset($SESSION->{self::SESSION_KEY});
        if ((int) ($pending['time'] ?? 0) < time() - self::ATTEMPT_TTL) {
            return null;
        }
        return $pending;
    }

    /**
     * Swap the code for an API key and save it as this site's key.
     *
     * @param array $pending Attempt returned by consume_state()
     * @param string $code Authorization code
     * @return void
     */
    public function complete(array $pending, string $code): void {
        global $SESSION;

        if ($code === '') {
            throw new \moodle_exception('connect_error_failed', 'mod_coassemble');
        }
        $result = $this->exchange_code($pending, $code);

        set_config('apikey', $result['apikey'], 'mod_coassemble');
        set_config('connectkeyhash', self::key_hash($result['apikey']), 'mod_coassemble');
        set_config('connectworkspace', $result['workspace'], 'mod_coassemble');
        unset($SESSION->{self::SESSION_KEY . '_details'});
    }

    /**
     * Whether the current API key is the one Connect set.
     *
     * @return bool
     */
    public static function is_connected(): bool {
        $stored = (string) get_config('mod_coassemble', 'connectkeyhash');
        $apikey = (string) get_config('mod_coassemble', 'apikey');
        if ($stored === '' || trim($apikey) === '') {
            return false;
        }
        return hash_equals($stored, self::key_hash($apikey));
    }

    /**
     * Forget the Connect marker once an admin pastes a different key.
     *
     * @return void
     */
    public static function forget_if_key_replaced(): void {
        if (!self::is_connected()) {
            unset_config('connectkeyhash', 'mod_coassemble');
            unset_config('connectworkspace', 'mod_coassemble');
        }
    }

    /**
     * Connection details for the settings page, reused briefly per session.
     *
     * Never throws: returns null when Coassemble cannot be reached.
     *
     * @param \mod_coassemble\api\client|null $client
     * @return array|null
     */
    public static function connection_details(?\mod_coassemble\api\client $client = null): ?array {
        global $SESSION;

        $cachekey = self::SESSION_KEY . '_details';
        $keyhash = self::key_hash((string) get_config('mod_coassemble', 'apikey'));
        $cached = $SESSION->{$cachekey} ?? null;
        if (is_array($cached) && ($cached['keyhash'] ?? '') === $keyhash && $cached['time'] > time() - self::DETAILS_TTL) {
            return $cached['details'];
        }

        $details = null;
        try {
            $client = $client ?? new \mod_coassemble\api\client();
            $result = $client->get_connection();
            if (!empty($result['workspace']) && is_array($result['workspace'])) {
                $details = $result;
            }
        } catch (\Throwable $e) {
            diagnostics::log($e, 'connect_details');
        }

        // Only successes are reused, so reloading after a failure tries again.
        if ($details !== null) {
            $SESSION->{$cachekey} = ['keyhash' => $keyhash, 'time' => time(), 'details' => $details];
        }
        return $details;
    }

    /**
     * Developer console page listing the workspace's API keys.
     *
     * @return \moodle_url
     */
    public static function manage_url(): \moodle_url {
        $appurl = rtrim((string) get_config('mod_coassemble', 'appurl'), '/');
        if ($appurl === '') {
            $appurl = self::DEFAULT_APPURL;
        }
        return new \moodle_url($appurl . '/dashboard/developer', ['tab' => 'api-keys']);
    }

    /**
     * Fetch the authorization server metadata from the configured API URL.
     *
     * The metadata must name the API URL's origin as its issuer, and both
     * endpoints must be on that origin, so the code and verifier only ever go
     * to the configured API. The Connect resource is the app, which may be a
     * different origin.
     *
     * @return array authorization_endpoint, token_endpoint, coassemble_connect_resource
     */
    protected function discover(): array {
        $curl = $this->transport();
        $curl->setHeader(['Accept: application/json']);
        $response = $curl->get($this->apiurl . '/.well-known/oauth-authorization-server');

        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        $errno = method_exists($curl, 'get_errno') ? $curl->get_errno() : 0;
        $metadata = json_decode((string) $response, true);

        $valid = !$errno && $httpcode >= 200 && $httpcode < 300 && is_array($metadata);
        foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'coassemble_connect_resource'] as $field) {
            if (!$valid || !is_string($metadata[$field] ?? null) || self::https_origin($metadata[$field]) === null) {
                $valid = false;
                break;
            }
        }
        if ($valid) {
            $origin = self::https_origin($this->apiurl);
            $issuerpath = (string) parse_url($metadata['issuer'], PHP_URL_PATH);
            $valid = $origin !== null
                && self::https_origin($metadata['issuer']) === $origin
                && ($issuerpath === '' || $issuerpath === '/')
                && parse_url($metadata['issuer'], PHP_URL_QUERY) === null
                && self::https_origin($metadata['authorization_endpoint']) === $origin
                && self::https_origin($metadata['token_endpoint']) === $origin;
        }
        if (!$valid) {
            diagnostics::record('connect_discovery', '', $httpcode, $errno);
            throw new \moodle_exception('connect_error_discovery', 'mod_coassemble', '', s($this->apiurl));
        }
        return $metadata;
    }

    /**
     * POST the code and verifier to the token endpoint.
     *
     * @param array $pending
     * @param string $code
     * @return array apikey, workspace
     */
    protected function exchange_code(array $pending, string $code): array {
        $curl = $this->transport();
        $curl->setHeader([
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        $response = $curl->post((string) $pending['tokenendpoint'], http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => (string) $pending['verifier'],
            'client_id' => self::client_id(),
            'redirect_uri' => self::redirect_uri(),
        ], '', '&'));

        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        $errno = method_exists($curl, 'get_errno') ? $curl->get_errno() : 0;
        $token = json_decode((string) $response, true);
        $apikey = is_array($token) && is_string($token['api_key'] ?? null)
            ? \mod_coassemble\api\client::normalise_apikey($token['api_key']) : '';

        if ($errno || $httpcode < 200 || $httpcode >= 300 || strpos($apikey, 'COASSEMBLE:') !== 0) {
            // Never log the response: on success it carries the key.
            diagnostics::record('connect_token', '', $httpcode, $errno);
            throw new \moodle_exception('connect_error_failed', 'mod_coassemble');
        }

        $workspace = is_array($token['workspace'] ?? null) ? $token['workspace'] : [];
        return [
            'apikey' => $apikey,
            'workspace' => is_string($workspace['name'] ?? null) ? $workspace['name'] : '',
        ];
    }

    /**
     * Create the Moodle HTTP transport.
     *
     * @return \curl
     */
    protected function create_curl(): \curl {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        return new \curl();
    }

    /**
     * HTTP transport for discovery and the token exchange.
     *
     * Moodle's \curl does not verify peers by default and follows redirects;
     * both are turned off here. The CA bundle stays Moodle's own (CURLOPT_CAINFO).
     *
     * @return \curl
     */
    protected function transport(): \curl {
        $curl = $this->create_curl();
        $curl->setopt([
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_MAXREDIRS' => 0,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 15,
        ]);
        return $curl;
    }

    /**
     * Origin of an absolute https URL, with the default port made explicit.
     *
     * @param string $url
     * @return string|null scheme://host:port, or null unless $url is https with a host and
     *     no user info, fragment, whitespace or backslash
     */
    private static function https_origin(string $url): ?string {
        if (preg_match('/[\s\\\\]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return null;
        }
        return 'https://' . strtolower($parts['host']) . ':' . (int) ($parts['port'] ?? 443);
    }

    /**
     * One-way fingerprint of a key, so the marker never stores the key twice.
     *
     * @param string $apikey
     * @return string
     */
    private static function key_hash(string $apikey): string {
        return hash('sha256', \mod_coassemble\api\client::normalise_apikey($apikey));
    }

    /**
     * Base64url encode without padding.
     *
     * @param string $data
     * @return string
     */
    private static function base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
