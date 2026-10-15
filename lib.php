<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Library callbacks for Decision Forge.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return supported Moodle features.
 *
 * @param string $feature
 * @return mixed
 */
function decisionforge_supports(string $feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Prepare editor data from the activity form.
 *
 * @param stdClass $data
 * @return void
 */
function decisionforge_extract_editor_data(stdClass $data): void {
    if (isset($data->instructions_editor) && is_array($data->instructions_editor)) {
        $data->instructions = $data->instructions_editor['text'];
        $data->instructionsformat = $data->instructions_editor['format'];
    }
}

/**
 * Normalise optional activity settings before a database write.
 *
 * Moodle may submit optional core grading controls as null. Database defaults
 * are not applied when a null value is explicitly present, so all required
 * configuration fields are completed at the module boundary.
 *
 * @param stdClass $data
 * @return void
 */
function decisionforge_normalise_instance_data(stdClass $data): void {
    $defaults = [
        'introformat' => FORMAT_HTML,
        'instructionsformat' => FORMAT_HTML,
        'experiencemode' => 'simulation',
        'missionlabel' => get_string('missionlabeldefault', 'mod_decisionforge'),
        'timeopen' => 0,
        'timeclose' => 0,
        'timelimit' => 0,
        'attemptsallowed' => 0,
        'grade' => 100.0,
        'gradepass' => 0.0,
        'grademethod' => 'highest',
        'feedbackmode' => 'immediate',
        'shufflecases' => 0,
        'shuffleanswers' => 1,
        'showprogress' => 1,
        'requirejustification' => 0,
        'requireconfidence' => 0,
        'initialsafety' => 72,
        'initialcompliance' => 68,
        'initialtrust' => 74,
        'initialresources' => 80,
    ];
    foreach ($defaults as $field => $default) {
        if (!isset($data->{$field}) || $data->{$field} === '') {
            $data->{$field} = $default;
        }
    }

    $data->grade = (float) $data->grade;
    $data->gradepass = (float) $data->gradepass;
    foreach ([
        'introformat', 'instructionsformat', 'timeopen', 'timeclose', 'timelimit', 'attemptsallowed',
        'shufflecases', 'shuffleanswers', 'showprogress', 'requirejustification', 'requireconfidence',
        'initialsafety', 'initialcompliance', 'initialtrust', 'initialresources',
    ] as $field) {
        $data->{$field} = (int) $data->{$field};
    }
}

/**
 * Add a Decision Forge instance.
 *
 * @param stdClass $data
 * @param mod_decisionforge_mod_form|null $mform
 * @return int
 */
function decisionforge_add_instance(stdClass $data, $mform = null): int {
    global $DB;

    decisionforge_extract_editor_data($data);
    decisionforge_normalise_instance_data($data);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('decisionforge', $data);

    decisionforge_grade_item_update($data);
    decisionforge_update_calendar_events($data);

    return $data->id;
}

/**
 * Update a Decision Forge instance.
 *
 * @param stdClass $data
 * @param mod_decisionforge_mod_form|null $mform
 * @return bool
 */
function decisionforge_update_instance(stdClass $data, $mform = null): bool {
    global $DB;

    $previous = $DB->get_record('decisionforge', ['id' => $data->instance], '*', MUST_EXIST);
    decisionforge_extract_editor_data($data);
    decisionforge_normalise_instance_data($data);
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('decisionforge', $data);

    if ((float) $previous->grade !== (float) $data->grade) {
        decisionforge_recalculate_attempt_grades($data);
    }

    decisionforge_grade_item_update($data);
    decisionforge_update_grades($data);
    decisionforge_recalculate_completion($data);
    decisionforge_update_calendar_events($data);

    return true;
}

/**
 * Recalculate stored attempt grades after the activity maximum changes.
 *
 * @param stdClass $activity
 * @return void
 */
function decisionforge_recalculate_attempt_grades(stdClass $activity): void {
    global $DB;

    $attempts = $DB->get_records('decisionforge_attempts', [
        'decisionforgeid' => $activity->id,
        'state' => 'finished',
    ]);
    foreach ($attempts as $attempt) {
        if (($attempt->mode ?? 'quick') === 'simulation') {
            $possible = (float) $attempt->possiblepoints;
        } else {
            $possible = (float) $DB->get_field_sql(
                'SELECT COALESCE(SUM(c.points), 0)
                   FROM {decisionforge_attempt_cases} ac
                   JOIN {decisionforge_cases} c ON c.id = ac.caseid
                  WHERE ac.attemptid = :attemptid',
                ['attemptid' => $attempt->id]
            );
        }
        $attempt->finalgrade = $possible > 0
            ? round(((float) $attempt->rawscore / $possible) * (float) $activity->grade, 5)
            : 0;
        $attempt->finalgrade = max(0, min((float) $activity->grade, $attempt->finalgrade));
        $attempt->timemodified = time();
        $DB->update_record('decisionforge_attempts', $attempt);
    }
}

/**
 * Delete an activity instance and its data.
 *
 * @param int $id
 * @return bool
 */
function decisionforge_delete_instance(int $id): bool {
    global $DB;

    $activity = $DB->get_record('decisionforge', ['id' => $id]);
    if (!$activity) {
        return false;
    }

    $cm = get_coursemodule_from_instance('decisionforge', $id, $activity->course, false, IGNORE_MISSING);
    if ($cm) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_decisionforge');
    }

    $attemptids = $DB->get_fieldset_select('decisionforge_attempts', 'id', 'decisionforgeid = :id', ['id' => $id]);
    if ($attemptids) {
        [$insql, $params] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'attempt');
        $DB->delete_records_select('decisionforge_responses', "attemptid $insql", $params);
        $DB->delete_records_select('decisionforge_simresponses', "attemptid $insql", $params);
        $DB->delete_records_select('decisionforge_attempt_cases', "attemptid $insql", $params);
    }

    $caseids = $DB->get_fieldset_select('decisionforge_cases', 'id', 'decisionforgeid = :id', ['id' => $id]);
    if ($caseids) {
        [$insql, $params] = $DB->get_in_or_equal($caseids, SQL_PARAMS_NAMED, 'case');
        $DB->delete_records_select('decisionforge_options', "caseid $insql", $params);
    }

    $stageids = $DB->get_fieldset_select('decisionforge_simstages', 'id',
        'decisionforgeid = :id', ['id' => $id]);
    if ($stageids) {
        [$stagesql, $stageparams] = $DB->get_in_or_equal($stageids, SQL_PARAMS_NAMED, 'stage');
        $DB->delete_records_select('decisionforge_simchoices', "stageid $stagesql", $stageparams);
        $DB->delete_records_select('decisionforge_simevidence', "stageid $stagesql", $stageparams);
    }

    $DB->delete_records('decisionforge_attempts', ['decisionforgeid' => $id]);
    $DB->delete_records('decisionforge_aidrafts', ['decisionforgeid' => $id]);
    $DB->delete_records('decisionforge_cases', ['decisionforgeid' => $id]);
    $DB->delete_records('decisionforge_simstages', ['decisionforgeid' => $id]);
    $DB->delete_records('event', ['modulename' => 'decisionforge', 'instance' => $id]);
    $DB->delete_records('decisionforge', ['id' => $id]);

    decisionforge_grade_item_delete($activity);

    return true;
}

