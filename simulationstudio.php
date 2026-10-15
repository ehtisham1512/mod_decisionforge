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
 * Simulation authoring studio.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\simulation_manager;

$id = required_param('id', PARAM_INT);
$manager = simulation_manager::from_cmid($id);
$activity = $manager->get_activity();
$cm = $manager->get_cm();
$context = $manager->get_context();
$course = get_course($activity->course);
require_login($course, true, $cm);
require_capability('mod/decisionforge:managecases', $context);

$stages = array_values($manager->get_stages());
$stageindex = [];
foreach ($stages as $stage) {
    $stageindex[(int) $stage->id] = format_string($stage->title);
}
$nextpublished = [];
$nextpublishedtitle = null;
foreach (array_reverse($stages) as $stage) {
    $nextpublished[(int) $stage->id] = $nextpublishedtitle;
    if (!empty($stage->active)) {
        $nextpublishedtitle = format_string($stage->title);
    }
}
$templatestages = [];
foreach ($stages as $index => $stage) {
    $choices = $manager->get_choices((int) $stage->id);
    $evidence = $manager->get_evidence((int) $stage->id);
    $branchcount = count(array_unique(array_map(
        static fn($choice) => (int) $choice->nextstageid,
        $choices
    )));
    if ((int) $stage->nextstageid === -1) {
        $nextlabel = get_string('endsimulation', 'mod_decisionforge');
    } elseif (!empty($stage->nextstageid) && isset($stageindex[(int) $stage->nextstageid])) {
        $nextlabel = $stageindex[(int) $stage->nextstageid];
    } elseif (!empty($nextpublished[(int) $stage->id])) {
        $nextlabel = $nextpublished[(int) $stage->id];
    } else {
        $nextlabel = get_string('endsimulation', 'mod_decisionforge');
    }
    $templatestages[] = [
        'id' => $stage->id,
        'number' => $index + 1,
        'title' => format_string($stage->title),
        'kind' => $stage->stagekind,
        'kindlabel' => get_string('stagekind' . $stage->stagekind, 'mod_decisionforge'),
        'body' => shorten_text(trim(strip_tags($stage->body)), 130),
        'points' => format_float($stage->points, 0),
        'hasscore' => (float) $stage->points > 0,
        'choicecount' => count($choices),
        'evidencecount' => count($evidence),
        'branchcount' => $branchcount,
        'hasbranches' => $branchcount > 1,
        'active' => !empty($stage->active),
        'inactive' => empty($stage->active),
        'nextlabel' => $nextlabel,
        'editurl' => (new moodle_url('/mod/decisionforge/editstage.php', [
            'id' => $id,
            'stageid' => $stage->id,
        ]))->out(false),
        'moveupurl' => (new moodle_url('/mod/decisionforge/stageaction.php', [
            'id' => $id,
            'stageid' => $stage->id,
            'action' => 'moveup',
        ]))->out(false),
        'movedownurl' => (new moodle_url('/mod/decisionforge/stageaction.php', [
            'id' => $id,
            'stageid' => $stage->id,
            'action' => 'movedown',
        ]))->out(false),
        'deleteurl' => (new moodle_url('/mod/decisionforge/stageaction.php', [
            'id' => $id,
            'stageid' => $stage->id,
            'action' => 'delete',
        ]))->out(false),
        'canmoveup' => $index > 0,
        'canmovedown' => $index < count($stages) - 1,
    ];
}
$warnings = $manager->get_configuration_warnings();
$data = [
    'name' => format_string($activity->name),
    'missionlabel' => format_string($activity->missionlabel),
    'stages' => $templatestages,
    'hasstages' => !empty($stages),
    'nostages' => empty($stages),
    'stagecount' => count($stages),
    'warningcount' => count($warnings),
    'warnings' => array_map(static fn($warning) => ['text' => $warning], $warnings),
    'haswarnings' => !empty($warnings),
    'ready' => empty($warnings),
    'initialsafety' => $activity->initialsafety,
    'initialcompliance' => $activity->initialcompliance,
    'initialtrust' => $activity->initialtrust,
    'initialresources' => $activity->initialresources,
    'addurl' => (new moodle_url('/mod/decisionforge/editstage.php', ['id' => $id]))->out(false),
    'seedurl' => (new moodle_url('/mod/decisionforge/seedsimulation.php', [
        'id' => $id,
        'sesskey' => sesskey(),
    ]))->out(false),
    'previewurl' => (new moodle_url('/mod/decisionforge/view.php', ['id' => $id]))->out(false),
    'reporturl' => (new moodle_url('/mod/decisionforge/report.php', ['id' => $id]))->out(false),
    'gradebookurl' => (new moodle_url('/grade/report/grader/index.php', ['id' => $course->id]))->out(false),
    'canaibuild' => has_capability('mod/decisionforge:useai', $context),
    'aibuilderurl' => (new moodle_url('/mod/decisionforge/aibuilder.php', ['id' => $id]))->out(false),
    'sesskey' => sesskey(),
];

$PAGE->set_url('/mod/decisionforge/simulationstudio.php', ['id' => $id]);
$PAGE->set_title(get_string('simulationstudio', 'mod_decisionforge'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('mod-decisionforge-simulation-studio');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/simulation_studio', $data);
echo $OUTPUT->footer();
