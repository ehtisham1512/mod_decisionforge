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
 * Consequence reveal between simulation stages.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\simulation_manager;

$id = required_param('id', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);
$responseid = required_param('responseid', PARAM_INT);
$simulation = simulation_manager::from_cmid($id);
$activity = $simulation->get_activity();
$cm = $simulation->get_cm();
$context = $simulation->get_context();
$course = get_course($activity->course);
require_login($course, true, $cm);
require_capability('mod/decisionforge:view', $context);
$reportaccess = has_capability('mod/decisionforge:viewreports', $context);
$attempt = $simulation->get_attempt($attemptid, $USER->id, $reportaccess);
$response = $simulation->get_response($responseid, $attemptid);
$snapshot = $simulation->decode_snapshot($attempt);
$stage = $snapshot['stages'][(string) $response->stageid] ?? null;
if (!$stage) {
    throw new moodle_exception('errorinvalidstage', 'mod_decisionforge');
}
$choice = null;
foreach ($stage['choices'] as $item) {
    if ((int) $item['id'] === (int) $response->choiceid) {
        $choice = $item;
        break;
    }
}
$impacts = [];
foreach (['safety', 'compliance', 'trust', 'resources'] as $metric) {
    $value = $choice ? (int) ($choice[$metric . 'impact'] ?? 0) : 0;
    $impacts[] = [
        'label' => get_string('metric' . $metric, 'mod_decisionforge'),
        'value' => ($value > 0 ? '+' : '') . $value,
        'positive' => $value > 0,
        'negative' => $value < 0,
        'neutral' => $value === 0,
    ];
}
$consequenceformat = $choice ? $choice['consequenceformat'] : FORMAT_PLAIN;
$data = [
    'name' => format_string($activity->name),
    'stagetitle' => format_string($stage['title']),
    'kindlabel' => get_string('stagekind' . $stage['stagekind'], 'mod_decisionforge'),
    'consequence' => format_text($response->consequence, $consequenceformat, ['context' => $context]),
    'hasconsequence' => trim(strip_tags($response->consequence ?? '')) !== '',
    'choice' => $choice ? format_text($choice['choicetext'], $choice['choicetextformat'], ['context' => $context]) : '',
    'haschoice' => !empty($choice),
    'score' => format_float($response->score, 1),
    'maxscore' => format_float($response->maxscore, 1),
    'hasscore' => (float) $response->maxscore > 0,
    'impacts' => $impacts,
    'safety' => $response->safety,
    'compliance' => $response->compliance,
    'trust' => $response->trust,
    'resources' => $response->resources,
    'finished' => $attempt->state === 'finished',
    'buttonlabel' => get_string($attempt->state === 'finished' ? 'openafteractionreport' : 'continueroute',
        'mod_decisionforge'),
    'nexturl' => (new moodle_url($attempt->state === 'finished'
        ? '/mod/decisionforge/simresult.php' : '/mod/decisionforge/simattempt.php', [
            'id' => $id,
            'attemptid' => $attemptid,
        ]))->out(false),
];
$PAGE->set_url('/mod/decisionforge/simfeedback.php', [
    'id' => $id,
    'attemptid' => $attemptid,
    'responseid' => $responseid,
]);
$PAGE->set_title(get_string('consequencereveal', 'mod_decisionforge'));
$PAGE->set_heading(format_string($activity->name));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->add_body_class('mod-decisionforge-consequence');
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/simulation_feedback', $data);
echo $OUTPUT->footer();
