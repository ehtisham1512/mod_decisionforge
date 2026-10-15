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
 * Activity landing page.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\manager;

$id = required_param('id', PARAM_INT);
$manager = manager::from_cmid($id);
$activity = $manager->get_activity();
$cm = $manager->get_cm();
$context = $manager->get_context();
$course = get_course($activity->course);

require_login($course, true, $cm);
require_capability('mod/decisionforge:view', $context);

$PAGE->set_url('/mod/decisionforge/view.php', ['id' => $id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('mod-decisionforge');

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$existingattempt = $manager->get_inprogress_attempt($USER->id);
if ($existingattempt && ($existingattempt->mode ?? 'quick') !== ($activity->experiencemode ?? 'quick')) {
    $target = ($existingattempt->mode ?? 'quick') === 'simulation' ? 'simattempt.php' : 'attempt.php';
    redirect(new moodle_url('/mod/decisionforge/' . $target, [
        'id' => $id,
        'attemptid' => $existingattempt->id,
    ]));
}
if (($activity->experiencemode ?? 'quick') === 'simulation') {
    redirect(new moodle_url('/mod/decisionforge/simulationview.php', ['id' => $id]));
}

$cases = $manager->get_cases(true);
$availability = $manager->get_availability();
$inprogress = $manager->get_inprogress_attempt($USER->id);
$latest = $manager->get_latest_attempt($USER->id, 'finished', 'quick');
$attemptcount = $manager->count_attempts($USER->id);
$canattempt = has_capability('mod/decisionforge:attempt', $context);
$canstart = $canattempt && $availability['available'] && !empty($cases) && $manager->can_start_attempt($USER->id);
$remaining = empty($activity->attemptsallowed) ? null : max(0, (int) $activity->attemptsallowed - $attemptcount);

$data = [
    'name' => format_string($activity->name),
    'intro' => format_module_intro('decisionforge', $activity, $cm->id),
    'hasintro' => trim(strip_tags($activity->intro ?? '')) !== '',
    'instructions' => format_text($activity->instructions, $activity->instructionsformat, ['context' => $context]),
    'hasinstructions' => trim(strip_tags($activity->instructions ?? '')) !== '',
    'casecount' => count($cases),
    'attemptcount' => $attemptcount,
    'attemptslabel' => empty($activity->attemptsallowed)
        ? get_string('unlimited', 'mod_decisionforge')
        : $activity->attemptsallowed,
    'timelimit' => empty($activity->timelimit) ? get_string('none') : format_time($activity->timelimit),
    'maxgrade' => format_float($activity->grade, 0),
    'availabilityreason' => $availability['reason'],
    'unavailable' => !$availability['available'],
    'canattempt' => $canattempt,
    'canstart' => $canstart && !$inprogress,
    'hasinprogress' => !empty($inprogress),
    'continueurl' => $inprogress
        ? (new moodle_url('/mod/decisionforge/attempt.php', ['id' => $id, 'attemptid' => $inprogress->id]))->out(false)
        : '',
    'starturl' => (new moodle_url('/mod/decisionforge/startattempt.php', ['id' => $id]))->out(false),
    'sesskey' => sesskey(),
    'haslatest' => !empty($latest),
    'latestscore' => $latest ? format_float($latest->finalgrade, 1) : '',
    'latestmax' => format_float($activity->grade, 0),
    'latesturl' => $latest
        ? (new moodle_url('/mod/decisionforge/result.php', ['id' => $id, 'attemptid' => $latest->id]))->out(false)
        : '',
    'attemptlimitreached' => $canattempt && !$inprogress && !$manager->can_start_attempt($USER->id),
    'remaining' => $remaining,
    'hasremaining' => $remaining !== null,
    'nocases' => empty($cases),
    'isteacher' => has_capability('mod/decisionforge:managecases', $context),
    'manageurl' => (new moodle_url('/mod/decisionforge/manage.php', ['id' => $id]))->out(false),
    'reporturl' => (new moodle_url('/mod/decisionforge/report.php', ['id' => $id]))->out(false),
    'gradebookurl' => (new moodle_url('/grade/report/grader/index.php', ['id' => $course->id]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/landing', $data);
echo $OUTPUT->footer();
