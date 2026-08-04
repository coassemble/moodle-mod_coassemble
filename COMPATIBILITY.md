# Compatibility matrix — mod_coassemble

Declared support: `$plugin->supported = [401, 501]` — **Moodle 4.1 to 5.1**.
Minimum: `$plugin->requires = 2022112800` (Moodle 4.1). Maturity: **stable**.
Plugin release **1.2.0**, version `2026080500`.

| Moodle | PHP | Status |
|--------|-----|--------|
| 4.1 (LTS) | 8.1 | Supported — CI tested |
| 4.2 | 8.0–8.1 | Supported |
| 4.3 | 8.0–8.2 | Supported |
| 4.4 | 8.2 | Supported — CI tested |
| 4.5 (LTS) | 8.3 | Supported — CI tested |
| 5.0 | 8.2 | Supported — CI tested |
| 5.1 | 8.3 | Supported — CI tested |

Continuous integration (GitHub Actions, `moodle-plugin-ci`) runs the matrix above
across PHP 8.1–8.3 on **PostgreSQL 15** and **MariaDB 10.11** (the minimums Moodle
5.1 requires). Rows marked "CI tested" build on every push; 4.2 and 4.3 sit inside
the declared support range and are covered by the shared 4.x code paths. The module
uses only current completion, privacy, backup/restore and event APIs, with none
deprecated or removed on the 5.0/5.1 line.

Note: Moodle 4.1 reached end of life in December 2025. The `requires` floor is kept
at 4.1 so existing installs can upgrade in place; it is expected to rise in a future
release.

## Coassemble API

| Capability | Endpoint family | Entitlement |
|------------|-----------------|-------------|
| List / get courses | `GET /api/v1/headless/courses` | `api` |
| Course builder embed | `POST /api/v1/headless/embed/course` `action=edit` | `api_authoring` |
| Course player embed | `POST /api/v1/headless/embed/course` `action=view` | `api` |
| Publish / revert | `POST /api/v1/headless/course/:id/publish` | `api_authoring` |
| Trackings | `GET /api/v1/headless/trackings` | `api` |
| Analytics embeds | `POST /api/v1/headless/embed/analytics/*` | analytics entitlement |
| Collection embeds | `POST /api/v1/headless/embed/collection` | `api` / authoring for edit |
| SCORM export | `GET /api/v1/headless/course/scorm/:id` | authoring / static SCORM rules |

## Distribution

- **Moodle Marketplace** listing (component `mod_coassemble`).
- Source code: <https://github.com/coassemble/moodle-mod_coassemble>
- Install via the Marketplace, a release ZIP, or by placing the repository contents
  in `mod/coassemble` and completing the upgrade.
