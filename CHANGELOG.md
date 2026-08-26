# Changelog

## 1.3.0 — 2026-08-26

### Changed
- New Moodle-created courses use Builder 2. Every course embed sets `options.legacy: false`.
- Create flow choices are now start from scratch and generate with AI. Document and presentation flows are no longer offered.

## 1.2.0 — 2026-08-05

Addresses the Moodle plugins directory review feedback.

### Security
- **Webhook deliveries now always require a verified signature.** Deliveries are rejected with `403` while no signing secret is stored (registration is automatic when API credentials are saved, and can be repaired from the connection test); the previous accept-unauthenticated fallback is removed. Progress still syncs through the authenticated player flow while webhooks are unregistered.

### Changed
- **AJAX endpoints migrated to External Services.** `update_course.php` and `update_progress.php` are replaced by the `mod_coassemble_update_course` and `mod_coassemble_update_progress` external functions (`classes/external/` + `db/services.php`), called from `mod_coassemble/embed` via `core/ajax`. Capability and context validation lives inside the external `execute()` methods; sesskey handling is core's.
- Progress report preloads tracked users in a single query instead of one lookup per row.

### Fixed
- The connection-test course count is now a language-pack string (`connection_coursecount`) instead of hard-coded English.
- `amd/src/embed.js` carries the standard Moodle GPL boilerplate header.

## 1.1.1 — 2026-08-03

### Docs
- Reconciled `COMPATIBILITY.md` with the declared support range: it now states **Moodle 4.1 – 5.1** (matching `version.php` and `README.md`) with the CI-tested branches and the PostgreSQL 15 / MariaDB 10.11 minimums, replacing the earlier "4.1–4.5 supported, 5.0+ not yet verified" table. No code changes.

## 1.1.0 — 2026-08-03

### Stable release
- Promoted from beta to **stable** (`MATURITY_STABLE`). First production-supported release.

### Moodle 5.x support
- Extended supported versions to **Moodle 4.1 – 5.1** (`$plugin->supported = [401, 501]`). No new deprecated or removed APIs are used on the 5.0/5.1 line; the module already targets the current completion, privacy, backup and event APIs.
- CI matrix now also builds against `MOODLE_500_STABLE` and `MOODLE_501_STABLE` (PHP 8.2/8.3), alongside the existing 4.1/4.4/4.5 jobs. AMD build-freshness (grunt) check moved to the newest supported branch.
- Note: Moodle 4.1 reached end of life in December 2025. The `requires` floor is kept at 4.1 for now so existing installs can upgrade in place; plan to raise it in a future release.

## 1.0.10-beta — 2026-07-22

### Fixed
- In **single activity** course format the exit link now goes to the Dashboard (labelled with Moodle's own "Dashboard" string) — the course page *is* the activity in that format, so "Back to course" just reloaded the player.

## 1.0.9-beta — 2026-07-22

### Changed
- The author preview hides the builder's internal back arrow (`back: hidden`) — it had nowhere to go; the Moodle bar's "Back to course" owns navigation.

## 1.0.8-beta — 2026-07-22

### Changed
- In-builder publishing is disabled: Moodle's **Manage content** page owns publish/revert, avoiding two competing notions of "published". (The embed API's default already excludes publishing; the plugin previously opted in.)

## 1.0.7-beta — 2026-07-22

### Full-bleed learner player
- The learner view now uses the same chrome-free embedded layout as authoring: a slim top bar (Back to course, plus management links for staff) with the player filling the rest of the viewport. Collection view matches. Informational pages (not configured / not linked) keep the standard layout so navigation stays available.
- Authors opening the activity now get a **builder preview** (`action: edit`, `flow: preview`) instead of the learner player, so staff viewing content never creates learner trackings or skews analytics.

## 1.0.6-beta — 2026-07-22

### Simplified activity form
- The activity form is now just name, description, create flow and grading. Removed: manual Coassemble course ID (linking is builder-driven only), Collection ID, Theme ID, and Player/builder language.
- Create flow choices trimmed to: start from scratch, generate with AI, document to course, convert presentation.
- Editing activity settings no longer risks touching the course link — the builder integration owns it exclusively.
- The DB fields for collection/theme/language remain for values set previously; they are honoured if present but no longer editable in the UI.

## 1.0.5-beta — 2026-07-22

### Zero-touch webhooks
- All webhook settings removed from the admin UI. Saving the API key auto-registers this site's receiver with Coassemble and stores the signing secret internally; the connection test reports (and repairs) registration. Non-https sites and workspaces without the API entitlement degrade gracefully to player-based sync.
- Requires the workspace plan to include the webhook management APIs (gated at Embed Automate product-side).

## 1.0.4-beta — 2026-07-22

