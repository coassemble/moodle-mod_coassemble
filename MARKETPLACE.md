# Coassemble for Moodle — directory description

Coassemble for Moodle embeds course authoring and learner delivery in a Moodle
activity. Teachers can create and manage content; learners take linked courses
inside Moodle. Progress, completion and grades synchronise with Moodle, with
learner progress reports available to authorised staff in Moodle.

The Coassemble plug-in requires a commercial subscription to Coassemble, with available plans for clients and partners. For more information, please contact [moodle@coassemble.com](mailto:moodle@coassemble.com), or visit [moodle.coassemble.com](http://moodle.coassemble.com).

The plugin is free to install; a Coassemble subscription is not included.
API access and authoring entitlement are required.

Want to try it first? [Create a free Coassemble sandbox](https://coassemble.com/signin?channel=moodle)
and connect it to your Moodle site.

Requires Moodle 4.1–5.3 and a PHP version supported by your Moodle release.
Use a maintained Moodle release for new installations. Under Site administration
→ Plugins → Activity modules → Coassemble, choose Connect Coassemble to sign in
and approve the site (the plugin sets up its API key and progress updates
itself), or paste an API key from the Coassemble developer console. Then run the
connection test. HTTPS is required for Connect and for webhook registration.

The service receives a pseudonymous user identifier, display name, learner
profile picture URL, and site tenant identifier. It returns signed embed URLs,
course metadata and learner tracking. Connect sends the administrator to
Coassemble to sign in and exchanges a one-time code for an API key; Coassemble
fetches the site's public client metadata document (site name and address).
See the [external service disclosure](README.md#external-services-disclosure),
[installation guide](INSTALL.md), [privacy policy](https://coassemble.com/privacy-policy)
and [support guide](SUPPORT.md).

For help or bug reports, email [gday@coassemble.com](mailto:gday@coassemble.com),
or log in at [coassemble.com](https://coassemble.com) and use the support chat.

Licensed under GNU GPL v3 or later.
