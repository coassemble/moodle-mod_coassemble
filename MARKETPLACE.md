# Coassemble for Moodle — directory description

Coassemble for Moodle embeds course authoring and learner delivery in a Moodle
activity. Teachers can create and manage content; learners take linked courses
inside Moodle. Progress, completion and grades synchronise with Moodle, with
learner progress reports available to authorised staff in Moodle.

The Coassemble plug-in requires a commercial subscription to Coassemble, with available plans for clients and partners. For more information, please contact [moodle@coassemble.com](mailto:moodle@coassemble.com), or visit [moodle.coassemble.com](http://moodle.coassemble.com).

The plugin is free to install; a Coassemble subscription is not included.
API access and authoring entitlement are required.

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
