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
 * Library functions for mod_coassemble.
 *
 * @package   mod_coassemble
 * @copyright 2026 Coassemble
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Supported features.
 *
 * @param string $feature
 * @return mixed
 */
function coassemble_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Add a new instance.
 *
 * @param stdClass $data
 * @param mod_coassemble_mod_form|null $mform
 * @return int
 */
function coassemble_add_instance(stdClass $data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    if (empty($data->flow)) {
        $data->flow = null;
    }
    if (empty($data->coassemblecourseid)) {
        $data->coassemblecourseid = null;
    }
    if (empty($data->collectionid)) {
        $data->collectionid = null;
    }
    // Unchecked completion rule checkbox is absent from submitted data.
    $data->completioncourse = !empty($data->completioncourse) ? 1 : 0;
    $data->grademethod = !empty($data->grademethod) ? 1 : 0;
    $data->timeauthored = null;

    $data->id = $DB->insert_record('coassemble', $data);
    coassemble_grade_item_update($data);
    return $data->id;
}

/**
 * Update an instance.
 *
 * @param stdClass $data
 * @param mod_coassemble_mod_form|null $mform
 * @return bool
 */
function coassemble_update_instance(stdClass $data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    if (empty($data->flow)) {
        $data->flow = null;
    }
    // The Coassemble course link (and collection/theme/language) are managed
    // by the builder integration, not the settings form — never touch them
    // here or editing settings would unlink the course.
    unset($data->coassemblecourseid, $data->collectionid, $data->themeid, $data->language);
    $data->completioncourse = !empty($data->completioncourse) ? 1 : 0;
    $data->grademethod = !empty($data->grademethod) ? 1 : 0;

    $result = $DB->update_record('coassemble', $data);
    coassemble_grade_item_update($data);
    return $result;
}

/**
 * Admin-setting callback: auto-register the webhook receiver when API
 * credentials change.
 *
 * Failures are reported as notifications but never block saving settings —
 * progress still syncs via the player without webhooks.
 *
 * @return void
 */
function mod_coassemble_credentials_updated() {
    $result = \mod_coassemble\local\webhook_registration::ensure(true);
    switch ($result['status']) {
        case \mod_coassemble\local\webhook_registration::STATUS_REGISTERED:
            \core\notification::success(get_string('webhook_auto_ok', 'mod_coassemble'));
            break;
        case \mod_coassemble\local\webhook_registration::STATUS_NEEDSHTTPS:
            \core\notification::info(get_string('webhook_auto_needshttps', 'mod_coassemble'));
            break;
        case \mod_coassemble\local\webhook_registration::STATUS_FAILED:
            \core\notification::warning(get_string('webhook_auto_failed', 'mod_coassemble', $result['message']));
            break;
        default:
            // Not configured / already registered — nothing to report.
            break;
    }
}

/**
 * Course module info: announce custom completion rules (and description).
 *
 * Without this, completion machinery treats the completioncourse rule as
 * unused and never evaluates it.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|false
 */
function coassemble_get_coursemodule_info($coursemodule) {
    global $DB;

    $instance = $DB->get_record(
        'coassemble',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat, completioncourse'
    );
    if (!$instance) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('coassemble', $instance, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completioncourse'] = (int) $instance->completioncourse;
    }

    return $info;
}

/**
 * Human-readable descriptions of active completion rules for the module info.
 *
 * @param cm_info|stdClass $cm
 * @return string[]
 */
function mod_coassemble_get_completion_active_rule_descriptions($cm) {
    if (empty($cm->customdata['customcompletionrules'])) {
        return [];
    }
    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $rule => $enabled) {
        if ($rule === 'completioncourse' && $enabled) {
            $descriptions[] = get_string('completiondetail:course', 'mod_coassemble');
        }
    }
    return $descriptions;
}

/**
 * Build a browser origin (scheme://host[:port]) for postMessage checks.
 *
 * @param string $url Embed URL
 * @return string
 */
function coassemble_embed_origin(string $url): string {
    $scheme = (string) parse_url($url, PHP_URL_SCHEME);
    $host = (string) parse_url($url, PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT);
    $origin = $scheme . '://' . $host;
    if (!empty($port)) {
        $origin .= ':' . $port;
    }
    return $origin;
}

/**
 * Delete an instance.
 *
 * @param int $id
 * @return bool
 */
