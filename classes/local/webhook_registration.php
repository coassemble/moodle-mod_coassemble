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
 * Automatic webhook registration against the Coassemble workspace.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coassemble\local;

/**
 * Registers this site's webhook receiver and stores the signing secret.
 *
 * There is no admin UI for webhooks: registration runs automatically when
 * API credentials are saved, and can be re-run from the connection test.
 */
class webhook_registration {
    /** @var string[] Events the plugin consumes. */
    const EVENTS = ['course.commenced', 'course.completed', 'course.created'];

    /** Registered now (secret stored). */
    const STATUS_REGISTERED = 'registered';
    /** Already registered with a stored secret. */
    const STATUS_OK = 'ok';
    /** Site is not https — Coassemble only delivers to https receivers. */
    const STATUS_NEEDSHTTPS = 'needshttps';
    /** API credentials missing. */
    const STATUS_NOTCONFIGURED = 'notconfigured';
    /** Registration attempt failed (API error / plan entitlement). */
    const STATUS_FAILED = 'failed';

    /**
     * Ensure this site's receiver is registered; never throws.
     *
     * @param bool $force Re-register (rotates the secret) even when one is stored
     * @return array {status: string, message: string}
     */
    public static function ensure(bool $force = false): array {
        $client = new \mod_coassemble\api\client();
        if (!$client->is_configured()) {
            return ['status' => self::STATUS_NOTCONFIGURED, 'message' => ''];
        }

        $webhookurl = (new \moodle_url('/mod/coassemble/webhook.php'))->out(false);
        if (strpos($webhookurl, 'https://') !== 0) {
            return ['status' => self::STATUS_NEEDSHTTPS, 'message' => $webhookurl];
        }

        try {
            $existing = $client->list_webhooks();
            $registered = false;
            foreach (($existing['endpoints'] ?? []) as $endpoint) {
                if (!is_array($endpoint) || ($endpoint['url'] ?? '') !== $webhookurl || empty($endpoint['id'])) {
                    continue;
                }
                if (!$force && (string) get_config('mod_coassemble', 'webhooksecret') !== '') {
                    $registered = true;
                    continue;
                }
                // Secret unknown (or rotation forced) — replace the endpoint.
                $client->delete_webhook((int) $endpoint['id']);
            }

            if ($registered) {
                return ['status' => self::STATUS_OK, 'message' => ''];
            }

            $created = $client->create_webhook($webhookurl, self::EVENTS);
            set_config('webhooksecret', (string) $created['secret'], 'mod_coassemble');

            return ['status' => self::STATUS_REGISTERED, 'message' => ''];
        } catch (\Throwable $e) {
            return ['status' => self::STATUS_FAILED, 'message' => $e->getMessage()];
        }
    }
}
