# mod_coassemble — Coassemble for Moodle

Moodle activity module that embeds the **full Coassemble authoring experience** inside Moodle via the Headless / Embed API.

Teachers create and edit learning objects in the Coassemble Course Builder iframe. Learner delivery (player, completion, gradebook) is available only after a Coassemble course is linked to the activity.

This package lives in its **own repository** and installs as `mod/coassemble`.

## Links

- **Source code:** https://github.com/coassemble/moodle-mod_coassemble
- **Support and bug reports:** Email [gday@coassemble.com](mailto:gday@coassemble.com), or log in at [coassemble.com](https://coassemble.com) and use the support chat.
- **Documentation:** [Installation](INSTALL.md), [support](SUPPORT.md), and [Coassemble API documentation](https://developers.coassemble.com/get-started)

## Subscription required

The Coassemble plug-in requires a commercial subscription to Coassemble, with available plans for clients and partners. For more information, please contact [moodle@coassemble.com](mailto:moodle@coassemble.com), or visit [moodle.coassemble.com](http://moodle.coassemble.com).

The plugin is free to download and install; a Coassemble subscription is not
included. API access and authoring entitlement are required.

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
| Moodle | 4.1 – 5.2 (`$plugin->supported`) |
| PHP | 8.1+ |
| Coassemble plan | Paid subscription with API access **and** `api_authoring` |

Optional builder features default to on and can be disabled under the plugin's
site settings. AI generation, Google Drive import and OneDrive import are
site governance controls, independent of plan entitlements. Disabling AI also
removes the AI creation choice and rejects AI creation requests. The following
features additionally require Coassemble entitlements:

| Builder feature | Additional Coassemble entitlements |
|-----------------|------------------------------------|
| Narrations | `narrations` |
| Translations | `api_advanced` and `translations` |
| Brand voice | `brand_kit` |

These are in addition to `api` and `api_authoring`. The plugin cannot detect
entitlements. Narration usage draws from the shared workspace allowance.
Publishing is controlled by Moodle's `mod/coassemble:manage` capability; it has
no separate feature setting or Coassemble publishing entitlement.

Moodle 5.1+ Activities overview shows managers the remote course title,
publication state and relationship, with the same cached metadata as the
Coassemble index. Learners retain Moodle completion and grade information.

## Install

See [INSTALL.md](INSTALL.md) for the full checklist.

Install from a release ZIP via **Site administration → Plugins → Install plugins**,
or place this repository's contents in a `mod/coassemble` folder under your Moodle
root and complete the upgrade at **Site administration → Notifications**.

Configure **Site administration → Plugins → Activity modules → Coassemble**, then run **Test connection** (list + authoring probe).

## Teacher flow (core)

1. Add a **Coassemble** activity and save its name, description, grading and initial create flow.
2. Choose **Start from scratch**, **Generate with AI**, or **Use an existing course**.
   New content uses Builder 2; opening the activity alone does not create a draft.
3. The existing-course path opens a native Moodle library with title search and
   paging. Legacy courses and hosted SCORM packages are visible but unavailable.
4. Select **Use this course** to share the original, or **Make a copy** for a separate
   course. Editing or publishing a linked original changes it wherever it is used.
5. Publish and revert inside the Coassemble builder. Use **Manage content** to
   check publication status, duplicate, unlink or export SCORM.
   Remote deletion is available only for created/copied courses with no other
   Moodle activities pointing to them. Unlink leaves the remote course intact.
6. Learners open the activity in the full-bleed player, with the Moodle course
   name, activity name and return link visible in a slim bar. The course builder
   uses the same full-window layout and Moodle context.

Teachers can also open **Course → More → Coassemble course library** before
adding an activity. The per-course **Coassemble activities** index shows course
titles, publication state, origin and progress-report links; metadata is cached
for up to five minutes and refreshed after local content changes.

Unlinking or switching to a duplicate clears that activity's local progress,
grades and completion, with confirmation. It does not delete the original
Coassemble course or its learner records. Shared-course counts cover this Moodle
site; they cannot detect references from another site or the Coassemble web app.

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
