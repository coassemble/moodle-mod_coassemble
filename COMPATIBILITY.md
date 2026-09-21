# Compatibility matrix — mod_coassemble

Declared support: `$plugin->supported = [401, 502]` — **Moodle 4.1 to 5.2**.
Minimum: `$plugin->requires = 2022112800` (Moodle 4.1).
Plugin release **1.3.1**, version `2026092100`.

| Moodle | PHP in CI | Coverage |
|--------|-----------|----------|
| 4.1 | 8.1 | Legacy compatibility, PostgreSQL and MariaDB |
| 4.4 | 8.2 | Legacy compatibility, PostgreSQL |
| 4.5 (LTS) | 8.3 | PostgreSQL and MariaDB |
| 5.0 | 8.2 | PostgreSQL |
| 5.1 | 8.3 | PostgreSQL and MariaDB |
| 5.2 | 8.3 | PostgreSQL and MariaDB |
| 5.3 development (`main`) | 8.4 | Forward compatibility, PostgreSQL; not declared stable support |

The workflow defines the CI targets above. Check its results for the revision
you plan to deploy. Moodle 4.2 and 4.3 remain within the legacy range but have
no dedicated CI jobs.

As of 21 September 2026, maintained Moodle releases are 4.5, 5.0, 5.1 and 5.2;
5.3 is scheduled for 5 October 2026. See the official
[release schedule](https://moodledev.io/general/releases).
Older branches are retained for existing installations; new sites should use a
maintained Moodle release and its supported PHP version. CI uses PostgreSQL 16
and MariaDB 10.11. Bootstrap spacing uses plugin-scoped logical CSS properties,
so it works on both Bootstrap 4 and Bootstrap 5 without deprecated utilities.

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
