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
 * Immersive simulation dossier.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\manager;
use mod_decisionforge\local\simulation_manager;

$id = required_param('id', PARAM_INT);
$simulation = simulation_manager::from_cmid($id);
$activity = $simulation->get_activity();
$cm = $simulation->get_cm();
$context = $simulation->get_context();
$course = get_course($activity->course);
require_login($course, true, $cm);
require_capability('mod/decisionforge:view', $context);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$coremanager = new manager($activity, $cm);
$availability = $coremanager->get_availability();
$inprogress = $coremanager->get_inprogress_attempt($USER->id, 'simulation');
$latest = $coremanager->get_latest_attempt($USER->id, 'finished', 'simulation');
$attemptcount = $coremanager->count_attempts($USER->id);
$latestoutcomecode = $latest && in_array($latest->outcomecode, ['command', 'controlled', 'fragile', 'critical'], true)
    ? $latest->outcomecode : 'critical';
$warnings = $simulation->get_configuration_warnings();
$canattempt = has_capability('mod/decisionforge:attempt', $context);
$canstart = $canattempt && $availability['available'] && !$warnings && $coremanager->can_start_attempt($USER->id);
$stages = array_values($simulation->get_stages(true));
$stagekinds = [];
foreach ($stages as $stage) {
    $stagekinds[$stage->stagekind] = true;
}
$data = [
    'name' => format_string($activity->name),
    'missionlabel' => format_string($activity->missionlabel),
    'intro' => format_module_intro('decisionforge', $activity, $cm->id),
    'hasintro' => trim(strip_tags($activity->intro ?? '')) !== '',
    'instructions' => format_text($activity->instructions, $activity->instructionsformat, ['context' => $context]),
    'hasinstructions' => trim(strip_tags($activity->instructions ?? '')) !== '',
    'stagecount' => count($stages),
    'attemptslabel' => empty($activity->attemptsallowed)
        ? get_string('unlimited', 'mod_decisionforge') : $activity->attemptsallowed,
    'timelimit' => empty($activity->timelimit) ? get_string('none') : format_time($activity->timelimit),
    'maxgrade' => format_float($activity->grade, 0),
    'hasevidence' => isset($stagekinds['evidence']),
    'hasbranching' => isset($stagekinds['decision']),
    'hasprioritise' => isset($stagekinds['prioritise']),
    'hasreflection' => isset($stagekinds['reflection']),
    'initialsafety' => $activity->initialsafety,
    'initialcompliance' => $activity->initialcompliance,
    'initialtrust' => $activity->initialtrust,
    'initialresources' => $activity->initialresources,
    'hasinprogress' => !empty($inprogress),
    'continueurl' => $inprogress ? (new moodle_url('/mod/decisionforge/simattempt.php', [
        'id' => $id,
        'attemptid' => $inprogress->id,
    ]))->out(false) : '',
    'canstart' => $canstart && !$inprogress,
    'starturl' => (new moodle_url('/mod/decisionforge/startattempt.php', ['id' => $id]))->out(false),
    'sesskey' => sesskey(),
    'haslatest' => !empty($latest),
    'latestgrade' => $latest ? format_float($latest->finalgrade, 1) : '',
    'latestoutcome' => $latest ? get_string('outcome' . $latestoutcomecode, 'mod_decisionforge') : '',
    'latesturl' => $latest ? (new moodle_url('/mod/decisionforge/simresult.php', [
        'id' => $id,
        'attemptid' => $latest->id,
    ]))->out(false) : '',
    'attemptcount' => $attemptcount,
    'unavailable' => !$availability['available'],
    'availabilityreason' => $availability['reason'],
    'haswarnings' => !empty($warnings),
    'warningtext' => implode(' ', $warnings),
    'isteacher' => has_capability('mod/decisionforge:managecases', $context),
    'studiourl' => (new moodle_url('/mod/decisionforge/simulationstudio.php', ['id' => $id]))->out(false),
    'reporturl' => (new moodle_url('/mod/decisionforge/report.php', ['id' => $id]))->out(false),
];

$PAGE->set_url('/mod/decisionforge/simulationview.php', ['id' => $id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('mod-decisionforge-dossier');
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/simulation_landing', $data);
echo $OUTPUT->footer();