function coassemble_delete_instance($id) {
    global $DB;

    if (!$instance = $DB->get_record('coassemble', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('coassemble_track', ['coassembleid' => $id]);
    $DB->delete_records('coassemble', ['id' => $id]);
    coassemble_grade_item_delete($instance);
    return true;
}

/**
 * Create / update grade item.
 *
 * @param stdClass $instance
 * @param array|null $grades
 * @return int
 */
function coassemble_grade_item_update(stdClass $instance, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => $instance->name,
        'idnumber' => $instance->cmidnumber ?? '',
    ];

    if (isset($instance->grade) && $instance->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = (float) $instance->grade;
        $params['grademin'] = 0;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/coassemble',
        $instance->course,
        'mod',
        'coassemble',
        $instance->id,
        0,
        $grades,
        $params
    );
}

/**
 * Delete grade item.
 *
 * @param stdClass $instance
 * @return int
 */
function coassemble_grade_item_delete(stdClass $instance) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/coassemble',
        $instance->course,
        'mod',
        'coassemble',
        $instance->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Standard gradebook callback: (re)push grades from mirrored tracking.
 *
 * Called by core during regrades and by coassemble_record_progress.
 *
 * @param stdClass $instance
 * @param int $userid 0 means all users
 * @param bool $nullifnone Push a null grade when a user has no tracking
 * @return void
 */
function coassemble_update_grades(stdClass $instance, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if (empty($instance->grade) || $instance->grade <= 0) {
        coassemble_grade_item_update($instance);
        return;
    }

    $grademax = (float) $instance->grade;
    $usescore = !empty($instance->grademethod);
    $params = ['coassembleid' => $instance->id];
    if ($userid) {
        $params['userid'] = (int) $userid;
    }

    $grades = [];
    foreach ($DB->get_records('coassemble_track', $params) as $track) {
        if ($usescore) {
            // Score-based: no grade until Coassemble reports a quiz score.
            if ($track->score === null) {
                continue;
            }
            $score = max(0.0, min(100.0, (float) $track->score));
            $rawgrade = ($score / 100.0) * $grademax;
        } else {
            $progress = max(0.0, min(100.0, (float) $track->progress));
            $rawgrade = !empty($track->completed) ? $grademax : ($progress / 100.0) * $grademax;
        }
        $grades[$track->userid] = (object) [
            'userid' => (int) $track->userid,
            'rawgrade' => $rawgrade,
            'dategraded' => (int) $track->timemodified,
        ];
    }

    if (!$grades && $userid && $nullifnone) {
        $grades[$userid] = (object) ['userid' => (int) $userid, 'rawgrade' => null];
    }

    coassemble_grade_item_update($instance, $grades ?: null);
}

/**
 * Persist mirrored tracking and push grade/completion.
 *
 * @param stdClass $instance
 * @param int $userid
 * @param float $progress
 * @param bool $completed
 * @param bool $commenced
 * @param float|null $score Best-attempt quiz score 0-100, null when unknown
 * @param bool|null $passed Whether the latest attempt passed, null when unknown
 * @return void
 */
function coassemble_record_progress(
    stdClass $instance,
    $userid,
    $progress,
    $completed = false,
    $commenced = false,
    $score = null,
    $passed = null
) {
    global $DB;

    $userid = (int) $userid;
    $progress = max(0, min(100, (float) $progress));
    if ($score !== null) {
        $score = max(0, min(100, (float) $score));
    }
    $now = time();

    $apply = function (stdClass $existing) use ($DB, $progress, $completed, $commenced, $score, $passed, $now) {
        $existing->progress = max((float) $existing->progress, $progress);
        // Mirrored score only ever improves as the learner retries.
        if ($score !== null && ($existing->score === null || $score > (float) $existing->score)) {
            $existing->score = $score;
        }
        if ($passed !== null && empty($existing->passed)) {
            $existing->passed = $passed ? 1 : 0;
        }
        if ($commenced && empty($existing->commenced)) {
            $existing->commenced = $now;
        }
        if ($completed && empty($existing->completed)) {
            $existing->completed = $now;
        }
        $existing->timemodified = $now;
        $DB->update_record('coassemble_track', $existing);
        return $existing;
    };

    $existing = $DB->get_record('coassemble_track', [
        'coassembleid' => $instance->id,
        'userid' => $userid,
    ]);

    if ($existing) {
        $existing = $apply($existing);
        $completed = !empty($existing->completed);
    } else {
        $record = (object) [
            'coassembleid' => $instance->id,
            'userid' => $userid,
            'progress' => $progress,
            'score' => $score,
            'passed' => $passed === null ? null : ($passed ? 1 : 0),
            'completed' => $completed ? $now : null,
            'commenced' => ($commenced || $completed || $progress > 0) ? $now : null,
            'timemodified' => $now,
        ];
        try {
            $DB->insert_record('coassemble_track', $record);
        } catch (dml_exception $e) {
            // Concurrent request won the unique (coassembleid, userid) insert race.
            $existing = $DB->get_record('coassemble_track', [
                'coassembleid' => $instance->id,
                'userid' => $userid,
            ], '*', MUST_EXIST);
            $existing = $apply($existing);
            $completed = !empty($existing->completed);
        }
    }

    // Grades derive from the tracking row written above.
    coassemble_update_grades($instance, $userid);

    if ($completed && !empty($instance->completioncourse)) {
        $cm = get_coursemodule_from_instance('coassemble', $instance->id, $instance->course);
        if ($cm) {
            $completion = new completion_info(get_course($instance->course));
            if ($completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
            }
        }
    }
}

/**
 * Course reset form definition.
 *
 * @param MoodleQuickForm $mform
 * @return void
 */
function coassemble_reset_course_form_definition($mform) {
    $mform->addElement('header', 'coassembleheader', get_string('modulenameplural', 'mod_coassemble'));
    $mform->addElement('advcheckbox', 'reset_coassemble_track', get_string('reset_tracks', 'mod_coassemble'));
}

/**
 * Course reset form defaults.
 *
 * @param stdClass $course
 * @return array
 */
function coassemble_reset_course_form_defaults($course) {
    return ['reset_coassemble_track' => 1];
}

/**
 * Reset user data (mirrored tracking and grades) for all instances in a course.
 *
 * @param stdClass $data Reset settings
 * @return array Status entries
 */
function coassemble_reset_userdata($data) {
    global $DB;

    $status = [];
    $componentstr = get_string('modulenameplural', 'mod_coassemble');

    if (!empty($data->reset_coassemble_track)) {
        $instances = $DB->get_records('coassemble', ['course' => $data->courseid]);
        foreach ($instances as $instance) {
            $DB->delete_records('coassemble_track', ['coassembleid' => $instance->id]);
            if (empty($data->reset_gradebook_grades)) {
                coassemble_grade_item_update($instance, 'reset');
            }
        }
        $status[] = [
            'component' => $componentstr,
            'item' => get_string('reset_tracks', 'mod_coassemble'),
            'error' => false,
        ];
    }

    return $status;
}

/**
 * Extend settings navigation with author / analytics links.
 *
 * @param settings_navigation $settings
 * @param navigation_node $nodenavigation
 * @return void
 */
function coassemble_extend_settings_navigation(settings_navigation $settings, navigation_node $nodenavigation) {
    global $PAGE;

    if (!$cm = $PAGE->cm) {
        return;
    }
    $context = context_module::instance($cm->id);

    if (has_capability('mod/coassemble:author', $context)) {
        $url = new moodle_url('/mod/coassemble/view.php', ['id' => $cm->id, 'mode' => 'edit']);
        $nodenavigation->add(
            get_string('nav_editcontent', 'mod_coassemble'),
            $url,
            navigation_node::TYPE_SETTING
        );

        $manageurl = new moodle_url('/mod/coassemble/manage.php', ['id' => $cm->id]);
        $nodenavigation->add(
            get_string('nav_manage', 'mod_coassemble'),
            $manageurl,
            navigation_node::TYPE_SETTING
        );
    }

    if (has_capability('mod/coassemble:manage', $context)) {
        $nodenavigation->add(
            get_string('nav_report', 'mod_coassemble'),
            new moodle_url('/mod/coassemble/report.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING
        );
    }

    if (has_capability('mod/coassemble:viewanalytics', $context)) {
        $url = new moodle_url('/mod/coassemble/analytics.php', ['id' => $cm->id]);
        $nodenavigation->add(
            get_string('nav_analytics', 'mod_coassemble'),
            $url,
            navigation_node::TYPE_SETTING
        );
    }
}

/**
 * List activity instances for a course.
 *
 * @param int $courseid
 * @return array
 */
function coassemble_get_instances($courseid) {
    global $DB;
    return $DB->get_records('coassemble', ['course' => $courseid], 'name ASC');
}
