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
 * Headless / Embed API client for Coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\api;

/**
 * Server-side Coassemble Headless API client.
 *
 * The workspace API key must never be sent to the browser.
 */
class client {
    /** @var string */
    private $apiurl;

    /** @var string */
    private $apikey;

    /**
     * Constructor.
     *
     * @param string|null $apiurl
     * @param string|null $apikey
     */
    public function __construct($apiurl = null, $apikey = null) {
        $this->apiurl = rtrim($apiurl !== null ? $apiurl : (string) get_config('mod_coassemble', 'apiurl'), '/');
        $this->apikey = self::normalise_apikey(
            (string) ($apikey !== null ? $apikey : get_config('mod_coassemble', 'apikey'))
        );
    }

    /**
     * Normalise a pasted API key to the full COASSEMBLE:{workspace}:{secret} form.
     *
     * Coassemble issues keys with the workspace id embedded; tolerate keys
     * pasted without the scheme prefix.
     *
     * @param string $apikey
     * @return string
     */
    public static function normalise_apikey(string $apikey): string {
        $apikey = trim($apikey);
        if ($apikey === '' || strpos($apikey, 'COASSEMBLE:') === 0) {
            return $apikey;
        }
        if (preg_match('/^\d+:[a-f0-9]{64}$/', $apikey)) {
            return 'COASSEMBLE:' . $apikey;
        }
        return $apikey;
    }

    /**
     * Whether credentials appear configured.
     *
     * @return bool
     */
    public function is_configured() {
        return $this->apiurl !== '' && $this->apikey !== '';
    }

    /**
     * Test connectivity, list access, and authoring entitlement.
     *
     * Authoring is verified by minting a temporary edit embed (which creates a
     * draft course server-side), then soft-deleting that course.
     *
     * @return array
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return [
                'ok' => false,
                'message' => get_string('error_notconfigured', 'mod_coassemble'),
            ];
        }

        try {
            $courses = $this->list_courses(['length' => 1]);
            $count = is_array($courses) ? count($courses) : 0;
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'list_ok' => false,
                'authoring_ok' => false,
            ];
        }

        $authoringok = false;
        $authoringmessage = '';
        try {
            $probeid = 'moodle-connection-probe:' . time();
            $embed = $this->issue_course_embed([
                'action' => 'edit',
                'identifier' => $probeid,
                'clientIdentifier' => 'moodle-connection-probe',
                'name' => 'Moodle connection probe',
                'options' => ['back' => 'hidden', 'legacy' => false],
            ]);
            $authoringok = true;
            if (!empty($embed['courseid'])) {
                try {
                    $this->delete_course((int) $embed['courseid']);
                } catch (\Throwable $ignore) {
                    // Probe course left soft-deleted-or-not; non-fatal for the test.
                    debugging('Probe course cleanup failed: ' . $ignore->getMessage(), DEBUG_DEVELOPER);
                }
            }
            $authoringmessage = get_string('connection_authoring_ok', 'mod_coassemble');
        } catch (\Throwable $e) {
            $authoringmessage = $e->getMessage();
        }

        if (!$authoringok) {
            return [
                'ok' => false,
                'message' => get_string('connection_authoring_failed', 'mod_coassemble', $authoringmessage),
                'list_ok' => true,
                'authoring_ok' => false,
                'coursecount' => $count,
            ];
        }

        return [
            'ok' => true,
            'message' => get_string('connection_ok', 'mod_coassemble'),
            'list_ok' => true,
            'authoring_ok' => true,
            'coursecount' => $count,
            'authoring_message' => $authoringmessage,
        ];
    }

    /**
     * List courses from the Headless API.
     *
     * @param array $query
     * @return array
     */
    public function list_courses(array $query = []) {
        $result = $this->request('GET', '/api/v1/headless/courses', null, $query);
        if (!is_array($result)) {
            return [];
        }
        if (isset($result['data']) && is_array($result['data'])) {
            return $result['data'];
        }
        return $result;
    }

    /**
     * Fetch a single course.
     *
     * @param int $courseid
     * @param array $query
     * @return array
     */
    public function get_course($courseid, array $query = []) {
        $result = $this->request('GET', '/api/v1/headless/courses/' . (int) $courseid, null, $query);
        return is_array($result) ? $result : [];
    }

    /**
     * Drop an empty options member from an embed body.
     *
     * PHP encodes an empty array as JSON [] but the API schema requires
     * options to be an object, so an empty one must be omitted entirely.
     *
     * @param array $body
     * @return array
     */
    public static function strip_empty_options(array $body): array {
        if (isset($body['options']) && $body['options'] === []) {
            unset($body['options']);
        }
        return $body;
    }

    /**
     * Headless treats omitted or true options.legacy as Builder 1.
     *
     * @param array $body
     * @return array
     */
    public static function force_builder2_options(array $body): array {
        if (!isset($body['options']) || !is_array($body['options'])) {
            $body['options'] = [];
        }
        $body['options']['legacy'] = false;
        return self::strip_empty_options($body);
    }

