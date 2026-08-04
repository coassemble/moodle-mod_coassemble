<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English language strings for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['analytics_course'] = 'Course analytics';
$string['analytics_iframe_title'] = 'Coassemble analytics';
$string['analytics_nousers'] = 'No enrolled users found for analytics.';
$string['analytics_pick_user'] = 'Choose a user';
$string['analytics_user'] = 'User analytics';
$string['authoringnote'] = 'After you save this activity, open it to launch the Coassemble builder and create your learning object. Learner delivery is available only once a course is linked.';
$string['backtoactivity'] = 'Back to activity';
$string['backtocourse'] = 'Back to course';
$string['builder_iframe_title'] = 'Coassemble course builder';
$string['coassemble:addinstance'] = 'Add a new Coassemble activity';
$string['coassemble:author'] = 'Author Coassemble content';
$string['coassemble:manage'] = 'Manage Coassemble content lifecycle';
$string['coassemble:view'] = 'View a Coassemble activity';
$string['coassemble:viewanalytics'] = 'View Coassemble analytics';
$string['coassemblecourseid'] = 'Coassemble course ID';
$string['coassembleheader'] = 'Coassemble content';
$string['collection_heading'] = 'Collection player';
$string['collection_help'] = 'Launch a Coassemble collection embed linked by collection id (advanced).';
$string['collection_iframe_title'] = 'Coassemble collection';
$string['collectionid'] = 'Collection ID';
$string['commenced'] = 'Commenced';
$string['completed'] = 'Completed';
$string['completioncourse'] = 'Student must complete the Coassemble course';
$string['completiondetail:course'] = 'Complete the Coassemble course';
$string['connection_authoring_failed'] = 'API list access works, but authoring failed: {$a}. Confirm the workspace plan includes api_authoring.';
$string['connection_authoring_note'] = 'List + authoring checks both passed.';
$string['connection_authoring_ok'] = 'Authoring embed issuance succeeded (probe course cleaned up when possible).';
$string['connection_coursecount'] = 'Courses visible to API key (sample page): {$a}';
$string['connection_ok'] = 'Successfully connected to the Coassemble Headless API, including authoring.';
$string['error_apirequest'] = 'Coassemble API request failed: {$a}';
$string['error_embedurl'] = 'Coassemble did not return a valid signed embed URL.';
$string['error_nocollection'] = 'No Coassemble collection is linked to this activity.';
$string['error_nocourseyet'] = 'No Coassemble learning object is linked yet. An author needs to create one in the builder first.';
$string['error_notconfigured'] = 'Coassemble API credentials are not configured. Ask a site administrator to set them under Site administration → Plugins → Activity modules → Coassemble.';
$string['error_useridrequired'] = 'A user id is required for user analytics.';
$string['error_webhookregister'] = 'Coassemble did not return a webhook signing secret.';
$string['flow'] = 'Initial create flow';
$string['flow_ai'] = 'Generate with AI';
$string['flow_document'] = 'Document to course';
$string['flow_help'] = 'Choose how the builder should open the first time (before a Coassemble course is linked). You can switch flows from the activity page.';
$string['flow_presentation'] = 'Convert presentation';
$string['flow_scratch'] = 'Start from scratch';
$string['grademethod'] = 'Grading method';
$string['grademethod_help'] = 'Progress grades on the completion percentage of the Coassemble course. Score grades on the quiz score reported by Coassemble (averaged across all scored quizzes in the course) — learners receive no grade until a scored quiz has been attempted.';
$string['grademethod_progress'] = 'Course progress percentage';
$string['grademethod_score'] = 'Quiz score';
$string['manage_delete'] = 'Soft-delete';
$string['manage_delete_ok'] = 'Course soft-deleted in Coassemble.';
$string['manage_duplicate'] = 'Duplicate';
$string['manage_duplicate_ok'] = 'Course duplicated and linked to this activity.';
$string['manage_intro'] = 'Publish, duplicate, or export the Coassemble learning object linked to this activity.';
$string['manage_publish'] = 'Publish';
$string['manage_publish_ok'] = 'Course published.';
$string['manage_refresh'] = 'Refresh metadata';
$string['manage_refresh_ok'] = 'Metadata refreshed.';
$string['manage_remote_published'] = 'Published';
$string['manage_remote_title'] = 'Remote title';
$string['manage_restore'] = 'Restore';
$string['manage_restore_ok'] = 'Course restored in Coassemble.';
$string['manage_revert'] = 'Revert to published';
$string['manage_revert_ok'] = 'Course reverted to the published version.';
$string['manage_scorm'] = 'Download SCORM package';
$string['modulename'] = 'Coassemble';
$string['modulename_help'] = 'The Coassemble activity embeds the full Coassemble authoring experience inside Moodle. Teachers create and edit a learning object in the builder; learners take that object once it exists.';
$string['modulenameplural'] = 'Coassemble activities';
$string['nav_analytics'] = 'Analytics';
$string['nav_editcontent'] = 'Edit in Coassemble';
$string['nav_manage'] = 'Manage content';
$string['nav_report'] = 'Progress report';
$string['notyetlinked'] = 'Not linked yet';
$string['passed'] = 'Passed';
$string['player_iframe_title'] = 'Coassemble course player';
$string['pluginadministration'] = 'Coassemble administration';
$string['pluginname'] = 'Coassemble';


