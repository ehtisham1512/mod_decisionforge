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
 * After-action report for an immersive simulation.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\manager;
use mod_decisionforge\local\simulation_manager;

$id = required_param('id', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);
$simulation = simulation_manager::from_cmid($id);
$activity = $simulation->get_activity();
$cm = $simulation->get_cm();
$context = $simulation->get_context();
$course = get_course($activity->course);
require_login($course, true, $cm);
require_capability('mod/decisionforge:view', $context);
$reportaccess = has_capability('mod/decisionforge:viewreports', $context);
$attempt = $simulation->get_attempt($attemptid, $USER->id, $reportaccess);
if ($attempt->state !== 'finished') {
    if ((int) $attempt->userid === (int) $USER->id) {
        redirect(new moodle_url('/mod/decisionforge/simattempt.php', ['id' => $id, 'attemptid' => $attemptid]));
    }
    throw new moodle_exception('errorattemptfinished', 'mod_decisionforge');
}
$review = $simulation->get_review($attempt);
$route = [];
foreach ($review as $index => $item) {
    $response = $item['response'];
    $stage = $item['stage'];
    $rankingtext = [];
    foreach ($item['ranking'] as $choiceid) {
        foreach ($stage['choices'] as $choice) {
            if ((int) $choice['id'] === (int) $choiceid) {
                $rankingtext[] = format_string(strip_tags($choice['choicetext']));
                break;
            }
        }
    }
    $openedtext = [];
    foreach ($item['opened'] as $evidenceid) {
        foreach ($stage['evidence'] as $evidence) {
            if ((int) $evidence['id'] === (int) $evidenceid) {
                $openedtext[] = format_string($evidence['title']);
                break;
            }
        }
    }
    $route[] = [
        'number' => $index + 1,
        'title' => format_string($stage['title']),
        'kind' => $stage['stagekind'],
        'kindlabel' => get_string('stagekind' . $stage['stagekind'], 'mod_decisionforge'),
        'choice' => $item['choice']
            ? format_text($item['choice']['choicetext'], $item['choice']['choicetextformat'], ['context' => $context]) : '',
        'haschoice' => !empty($item['choice']),
        'response' => format_text($response->response, FORMAT_PLAIN, ['context' => $context]),
        'hasresponse' => trim($response->response ?? '') !== '',
        'ranking' => implode(' → ', $rankingtext),
        'hasranking' => !empty($rankingtext),
        'openedcount' => count($item['opened']),
        'hasopened' => !empty($item['opened']),
        'openedevidence' => implode(' · ', $openedtext),
        'score' => format_float($response->score, 1),
        'maxscore' => format_float($response->maxscore, 1),
        'hasscore' => (float) $response->maxscore > 0,
        'consequence' => format_text(
            $response->consequence,
            $item['choice']['consequenceformat'] ?? FORMAT_PLAIN,
            ['context' => $context]
        ),
        'hasconsequence' => trim($response->consequence ?? '') !== '',
    ];
}
$percent = (float) $activity->grade > 0
    ? max(0, min(100, round(((float) $attempt->finalgrade / (float) $activity->grade) * 100))) : 0;
$haspassinggrade = (float) $activity->gradepass > 0;
$passed = $haspassinggrade && (float) $attempt->finalgrade >= (float) $activity->gradepass;
$coremanager = new manager($activity, $cm);
$availability = $coremanager->get_availability();
$canreattempt = (int) $attempt->userid === (int) $USER->id
    && $availability['available'] && $coremanager->can_start_attempt($USER->id);
$participant = core_user::get_user($attempt->userid, '*', MUST_EXIST);
$duration = max(0, (int) $attempt->timefinish - (int) $attempt->timestart);
$outcomecode = in_array($attempt->outcomecode, ['command', 'controlled', 'fragile', 'critical'], true)
    ? $attempt->outcomecode : 'critical';
$data = [
    'name' => format_string($activity->name),
    'missionlabel' => format_string($activity->missionlabel),
    'participant' => fullname($participant),
    'showparticipant' => $reportaccess,
    'attemptnumber' => $attempt->attemptnumber,
    'outcomecode' => $outcomecode,
    'outcome' => get_string('outcome' . $outcomecode, 'mod_decisionforge'),
    'outcomedescription' => get_string('outcome' . $outcomecode . 'desc', 'mod_decisionforge'),
    'percent' => $percent,
    'grade' => format_float($attempt->finalgrade, 1),
    'maxgrade' => format_float($activity->grade, 0),
    'passed' => $passed,
    'notpassed' => $haspassinggrade && !$passed,
    'completedwithoutpass' => !$haspassinggrade,
    'haspassinggrade' => $haspassinggrade,
    'passinggrade' => format_float($activity->gradepass, 1),
    'safety' => $attempt->safety,
    'compliance' => $attempt->compliance,
    'trust' => $attempt->trust,
    'resources' => $attempt->resources,
    'route' => $route,
    'routecount' => count($route),
    'duration' => format_time($duration),
    'canreattempt' => $canreattempt,
    'starturl' => (new moodle_url('/mod/decisionforge/startattempt.php', ['id' => $id]))->out(false),
    'sesskey' => sesskey(),
    'backurl' => (new moodle_url('/mod/decisionforge/simulationview.php', ['id' => $id]))->out(false),
    'reportaccess' => $reportaccess,
    'reporturl' => (new moodle_url('/mod/decisionforge/report.php', ['id' => $id]))->out(false),
];

$PAGE->set_url('/mod/decisionforge/simresult.php', ['id' => $id, 'attemptid' => $attemptid]);
$PAGE->set_title(get_string('afteractionreport', 'mod_decisionforge'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('mod-decisionforge-after-action');
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/simulation_result', $data);
echo $OUTPUT->footer();
