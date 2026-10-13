# Install checklist — mod_coassemble

## Subscription required

The Coassemble plug-in requires a commercial subscription to Coassemble, with available plans for clients and partners. For more information, please contact [moodle@coassemble.com](mailto:moodle@coassemble.com), or visit [moodle.coassemble.com](http://moodle.coassemble.com).

Ensure your subscription includes API access and authoring entitlement before
configuring the plugin.

## Package

Install from a release ZIP, or build one by zipping this repository's contents
inside a folder named `coassemble`.

Place it so the folder is named `coassemble` under Moodle’s `mod/` directory:

```text
moodle/
  mod/
    coassemble/
      version.php
      ...
```

For a manual upgrade, replace the existing `mod/coassemble` directory with the
new package before running the upgrade. Do not copy the new files over the old
ones: obsolete entry points such as `collection.php` must be removed.

## Site setup

1. **Site administration → Notifications** — install / upgrade the plugin.
2. **Plugins → Activity modules → Coassemble**
   - API base URL
   - API key (the full key starting with `COASSEMBLE:` — it identifies your workspace)

   Saving the key **registers the site's webhook automatically** (requires the Moodle
   site to be `https://`; without it, progress still syncs via the player).
3. **Run connection test** — must pass list + authoring probe; it also verifies
   (and repairs) the webhook registration.
4. Ensure the Moodle site origin is allowed to frame Coassemble (CSP / origin allowlist).

## First activity

1. As editing teacher, add **Coassemble** to a course.
2. Select the initial create flow and choose **Save and display**.
3. The saved scratch or AI flow opens directly in Builder 2. The existing-course
   flow opens the library, where you can link the original or make a copy.
4. Confirm **Manage content** shows a Coassemble course ID and publish status.
5. Publish, then open as a student and complete the course.
6. Confirm gradebook + completion; optionally disconnect network mid-completion and rely on webhook.

See [COMPATIBILITY.md](COMPATIBILITY.md) and [SUPPORT.md](SUPPORT.md).
