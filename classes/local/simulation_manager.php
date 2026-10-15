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
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Branching simulation engine. Attempts keep immutable content snapshots.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class simulation_manager {
    private const MAX_STAGE_VISITS = 50;

    /** @var stdClass */
    private $activity;

    /** @var stdClass|\core_course\cm_info */
    private $cm;

    /** @var context_module */
    private $context;

    /** @param stdClass $activity @param stdClass|\core_course\cm_info $cm */
    public function __construct(stdClass $activity, stdClass|\core_course\cm_info $cm) {
        $this->activity = $activity;
        $this->cm = $cm;
        $this->context = context_module::instance($cm->id);
    }

    /** @param int $cmid @return self */
    public static function from_cmid(int $cmid): self {
        global $DB;
        [, $cm] = get_course_and_cm_from_cmid($cmid, 'decisionforge');
        $activity = $DB->get_record('decisionforge', ['id' => $cm->instance], '*', MUST_EXIST);
        return new self($activity, $cm);
    }

    /** @return stdClass */
    public function get_activity(): stdClass {
        return $this->activity;
    }

    /** @return stdClass|\core_course\cm_info */
    public function get_cm(): stdClass|\core_course\cm_info {
        return $this->cm;
    }

    /** @return context_module */
    public function get_context(): context_module {
        return $this->context;
    }

    /** @param bool $activeonly @return array */
    public function get_stages(bool $activeonly = false): array {
        global $DB;
        $conditions = ['decisionforgeid' => $this->activity->id];
        if ($activeonly) {
            $conditions['active'] = 1;
        }
        return $DB->get_records('decisionforge_simstages', $conditions, 'position ASC, id ASC');
    }

    /** @param int $stageid @return stdClass */
    public function get_stage(int $stageid): stdClass {
        global $DB;
        return $DB->get_record('decisionforge_simstages', [
            'id' => $stageid,
            'decisionforgeid' => $this->activity->id,
        ], '*', MUST_EXIST);
    }

    /** @param int $stageid @return array */
    public function get_choices(int $stageid): array {
        global $DB;
        return $DB->get_records('decisionforge_simchoices', ['stageid' => $stageid], 'position ASC, id ASC');
    }

    /** @param int $stageid @return array */
    public function get_evidence(int $stageid): array {
        global $DB;
        return $DB->get_records('decisionforge_simevidence', ['stageid' => $stageid], 'position ASC, id ASC');
    }

    /** @return array */
    public function get_configuration_warnings(): array {
        $stages = array_values($this->get_stages(true));
        if (!$stages) {
            return [get_string('simulationnostages', 'mod_decisionforge')];
        }
        $warnings = [];
        if (count($stages) > self::MAX_STAGE_VISITS) {
            $warnings[] = get_string('simulationstagelimit', 'mod_decisionforge', self::MAX_STAGE_VISITS);
        }
        $activeids = array_fill_keys(array_map(static fn($stage) => (int) $stage->id, $stages), true);
        $allowedkinds = ['briefing', 'evidence', 'decision', 'prioritise', 'reflection'];
        $allowedevidencekinds = ['document', 'message', 'record', 'alert'];
        foreach ($stages as $stage) {
            $choices = $this->get_choices((int) $stage->id);
            $evidenceitems = $this->get_evidence((int) $stage->id);
            if (!in_array($stage->stagekind, $allowedkinds, true)) {
                $warnings[] = get_string('simulationstagekindinvalid', 'mod_decisionforge', $stage->title);
            }
            if (in_array($stage->stagekind, ['decision', 'prioritise'], true) && count($choices) < 2) {
                $warnings[] = get_string('simulationstagechoicesmissing', 'mod_decisionforge', $stage->title);
            }
            if ($stage->stagekind === 'decision' && $choices
                    && max(array_map(static fn($choice) => (float) $choice->quality, $choices)) < 100) {
                $warnings[] = get_string('simulationbestchoicemissing', 'mod_decisionforge', $stage->title);
            }
            if ($stage->stagekind === 'evidence' && !$evidenceitems) {
                $warnings[] = get_string('simulationevidencemissing', 'mod_decisionforge', $stage->title);
            }
            foreach ($evidenceitems as $evidence) {
                if (!in_array($evidence->evidencekind, $allowedevidencekinds, true)) {
                    $warnings[] = get_string('simulationevidencekindinvalid', 'mod_decisionforge', $stage->title);
                    break;
                }
            }
            if ($stage->stagekind === 'prioritise') {
                $ranks = array_map(static fn($choice) => (int) $choice->targetrank, $choices);
                sort($ranks);
                if ($ranks !== range(1, count($choices))) {
                    $warnings[] = get_string('simulationranksinvalid', 'mod_decisionforge', $stage->title);
                }
            }
            if ((int) $stage->nextstageid > 0 && !isset($activeids[(int) $stage->nextstageid])) {
                $warnings[] = get_string('simulationrouteinvalid', 'mod_decisionforge', $stage->title);
            }
            if ((int) $stage->nextstageid === (int) $stage->id) {
                $warnings[] = get_string('simulationselfroute', 'mod_decisionforge', $stage->title);
            }
            foreach ($choices as $choice) {
                if ((int) $choice->nextstageid > 0 && !isset($activeids[(int) $choice->nextstageid])) {
                    $warnings[] = get_string('simulationchoicerouteinvalid', 'mod_decisionforge', $stage->title);
                    break;
                }
                if ((int) $choice->nextstageid === (int) $stage->id) {
                    $warnings[] = get_string('simulationselfroute', 'mod_decisionforge', $stage->title);
                    break;
                }
            }
        }
        return array_values(array_unique($warnings));
    }

    /** @return array */
    private function build_snapshot(): array {
        $stages = [];
        $order = [];
        foreach ($this->get_stages(true) as $stage) {
            $id = (int) $stage->id;
            $order[] = $id;
            $record = (array) $stage;
            $record['choices'] = array_values(array_map(static fn($item) => (array) $item, $this->get_choices($id)));
            $record['evidence'] = array_values(array_map(static fn($item) => (array) $item, $this->get_evidence($id)));
            $stages[(string) $id] = $record;
        }
        return ['version' => 1, 'order' => $order, 'stages' => $stages];
    }

    /** @param stdClass $attempt @return array */
    public function decode_snapshot(stdClass $attempt): array {
        $snapshot = json_decode($attempt->snapshotjson ?? '', true);
        if (!is_array($snapshot) || empty($snapshot['order']) || empty($snapshot['stages'])) {
            throw new moodle_exception('errorsimulationsnapshot', 'mod_decisionforge');
        }
        return $snapshot;
    }

    /** @param int $userid @return stdClass */
    public function start_attempt(int $userid): stdClass {
        global $DB;
        $coremanager = new manager($this->activity, $this->cm);
        if ($existing = $coremanager->get_inprogress_attempt($userid, 'simulation')) {
            return $existing;
        }
        if ($coremanager->get_inprogress_attempt($userid)) {
            throw new moodle_exception('errorothermodeinprogress', 'mod_decisionforge');
        }
        $availability = $coremanager->get_availability();
        if (!$availability['available']) {
            throw new moodle_exception('notopen', 'mod_decisionforge');
        }
        if (!$coremanager->can_start_attempt($userid)) {
            throw new moodle_exception('attemptlimitreached', 'mod_decisionforge');
        }
        $warnings = $this->get_configuration_warnings();
        if ($warnings) {
            throw new moodle_exception('errorsimulationconfiguration', 'mod_decisionforge', '', implode(' ', $warnings));
        }
        $snapshot = $this->build_snapshot();
        $firststageid = (int) reset($snapshot['order']);
        $now = time();
        $attempt = (object) [
            'decisionforgeid' => $this->activity->id,
            'userid' => $userid,
            'attemptnumber' => $coremanager->count_attempts($userid) + 1,
            'state' => 'inprogress',
            'mode' => 'simulation',
            'currentcase' => 0,
            'currentstageid' => $firststageid,
            'snapshotjson' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'pathjson' => json_encode([$firststageid]),
            'rawscore' => 0,
            'possiblepoints' => 0,
            'finalgrade' => 0,
            'safety' => $this->clamp_metric($this->activity->initialsafety ?? 72),
            'compliance' => $this->clamp_metric($this->activity->initialcompliance ?? 68),
            'trust' => $this->clamp_metric($this->activity->initialtrust ?? 74),
            'resources' => $this->clamp_metric($this->activity->initialresources ?? 80),
            'outcomecode' => '',
            'timestart' => $now,
            'timefinish' => 0,
            'timemodified' => $now,
        ];
        $attempt->id = $DB->insert_record('decisionforge_attempts', $attempt);
        $event = \mod_decisionforge\event\attempt_started::create([
            'objectid' => $attempt->id,
            'context' => $this->context,
            'relateduserid' => $userid,
            'other' => ['attemptnumber' => $attempt->attemptnumber],
        ]);
        $event->trigger();
        return $attempt;
    }

    /** @param int $attemptid @param int $userid @param bool $allowreportaccess @return stdClass */
    public function get_attempt(int $attemptid, int $userid, bool $allowreportaccess = false): stdClass {
        $attempt = (new manager($this->activity, $this->cm))->get_attempt($attemptid, $userid, $allowreportaccess);
        if (($attempt->mode ?? 'quick') !== 'simulation') {
            throw new moodle_exception('errornotsimulationattempt', 'mod_decisionforge');
        }
        return $attempt;
    }

    /** @param stdClass $attempt @return array|null */
    public function get_current_stage(stdClass $attempt): ?array {
        if (empty($attempt->currentstageid)) {
            return null;
        }
        $snapshot = $this->decode_snapshot($attempt);
        return $snapshot['stages'][(string) $attempt->currentstageid] ?? null;
    }

    /**
     * Submit the current stage and calculate score, indicators and next route.
     *
     * @param stdClass $attempt
     * @param int $stageid
     * @param array $input
     * @return array
     */
    public function submit_stage(stdClass $attempt, int $stageid, array $input): array {
        global $DB;
        if ($attempt->state !== 'inprogress') {
            throw new moodle_exception('errorattemptfinished', 'mod_decisionforge');
        }
        if ((int) $attempt->currentstageid !== $stageid) {
            throw new moodle_exception('errorstaleattempt', 'mod_decisionforge');
        }
        $responsecount = $DB->count_records('decisionforge_simresponses', ['attemptid' => $attempt->id]);
        if ($responsecount >= self::MAX_STAGE_VISITS) {
            $attempt = $this->finish_attempt($attempt);
            $attempt->outcomecode = 'critical';
            $DB->set_field('decisionforge_attempts', 'outcomecode', 'critical', ['id' => $attempt->id]);
            $this->after_attempt_finished($attempt);
            throw new moodle_exception('errorsimulationloop', 'mod_decisionforge');
        }
        if ($this->enforce_time_limit($attempt)) {
            throw new moodle_exception('expired', 'mod_decisionforge');
        }
        $snapshot = $this->decode_snapshot($attempt);
        $stage = $snapshot['stages'][(string) $stageid] ?? null;
        if (!$stage) {
            throw new moodle_exception('errorinvalidstage', 'mod_decisionforge');
        }

        $result = $this->evaluate_stage($stage, $input);
        foreach ($result['impacts'] as $metric => $impact) {
            $attempt->{$metric} = $this->clamp_metric((int) $attempt->{$metric} + $impact);
        }
        $nextstageid = $this->resolve_next_stage($snapshot, $stageid, $result['nextstageid']);
        $path = json_decode($attempt->pathjson ?? '[]', true);
        $path = is_array($path) ? $path : [];
        if ($nextstageid) {
            $path[] = $nextstageid;
        }

        $transaction = $DB->start_delegated_transaction();
        $response = (object) [
            'attemptid' => $attempt->id,
            'stageid' => $stageid,
            'responsesequence' => $responsecount + 1,
            'stagekind' => $stage['stagekind'],
            'choiceid' => $result['choiceid'],
            'response' => $result['response'],
            'rankingjson' => $result['ranking'] ? json_encode($result['ranking']) : null,
            'openedjson' => $result['opened'] ? json_encode($result['opened']) : null,
            'score' => $result['score'],
            'maxscore' => $result['maxscore'],
            'consequence' => $result['consequence'],
            'safety' => $attempt->safety,
            'compliance' => $attempt->compliance,
            'trust' => $attempt->trust,
            'resources' => $attempt->resources,
            'timeanswered' => time(),
        ];
        $response->id = $DB->insert_record('decisionforge_simresponses', $response);
        $attempt->rawscore = (float) $attempt->rawscore + $result['score'];
        $attempt->possiblepoints = (float) $attempt->possiblepoints + $result['maxscore'];
        $attempt->currentstageid = $nextstageid;
        $attempt->pathjson = json_encode($path);
        $attempt->timemodified = time();
        $finished = !$nextstageid;
        if ($finished) {
            $attempt = $this->finish_attempt($attempt);
        } else {
            $DB->update_record('decisionforge_attempts', $attempt);
        }
        $transaction->allow_commit();

        $event = \mod_decisionforge\event\simulation_response_recorded::create([
            'objectid' => $response->id,
            'context' => $this->context,
            'relateduserid' => $attempt->userid,
            'other' => ['attemptid' => $attempt->id, 'stageid' => $stageid],
        ]);
        $event->trigger();
        if ($finished) {
            $this->after_attempt_finished($attempt);
        }
        return [
            'response' => $response,
            'stage' => $stage,
            'finished' => $finished,
            'attempt' => $attempt,
            'impacts' => $result['impacts'],
        ];
    }

    /** @param stdClass $attempt @return bool */
    public function enforce_time_limit(stdClass $attempt): bool {
        if ($attempt->state !== 'inprogress') {
            return false;
        }
        $deadline = (new manager($this->activity, $this->cm))->get_attempt_deadline($attempt);
        if (!$deadline || time() < $deadline) {
            return false;
        }
        $attempt = $this->finish_attempt($attempt);
        $this->after_attempt_finished($attempt);
        return true;
    }

    /** @param stdClass $attempt @return void */
    private function after_attempt_finished(stdClass $attempt): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/decisionforge/lib.php');
        decisionforge_update_grades($this->activity, (int) $attempt->userid);
        $completion = new \completion_info(get_course($this->activity->course));
        if ($completion->is_enabled($this->cm)) {
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

    /** @param array $stage @param array $input @return array */
    private function evaluate_stage(array $stage, array $input): array {
        $kind = $stage['stagekind'];
        $result = [
            'score' => 0.0,
            'maxscore' => 0.0,
            'choiceid' => 0,
            'response' => trim((string) ($input['response'] ?? '')),
            'ranking' => [],
            'opened' => [],
            'consequence' => '',
            'nextstageid' => (int) ($stage['nextstageid'] ?? 0),
            'impacts' => ['safety' => 0, 'compliance' => 0, 'trust' => 0, 'resources' => 0],
        ];
        if ($kind === 'decision') {
            $result['choiceid'] = (int) ($input['choiceid'] ?? 0);
            $choice = $this->find_item($stage['choices'], $result['choiceid']);
            if (!$choice) {
                throw new moodle_exception('decisionrequired', 'mod_decisionforge');
            }
            $result['maxscore'] = max(0, (float) $stage['points']);
            $result['score'] = round($result['maxscore'] * max(0, min(100, (float) $choice['quality'])) / 100, 5);
            $result['consequence'] = (string) ($choice['consequence'] ?? '');
            $result['nextstageid'] = (int) ($choice['nextstageid'] ?? 0) ?: $result['nextstageid'];
            foreach ($result['impacts'] as $metric => $unused) {
                $result['impacts'][$metric] = (int) ($choice[$metric . 'impact'] ?? 0);
            }
        } elseif ($kind === 'prioritise') {
            $result['ranking'] = array_values(array_unique(array_map('intval', $input['ranking'] ?? [])));
            $choiceids = array_map(static fn($item) => (int) $item['id'], $stage['choices']);
            if (count($result['ranking']) !== count($choiceids) || array_diff($result['ranking'], $choiceids)) {
                throw new moodle_exception('rankingrequired', 'mod_decisionforge');
            }
            $distance = 0;
            foreach ($stage['choices'] as $choice) {
                $actualrank = array_search((int) $choice['id'], $result['ranking'], true) + 1;
                $distance += abs($actualrank - (int) $choice['targetrank']);
            }
            $count = count($result['ranking']);
            $maxdistance = max(1, (int) floor(($count * $count) / 2));
            $quality = max(0, 100 * (1 - ($distance / $maxdistance)));
            $result['maxscore'] = max(0, (float) $stage['points']);
            $result['score'] = round($result['maxscore'] * $quality / 100, 5);
            $result['consequence'] = get_string(
                $quality >= 80 ? 'rankingfeedbackstrong' : 'rankingfeedbackreview',
                'mod_decisionforge'
            );
        } elseif ($kind === 'evidence') {
            $result['opened'] = array_values(array_unique(array_map('intval', $input['opened'] ?? [])));
            $evidenceids = array_map(static fn($item) => (int) $item['id'], $stage['evidence']);
            if (array_diff($result['opened'], $evidenceids)) {
                throw new moodle_exception('errorevidenceinvalid', 'mod_decisionforge');
            }
            $criticalids = array_map(static fn($item) => (int) $item['id'], array_filter(
                $stage['evidence'], static fn($item) => !empty($item['critical'])
            ));
            if (!empty($stage['requireallcritical']) && array_diff($criticalids, $result['opened'])) {
                throw new moodle_exception('criticalevidencerequired', 'mod_decisionforge');
            }
            $result['maxscore'] = max(0, (float) $stage['points']);
            $requiredids = $criticalids ?: $evidenceids;
            $result['score'] = $requiredids && !array_diff($requiredids, $result['opened'])
                ? $result['maxscore'] : 0;
            $result['consequence'] = get_string('evidencecaptured', 'mod_decisionforge');
        } elseif ($kind === 'reflection') {
            if (\core_text::strlen($result['response']) < 10) {
                throw new moodle_exception('reflectionrequired', 'mod_decisionforge');
            }
            if (\core_text::strlen($result['response']) > 5000) {
                throw new moodle_exception('reflectiontoolong', 'mod_decisionforge');
            }
            $result['consequence'] = get_string('reflectionrecorded', 'mod_decisionforge');
        } elseif ($kind !== 'briefing') {
            throw new moodle_exception('errorinvalidstagekind', 'mod_decisionforge');
        }
        return $result;
    }

    /** @param stdClass $attempt @return stdClass */
    public function finish_attempt(stdClass $attempt): stdClass {
        global $DB;
        if ($attempt->state === 'finished') {
            return $attempt;
        }
        $attempt->finalgrade = (float) $attempt->possiblepoints > 0
            ? round(((float) $attempt->rawscore / (float) $attempt->possiblepoints) * (float) $this->activity->grade, 5)
            : 0;
        $attempt->finalgrade = max(0, min((float) $this->activity->grade, $attempt->finalgrade));
        $metricaverage = ((int) $attempt->safety + (int) $attempt->compliance
            + (int) $attempt->trust + (int) $attempt->resources) / 4;
        $gradepercent = (float) $this->activity->grade > 0
            ? ((float) $attempt->finalgrade / (float) $this->activity->grade) * 100 : 0;
        $outcomescore = ($metricaverage * 0.45) + ($gradepercent * 0.55);
        $attempt->outcomecode = $outcomescore >= 85 ? 'command' : ($outcomescore >= 70
            ? 'controlled' : ($outcomescore >= 50 ? 'fragile' : 'critical'));
        $attempt->state = 'finished';
        $attempt->currentstageid = 0;
        $attempt->timefinish = time();
        $attempt->timemodified = time();
        $DB->update_record('decisionforge_attempts', $attempt);
        return $attempt;
    }

    /** @param int $responseid @param int $attemptid @return stdClass */
    public function get_response(int $responseid, int $attemptid): stdClass {
        global $DB;
        return $DB->get_record('decisionforge_simresponses', [
            'id' => $responseid,
            'attemptid' => $attemptid,
        ], '*', MUST_EXIST);
    }

    /**
     * Return the latest saved response for a stage in an attempt.
     *
     * Callers must validate attempt ownership before using this method. It is
     * used to make repeated browser submissions idempotent: if the first POST
     * already advanced the route, a duplicate POST can reopen the saved
     * consequence instead of raising a stale-attempt exception.
     *
     * @param int $attemptid
     * @param int $stageid
     * @return stdClass|null
     */
    public function get_latest_response_for_stage(int $attemptid, int $stageid): ?stdClass {
        global $DB;

        $responses = $DB->get_records(
            'decisionforge_simresponses',
            ['attemptid' => $attemptid, 'stageid' => $stageid],
            'responsesequence DESC, id DESC',
            '*',
            0,
            1
        );
        return $responses ? reset($responses) : null;
    }

    /** @param stdClass $attempt @return array */
    public function get_review(stdClass $attempt): array {
        global $DB;
        $snapshot = $this->decode_snapshot($attempt);
        $responses = $DB->get_records('decisionforge_simresponses', ['attemptid' => $attempt->id],
            'responsesequence ASC');
        $review = [];
        foreach ($responses as $response) {
            $stage = $snapshot['stages'][(string) $response->stageid] ?? null;
            if (!$stage) {
                continue;
            }
            $review[] = [
                'response' => $response,
                'stage' => $stage,
                'choice' => $response->choiceid ? $this->find_item($stage['choices'], (int) $response->choiceid) : null,
                'opened' => json_decode($response->openedjson ?? '[]', true) ?: [],
                'ranking' => json_decode($response->rankingjson ?? '[]', true) ?: [],
            ];
        }
        return $review;
    }

    /** @param stdClass $data @return int */
    public function save_stage(stdClass $data): int {
        global $DB;
        if (empty($data->stageid) && count($this->get_stages()) >= self::MAX_STAGE_VISITS) {
            throw new moodle_exception('maxsimulationstages', 'mod_decisionforge', '', self::MAX_STAGE_VISITS);
        }
        $allowedkinds = ['briefing', 'evidence', 'decision', 'prioritise', 'reflection'];
        if (!in_array($data->stagekind, $allowedkinds, true)) {
            throw new moodle_exception('errorinvalidstagekind', 'mod_decisionforge');
        }
        if (trim((string) $data->title) === '' || trim((string) ($data->body_editor['text'] ?? '')) === '') {
            throw new moodle_exception('errorstagecontent', 'mod_decisionforge');
        }
        $editingstageid = (int) ($data->stageid ?? 0);
        $defaultnextstageid = $this->validate_route_target((int) ($data->nextstageid ?? 0), $editingstageid);
        $allowedevidencekinds = ['document', 'message', 'record', 'alert'];
        foreach ($data->choices ?? [] as $choice) {
            if (trim((string) ($choice['text'] ?? '')) !== '') {
                $this->validate_route_target((int) ($choice['nextstageid'] ?? 0), $editingstageid);
            }
        }
        foreach ($data->evidence ?? [] as $evidence) {
            if (!in_array($evidence['kind'] ?? 'document', $allowedevidencekinds, true)) {
                throw new moodle_exception('errorevidencekind', 'mod_decisionforge');
            }
        }
        $now = time();
        $stage = (object) [
            'decisionforgeid' => $this->activity->id,
            'title' => trim($data->title),
            'stagekind' => $data->stagekind,
            'body' => $data->body_editor['text'],
            'bodyformat' => $data->body_editor['format'],
            'prompt' => $data->prompt_editor['text'] ?? '',
            'promptformat' => $data->prompt_editor['format'] ?? FORMAT_HTML,
            'points' => in_array($data->stagekind, ['decision', 'prioritise', 'evidence'], true)
                ? max(0, (float) $data->points) : 0,
            'position' => 0,
            'nextstageid' => $defaultnextstageid,
            'active' => empty($data->active) ? 0 : 1,
            'requireallcritical' => empty($data->requireallcritical) ? 0 : 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $transaction = $DB->start_delegated_transaction();
        if (!empty($data->stageid)) {
            $existing = $this->get_stage((int) $data->stageid);
            $stage->id = $existing->id;
            $stage->position = $existing->position;
            $stage->timecreated = $existing->timecreated;
            $DB->update_record('decisionforge_simstages', $stage);
            $DB->delete_records('decisionforge_simchoices', ['stageid' => $stage->id]);
            $DB->delete_records('decisionforge_simevidence', ['stageid' => $stage->id]);
        } else {
            $stage->position = count($this->get_stages());
            $stage->id = $DB->insert_record('decisionforge_simstages', $stage);
        }
        if (in_array($stage->stagekind, ['decision', 'prioritise'], true)) {
            foreach ($data->choices ?? [] as $position => $choice) {
                if (trim($choice['text'] ?? '') === '') {
                    continue;
                }
                $DB->insert_record('decisionforge_simchoices', (object) [
                    'stageid' => $stage->id,
                    'choicetext' => trim($choice['text']),
                    'choicetextformat' => FORMAT_PLAIN,
                    'quality' => max(0, min(100, (float) ($choice['quality'] ?? 0))),
                    'consequence' => trim($choice['consequence'] ?? ''),
                    'consequenceformat' => FORMAT_PLAIN,
                    'nextstageid' => $this->validate_route_target(
                        (int) ($choice['nextstageid'] ?? 0),
                        (int) $stage->id
                    ),
                    'safetyimpact' => $this->clamp_impact($choice['safetyimpact'] ?? 0),
                    'complianceimpact' => $this->clamp_impact($choice['complianceimpact'] ?? 0),
                    'trustimpact' => $this->clamp_impact($choice['trustimpact'] ?? 0),
                    'resourcesimpact' => $this->clamp_impact($choice['resourcesimpact'] ?? 0),
                    'targetrank' => (int) ($choice['targetrank'] ?? ($position + 1)),
                    'position' => $position,
                ]);
            }
        }
        if ($stage->stagekind === 'evidence') {
            foreach ($data->evidence ?? [] as $position => $evidence) {
                if (trim($evidence['title'] ?? '') === '' || trim($evidence['body'] ?? '') === '') {
                    continue;
                }
                $DB->insert_record('decisionforge_simevidence', (object) [
                    'stageid' => $stage->id,
                    'title' => trim($evidence['title']),
                    'body' => trim($evidence['body']),
                    'bodyformat' => FORMAT_PLAIN,
                    'evidencekind' => $evidence['kind'] ?? 'document',
                    'critical' => empty($evidence['critical']) ? 0 : 1,
                    'position' => $position,
                ]);
            }
        }
        $transaction->allow_commit();
        return (int) $stage->id;
    }

    /** @param int $stageid @return void */
    public function delete_stage(int $stageid): void {
        global $DB;
        $this->get_stage($stageid);
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('decisionforge_simchoices', ['stageid' => $stageid]);
        $DB->delete_records('decisionforge_simevidence', ['stageid' => $stageid]);
        $DB->delete_records('decisionforge_simstages', ['id' => $stageid]);
        $DB->set_field('decisionforge_simstages', 'nextstageid', 0, [
            'decisionforgeid' => $this->activity->id,
            'nextstageid' => $stageid,
        ]);
        $DB->set_field('decisionforge_simchoices', 'nextstageid', 0, ['nextstageid' => $stageid]);
        $transaction->allow_commit();
        $this->normalise_positions();
    }

    /** @param int $stageid @param int $direction @return void */
    public function move_stage(int $stageid, int $direction): void {
        global $DB;
        $stages = array_values($this->get_stages());
        $index = null;
        foreach ($stages as $key => $stage) {
            if ((int) $stage->id === $stageid) {
                $index = $key;
                break;
            }
        }
        if ($index === null || !isset($stages[$index + $direction])) {
            return;
        }
        $target = $index + $direction;
        $oldposition = $stages[$index]->position;
        $stages[$index]->position = $stages[$target]->position;
        $stages[$target]->position = $oldposition;
        $DB->update_record('decisionforge_simstages', $stages[$index]);
        $DB->update_record('decisionforge_simstages', $stages[$target]);
        $this->normalise_positions();
    }

    /**
     * Install an editable hospital incident simulation demonstrating branching.
     *
     * @return void
     */
    public function seed_hospital_simulation(): void {
        global $DB;
        if ($this->get_stages()) {
            throw new moodle_exception('simulationseednotempty', 'mod_decisionforge');
        }
        $now = time();
        $definitions = [
            ['The 08:42 alert', 'briefing',
                '<p>You are the hospital duty manager. A patient may have received ten times the prescribed dose. The ward is busy, the family is arriving and an external inspection team is already on site.</p>',
                '<p>Your decisions will change patient safety, compliance, trust and available resources.</p>', 0],
            ['Open the incident file', 'evidence',
                '<p>Four records arrive from different systems. Decide what must be examined before you act.</p>',
                '<p>Open every record you believe is essential to a defensible first response.</p>', 8],
            ['Control the first ten minutes', 'prioritise',
                '<p>The ward needs an ordered response, not four actions happening without coordination.</p>',
                '<p>Drag the actions into the safest operational order.</p>', 12],
            ['The disclosure decision', 'decision',
                '<p>The patient is stable. A senior manager asks you to delay formal disclosure until the inspection team leaves.</p>',
                '<p>Choose your response. This decision changes the route of the incident.</p>', 20],
            ['Controlled response', 'reflection',
                '<p>Your transparent response keeps the incident controlled. The clinical director requests a concise handover.</p>',
                '<p>Write the three most important points you would include in the handover.</p>', 0],
            ['Escalation under pressure', 'decision',
                '<p>The delay is discovered, the family loses confidence and the patient begins to deteriorate.</p>',
                '<p>What should you do now to regain control?</p>', 20],
            ['Command review', 'reflection',
                '<p>The immediate incident is closed. Your final record will be reviewed by clinical governance.</p>',
                '<p>Explain what you would change in the system to prevent recurrence.</p>', 0],
        ];
        $ids = [];
        $transaction = $DB->start_delegated_transaction();
        foreach ($definitions as $position => $definition) {
            $ids[] = $DB->insert_record('decisionforge_simstages', (object) [
                'decisionforgeid' => $this->activity->id,
                'title' => $definition[0],
                'stagekind' => $definition[1],
                'body' => $definition[2],
                'bodyformat' => FORMAT_HTML,
                'prompt' => $definition[3],
                'promptformat' => FORMAT_HTML,
                'points' => $definition[4],
                'position' => $position,
                'nextstageid' => 0,
                'active' => 1,
                'requireallcritical' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        $this->insert_evidence($ids[1], [
            ['Electronic medication record', 'Prescription: 2.5 mg. Administration log: 25 mg at 08:31. User account: agency nurse A17.', 'record', 1],
            ['Bedside nurse message', 'Patient is drowsy, blood pressure falling. Rapid response team has not yet been called.', 'message', 1],
            ['Observation dashboard', 'The last documented observations are 22 minutes old and trend downward.', 'alert', 1],
            ['Incident disclosure policy', 'Open disclosure must begin promptly after immediate clinical safety is secured.', 'document', 0],
        ]);
        $this->insert_choices($ids[2], [
            ['Assess and stabilise the patient', 100, 1],
            ['Stop further doses and isolate the medication process', 100, 2],
            ['Escalate to the clinical lead and pharmacy', 100, 3],
            ['Preserve records and start the incident log', 100, 4],
        ], 'prioritise');
        $this->insert_choices($ids[3], [
            ['Begin open disclosure now and document the request to delay it.', 100,
                'The family receives a factual explanation and governance remains defensible.', $ids[4], 12, 16, 14, -8],
            ['Secure clinical safety, then disclose before the inspection ends.', 75,
                'The response is controlled, but the short delay creates avoidable trust risk.', $ids[4], 8, 7, 2, -4],
            ['Wait until the inspection team leaves, then report internally.', 20,
                'The delay is discovered through the electronic audit trail.', $ids[5], -18, -30, -28, -10],
            ['Remove the incorrect record and handle the matter informally.', 0,
                'The audit trail exposes attempted concealment and the incident escalates.', $ids[5], -30, -45, -40, -18],
        ]);
        $DB->set_field('decisionforge_simstages', 'nextstageid', $ids[6], ['id' => $ids[4]]);
        $DB->set_field('decisionforge_simstages', 'nextstageid', $ids[6], ['id' => $ids[5]]);
        $this->insert_choices($ids[5], [
            ['Activate the emergency response, disclose the delay and preserve all records.', 100,
                'Clinical control is restored and the organisation begins rebuilding trust.', $ids[6], 20, 14, 12, -15],
            ['Focus only on the patient and avoid discussing the earlier delay.', 55,
                'The patient receives care, but governance and trust remain exposed.', $ids[6], 16, -8, -12, -10],
            ['Ask the original nurse to resolve the matter alone.', 15,
                'Responsibility is misplaced and the response fragments further.', $ids[6], -15, -20, -18, 4],
            ['Wait for legal advice before taking any further action.', 5,
                'The delay worsens the clinical and regulatory position.', $ids[6], -22, -28, -25, -6],
        ]);
        $transaction->allow_commit();
    }

    /** @param int $stageid @param array $items @return void */
    private function insert_evidence(int $stageid, array $items): void {
        global $DB;
        foreach ($items as $position => $item) {
            $DB->insert_record('decisionforge_simevidence', (object) [
                'stageid' => $stageid,
                'title' => $item[0],
                'body' => $item[1],
                'bodyformat' => FORMAT_PLAIN,
                'evidencekind' => $item[2],
                'critical' => $item[3],
                'position' => $position,
            ]);
        }
    }

    /** @param int $stageid @param array $items @param string $kind @return void */
    private function insert_choices(int $stageid, array $items, string $kind = 'decision'): void {
        global $DB;
        foreach ($items as $position => $item) {
            $DB->insert_record('decisionforge_simchoices', (object) [
                'stageid' => $stageid,
                'choicetext' => $item[0],
                'choicetextformat' => FORMAT_PLAIN,
                'quality' => $kind === 'prioritise' ? 0 : $item[1],
                'consequence' => $kind === 'prioritise' ? '' : $item[2],
                'consequenceformat' => FORMAT_PLAIN,
                'nextstageid' => $kind === 'prioritise' ? 0 : $item[3],
                'safetyimpact' => $kind === 'prioritise' ? 0 : $item[4],
                'complianceimpact' => $kind === 'prioritise' ? 0 : $item[5],
                'trustimpact' => $kind === 'prioritise' ? 0 : $item[6],
                'resourcesimpact' => $kind === 'prioritise' ? 0 : $item[7],
                'targetrank' => $kind === 'prioritise' ? $item[2] : 0,
                'position' => $position,
            ]);
        }
    }

    /** @return void */
    private function normalise_positions(): void {
        global $DB;
        foreach (array_values($this->get_stages()) as $position => $stage) {
            if ((int) $stage->position !== $position) {
                $stage->position = $position;
                $DB->update_record('decisionforge_simstages', $stage);
            }
        }
    }

    /** @param array $items @param int $id @return array|null */
    private function find_item(array $items, int $id): ?array {
        foreach ($items as $item) {
            if ((int) $item['id'] === $id) {
                return $item;
            }
        }
        return null;
    }

    /** @param array $snapshot @param int $currentid @param int $configured @return int */
    private function resolve_next_stage(array $snapshot, int $currentid, int $configured): int {
        if ($configured === -1) {
            return 0;
        }
        if ($configured > 0 && isset($snapshot['stages'][(string) $configured])) {
            return $configured;
        }
        $order = array_map('intval', $snapshot['order']);
        $position = array_search($currentid, $order, true);
        return $position !== false && isset($order[$position + 1]) ? $order[$position + 1] : 0;
    }

    /**
     * Validate an author-configured route without allowing cross-activity targets.
     *
     * @param int $targetid
     * @param int $currentstageid
     * @return int
     */
    private function validate_route_target(int $targetid, int $currentstageid = 0): int {
        if ($targetid === 0 || $targetid === -1) {
            return $targetid;
        }
        if ($targetid < -1 || ($currentstageid && $targetid === $currentstageid)) {
            throw new moodle_exception('errorinvalidroute', 'mod_decisionforge');
        }
        $this->get_stage($targetid);
        return $targetid;
    }

    /** @param mixed $value @return int */
    private function clamp_metric($value): int {
        return max(0, min(100, (int) $value));
    }

    /** @param mixed $value @return int */
    private function clamp_impact($value): int {
        return max(-100, min(100, (int) $value));
    }
}