$string['privacy:metadata:coassemble_track'] = 'Stores mirrored learner progress for Coassemble activities.';
$string['privacy:metadata:coassemble_track:commenced'] = 'Commenced timestamp.';
$string['privacy:metadata:coassemble_track:completed'] = 'Completion timestamp.';
$string['privacy:metadata:coassemble_track:passed'] = 'Whether the best quiz attempt passed.';
$string['privacy:metadata:coassemble_track:progress'] = 'Progress percentage.';
$string['privacy:metadata:coassemble_track:score'] = 'Quiz score mirrored from Coassemble (average across scored quizzes).';
$string['privacy:metadata:coassemble_track:timemodified'] = 'Last update time.';
$string['privacy:metadata:coassemble_track:userid'] = 'The Moodle user id.';
$string['privacy:metadata:coassemble_webhook'] = 'Idempotency log of webhook deliveries (no user personal data).';
$string['privacy:metadata:external'] = 'User identifiers and display profile fields are sent to Coassemble to issue embeds and track learning.';
$string['privacy:metadata:external:avatar'] = 'User profile picture URL.';
$string['privacy:metadata:external:clientidentifier'] = 'Tenant key derived from this Moodle site.';
$string['privacy:metadata:external:identifier'] = 'Stable user identifier derived from the Moodle user id.';
$string['privacy:metadata:external:name'] = 'User display name.';
$string['progress'] = 'Progress';
$string['refresh_progress'] = 'Refresh learner progress from Coassemble';
$string['refresh_progress_ok'] = 'Progress refreshed for {$a} learner(s).';
$string['report_empty'] = 'No learner progress has been recorded yet.';
$string['reset_tracks'] = 'Delete Coassemble progress and completion records';
$string['resolve_course'] = 'Find linked course from Coassemble';
$string['score'] = 'Score';
$string['session_error'] = 'The Coassemble embed reported an error. Try reloading.';
$string['session_expired'] = 'Your Coassemble session expired. Reload the page to continue.';
$string['session_ready'] = 'Coassemble ready';
$string['settings_apikey'] = 'API key';
$string['settings_apikey_desc'] = 'Workspace API key from Coassemble API settings. Paste the full key — it starts with COASSEMBLE: and identifies your workspace.';
$string['settings_apiurl'] = 'API base URL';
$string['settings_apiurl_desc'] = 'Usually https://api.coassemble.com (override for staging).';
$string['settings_credentials'] = 'API credentials';
$string['settings_credentials_desc'] = 'Store your Coassemble workspace API credentials here. The API key must never be exposed to browsers. Authoring requires a plan with API + authoring entitlements.';
$string['settings_testconnection'] = 'Test connection';
$string['settings_testconnection_link'] = 'Run connection test';
$string['status_published'] = 'This Coassemble course is published.';
$string['status_unpublished'] = 'This Coassemble course is not published yet. Learners may not see the latest content until you publish.';
$string['task_cleanup_webhook_log'] = 'Clean up Coassemble webhook delivery log';
$string['timeauthored'] = 'First linked';
$string['webhook_auto_failed'] = 'Webhook auto-registration failed: {$a}. Progress still syncs when learners use the player; fix the issue and re-save the API key (or re-run the connection test) to retry.';
$string['webhook_auto_needshttps'] = 'Webhooks require this Moodle site to be served over https, so real-time sync is off. Progress still syncs when learners use the player.';
$string['webhook_auto_ok'] = 'Webhook registered automatically — learner progress will sync in near real time.';
$string['webhook_status'] = 'Webhook registration';
$string['webhook_status_ok'] = 'Webhook is registered and its signing secret is stored.';
