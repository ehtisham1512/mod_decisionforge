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
 * Premium case management screen.
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
require_capability('mod/decisionforge:managecases', $context);
if (($activity->experiencemode ?? 'quick') === 'simulation') {
    redirect(new moodle_url('/mod/decisionforge/simulationstudio.php', ['id' => $id]));
}

$PAGE->set_url('/mod/decisionforge/manage.php', ['id' => $id]);
$PAGE->set_title(get_string('managecases', 'mod_decisionforge'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('mod-decisionforge-authoring');

$cases = array_values($manager->get_cases());
$livecases = array_filter($cases, static fn($case) => empty($case->archived));
$casecount = count($livecases);
$activecount = count(array_filter($livecases, static fn($case) => !empty($case->active)));
$activecases = array_filter($livecases, static fn($case) => !empty($case->active));
$totalpoints = array_sum(array_map(static fn($case) => (float) $case->points, $activecases));

$templatecases = [];
foreach ($cases as $index => $case) {
    $options = $manager->get_options($case->id);
    $imageurl = $manager->get_case_image_url($case->id);
    $editurl = new moodle_url('/mod/decisionforge/editcase.php', ['id' => $id, 'caseid' => $case->id]);
    $baseaction = ['id' => $id, 'caseid' => $case->id];
    $templatecases[] = [
        'id' => $case->id,
        'number' => $index + 1,
        'title' => format_string($case->title),
        'scenario' => shorten_text(trim(strip_tags($case->scenario)), 150),
        'points' => format_float($case->points, 0),
        'optioncount' => count($options),
        'active' => !empty($case->active),
        'inactive' => empty($case->active),
        'archived' => !empty($case->archived),
        'editable' => empty($case->archived),
        'imageurl' => $imageurl,
        'hasimage' => !empty($imageurl),
        'editurl' => $editurl->out(false),
        'duplicateurl' => (new moodle_url('/mod/decisionforge/action.php', $baseaction + ['action' => 'duplicate']))->out(false),
        'toggleurl' => (new moodle_url('/mod/decisionforge/action.php', $baseaction + ['action' => 'toggle']))->out(false),
        'deleteurl' => (new moodle_url('/mod/decisionforge/action.php', $baseaction + [
            'action' => 'delete',
            'sesskey' => sesskey(),
        ]))->out(false),
        'moveupurl' => (new moodle_url('/mod/decisionforge/action.php', $baseaction + ['action' => 'moveup']))->out(false),
        'movedownurl' => (new moodle_url('/mod/decisionforge/action.php', $baseaction + ['action' => 'movedown']))->out(false),
        'canmoveup' => $index > 0,
        'canmovedown' => $index < ($casecount - 1),
    ];
}

$data = [
    'name' => format_string($activity->name),
    'casecount' => $casecount,
    'activecount' => $activecount,
    'totalpoints' => format_float($totalpoints, 0),
    'cases' => $templatecases,
    'hascases' => !empty($templatecases),
    'nocases' => empty($templatecases),
    'addurl' => (new moodle_url('/mod/decisionforge/editcase.php', ['id' => $id]))->out(false),
    'canuseai' => has_capability('mod/decisionforge:useai', $context),
    'aibuilderurl' => (new moodle_url('/mod/decisionforge/quickaibuilder.php', ['id' => $id]))->out(false),
    'sampleurl' => (new moodle_url('/mod/decisionforge/seed.php', ['id' => $id, 'sesskey' => sesskey()]))->out(false),
    'viewurl' => (new moodle_url('/mod/decisionforge/view.php', ['id' => $id]))->out(false),
    'reporturl' => (new moodle_url('/mod/decisionforge/report.php', ['id' => $id]))->out(false),
    'gradebookurl' => (new moodle_url('/grade/report/grader/index.php', ['id' => $course->id]))->out(false),
    'sesskey' => sesskey(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/manage', $data);
echo $OUTPUT->footer();