/**
 * Serve activity files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function decisionforge_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): bool {
    global $DB;

    if ($context->contextlevel !== CONTEXT_MODULE || !in_array($filearea, ['intro', 'caseimage'], true)) {
        return false;
    }

    require_login($course, true, $cm);
    require_capability('mod/decisionforge:view', $context);

    $itemid = array_shift($args);
    if ($filearea === 'intro' && (int) $itemid !== 0) {
        return false;
    }
    if ($filearea === 'caseimage' && !$DB->record_exists('decisionforge_cases', [
        'id' => $itemid,
        'decisionforgeid' => $cm->instance,
    ])) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args) . '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_decisionforge', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Update or create the grade item.
 *
 * @param stdClass $activity
 * @param mixed $grades
 * @return int
 */
function decisionforge_grade_item_update(stdClass $activity, $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => $activity->name,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax' => (float) $activity->grade,
        'grademin' => 0,
    ];
    if (isset($activity->gradepass)) {
        $params['gradepass'] = (float) $activity->gradepass;
    }

    return grade_update(
        'mod/decisionforge',
        $activity->course,
        'mod',
        'decisionforge',
        $activity->id,
        0,
        $grades,
        $params
    );
}

/**
 * Delete the grade item.
 *
 * @param stdClass $activity
 * @return int
 */
