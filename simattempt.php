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
 * Immersive simulation workspace.
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
require_capability('mod/decisionforge:attempt', $context);
$attempt = $simulation->get_attempt($attemptid, $USER->id);
$expired = false;
if ($attempt->state === 'inprogress') {
    // Use the same attempt lock as simsubmit.php so a timeout refresh cannot
    // finish the attempt while a learner's stage submission is being saved.
    $lockfactory = \core\lock\lock_config::get_lock_factory('mod_decisionforge');
    $lock = $lockfactory->get_lock('simulation-attempt-' . $attemptid, 10);
    if ($lock) {
        try {
            $attempt = $simulation->get_attempt($attemptid, $USER->id);
            $expired = $simulation->enforce_time_limit($attempt);
            $attempt = $simulation->get_attempt($attemptid, $USER->id);
        } finally {
            $lock->release();
        }
    }
}
if ($expired) {
    redirect(new moodle_url('/mod/decisionforge/simresult.php', ['id' => $id, 'attemptid' => $attemptid]),
        get_string('expired', 'mod_decisionforge'));
}
if ($attempt->state === 'finished') {
    redirect(new moodle_url('/mod/decisionforge/simresult.php', ['id' => $id, 'attemptid' => $attemptid]));
}
$stage = $simulation->get_current_stage($attempt);
if (!$stage) {
    throw new moodle_exception('errorinvalidstage', 'mod_decisionforge');
}
$snapshot = $simulation->decode_snapshot($attempt);
global $DB;
$responses = $DB->get_records('decisionforge_simresponses', ['attemptid' => $attemptid], 'responsesequence ASC');
$timeline = [];
foreach ($responses as $position => $response) {
    $item = $snapshot['stages'][(string) $response->stageid] ?? null;
    if (!$item) {
        continue;
    }
    $timeline[] = [
        'number' => $position + 1,
        'title' => format_string($item['title']),
        'kind' => $item['stagekind'],
        'kindlabel' => get_string('stagekind' . $item['stagekind'], 'mod_decisionforge'),
        'completed' => true,
        'current' => false,
        'pending' => false,
    ];
}
$timeline[] = [
    'number' => count($responses) + 1,
    'title' => format_string($stage['title']),
    'kind' => $stage['stagekind'],
    'kindlabel' => get_string('stagekind' . $stage['stagekind'], 'mod_decisionforge'),
    'completed' => false,
    'current' => true,
    'pending' => false,
];
$order = array_map('intval', $snapshot['order']);
$currentposition = array_search((int) $stage['id'], $order, true);
$hassequentialnext = $currentposition !== false && isset($order[$currentposition + 1]);
$defaultfuture = (int) ($stage['nextstageid'] ?? 0) > 0
    || ((int) ($stage['nextstageid'] ?? 0) === 0 && $hassequentialnext);
