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
 * Immediate consequence feedback screen.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\manager;

$id = required_param('id', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);
$responseid = required_param('responseid', PARAM_INT);

$manager = manager::from_cmid($id);
$activity = $manager->get_activity();
$cm = $manager->get_cm();
$context = $manager->get_context();
$course = get_course($activity->course);

require_login($course, true, $cm);
require_capability('mod/decisionforge:attempt', $context);
$attempt = $manager->get_attempt($attemptid, $USER->id);

global $DB;
$sql = 'SELECT r.*, c.title, c.points, o.optiontext, o.optiontextformat,
               o.score AS optionquality, o.consequence, o.consequenceformat
          FROM {decisionforge_responses} r
          JOIN {decisionforge_cases} c ON c.id = r.caseid
          JOIN {decisionforge_options} o ON o.id = r.optionid
         WHERE r.id = :responseid AND r.attemptid = :attemptid';
$response = $DB->get_record_sql($sql, ['responseid' => $responseid, 'attemptid' => $attemptid], MUST_EXIST);

$quality = (float) $response->optionquality;
if ($quality >= 100) {
    $status = 'best';
    $statuslabel = get_string('bestdecision', 'mod_decisionforge');
} elseif ($quality >= 75) {
    $status = 'good';
    $statuslabel = get_string('gooddecision', 'mod_decisionforge');
} elseif ($quality > 0) {
    $status = 'risky';
    $statuslabel = get_string('riskydecision', 'mod_decisionforge');
} else {
    $status = 'unsafe';
    $statuslabel = get_string('unsafedecision', 'mod_decisionforge');
}

$PAGE->set_url('/mod/decisionforge/feedback.php', ['id' => $id, 'attemptid' => $attemptid, 'responseid' => $responseid]);
$PAGE->set_title($statuslabel);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->add_body_class('mod-decisionforge-feedback');

$data = [
    'status' => $status,
    'statuslabel' => $statuslabel,
    'casetitle' => format_string($response->title),
    'decision' => format_text($response->optiontext, $response->optiontextformat, ['context' => $context]),
    'consequence' => format_text($response->consequence, $response->consequenceformat, ['context' => $context]),
    'hasconsequence' => trim(strip_tags($response->consequence ?? '')) !== '',
    'earned' => format_float($response->score, 1),
    'possible' => format_float($response->points, 1),
    'finished' => $attempt->state === 'finished',
    'buttonlabel' => $attempt->state === 'finished'
        ? get_string('viewresults', 'mod_decisionforge')
        : get_string('nextcase', 'mod_decisionforge'),
    'nexturl' => $attempt->state === 'finished'
        ? (new moodle_url('/mod/decisionforge/result.php', ['id' => $id, 'attemptid' => $attemptid]))->out(false)
        : (new moodle_url('/mod/decisionforge/attempt.php', ['id' => $id, 'attemptid' => $attemptid]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/feedback', $data);
echo $OUTPUT->footer();