    /**
     * Issue a signed course embed URL.
     *
     * @param array $body
     * @return array
     */
    public function issue_course_embed(array $body) {
        $body = self::force_builder2_options($body);
        $url = $this->request('POST', '/api/v1/headless/embed/course', $body, [], true);
        if (!is_string($url) || $url === '') {
            throw new \moodle_exception('error_embedurl', 'mod_coassemble');
        }
        return [
            'url' => $url,
            'courseid' => self::course_id_from_embed_url($url),
        ];
    }

    /**
     * Issue a signed collection embed URL.
     *
     * @param array $body
     * @return string
     */
    public function issue_collection_embed(array $body) {
        $body = self::strip_empty_options($body);
        $url = $this->request('POST', '/api/v1/headless/embed/collection', $body, [], true);
        if (!is_string($url) || $url === '') {
            throw new \moodle_exception('error_embedurl', 'mod_coassemble');
        }
        return $url;
    }

    /**
     * Issue a signed analytics embed URL.
     *
     * @param string $kind course|collection|user
     * @param array $body
     * @return string
     */
    public function issue_analytics_embed($kind, array $body) {
        switch ($kind) {
            case 'course':
                $path = '/api/v1/headless/embed/analytics/course';
                break;
            case 'collection':
                $path = '/api/v1/headless/embed/analytics/collection';
                break;
            case 'user':
                $path = '/api/v1/headless/embed/analytics/user';
                break;
            default:
                throw new \coding_exception('Unknown analytics embed kind: ' . $kind);
        }
        $body = self::strip_empty_options($body);
        $url = $this->request('POST', $path, $body, [], true);
        if (!is_string($url) || $url === '') {
            throw new \moodle_exception('error_embedurl', 'mod_coassemble');
        }
        return $url;
    }

    /**
     * Publish a course.
     *
     * @param int $courseid
     * @return array
     */
    public function publish_course($courseid) {
        $result = $this->request('POST', '/api/v1/headless/course/' . (int) $courseid . '/publish');
        return is_array($result) ? $result : [];
    }

    /**
     * Revert a course to its published version.
     *
     * @param int $courseid
     * @return array
     */
    public function revert_course($courseid) {
        $result = $this->request('POST', '/api/v1/headless/course/' . (int) $courseid . '/revert');
        return is_array($result) ? $result : [];
    }

    /**
     * Duplicate a course.
     *
     * @param int $courseid
     * @param array $body
     * @return array
     */
    public function duplicate_course($courseid, array $body = []) {
        $result = $this->request('POST', '/api/v1/headless/course/' . (int) $courseid . '/duplicate', $body);
        return is_array($result) ? $result : [];
    }

    /**
     * Soft-delete a course.
     *
     * @param int $courseid
     * @return array
     */
    public function delete_course($courseid) {
        $result = $this->request('DELETE', '/api/v1/headless/course/' . (int) $courseid);
        return is_array($result) ? $result : [];
    }

    /**
     * Restore a soft-deleted course.
     *
     * @param int $courseid
     * @return array
     */
    public function restore_course($courseid) {
        $result = $this->request('POST', '/api/v1/headless/course/' . (int) $courseid . '/restore');
        return is_array($result) ? $result : [];
    }

    /**
     * Download a SCORM package (teacher convenience).
     *
     * @param int $courseid
     * @param string $type
     * @param string $version
     * @return string Binary body
     */
    public function export_scorm($courseid, $type = 'dynamic', $version = '2004') {
        return $this->request(
            'GET',
            '/api/v1/headless/course/scorm/' . (int) $courseid,
            null,
            ['type' => $type, 'version' => $version],
            false,
            true
        );
    }

    /**
     * List trackings.
     *
     * @param array $query
     * @return array
     */
    public function list_trackings(array $query = []) {
        $result = $this->request('GET', '/api/v1/headless/trackings', null, $query);
        if (!is_array($result)) {
            return [];
        }
        if (isset($result['data']) && is_array($result['data'])) {
            return $result['data'];
        }
        return $result;
    }

    /**
     * List registered webhook endpoints.
     *
     * @return array ['events' => [...], 'endpoints' => [...]]
     */
    public function list_webhooks() {
        $result = $this->request('GET', '/api/v1/headless/webhooks');
        return is_array($result) ? $result : ['events' => [], 'endpoints' => []];
    }

    /**
     * Register a webhook endpoint. The response includes the signing secret
     * exactly once.
     *
     * @param string $url HTTPS receiver URL
     * @param array $events Event names, e.g. ['course.completed']
     * @return array Endpoint incl. 'secret'
     */
    public function create_webhook($url, array $events) {
        $result = $this->request('POST', '/api/v1/headless/webhooks', [
            'url' => $url,
            'events' => array_values($events),
            'enabled' => true,
        ]);
        if (!is_array($result) || empty($result['secret'])) {
            throw new \moodle_exception('error_webhookregister', 'mod_coassemble');
        }
        return $result;
    }