$hasfuture = $defaultfuture;
if ($stage['stagekind'] === 'decision') {
    $hasfuture = false;
    foreach ($stage['choices'] as $choice) {
        $choicenext = (int) ($choice['nextstageid'] ?? 0);
        if ($choicenext > 0 || ($choicenext === 0 && $defaultfuture)) {
            $hasfuture = true;
            break;
        }
    }
}
if ($hasfuture) {
    $timeline[] = [
        'number' => count($responses) + 2,
        'title' => get_string('routeunresolved', 'mod_decisionforge'),
        'kind' => 'unresolved',
        'kindlabel' => get_string('routeadaptive', 'mod_decisionforge'),
        'completed' => false,
        'current' => false,
        'pending' => true,
    ];
}
$choices = [];
$choicecount = count($stage['choices']);
foreach ($stage['choices'] as $position => $choice) {
    $rankoptions = [];
    for ($rank = 1; $rank <= $choicecount; $rank++) {
        $rankoptions[] = ['value' => $rank, 'selected' => $rank === $position + 1];
    }
    $choices[] = [
        'id' => (int) $choice['id'],
        'letter' => chr(65 + $position),
        'text' => format_text($choice['choicetext'], $choice['choicetextformat'], ['context' => $context]),
        'rankoptions' => $rankoptions,
    ];
}
$evidence = [];
$requiredevidencecount = 0;
foreach ($stage['evidence'] as $position => $item) {
    $required = !empty($stage['requireallcritical']) && !empty($item['critical']);
    if ($required) {
        $requiredevidencecount++;
    }
    $evidence[] = [
        'id' => (int) $item['id'],
        'number' => str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT),
        'title' => format_string($item['title']),
        'body' => format_text($item['body'], $item['bodyformat'], ['context' => $context]),
        'kind' => $item['evidencekind'],
        'kindlabel' => get_string('evidencekind' . $item['evidencekind'], 'mod_decisionforge'),
        'critical' => !empty($item['critical']),
        'required' => $required,
    ];
}
$evidenceprogress = get_string('evidenceprogress', 'mod_decisionforge', (object) [
    'reviewed' => 0,
    'required' => $requiredevidencecount,
]);
$coremanager = new manager($activity, $cm);
$deadline = $coremanager->get_attempt_deadline($attempt);
$data = [
    'name' => format_string($activity->name),
    'missionlabel' => format_string($activity->missionlabel),
    'attemptnumber' => $attempt->attemptnumber,
    'stageid' => $stage['id'],
    'stagenumber' => count($responses) + 1,
    'progresslabel' => get_string('adaptivestageprogress', 'mod_decisionforge', count($responses) + 1),
    'progress' => min(92, 18 + (count($responses) * 12)),
    'title' => format_string($stage['title']),
    'kind' => $stage['stagekind'],
    'kindlabel' => get_string('stagekind' . $stage['stagekind'], 'mod_decisionforge'),
    'body' => format_text($stage['body'], $stage['bodyformat'], ['context' => $context]),
    'prompt' => format_text($stage['prompt'], $stage['promptformat'], ['context' => $context]),
    'hasprompt' => trim(strip_tags($stage['prompt'] ?? '')) !== '',
    'isbriefing' => $stage['stagekind'] === 'briefing',
    'isevidence' => $stage['stagekind'] === 'evidence',
    'isdecision' => $stage['stagekind'] === 'decision',
    'isprioritise' => $stage['stagekind'] === 'prioritise',
    'isreflection' => $stage['stagekind'] === 'reflection',
    'choices' => $choices,
    'evidence' => $evidence,
    'hasrequiredevidence' => $requiredevidencecount > 0,
    'evidenceprogress' => $evidenceprogress,
    'evidenceincomplete' => get_string('evidenceincomplete', 'mod_decisionforge', $requiredevidencecount),
    'timeline' => $timeline,
    'safety' => $attempt->safety,
    'compliance' => $attempt->compliance,
    'trust' => $attempt->trust,
    'resources' => $attempt->resources,
    'deadline' => $deadline,
    'hastimer' => $deadline > 0,
    'submiturl' => (new moodle_url('/mod/decisionforge/simsubmit.php'))->out(false),
    'exiturl' => (new moodle_url('/mod/decisionforge/simulationview.php', ['id' => $id]))->out(false),
    'id' => $id,
    'attemptid' => $attemptid,
    'sesskey' => sesskey(),
];

$PAGE->set_url('/mod/decisionforge/simattempt.php', ['id' => $id, 'attemptid' => $attemptid]);
$PAGE->set_title(format_string($stage['title']));
$PAGE->set_heading(format_string($activity->name));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->add_body_class('mod-decisionforge-command-room');
$PAGE->requires->js_call_amd('mod_decisionforge/simulation', 'init', [[
    'root' => '#df-simapp',
    'evidenceprogress' => get_string('evidenceprogress', 'mod_decisionforge', (object) [
        'reviewed' => '__REVIEWED__',
        'required' => $requiredevidencecount,
    ]),
]]);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/simulation_attempt', $data);
echo $OUTPUT->footer();
