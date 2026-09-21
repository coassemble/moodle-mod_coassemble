# Coassemble for Moodle — directory description

Coassemble for Moodle embeds course authoring and learner delivery in a Moodle
activity. Teachers can create and manage content; learners take linked courses
inside Moodle. Progress, completion and grades synchronise with Moodle, with
course and learner analytics available to authorised staff.

The plugin is free to install, but **an active, paid Coassemble subscription with
API access and authoring entitlement is required to use it**. A subscription is
not included with the plugin. Obtain one directly from
[Coassemble](https://coassemble.com/pricing) or through a Coassemble partner.
Confirm the required entitlements before purchasing.

Requires Moodle 4.1–5.2 and a PHP version supported by your Moodle release.
Use a maintained Moodle release for new installations. Configure your workspace
credentials under Site administration → Plugins → Activity modules → Coassemble,
then run the connection test. HTTPS is required for webhook registration.

The service receives a pseudonymous user identifier, display name, learner
profile picture URL, and site tenant identifier. It returns signed embed URLs,
course metadata and learner tracking. See the [external service disclosure](README.md#external-services-disclosure),
[installation guide](INSTALL.md), [privacy policy](https://coassemble.com/privacy-policy)
and [support guide](SUPPORT.md).

For help or bug reports, email [gday@coassemble.com](mailto:gday@coassemble.com),
or log in at [coassemble.com](https://coassemble.com) and use the support chat.

Licensed under GNU GPL v3 or later.