function decisionforge_grade_item_delete(stdClass $activity): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/decisionforge',
        $activity->course,
        'mod',
        'decisionforge',
        $activity->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Calculate the grade that should be sent to the Gradebook.
 *
 * @param stdClass $activity
 * @param int $userid
 * @return stdClass|null
 */
function decisionforge_get_user_grade(stdClass $activity, int $userid): ?stdClass {
    global $DB;

    $attempts = $DB->get_records_select(
        'decisionforge_attempts',
        'decisionforgeid = :activityid AND userid = :userid AND state = :state',
        ['activityid' => $activity->id, 'userid' => $userid, 'state' => 'finished'],
        'attemptnumber ASC',
        'id, attemptnumber, finalgrade, timefinish'
    );
    if (!$attempts) {
        return null;
    }

    $grades = array_map(static fn($attempt) => (float) $attempt->finalgrade, $attempts);
    if ($activity->grademethod === 'average') {
        $finalgrade = array_sum($grades) / count($grades);
    } elseif ($activity->grademethod === 'last') {
        $lastattempt = end($attempts);
        $finalgrade = (float) $lastattempt->finalgrade;
    } else {
        $finalgrade = max($grades);
    }
    $lastattempt = end($attempts);
    $gradedtime = max(0, (int) $lastattempt->timefinish);

    return (object) [
        'userid' => $userid,
        'rawgrade' => round($finalgrade, 5),
        'dategraded' => $gradedtime,
        'datesubmitted' => $gradedtime,
    ];
}

/**
 * Return grades in the format expected by Moodle's Gradebook callbacks.
 *
 * @param stdClass $activity
 * @param int $userid Zero means all users with a finished attempt.
 * @return array Grades keyed by user ID.
 */
function decisionforge_get_user_grades(stdClass $activity, int $userid = 0): array {
    global $DB;

    if ($userid) {
        $grade = decisionforge_get_user_grade($activity, $userid);
        return $grade ? [$userid => $grade] : [];
    }

    $userids = $DB->get_fieldset_select(
        'decisionforge_attempts',
        'DISTINCT userid',
        'decisionforgeid = :activityid AND state = :state',
        ['activityid' => $activity->id, 'state' => 'finished']
    );
    $grades = [];
    foreach ($userids as $id) {
        $grade = decisionforge_get_user_grade($activity, (int) $id);
        if ($grade) {
            $grades[(int) $id] = $grade;
        }
    }
    return $grades;
}

/**
 * Push grades to the Moodle Gradebook.
 *
 * @param stdClass $activity
 * @param int $userid Zero means all users.
 * @param bool $nullifnone
 * @return void
 */
function decisionforge_update_grades(stdClass $activity, int $userid = 0, bool $nullifnone = true): void {
    if ($userid) {
        $grade = decisionforge_get_user_grade($activity, $userid);
        if (!$grade && $nullifnone) {
            $grade = (object) ['userid' => $userid, 'rawgrade' => null];
        }
        decisionforge_grade_item_update($activity, $grade ? [$userid => $grade] : []);
        return;
    }

    decisionforge_grade_item_update($activity, decisionforge_get_user_grades($activity));
}

/**
 * Recalculate Moodle's standard completion rules after grading settings change.
 *
 * @param stdClass $activity
 * @param int $userid Zero means every user with a finished attempt.
 * @return void
 */
