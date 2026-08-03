/**
 * Coassemble iframe host: origin-checked postMessage handling.
 *
 * @module     mod_coassemble/embed
 * @copyright  2026 Coassemble
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification'], function(Notification) {
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
     * POST url-encoded params to a plugin ajax endpoint.
     *
     * @param {string} url
     * @param {URLSearchParams} body
     * @return {Promise}
     */
    const postForm = (url, body) => fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body.toString(),
        credentials: 'same-origin',
    });

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
        const body = new URLSearchParams();
        body.set('cmid', String(config.cmid));
        body.set('sesskey', config.sesskey);
        body.set('courseid', String(payload.data.id));
        if (payload.data.title) {
            body.set('title', String(payload.data.title));
        }
        postForm(config.updateCourseUrl, body).catch(() => {
            // Non-fatal: the server-side resolve fallback recovers the link.
        });
    };

    /**
     * Trigger a server-side progress sync when the player reports activity.
     *
     * @param {Object} payload
     * @param {Object} config
     */
    const handleCourseViewEvent = (payload, config) => {
        const info = classifyCourseEvent(payload);
        if (!info.isProgress && !info.isCompleted && !info.isCommenced) {
            return;
        }
        const body = new URLSearchParams();
        body.set('cmid', String(config.cmid));
        body.set('sesskey', config.sesskey);
        if (info.progress !== null) {
            body.set('progress', String(info.progress));
        }
        if (info.isCommenced) {
            body.set('commenced', '1');
        }
        if (info.isCompleted) {
            body.set('completed', '1');
            if (info.progress === null) {
                body.set('progress', '100');
            }
        }
        postForm(config.progressUrl, body).catch((err) => {
            Notification.exception(err);
        });
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
            } else if (payload.type === 'course' && config.mode === 'edit') {
                handleCourseEditEvent(payload, config);
            } else if (payload.type === 'course' && config.mode === 'view' && config.progressUrl) {
                handleCourseViewEvent(payload, config);
            }
        };

        window.addEventListener('message', onMessage);
    };

    return {init};
});
