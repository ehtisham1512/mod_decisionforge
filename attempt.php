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
 * Learner attempt screen.
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
require_capability('mod/decisionforge:attempt', $context);

$attempt = $manager->get_attempt($attemptid, $USER->id);
if ($attempt->state === 'finished') {
    redirect(new moodle_url('/mod/decisionforge/result.php', ['id' => $id, 'attemptid' => $attemptid]));
}
if ($manager->enforce_time_limit($attempt)) {
    redirect(
        new moodle_url('/mod/decisionforge/result.php', ['id' => $id, 'attemptid' => $attemptid]),
        get_string('expired', 'mod_decisionforge'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

if (data_submitted()) {
    require_sesskey();
    $optionid = required_param('optionid', PARAM_INT);
    $justification = optional_param('justification', '', PARAM_TEXT);
    $confidence = optional_param('confidence', 50, PARAM_INT);
    $result = $manager->record_response($attempt, $optionid, $justification, $confidence);

    if ($activity->feedbackmode === 'immediate') {
        redirect(new moodle_url('/mod/decisionforge/feedback.php', [
            'id' => $id,
            'attemptid' => $attemptid,
            'responseid' => $result['response']->id,
        ]));
    }
    if ($result['finished']) {
        redirect(new moodle_url('/mod/decisionforge/result.php', ['id' => $id, 'attemptid' => $attemptid]));
    }
    redirect(new moodle_url('/mod/decisionforge/attempt.php', ['id' => $id, 'attemptid' => $attemptid]));
}

$current = $manager->get_current_case($attempt);
if (!$current) {
    $manager->finish_attempt($attempt);
    redirect(new moodle_url('/mod/decisionforge/result.php', ['id' => $id, 'attemptid' => $attemptid]));
}

$case = $current['case'];
if (count($current['options']) < 2) {
    throw new moodle_exception('errorcaseconfiguration', 'mod_decisionforge', '', format_string($case->title));
}
$options = [];
foreach ($current['options'] as $option) {
    $options[] = [
        'id' => $option->id,
        'letter' => chr(65 + count($options)),
        'text' => format_text($option->optiontext, $option->optiontextformat, ['context' => $context]),
    ];
}

$attemptcases = $manager->get_attempt_cases($attemptid);
$totalcases = count($attemptcases);
$currentnumber = (int) $attempt->currentcase + 1;
$progress = $totalcases ? round(($attempt->currentcase / $totalcases) * 100) : 0;
$imageurl = $manager->get_case_image_url($case->id);
$deadline = $manager->get_attempt_deadline($attempt);
$timeleft = $deadline ? max(0, $deadline - time()) : 0;

$PAGE->set_url('/mod/decisionforge/attempt.php', ['id' => $id, 'attemptid' => $attemptid]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->add_body_class('mod-decisionforge-attempt');
$PAGE->requires->js_call_amd('mod_decisionforge/attempt', 'init', [[
    'formid' => 'decisionforge-response-form',
    'requireconfidence' => !empty($activity->requireconfidence),
    'timeleft' => $timeleft,
    'expiredurl' => (new moodle_url('/mod/decisionforge/attempt.php', ['id' => $id, 'attemptid' => $attemptid]))->out(false),
]]);

$data = [
    'name' => format_string($activity->name),
    'casetitle' => format_string($case->title),
    'scenario' => format_text($case->scenario, $case->scenarioformat, ['context' => $context]),
    'questiontext' => format_string($case->questiontext),
    'caseimage' => $imageurl,
    'hasimage' => !empty($imageurl),
    'options' => $options,
    'current' => $currentnumber,
    'total' => $totalcases,
    'progress' => $progress,
    'progresslabel' => get_string('caseprogress', 'mod_decisionforge', [
        'current' => $currentnumber,
        'total' => $totalcases,
    ]),
    'showprogress' => !empty($activity->showprogress),
    'attemptnumber' => $attempt->attemptnumber,
    'formurl' => (new moodle_url('/mod/decisionforge/attempt.php', ['id' => $id, 'attemptid' => $attemptid]))->out(false),
    'sesskey' => sesskey(),
    'requirejustification' => !empty($activity->requirejustification),
    'requireconfidence' => !empty($activity->requireconfidence),
    'timeleft' => $timeleft,
    'hastimelimit' => $deadline > 0,
    'timeleftformatted' => $timeleft ? format_time($timeleft) : '',
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/attempt', $data);
echo $OUTPUT->footer();
