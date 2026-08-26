# mod_coassemble — Coassemble for Moodle

Moodle activity module that embeds the **full Coassemble authoring experience** inside Moodle via the Headless / Embed API.

Teachers create and edit learning objects in the Coassemble Course Builder iframe. Learner delivery (player, completion, gradebook) is available only after a Coassemble course is linked to the activity.

This package lives in its **own repository** and installs as `mod/coassemble`.

## Links

- **Source code:** https://github.com/coassemble/moodle-mod_coassemble
- **Issue tracker:** https://github.com/coassemble/moodle-mod_coassemble/issues
- **Documentation:** _TODO: docs page on coassemble.com_

## External services disclosure

This plugin communicates with the **Coassemble** platform (an external, paid service — [coassemble.com](https://coassemble.com)) using API credentials configured by a site administrator. Without a Coassemble workspace and API access it does nothing.

Data sent to Coassemble when users interact with an activity:

- A **pseudonymous user identifier** derived from the Moodle user id and a site hash (`moodle:<sitehash>:<userid>`) — no username or email is included in the identifier.
- The user's **display name** and (learner view only) **profile picture URL**, so the embedded player/builder can show them.
- A **tenant identifier** derived from this Moodle site (one Coassemble tenant per site).

Data received from Coassemble: course metadata (titles, publish state), learner progress/completion/score tracking, and signed embed URLs. Progress data is mirrored into the plugin's tables to drive Moodle grades and activity completion, and is covered by the Moodle Privacy API (export and deletion). See Coassemble's [privacy policy](https://coassemble.com/privacy-policy) for how the service handles data.

## Requirements

| Component | Version |
|-----------|---------|
| Moodle | 4.1 – 5.1 (`$plugin->supported`) |
| PHP | 8.1+ |
| Coassemble plan | API access **and** `api_authoring` |

## Install

See [INSTALL.md](INSTALL.md) for the full checklist.

Install from a release ZIP via **Site administration → Plugins → Install plugins**,
or place this repository's contents in a `mod/coassemble` folder under your Moodle
root and complete the upgrade at **Site administration → Notifications**.

Configure **Site administration → Plugins → Activity modules → Coassemble**, then run **Test connection** (list + authoring probe).

## Teacher flow (core)

1. Add a **Coassemble** activity to a Moodle course.
2. Open the activity → Course Builder embed loads in an embedded (full-bleed) layout (`POST /api/v1/headless/embed/course`, `action: edit`).
3. Pick a create flow: start from scratch or generate with AI. New courses use Builder 2.
4. The plugin stores the Coassemble `courseId` (JWT claim and/or `course.updated` postMessage). Fallback: **Find linked course from Coassemble**.
5. Use **Manage content** to publish, revert, duplicate, soft-delete, restore, or download SCORM.
6. Learners open the same activity to take the course in the player embed.

## Learner delivery

- Player: `action: view` signed URL, minted server-side per user.
- Progress / commencement / completion via `window.postMessage` (object or JSON string; tolerates `complete` / `completed`).
- Gradebook item mirrors progress; custom completion rule “must complete Coassemble course”.
- Durable sync: webhook URL `/mod/coassemble/webhook.php` for `course.commenced` / `course.completed`.
- Teachers: **Progress report** + **Refresh learner progress** (`GET /v1/headless/trackings`).

## Analytics and collections

- Course analytics embed + enrolled-user picker for user analytics.
- Optional activity `collectionid` → `mode=collection` player/builder.
- Advanced: `/mod/coassemble/collection.php?id=<cmid>&collectionid=<id>`.

## Identity mapping

| Headless field | Default |
|----------------|---------|
| `identifier` | `moodle:<sitehash>:<userid>` |
| `clientIdentifier` | `moodle-site:<sitehash>` (one tenant per Moodle site) |

## Capabilities

| Capability | Purpose |
|------------|---------|
| `mod/coassemble:view` | Open activity |
| `mod/coassemble:author` | Launch builder |
| `mod/coassemble:manage` | Publish / duplicate / SCORM / report / refresh |
| `mod/coassemble:viewanalytics` | Analytics embed |
| `mod/coassemble:addinstance` | Add activity to course |

## Security notes

- API key stays on the Moodle server only.
- Fresh signed embed URLs are minted per page load; do not cache across users.
- postMessage handlers check `event.origin` and `event.source`.
- Webhook signatures use HMAC SHA-256 over `{timestamp}.{raw_body}`.

## Docs in this package

- [INSTALL.md](INSTALL.md) — install checklist
- [SUPPORT.md](SUPPORT.md) — runbook
- [COMPATIBILITY.md](COMPATIBILITY.md) — Moodle / API matrix
- [CHANGELOG.md](CHANGELOG.md)

## License

GNU GPL v3 or later (Moodle plugin standard).
