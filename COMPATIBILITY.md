# Compatibility matrix — mod_coassemble

| Moodle | PHP | Plugin release | Status |
|--------|-----|----------------|--------|
| 4.1 (LTS) | 7.4–8.1 | 1.0.1-beta | Supported target |
| 4.2 | 8.0–8.1 | 1.0.1-beta | Expected compatible |
| 4.3 | 8.0–8.2 | 1.0.1-beta | Expected compatible |
| 4.4 | 8.1–8.3 | 1.0.1-beta | Expected compatible |
| 4.5 (LTS) | 8.1–8.3 | 1.0.1-beta | Supported target |
| 5.0+ | 8.2+ | — | Not yet verified |

`version.php` currently requires Moodle **4.1** (`$plugin->requires = 2022112800`), plugin version `2026072201`.

## Coassemble API

| Capability | Endpoint family | Entitlement |
|------------|-----------------|-------------|
| List / get courses | `GET /api/v1/headless/courses` | `api` |
| Course builder embed | `POST /api/v1/headless/embed/course` `action=edit` | `api_authoring` |
| Course player embed | `POST /api/v1/headless/embed/course` `action=view` | `api` |
| Publish / revert | `POST /api/v1/headless/course/:id/publish` | advanced / automate (plan-gated) |
| Trackings | `GET /api/v1/headless/trackings` | `api` |
| Analytics embeds | `POST /api/v1/headless/embed/analytics/*` | analytics entitlement |
| Collection embeds | `POST /api/v1/headless/embed/collection` | `api` / authoring for edit |
| SCORM export | `GET /api/v1/headless/course/scorm/:id` | authoring / static SCORM rules |

## Distribution

- Primary: private ZIP / customer git checkout into `mod/coassemble`
- Moodle plugins directory: optional later; not required for 1.0.0-beta
