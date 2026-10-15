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

namespace mod_decisionforge\local;

use context_module;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Core business logic for Decision Forge.
 *
 * All score calculations and ownership checks happen on the server. No answer
 * quality data is exposed to the learner before a response is committed.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager {
    /** @var stdClass */
    private $activity;

    /** @var stdClass|\core_course\cm_info */
    private $cm;

    /** @var context_module */
    private $context;

    /**
     * Constructor.
     *
     * @param stdClass $activity
     * @param stdClass|\core_course\cm_info $cm
     */
    public function __construct(stdClass $activity, stdClass|\core_course\cm_info $cm) {
        $this->activity = $activity;
        $this->cm = $cm;
        $this->context = context_module::instance($cm->id);
    }

    /**
     * Build a manager from a course-module id.
     *
     * @param int $cmid
     * @return self
     */
    public static function from_cmid(int $cmid): self {
        global $DB;

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'decisionforge');
        $activity = $DB->get_record('decisionforge', ['id' => $cm->instance], '*', MUST_EXIST);
        return new self($activity, $cm);
    }

    /**
     * Get the activity record.
     *
     * @return stdClass
     */
    public function get_activity(): stdClass {
        return $this->activity;
    }

    /**
     * Get course module.
     *
     * @return stdClass|\core_course\cm_info
     */
    public function get_cm(): stdClass|\core_course\cm_info {
        return $this->cm;
    }

    /**
     * Get module context.
     *
     * @return context_module
     */
    public function get_context(): context_module {
        return $this->context;
    }

    /**
     * Get cases.
     *
     * @param bool $activeonly
     * @return array
     */
    public function get_cases(bool $activeonly = false): array {
        global $DB;

        $conditions = ['decisionforgeid' => $this->activity->id];
        if ($activeonly) {
            $conditions['active'] = 1;
            $conditions['archived'] = 0;
        }
        return $DB->get_records('decisionforge_cases', $conditions, 'position ASC, id ASC');
    }

    /**
     * Get one case, ensuring it belongs to this activity.
     *
     * @param int $caseid
     * @return stdClass
     */
    public function get_case(int $caseid): stdClass {
        global $DB;

        return $DB->get_record(
            'decisionforge_cases',
            ['id' => $caseid, 'decisionforgeid' => $this->activity->id],
            '*',
            MUST_EXIST
        );
    }

    /**
     * Get options for a case.
     *
     * @param int $caseid
     * @return array
     */
    public function get_options(int $caseid): array {
        global $DB;
        return $DB->get_records('decisionforge_options', ['caseid' => $caseid], 'position ASC, id ASC');
    }

    /**
     * Return availability state for the current time.
     *
     * @return array
     */
    public function get_availability(): array {
        $now = time();
        if (!empty($this->activity->timeopen) && $now < $this->activity->timeopen) {
            return [
                'available' => false,
                'reason' => get_string(
                    'notavailableyet',
                    'mod_decisionforge',
                    userdate($this->activity->timeopen)
                ),
            ];
        }
        if (!empty($this->activity->timeclose) && $now > $this->activity->timeclose) {
            return [
                'available' => false,
                'reason' => get_string(
                    'activityclosed',
                    'mod_decisionforge',
                    userdate($this->activity->timeclose)
                ),
            ];
        }
        return ['available' => true, 'reason' => ''];
    }

    /**
     * Count a user's attempts.
     *
     * @param int $userid
     * @param string|null $state
     * @return int
     */
    public function count_attempts(int $userid, ?string $state = null): int {
        global $DB;
        $conditions = ['decisionforgeid' => $this->activity->id, 'userid' => $userid];
        if ($state !== null) {
            $conditions['state'] = $state;
        }
        return $DB->count_records('decisionforge_attempts', $conditions);
    }

    /**
     * Get the user's current in-progress attempt.
     *
     * @param int $userid
     * @param string|null $mode Restrict the result to one experience mode.
     * @return stdClass|null
     */
    public function get_inprogress_attempt(int $userid, ?string $mode = null): ?stdClass {
        global $DB;

        $params = [
            'activityid' => $this->activity->id,
            'userid' => $userid,
            'state' => 'inprogress',
        ];
        $modesql = '';
        if ($mode !== null) {
            $modesql = ' AND mode = :mode';
            $params['mode'] = $mode;
        }
        $attempts = $DB->get_records_sql(
            "SELECT *
               FROM {decisionforge_attempts}
              WHERE decisionforgeid = :activityid
                    AND userid = :userid
                    AND state = :state $modesql
           ORDER BY attemptnumber DESC",
            $params,
            0,
            1
        );
        return $attempts ? reset($attempts) : null;
    }

    /**
     * Get the latest attempt.
     *
     * @param int $userid
     * @param string|null $state
     * @param string|null $mode
     * @return stdClass|null
     */
    public function get_latest_attempt(int $userid, ?string $state = null, ?string $mode = null): ?stdClass {
        global $DB;
        $params = ['activityid' => $this->activity->id, 'userid' => $userid];
        $statesql = '';
        $modesql = '';
        if ($state !== null) {
            $statesql = ' AND state = :state';
            $params['state'] = $state;
        }
        if ($mode !== null) {
            $modesql = ' AND mode = :mode';
            $params['mode'] = $mode;
        }
        $attempts = $DB->get_records_sql(
            "SELECT *
               FROM {decisionforge_attempts}
              WHERE decisionforgeid = :activityid AND userid = :userid $statesql $modesql
           ORDER BY attemptnumber DESC",
            $params,
            0,
            1
        );
        return $attempts ? reset($attempts) : null;
    }

    /**
     * Whether a learner can create another attempt.
     *
     * @param int $userid
     * @return bool
     */
    public function can_start_attempt(int $userid): bool {
        if ($this->get_inprogress_attempt($userid)) {
            return true;
        }
        if (empty($this->activity->attemptsallowed)) {
            return true;
        }
        return $this->count_attempts($userid) < (int) $this->activity->attemptsallowed;
    }

    /**
     * Create or return an in-progress attempt.
     *
     * @param int $userid
     * @return stdClass
     */
    public function start_attempt(int $userid): stdClass {
        global $DB;

        if ($existing = $this->get_inprogress_attempt($userid, 'quick')) {
            return $existing;
        }
        if ($this->get_inprogress_attempt($userid)) {
            throw new moodle_exception('errorothermodeinprogress', 'mod_decisionforge');
        }
        $availability = $this->get_availability();
        if (!$availability['available']) {
            throw new moodle_exception('notopen', 'mod_decisionforge');
        }
        if (!$this->can_start_attempt($userid)) {
            throw new moodle_exception('attemptlimitreached', 'mod_decisionforge');
        }

        $cases = array_values($this->get_cases(true));
        if (!$cases) {
            throw new moodle_exception('erroremptyactivity', 'mod_decisionforge');
        }
        if (!empty($this->activity->shufflecases)) {
            shuffle($cases);
        }

        $transaction = $DB->start_delegated_transaction();
        $attempt = (object) [
            'decisionforgeid' => $this->activity->id,
            'userid' => $userid,
            'attemptnumber' => $this->count_attempts($userid) + 1,
            'state' => 'inprogress',
            'mode' => 'quick',
            'currentcase' => 0,
            'rawscore' => 0,
            'finalgrade' => 0,
            'timestart' => time(),
            'timefinish' => 0,
            'timemodified' => time(),
        ];
        $attempt->id = $DB->insert_record('decisionforge_attempts', $attempt);

        foreach ($cases as $position => $case) {
            $optionids = array_keys($this->get_options($case->id));
            if (count($optionids) < 2) {
                throw new moodle_exception('errorcaseconfiguration', 'mod_decisionforge', '', format_string($case->title));
            }
            if (!empty($this->activity->shuffleanswers)) {
                shuffle($optionids);
            }
            $DB->insert_record('decisionforge_attempt_cases', (object) [
                'attemptid' => $attempt->id,
                'caseid' => $case->id,
                'caseposition' => $position,
                'optionorder' => implode(',', $optionids),
            ]);
        }
        $transaction->allow_commit();

        $event = \mod_decisionforge\event\attempt_started::create([
            'objectid' => $attempt->id,
            'context' => $this->context,
            'relateduserid' => $userid,
            'other' => ['attemptnumber' => $attempt->attemptnumber],
        ]);
        $event->trigger();

        return $attempt;
    }

    /**
     * Get and validate an attempt.
     *
     * @param int $attemptid
     * @param int $userid
     * @param bool $allowreportaccess
     * @return stdClass
     */
    public function get_attempt(int $attemptid, int $userid, bool $allowreportaccess = false): stdClass {
        global $DB;

        $attempt = $DB->get_record(
            'decisionforge_attempts',
            ['id' => $attemptid, 'decisionforgeid' => $this->activity->id],
            '*',
            MUST_EXIST
        );
        if ((int) $attempt->userid !== $userid && !$allowreportaccess) {
            throw new moodle_exception('errorattemptowner', 'mod_decisionforge');
        }
        return $attempt;
    }

    /**
     * Get ordered case snapshots for an attempt.
     *
     * @param int $attemptid
     * @return array
     */
    public function get_attempt_cases(int $attemptid): array {
        global $DB;
        return $DB->get_records('decisionforge_attempt_cases', ['attemptid' => $attemptid], 'caseposition ASC');
    }

    /**
     * Check and finish an expired attempt.
     *
     * @param stdClass $attempt
     * @return bool True if the attempt was expired and finished.
     */
    public function enforce_time_limit(stdClass $attempt): bool {
        if ($attempt->state !== 'inprogress') {
            return false;
        }
        $deadline = $this->get_attempt_deadline($attempt);
        if ($deadline && time() >= $deadline) {
            $this->finish_attempt($attempt);
            return true;
        }
        return false;
    }

    /**
     * Get the earliest applicable deadline for an attempt.
     *
     * @param stdClass $attempt
     * @return int Unix timestamp, or zero when there is no deadline.
     */
    public function get_attempt_deadline(stdClass $attempt): int {
        $deadlines = [];
        if (!empty($this->activity->timelimit)) {
            $deadlines[] = (int) $attempt->timestart + (int) $this->activity->timelimit;
        }
        if (!empty($this->activity->timeclose)) {
            $deadlines[] = (int) $this->activity->timeclose;
        }
        return $deadlines ? min($deadlines) : 0;
    }

    /**
     * Get the active case at the attempt's current position.
     *
     * @param stdClass $attempt
     * @return array|null Array containing snapshot, case and ordered options.
     */
    public function get_current_case(stdClass $attempt): ?array {
        global $DB;

        $snapshot = $DB->get_record('decisionforge_attempt_cases', [
            'attemptid' => $attempt->id,
            'caseposition' => $attempt->currentcase,
        ]);
        if (!$snapshot) {
            return null;
        }
        $case = $DB->get_record('decisionforge_cases', ['id' => $snapshot->caseid], '*', MUST_EXIST);
        $alloptions = $DB->get_records('decisionforge_options', ['caseid' => $case->id]);
        $options = [];
        foreach (array_filter(array_map('intval', explode(',', $snapshot->optionorder))) as $optionid) {
            if (isset($alloptions[$optionid])) {
                $options[$optionid] = $alloptions[$optionid];
            }
        }
        return ['snapshot' => $snapshot, 'case' => $case, 'options' => $options];
    }

    /**
     * Record a learner decision atomically.
     *
     * @param stdClass $attempt
     * @param int $optionid
     * @param string $justification
     * @param int $confidence
     * @return array Response, case, option and finished flag.
     */
    public function record_response(stdClass $attempt, int $optionid, string $justification, int $confidence): array {
        global $DB;

        if ($attempt->state !== 'inprogress') {
            throw new moodle_exception('errorattemptfinished', 'mod_decisionforge');
        }
        if ($this->enforce_time_limit($attempt)) {
            throw new moodle_exception('expired', 'mod_decisionforge');
        }
        if ($confidence < 0 || $confidence > 100) {
            throw new moodle_exception('confidenceinvalid', 'mod_decisionforge');
        }
        $justification = trim($justification);
        if (\core_text::strlen($justification) > 2000) {
            throw new moodle_exception('justificationtoolong', 'mod_decisionforge');
        }
        if (!empty($this->activity->requirejustification) && $justification === '') {
            throw new moodle_exception('justificationrequired', 'mod_decisionforge');
        }

        $current = $this->get_current_case($attempt);
        if (!$current) {
            throw new moodle_exception('errorinvalidcase', 'mod_decisionforge');
        }
        $case = $current['case'];
        if (!isset($current['options'][$optionid])) {
            throw new moodle_exception('errorinvalidoption', 'mod_decisionforge');
        }
        if ($DB->record_exists('decisionforge_responses', ['attemptid' => $attempt->id, 'caseid' => $case->id])) {
            throw new moodle_exception('errorconcurrentresponse', 'mod_decisionforge');
        }
        $option = $current['options'][$optionid];
        $score = round((float) $case->points * ((float) $option->score / 100), 5);

        $transaction = $DB->start_delegated_transaction();
        $response = (object) [
            'attemptid' => $attempt->id,
            'caseid' => $case->id,
            'optionid' => $optionid,
            'justification' => $justification,
            'confidence' => $confidence,
            'score' => $score,
            'timeanswered' => time(),
        ];
        $response->id = $DB->insert_record('decisionforge_responses', $response);

        $attempt->rawscore = (float) $attempt->rawscore + $score;
        $attempt->currentcase = (int) $attempt->currentcase + 1;
        $attempt->timemodified = time();
        $DB->update_record('decisionforge_attempts', $attempt);

        $totalcases = $DB->count_records('decisionforge_attempt_cases', ['attemptid' => $attempt->id]);
        $finished = $attempt->currentcase >= $totalcases;
        if ($finished) {
            $attempt = $this->finish_attempt($attempt, false);
        }
        $transaction->allow_commit();

        $event = \mod_decisionforge\event\response_recorded::create([
            'objectid' => $response->id,
            'context' => $this->context,
            'relateduserid' => $attempt->userid,
            'other' => ['attemptid' => $attempt->id, 'caseid' => $case->id],
        ]);
        $event->trigger();

        if ($finished) {
            $this->after_attempt_finished($attempt);
        }

        return [
            'response' => $response,
            'case' => $case,
            'option' => $option,
            'finished' => $finished,
            'attempt' => $attempt,
        ];
    }

    /**
     * Finish an attempt and calculate its grade.
     *
     * @param stdClass $attempt
     * @param bool $commit Whether to run post-finish grade/event processing now.
     * @return stdClass
     */
    public function finish_attempt(stdClass $attempt, bool $commit = true): stdClass {
        global $DB;

        if ($attempt->state === 'finished') {
            return $attempt;
        }
        $sql = 'SELECT COALESCE(SUM(c.points), 0)
                  FROM {decisionforge_attempt_cases} ac
                  JOIN {decisionforge_cases} c ON c.id = ac.caseid
                 WHERE ac.attemptid = :attemptid';
        $possible = (float) $DB->get_field_sql($sql, ['attemptid' => $attempt->id]);
        $attempt->finalgrade = $possible > 0
            ? round(((float) $attempt->rawscore / $possible) * (float) $this->activity->grade, 5)
            : 0;
        $attempt->finalgrade = max(0, min((float) $this->activity->grade, $attempt->finalgrade));
        $attempt->state = 'finished';
        $attempt->timefinish = time();
        $attempt->timemodified = time();
        $DB->update_record('decisionforge_attempts', $attempt);

        if ($commit) {
            $this->after_attempt_finished($attempt);
        }
        return $attempt;
    }

    /**
     * Perform gradebook, completion and event work after a committed finish.
     *
     * @param stdClass $attempt
     * @return void
     */
    private function after_attempt_finished(stdClass $attempt): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/decisionforge/lib.php');
        decisionforge_update_grades($this->activity, (int) $attempt->userid);

        $completion = new \completion_info(get_course($this->activity->course));
        if ($completion->is_enabled($this->cm)) {
            // Re-evaluate Moodle's configured view, grade and passing-grade rules.
            // A finished attempt must not be forced complete when its grade is below
            // the activity's grade-to-pass threshold.
            $completion->update_state($this->cm, COMPLETION_UNKNOWN, (int) $attempt->userid);
        }

        $event = \mod_decisionforge\event\attempt_submitted::create([
            'objectid' => $attempt->id,
            'context' => $this->context,
            'relateduserid' => $attempt->userid,
            'other' => ['attemptnumber' => $attempt->attemptnumber, 'finalgrade' => $attempt->finalgrade],
        ]);
        $event->trigger();
    }

    /**
     * Return all response details for a finished attempt.
     *
     * @param int $attemptid
     * @return array
     */
    public function get_attempt_review(int $attemptid): array {
        global $DB;

        $sql = 'SELECT ac.id AS snapshotid, ac.caseposition, c.id AS caseid, c.title,
                       c.questiontext, c.points, r.id AS responseid, r.optionid,
                       r.justification, r.confidence, r.score, r.timeanswered,
                       o.optiontext, o.optiontextformat, o.score AS optionquality,
                       o.consequence, o.consequenceformat
                  FROM {decisionforge_attempt_cases} ac
                  JOIN {decisionforge_cases} c ON c.id = ac.caseid
             LEFT JOIN {decisionforge_responses} r ON r.attemptid = ac.attemptid AND r.caseid = c.id
             LEFT JOIN {decisionforge_options} o ON o.id = r.optionid
                 WHERE ac.attemptid = :attemptid
              ORDER BY ac.caseposition ASC';
        return $DB->get_records_sql($sql, ['attemptid' => $attemptid]);
    }

    /**
     * Return a secure URL to the first image in a case image file area.
     *
     * @param int $caseid
     * @return string|null
     */
    public function get_case_image_url(int $caseid): ?string {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id,
            'mod_decisionforge',
            'caseimage',
            $caseid,
            'sortorder, id',
            false
        );
        if (!$files) {
            return null;
        }
        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            $this->context->id,
            'mod_decisionforge',
            'caseimage',
            $caseid,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    /**
     * Normalize case positions after a mutation.
     *
     * @return void
     */
    public function normalize_positions(): void {
        global $DB;
        foreach (array_values($this->get_cases()) as $position => $case) {
            if ((int) $case->position !== $position) {
                $case->position = $position;
                $DB->update_record('decisionforge_cases', $case);
            }
        }
    }

    /**
     * Move a case by one place.
     *
     * @param int $caseid
     * @param int $direction -1 for up, 1 for down.
     * @return void
     */
    public function move_case(int $caseid, int $direction): void {
        global $DB;

        $cases = array_values($this->get_cases());
        $index = null;
        foreach ($cases as $key => $case) {
            if ((int) $case->id === $caseid) {
                $index = $key;
                break;
            }
        }
        if ($index === null) {
            throw new moodle_exception('errorinvalidcase', 'mod_decisionforge');
        }
        $target = $index + $direction;
        if (!isset($cases[$target])) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $cases[$index]->position = $target;
        $cases[$target]->position = $index;
        $DB->update_record('decisionforge_cases', $cases[$index]);
        $DB->update_record('decisionforge_cases', $cases[$target]);
        $transaction->allow_commit();
        $this->normalize_positions();
    }

    /**
     * Toggle case availability.
     *
     * @param int $caseid
     * @return void
     */
    public function toggle_case(int $caseid): void {
        global $DB;
        $case = $this->get_case($caseid);
        if (!empty($case->archived)) {
            throw new moodle_exception('errorarchivedcase', 'mod_decisionforge');
        }
        $case->active = empty($case->active) ? 1 : 0;
        $case->timemodified = time();
        $DB->update_record('decisionforge_cases', $case);
    }

    /**
     * Delete a case and its files.
     *
     * @param int $caseid
     * @return void
     */
    public function delete_case(int $caseid): void {
        global $DB;
        $case = $this->get_case($caseid);
        if ($DB->record_exists('decisionforge_attempt_cases', ['caseid' => $caseid])) {
            // Preserve referential integrity for recorded attempts; hide instead of deleting.
            $case->active = 0;
            $case->archived = 1;
            if (strpos($case->title, '(' . get_string('archived', 'mod_decisionforge') . ')') === false) {
                $case->title .= ' (' . get_string('archived', 'mod_decisionforge') . ')';
            }
            $case->timemodified = time();
            $DB->update_record('decisionforge_cases', $case);
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('decisionforge_options', ['caseid' => $caseid]);
        $DB->delete_records('decisionforge_cases', ['id' => $caseid]);
        $transaction->allow_commit();
        get_file_storage()->delete_area_files($this->context->id, 'mod_decisionforge', 'caseimage', $caseid);
        $this->normalize_positions();
    }

    /**
     * Duplicate a case and its option records/files.
     *
     * @param int $caseid
     * @return int
     */
    public function duplicate_case(int $caseid): int {
        global $DB;

        $configuredmax = (int) get_config('decisionforge', 'maxcases');
        $maxcases = $configuredmax > 0 ? $configuredmax : 50;
        if (count($this->get_cases()) >= $maxcases) {
            throw new moodle_exception('maxcasesreached', 'mod_decisionforge', '', $maxcases);
        }
        $case = $this->get_case($caseid);
        unset($case->id);
        $case->title .= ' (' . get_string('copy') . ')';
        $case->position = count($this->get_cases());
        $case->active = 1;
        $case->archived = 0;
        $case->timecreated = time();
        $case->timemodified = time();
        $transaction = $DB->start_delegated_transaction();
        $newcaseid = $DB->insert_record('decisionforge_cases', $case);

        foreach ($this->get_options($caseid) as $option) {
            unset($option->id);
            $option->caseid = $newcaseid;
            $DB->insert_record('decisionforge_options', $option);
        }
        $transaction->allow_commit();

        $fs = get_file_storage();
        foreach ($fs->get_area_files($this->context->id, 'mod_decisionforge', 'caseimage', $caseid, 'id', false) as $file) {
            $record = [
                'contextid' => $this->context->id,
                'component' => 'mod_decisionforge',
                'filearea' => 'caseimage',
                'itemid' => $newcaseid,
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
            ];
            $fs->create_file_from_storedfile($record, $file);
        }
        return $newcaseid;
    }

    /**
     * Delete an attempt and recalculate the user's grade.
     *
     * @param int $attemptid
     * @return void
     */
    public function delete_attempt(int $attemptid): void {
        global $CFG, $DB;

        $attempt = $DB->get_record(
            'decisionforge_attempts',
            ['id' => $attemptid, 'decisionforgeid' => $this->activity->id],
            '*',
            MUST_EXIST
        );
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('decisionforge_responses', ['attemptid' => $attemptid]);
        $DB->delete_records('decisionforge_simresponses', ['attemptid' => $attemptid]);
        $DB->delete_records('decisionforge_attempt_cases', ['attemptid' => $attemptid]);
        $DB->delete_records('decisionforge_attempts', ['id' => $attemptid]);
        $transaction->allow_commit();

        require_once($CFG->dirroot . '/mod/decisionforge/lib.php');
        decisionforge_update_grades($this->activity, (int) $attempt->userid, true);
        $completion = new \completion_info(get_course($this->activity->course));
        if ($completion->is_enabled($this->cm)) {
            $completion->update_state($this->cm, COMPLETION_UNKNOWN, (int) $attempt->userid);
        }
    }
}
