# Support runbook — mod_coassemble

## Common failures

### 403 from Headless API / “FORBIDDEN”

- Workspace plan missing `api` or `api_authoring`.
- API key regenerated but Moodle settings not updated.
- Authoring embed called without authoring entitlement.

**Fix:** Confirm plan entitlements in Coassemble; re-test connection; retry builder.

### “Coassemble API credentials are not configured”

- Empty workspace id or API key in Site administration → Plugins → Coassemble.

### Embed iframe blank / blocked

- Moodle theme or CSP blocking third-party frames.
- Coassemble origin allowlist does not include the Moodle site origin.
- Mixed content (Moodle HTTPS embedding HTTP API/site URL).

**Fix:** Allow the Coassemble site origin in CSP `frame-src`; ensure Moodle and Coassemble are HTTPS; add Moodle origin in Coassemble workspace allowlist if enforced.

### Signed URL / session expired mid-authoring

- Embed JWT lifetime is finite (currently ~3 hours on issue).
- Teacher left the builder open too long.

**Fix:** Reload the activity (mints a new URL). Persist work in the builder before long idle periods.

### Course created in Coassemble but Moodle still says “not linked”

- Rare if JWT decode failed and no `course.updated` postMessage arrived.

**Fix:** Open **Manage content** is empty → reopen builder; or paste Coassemble course id into the activity settings field `coassemblecourseid`, save, reopen.

### Completion / grades not updating

1. Confirm learner finished the course (player `course.complete` / `course.completed`).
2. Check browser console for blocked postMessage / wrong origin.
3. Configure webhook secret + Coassemble webhook pointing at `/mod/coassemble/webhook.php`.
4. Use **Refresh learner progress** on Manage content (pulls `GET /v1/headless/trackings`).

### Webhook 401 Invalid signature

- Secret in Moodle does not match Coassemble endpoint secret.
- Body was parsed/modified before HMAC (must use raw body).
- Clock skew &gt; 5 minutes on Moodle server.

### Webhook OK but not mapped

- `identifier` was not minted by this plugin (`moodle:<site>:<userid>`).
- `courseId` in payload does not match any activity’s `coassemblecourseid`.

### SCORM download fails

- SCORM export is a teacher convenience, not the primary delivery path.
- Requires appropriate Coassemble entitlements (`api_authoring` / static SCORM rules on the API).

## Logging

- Moodle: enable developer debug briefly and reproduce.
- Coassemble: check API request logs / webhook delivery history in workspace settings.

## Escalation checklist

1. Moodle version + plugin `version.php` release
2. API base URL / workspace id (not the API key)
3. Whether failure is authoring, viewing, grading, or webhooks
4. Exact HTTP status + response message from Headless
5. Whether postMessage events fire in the browser