    /**
     * Delete a webhook endpoint.
     *
     * @param int $endpointid
     * @return void
     */
    public function delete_webhook($endpointid) {
        $this->request('DELETE', '/api/v1/headless/webhooks/' . (int) $endpointid);
    }

    /**
     * List themes.
     *
     * @return array
     */
    public function list_themes() {
        $result = $this->request('GET', '/api/v1/headless/themes');
        if (!is_array($result)) {
            return [];
        }
        if (isset($result['data']) && is_array($result['data'])) {
            return $result['data'];
        }
        return $result;
    }

    /**
     * Decode course id from a signed embed URL JWT payload (unverified read).
     *
     * @param string $url
     * @return int|null
     */
    public static function course_id_from_embed_url($url) {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }
        $parts = explode('/', trim($path, '/'));
        $token = end($parts);
        if (!is_string($token) || substr_count($token, '.') < 2) {
            return null;
        }
        $segments = explode('.', $token);
        $payload = self::base64url_decode($segments[1]);
        if ($payload === null) {
            return null;
        }
        $claims = json_decode($payload, true);
        if (!is_array($claims)) {
            return null;
        }
        if (isset($claims['course']) && is_numeric($claims['course'])) {
            return (int) $claims['course'];
        }
        return null;
    }

    /**
     * Build the Authorization header value.
     *
     * @return string
     */
    private function auth_header() {
        return $this->apikey;
    }

    /**
     * Perform an HTTP request against the Headless API.
     *
     * @param string $method
     * @param string $path
     * @param array|null $body
     * @param array $query
     * @param bool $rawstring
     * @param bool $rawbinary
     * @return mixed
     */
    private function request($method, $path, $body = null, array $query = [], $rawstring = false, $rawbinary = false) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        if (!$this->is_configured()) {
            throw new \moodle_exception('error_notconfigured', 'mod_coassemble');
        }

        $url = $this->apiurl . $path;
        if (!empty($query)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($query);
        }

        $curl = new \curl();
        $headers = [
            'Authorization: ' . $this->auth_header(),
            'Accept: application/json',
        ];
        if ($body !== null || $method === 'POST' || $method === 'PUT') {
            $headers[] = 'Content-Type: application/json';
        }
        $curl->setHeader($headers);
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 60,
            'CURLOPT_CONNECTTIMEOUT' => 15,
        ]);

        $payload = $body !== null ? json_encode($body) : '';
        switch (strtoupper($method)) {
            case 'GET':
                $response = $curl->get($url);
                break;
            case 'POST':
                $response = $curl->post($url, $payload !== '' ? $payload : '{}');
                break;
            case 'PUT':
                $response = $curl->put($url, ['file' => $payload !== '' ? $payload : '{}']);
                break;
            case 'DELETE':
                $response = $curl->delete($url);
                break;
            default:
                throw new \coding_exception('Unsupported HTTP method: ' . $method);
        }

        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        $errno = method_exists($curl, 'get_errno') ? $curl->get_errno() : 0;

        if ($errno) {
            $err = property_exists($curl, 'error') ? $curl->error : 'curl error';
            throw new \moodle_exception('error_apirequest', 'mod_coassemble', '', $err);
        }

        if ($httpcode < 200 || $httpcode >= 300) {
            $message = self::extract_error_message((string) $response);
            if ($message === null) {
                $message = 'HTTP ' . $httpcode;
            }
            throw new \moodle_exception('error_apirequest', 'mod_coassemble', '', $message);
        }

        if ($rawbinary) {
            return $response;
        }

        $decoded = json_decode($response, true);
        if ($rawstring) {
            if (is_string($decoded)) {
                return $decoded;
            }
            $trimmed = trim((string) $response);
            if (strpos($trimmed, 'http') === 0) {
                return trim($response, "\" \n\r\t");
            }
            if (is_array($decoded) && isset($decoded['url']) && is_string($decoded['url'])) {
                return $decoded['url'];
            }
            throw new \moodle_exception('error_embedurl', 'mod_coassemble');
        }

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('error_apirequest', 'mod_coassemble', '', 'Invalid JSON response');
        }

        return $decoded;
    }

    /**
     * Pull a human-readable error message out of an API response body.
     *
     * @param string $response
     * @return string|null
     */
    private static function extract_error_message($response) {
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return null;
        }
        if (isset($decoded['message']) && is_string($decoded['message'])) {
            return $decoded['message'];
        }
        if (isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
            return $decoded['error']['message'];
        }
        return null;
    }

    /**
     * Decode a base64url string, returning null on invalid input.
     *
     * @param string $data
     * @return string|null
     */
    private static function base64url_decode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
