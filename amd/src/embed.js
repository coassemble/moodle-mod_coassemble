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
 * Coassemble iframe host: origin-checked postMessage handling.
 *
 * @module     mod_coassemble/embed
 * @copyright  2026 Coassemble
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    /**
     * Parse a postMessage payload into an object, tolerating JSON strings.
     *
     * @param {*} data
     * @return {Object|null}
     */
    const parsePayload = (data) => {
        if (!data) {
            return null;
        }
        if (typeof data === 'string') {
            try {
                const parsed = JSON.parse(data);
                return typeof parsed === 'object' && parsed !== null ? parsed : null;
            } catch (e) {
                return null;
            }
        }
        if (typeof data === 'object') {
            return data;
        }
        return null;
    };

    /**
     * Extract the raw progress value from a course event payload.
     *
     * @param {Object} payload
     * @return {number|null}
     */
    const extractProgress = (payload) => {
        let raw = null;
        if (payload.data && typeof payload.data.progress !== 'undefined') {
            raw = Number(payload.data.progress);
        } else if (typeof payload.progress !== 'undefined') {
            raw = Number(payload.progress);
        }
        return Number.isFinite(raw) ? raw : null;
    };

    /**
     * Classify a course event payload.
     *
     * @param {Object} payload
     * @return {{isProgress: boolean, isCompleted: boolean, isCommenced: boolean, progress: number|null}}
     */
    const classifyCourseEvent = (payload) => {
        const eventName = (payload.event || payload.status || '').toString().toLowerCase();

        return {
            isProgress: eventName === 'progress',
            isCompleted: eventName === 'completed' || eventName === 'complete',
            isCommenced: eventName === 'commenced' || eventName === 'start',
            progress: extractProgress(payload),
        };
    };

    /**
     * Show a session status message.
     *
     * @param {HTMLElement|null} el
     * @param {string} message
     * @param {string} kind
     */
    const setStatus = (el, message, kind) => {
        if (!el || !message) {
            return;
        }
        el.textContent = message;
        el.className = 'coassemble-session-status coassemble-session-status--' + kind;
        el.hidden = false;
    };

    /**
     * Handle a session lifecycle event.
     *
     * @param {Object} payload
     * @param {HTMLElement|null} statusEl
     * @param {Object} strings
     */
    const handleSessionEvent = (payload, statusEl, strings) => {
        const status = (payload.event || payload.status || '').toString().toLowerCase();
        if (status === 'ready') {
            setStatus(statusEl, strings.ready || 'Ready', 'ready');
        } else if (status === 'expired') {
            setStatus(statusEl, strings.expired || 'Session expired — reload the page.', 'expired');
        } else if (status === 'error') {
            setStatus(statusEl, strings.error || 'Embed error', 'error');
        }
    };

    /**
     * Persist the linked course when the builder reports one.
     *
     * @param {Object} payload
     * @param {Object} config
     */
    const handleCourseEditEvent = (payload, config) => {
        const eventName = (payload.event || '').toString().toLowerCase();
        const isLinkEvent = eventName === 'created' || eventName === 'updated' || eventName === 'generated';
        if (!isLinkEvent || !payload.data || !payload.data.id) {
            return;
        }
        Ajax.call([{
            methodname: 'mod_coassemble_update_course',
            args: {
                cmid: config.cmid,
                courseid: Number(payload.data.id),
                title: payload.data.title ? String(payload.data.title) : '',
            },
        }])[0].catch(() => {
            // Non-fatal: the server-side resolve fallback recovers the link.
        });
    };

    /**
     * Trigger a server-side progress sync when the player reports activity.
     *
     * The server ignores client-reported values and pulls authoritative
     * trackings from Coassemble, so the event is only a sync trigger.
     *
     * @param {Object} payload
     * @param {Object} config
     */
    const handleCourseViewEvent = (payload, config) => {
        const info = classifyCourseEvent(payload);
        if (!info.isProgress && !info.isCompleted && !info.isCommenced) {
            return;
        }
        Ajax.call([{
            methodname: 'mod_coassemble_update_progress',
            args: {cmid: config.cmid},
        }])[0].catch(Notification.exception);
    };

    /**
     * Initialise origin-checked message handling for the embed iframe.
     *
     * @param {Object} config
     */
    const init = (config) => {
        const iframe = document.getElementById(config.iframeId);
        if (!iframe) {
            return;
        }
        const statusEl = config.statusElId ? document.getElementById(config.statusElId) : null;
        const strings = config.strings || {};

        const onMessage = (event) => {
            if (config.expectedOrigin && event.origin !== config.expectedOrigin) {
                return;
            }
            if (event.source !== iframe.contentWindow) {
                return;
            }

            const payload = parsePayload(event.data);
            if (!payload || typeof payload.type === 'undefined') {
                return;
            }

            if (payload.type === 'back') {
                if (config.backUrl) {
                    window.location.href = config.backUrl;
                }
            } else if (payload.type === 'session') {
                handleSessionEvent(payload, statusEl, strings);
            } else if (payload.type === 'course' && config.mode === 'edit' && config.persistCourse) {
                handleCourseEditEvent(payload, config);
            } else if (payload.type === 'course' && config.mode === 'view' && config.syncProgress) {
                handleCourseViewEvent(payload, config);
            }
        };

        window.addEventListener('message', onMessage);
    };

    return {init};
});