### Simplified configuration
- **Single API key field** — the workspace id is embedded in the key Coassemble issues (`COASSEMBLE:{workspace}:{secret}`), so the separate Workspace ID setting is gone. Keys pasted without the prefix are normalised; upgrading merges legacy split credentials automatically.
- **Fixed tenant identifier** — the clientIdentifier strategy setting (course/site/custom) is removed; the plugin always uses `moodle-site:<sitehash>`, one Coassemble tenant per Moodle site. Note: content authored under the old per-course default lives in different tenants — use "Find linked course from Coassemble" or relink if upgrading a site with existing beta content.
- **Webhooks work without a secret** — while no signing secret is stored, deliveries are accepted unauthenticated (setup-friendly); registering the webhook via the settings link stores a secret and turns verification on.

## 1.0.3-beta — 2026-07-22

### Fixed (found by the new test suite on a real Moodle 4.5)
- Implemented `coassemble_get_coursemodule_info()` — without it Moodle treated the custom completion rule as "not used by this activity", so automatic completion via the rule never worked; also announces active rule descriptions and honours "show description"

### Code readiness
- GitHub Actions CI via moodle-plugin-ci (Moodle 4.1/4.4/4.5 × PHP 8.1–8.3, MariaDB + PostgreSQL)
- PHPUnit suite: identity mapping, progress/score recording, both grading methods incl. core regrade callback, course reset, custom completion, privacy provider, webhook verification/parsing
- Behat smoke feature and a module data generator
- Webhook signature/payload logic extracted into unit-testable `\mod_coassemble\local\webhook_utils` (behaviour unchanged)
- `LICENSE` (GPLv3), README external-services disclosure, `$plugin->supported = [401, 405]`
- Rebuilt the docker smoke stack on maintained multi-arch images (`moodlehq/moodle-php-apache` + official `mariadb`) with self-installing Moodle and PHPUnit support — the previous `bitnami/*` tags no longer exist on Docker Hub

### Added
- **Course picker**: the activity form now lists workspace courses in a searchable autocomplete (falls back to the numeric id field when the API is unreachable)
- **Score-based grading**: new per-activity grading method (course progress % or quiz score, averaged across the course's scored quizzes); `score`/`passed` mirrored into `coassemble_track`, shown on the progress report, exported via the Privacy API and included in backups
- **Webhook auto-registration**: one-click registration of this site's receiver via the new Headless webhook endpoints — creates/rotates the endpoint and stores the signing secret automatically

### Design decision
- One Coassemble workspace per Moodle site (1:1). A per-category multi-workspace mapping was prototyped and deliberately removed to keep credential handling and webhook verification simple.

### Requires
- Coassemble Headless API with `score` on the trackings list and `/v1/headless/webhooks` endpoints (coassemble-author `moodle/headless-score-and-webhooks`, targeted at `hotfix`)

## 1.0.2-beta — 2026-07-22

### Added
- Moodle 4.x `custom_completion` class (replaces the legacy `get_completion_state` callback, which is ignored on Moodle 4.x)
- `course_module_viewed` event triggered on activity view
- Backup / restore support (`backup/moodle2`, incl. user tracking data) + `FEATURE_BACKUP_MOODLE2`
- Course reset support (`coassemble_reset_userdata`) for mirrored tracking and grades

### Fixed
- Regenerated stale `amd/build/embed.min.js` (was missing the `created` builder event, so newly created courses could stay unlinked)
- Completion no longer forced when the "complete the Coassemble course" rule is disabled
- `coassemble_update_instance` now normalises empty `collectionid` (and both add/update store an explicit 0 when the completion rule is unchecked)
- Privacy export now includes the `commenced` timestamp (and formats dates)
- Webhook identifiers from a *different* Moodle site sharing the workspace no longer map to local user ids
- Webhook now reads progress nested under `data.tracking` and only mirrors progress for users enrolled in the target course
- Collection embed origin check no longer drops a non-standard port
- `index.php` respects activity visibility (`uservisible`) and hides the Coassemble course id from students

## 1.0.1-beta — 2026-07-22

### Added
- Connection test probes **authoring** (temporary edit embed + cleanup), not only course list
- Embedded full-bleed authoring layout with session status (ready / error / expired)
- Course-link helper with API fallback resolve by `identifier` + `clientIdentifier`
- `timeauthored`, optional `collectionid`, `completioncourse` activity fields
- `commenced` tracking + webhook / postMessage handling
- Teacher **Progress report** page with refresh-from-API
- Analytics enrolled-user picker
- Activity-level collection mode (`mode=collection`)
- Extra builder flows: generate / transform / convert
- Unpublished vs published status banners on manage / view
- Docker Compose smoke stack, `scripts/package.sh`, INSTALL checklist

### Changed
- Plugin release `1.0.1-beta` (`2026072201`)

## 1.0.0-beta — 2026-07-22

Initial authoring-first Moodle activity module bootstrap.