function decisionforge_recalculate_completion(stdClass $activity, int $userid = 0): void {
    global $DB;

    $cm = get_coursemodule_from_instance(
        'decisionforge',
        $activity->id,
        $activity->course,
        false,
        IGNORE_MISSING
    );
    if (!$cm) {
        return;
    }

    $completion = new completion_info(get_course($activity->course));
    if (!$completion->is_enabled($cm)) {
        return;
    }

    $userids = $userid ? [$userid] : $DB->get_fieldset_select(
        'decisionforge_attempts',
        'DISTINCT userid',
        'decisionforgeid = :activityid AND state = :state',
        ['activityid' => $activity->id, 'state' => 'finished']
    );
    foreach ($userids as $id) {
        $completion->update_state($cm, COMPLETION_UNKNOWN, (int) $id);
    }
}

/**
 * Update open and close calendar events.
 *
 * @param stdClass $activity
 * @return void
 */
function decisionforge_update_calendar_events(stdClass $activity): void {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/calendar/lib.php');

    if (!empty($activity->coursemodule)) {
        $cm = get_coursemodule_from_id('decisionforge', $activity->coursemodule, $activity->course, false, IGNORE_MISSING);
    } else {
        $cm = get_coursemodule_from_instance('decisionforge', $activity->id, $activity->course, false, IGNORE_MISSING);
    }
    if (!$cm) {
        return;
    }

    foreach (['open' => 'timeopen', 'close' => 'timeclose'] as $type => $field) {
        $eventid = $DB->get_field('event', 'id', [
            'modulename' => 'decisionforge',
            'instance' => $activity->id,
            'eventtype' => $type,
        ]);
        if (!empty($activity->{$field})) {
            $event = $eventid ? calendar_event::load($eventid) : new stdClass();
            $event->name = $activity->name;
            $event->description = format_module_intro('decisionforge', $activity, $cm->id, false);
            $event->format = FORMAT_HTML;
            $event->courseid = $activity->course;
            $event->groupid = 0;
            $event->userid = 0;
            $event->modulename = 'decisionforge';
            $event->instance = $activity->id;
            $event->eventtype = $type;
            $event->timestart = $activity->{$field};
            $event->timeduration = 0;
            $event->visible = $cm->visible;
            if ($eventid) {
                $event->update($event);
            } else {
                calendar_event::create($event);
            }
        } elseif ($eventid) {
            calendar_event::load($eventid)->delete();
        }
    }
}

/**
 * Reset user data in a course.
 *
 * @param stdClass $data
 * @return array
 */
function decisionforge_reset_userdata(stdClass $data): array {
    global $DB;

    $status = [];
    if (!empty($data->reset_decisionforge_attempts)) {
        $activityids = $DB->get_fieldset_select('decisionforge', 'id', 'course = :course', ['course' => $data->courseid]);
        if ($activityids) {
            [$insql, $params] = $DB->get_in_or_equal($activityids, SQL_PARAMS_NAMED, 'activity');
            $attemptids = $DB->get_fieldset_select('decisionforge_attempts', 'id', "decisionforgeid $insql", $params);
            if ($attemptids) {
                [$attemptsql, $attemptparams] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'attempt');
                $DB->delete_records_select('decisionforge_responses', "attemptid $attemptsql", $attemptparams);
                $DB->delete_records_select('decisionforge_simresponses', "attemptid $attemptsql", $attemptparams);
                $DB->delete_records_select('decisionforge_attempt_cases', "attemptid $attemptsql", $attemptparams);
                $DB->delete_records_select('decisionforge_attempts', "id $attemptsql", $attemptparams);
            }
            foreach ($activityids as $activityid) {
                $activity = $DB->get_record('decisionforge', ['id' => $activityid]);
                decisionforge_grade_item_update($activity, 'reset');
            }
        }
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_decisionforge'),
            'item' => get_string('attempts', 'mod_decisionforge'),
            'error' => false,
        ];
    }
    return $status;
}

/**
 * Add reset options.
 *
 * @param MoodleQuickForm $mform
 * @return void
 */
function decisionforge_reset_course_form_definition(MoodleQuickForm $mform): void {
    $mform->addElement('header', 'decisionforgeheader', get_string('modulenameplural', 'mod_decisionforge'));
    $mform->addElement('advcheckbox', 'reset_decisionforge_attempts', get_string('attempts', 'mod_decisionforge'));
}
