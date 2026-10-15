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
 * Teacher attempt report.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\manager;

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 25;

$manager = manager::from_cmid($id);
$activity = $manager->get_activity();
$cm = $manager->get_cm();
$context = $manager->get_context();
$course = get_course($activity->course);

require_login($course, true, $cm);
require_capability('mod/decisionforge:viewreports', $context);

global $DB;
$total = $DB->count_records('decisionforge_attempts', ['decisionforgeid' => $activity->id]);
$sql = "SELECT a.*, u.id AS participantid, u.firstname, u.lastname,
               u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
               u.picture, u.imagealt, u.email
          FROM {decisionforge_attempts} a
          JOIN {user} u ON u.id = a.userid
         WHERE a.decisionforgeid = :activityid
      ORDER BY a.timemodified DESC";
$attempts = $DB->get_records_sql($sql, ['activityid' => $activity->id], $page * $perpage, $perpage);

$rows = [];
foreach ($attempts as $attempt) {
    $user = (object) [
        'id' => $attempt->participantid,
        'firstname' => $attempt->firstname,
        'lastname' => $attempt->lastname,
        'firstnamephonetic' => $attempt->firstnamephonetic,
        'lastnamephonetic' => $attempt->lastnamephonetic,
        'middlename' => $attempt->middlename,
        'alternatename' => $attempt->alternatename,
        'picture' => $attempt->picture,
        'imagealt' => $attempt->imagealt,
        'email' => $attempt->email,
    ];
    $finished = $attempt->state === 'finished';
    $issimulation = ($attempt->mode ?? 'quick') === 'simulation';
    $outcomecode = in_array($attempt->outcomecode, ['command', 'controlled', 'fragile', 'critical'], true)
        ? $attempt->outcomecode : 'critical';
    $haspassinggrade = (float) $activity->gradepass > 0;
    $passed = $finished && $haspassinggrade && (float) $attempt->finalgrade >= (float) $activity->gradepass;
    $rows[] = [
        'userpic' => $OUTPUT->user_picture($user, ['size' => 40, 'link' => false]),
        'fullname' => fullname($user),
        'attemptnumber' => $attempt->attemptnumber,
        'issimulation' => $issimulation,
        'isquick' => !$issimulation,
        'modelabel' => get_string($issimulation ? 'modeimmersive' : 'modequick', 'mod_decisionforge'),
        'hasoutcome' => $issimulation && $finished && !empty($attempt->outcomecode),
        'outcome' => $issimulation && $finished && !empty($attempt->outcomecode)
            ? get_string('outcome' . $outcomecode, 'mod_decisionforge') : '',
        'state' => $attempt->state,
        'finished' => $finished,
        'inprogress' => $attempt->state === 'inprogress',
        'completedwithoutpass' => $finished && !$haspassinggrade,
        'passed' => $passed,
        'notpassed' => $finished && $haspassinggrade && !$passed,
        'grade' => $attempt->state === 'finished' ? format_float($attempt->finalgrade, 1) : '—',
        'maxgrade' => format_float($activity->grade, 0),
        'started' => userdate($attempt->timestart),
        'finishedtime' => $attempt->timefinish ? userdate($attempt->timefinish) : '—',
        'duration' => $attempt->timefinish
            ? format_time($attempt->timefinish - $attempt->timestart)
            : format_time(time() - $attempt->timestart),
        'viewurl' => (new moodle_url($issimulation
            ? '/mod/decisionforge/simresult.php' : '/mod/decisionforge/result.php', [
                'id' => $id,
                'attemptid' => $attempt->id,
            ]))->out(false),
        'deleteurl' => (new moodle_url('/mod/decisionforge/deleteattempt.php', [
            'id' => $id,
            'attemptid' => $attempt->id,
            'sesskey' => sesskey(),
        ]))->out(false),
        'canview' => $attempt->state === 'finished',
        'candelete' => has_capability('mod/decisionforge:deleteattempts', $context),
    ];
}

$PAGE->set_url('/mod/decisionforge/report.php', ['id' => $id, 'page' => $page]);
$PAGE->set_title(get_string('attemptreport', 'mod_decisionforge'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('mod-decisionforge-report');

$data = [
    'name' => format_string($activity->name),
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'norows' => empty($rows),
    'total' => $total,
    'manageurl' => (new moodle_url('/mod/decisionforge/manage.php', ['id' => $id]))->out(false),
    'viewurl' => (new moodle_url('/mod/decisionforge/view.php', ['id' => $id]))->out(false),
    'gradebookurl' => (new moodle_url('/grade/report/grader/index.php', ['id' => $course->id]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/report', $data);
if ($total > $perpage) {
    echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url('/mod/decisionforge/report.php', ['id' => $id]));
}
echo $OUTPUT->footer();
