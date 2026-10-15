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
 * Attempt result and review.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\manager;

$id = required_param('id', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);

$manager = manager::from_cmid($id);
$activity = $manager->get_activity();
$cm = $manager->get_cm();
$context = $manager->get_context();
$course = get_course($activity->course);

require_login($course, true, $cm);
require_capability('mod/decisionforge:view', $context);
$reportaccess = has_capability('mod/decisionforge:viewreports', $context);
$attempt = $manager->get_attempt($attemptid, $USER->id, $reportaccess);
if (($attempt->mode ?? 'quick') === 'simulation') {
    redirect(new moodle_url('/mod/decisionforge/simresult.php', ['id' => $id, 'attemptid' => $attemptid]));
}
if ($attempt->state !== 'finished') {
    if ((int) $attempt->userid === (int) $USER->id) {
        redirect(new moodle_url('/mod/decisionforge/attempt.php', ['id' => $id, 'attemptid' => $attemptid]));
    }
    throw new moodle_exception('errorattemptfinished', 'mod_decisionforge');
}

$review = $manager->get_attempt_review($attemptid);
$percent = (float) $activity->grade > 0 ? round(((float) $attempt->finalgrade / (float) $activity->grade) * 100) : 0;
$percent = max(0, min(100, $percent));
$bestcount = 0;
$reviewcases = [];
foreach ($review as $row) {
    if ($row->responseid === null) {
        continue;
    }
    $quality = (float) $row->optionquality;
    if ($quality >= 100) {
        $state = 'best';
        $label = get_string('bestdecision', 'mod_decisionforge');
        $bestcount++;
    } elseif ($quality >= 75) {
        $state = 'good';
        $label = get_string('gooddecision', 'mod_decisionforge');
    } elseif ($quality > 0) {
        $state = 'risky';
        $label = get_string('riskydecision', 'mod_decisionforge');
    } else {
        $state = 'unsafe';
        $label = get_string('unsafedecision', 'mod_decisionforge');
    }
    $reviewcases[] = [
        'number' => (int) $row->caseposition + 1,
        'title' => format_string($row->title),
        'question' => format_string($row->questiontext),
        'decision' => format_text($row->optiontext, $row->optiontextformat, ['context' => $context]),
        'justification' => $row->justification,
        'hasjustification' => trim($row->justification ?? '') !== '',
        'confidence' => $row->confidence,
        'feedback' => format_text($row->consequence, $row->consequenceformat, ['context' => $context]),
        'hasfeedback' => trim(strip_tags($row->consequence ?? '')) !== '',
        'earned' => format_float($row->score, 1),
        'possible' => format_float($row->points, 1),
        'state' => $state,
        'statelabel' => $label,
    ];
}

if ($percent >= 85) {
    $message = get_string('resultmessageexcellent', 'mod_decisionforge');
    $band = 'excellent';
} elseif ($percent >= 60) {
    $message = get_string('resultmessagegood', 'mod_decisionforge');
    $band = 'good';
} else {
    $message = get_string('resultmessagereview', 'mod_decisionforge');
    $band = 'review';
}

$duration = max(0, (int) $attempt->timefinish - (int) $attempt->timestart);
$availability = $manager->get_availability();
$canreattempt = (int) $attempt->userid === (int) $USER->id
    && $availability['available']
    && $manager->can_start_attempt($USER->id);
$participant = core_user::get_user($attempt->userid, '*', MUST_EXIST);
$haspassinggrade = (float) $activity->gradepass > 0;
$passed = $haspassinggrade && (float) $attempt->finalgrade >= (float) $activity->gradepass;

$PAGE->set_url('/mod/decisionforge/result.php', ['id' => $id, 'attemptid' => $attemptid]);
$PAGE->set_title(get_string('attemptcomplete', 'mod_decisionforge'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('mod-decisionforge-result');

$data = [
    'name' => format_string($activity->name),
    'attemptnumber' => $attempt->attemptnumber,
    'participant' => fullname($participant),
    'showparticipant' => $reportaccess,
    'percent' => $percent,
    'grade' => format_float($attempt->finalgrade, 1),
    'maxgrade' => format_float($activity->grade, 0),
    'haspassinggrade' => $haspassinggrade,
    'passinggrade' => format_float($activity->gradepass, 1),
    'passed' => $passed,
    'notpassed' => $haspassinggrade && !$passed,
    'casecount' => count($reviewcases),
    'bestcount' => $bestcount,
    'duration' => format_time($duration),
    'message' => $message,
    'band' => $band,
    'reviewcases' => $reviewcases,
    'hasreviewcases' => !empty($reviewcases),
    'canreattempt' => $canreattempt,
    'starturl' => (new moodle_url('/mod/decisionforge/startattempt.php', ['id' => $id]))->out(false),
    'sesskey' => sesskey(),
    'backurl' => (new moodle_url('/mod/decisionforge/view.php', ['id' => $id]))->out(false),
    'reporturl' => (new moodle_url('/mod/decisionforge/report.php', ['id' => $id]))->out(false),
    'reportaccess' => $reportaccess,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/result', $data);
echo $OUTPUT->footer();
