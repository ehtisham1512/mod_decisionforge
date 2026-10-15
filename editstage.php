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
 * Add or edit a simulation stage.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\form\stage_form;
use mod_decisionforge\local\simulation_manager;

$id = required_param('id', PARAM_INT);
$stageid = optional_param('stageid', 0, PARAM_INT);
$manager = simulation_manager::from_cmid($id);
$activity = $manager->get_activity();
$cm = $manager->get_cm();
$context = $manager->get_context();
$course = get_course($activity->course);

require_login($course, true, $cm);
require_capability('mod/decisionforge:managecases', $context);

$targets = [
    0 => get_string('nextstageinsequence', 'mod_decisionforge'),
    -1 => get_string('endsimulation', 'mod_decisionforge'),
];
foreach ($manager->get_stages() as $target) {
    if ((int) $target->id !== $stageid) {
        $targets[$target->id] = format_string($target->title);
    }
}
$form = new stage_form(null, ['targets' => $targets]);
$defaults = (object) ['id' => $id, 'stageid' => $stageid];
if ($stageid) {
    $stage = $manager->get_stage($stageid);
    $defaults = clone $stage;
    $defaults->stageid = $stageid;
    $defaults->id = $id;
    $defaults->body_editor = ['text' => $stage->body, 'format' => $stage->bodyformat];
    $defaults->prompt_editor = ['text' => $stage->prompt, 'format' => $stage->promptformat];
    foreach (array_values($manager->get_choices($stageid)) as $index => $choice) {
        $defaults->{'choice_' . $index} = [
            'text' => $choice->choicetext,
            'quality' => $choice->quality,
            'targetrank' => $choice->targetrank,
        ];
        $defaults->{'choice_consequence_' . $index} = $choice->consequence;
        $defaults->{'choice_next_' . $index} = $choice->nextstageid;
        $defaults->{'choice_impacts_' . $index} = [
            'safety' => $choice->safetyimpact,
            'compliance' => $choice->complianceimpact,
            'trust' => $choice->trustimpact,
            'resources' => $choice->resourcesimpact,
        ];
    }
    foreach (array_values($manager->get_evidence($stageid)) as $index => $evidence) {
        $defaults->{'evidence_' . $index} = [
            'title' => $evidence->title,
            'kind' => $evidence->evidencekind,
            'critical' => $evidence->critical,
        ];
        $defaults->{'evidence_body_' . $index} = $evidence->body;
    }
} else {
    $defaults->body_editor = ['text' => '', 'format' => FORMAT_HTML];
    $defaults->prompt_editor = ['text' => '', 'format' => FORMAT_HTML];
}
$form->set_data($defaults);
$returnurl = new moodle_url('/mod/decisionforge/simulationstudio.php', ['id' => $id]);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    $data->choices = [];
    $data->evidence = [];
    for ($i = 0; $i < 4; $i++) {
        $choice = (array) ($data->{'choice_' . $i} ?? []);
        $impact = (array) ($data->{'choice_impacts_' . $i} ?? []);
        $data->choices[] = [
            'text' => $choice['text'] ?? '',
            'quality' => $choice['quality'] ?? 0,
            'targetrank' => $choice['targetrank'] ?? ($i + 1),
            'consequence' => $data->{'choice_consequence_' . $i} ?? '',
            'nextstageid' => $data->{'choice_next_' . $i} ?? 0,
            'safetyimpact' => $impact['safety'] ?? 0,
            'complianceimpact' => $impact['compliance'] ?? 0,
            'trustimpact' => $impact['trust'] ?? 0,
            'resourcesimpact' => $impact['resources'] ?? 0,
        ];
        $evidence = (array) ($data->{'evidence_' . $i} ?? []);
        $data->evidence[] = [
            'title' => $evidence['title'] ?? '',
            'kind' => $evidence['kind'] ?? 'document',
            'critical' => $evidence['critical'] ?? 0,
            'body' => $data->{'evidence_body_' . $i} ?? '',
        ];
    }
    $manager->save_stage($data);
    redirect($returnurl, get_string('stagesaved', 'mod_decisionforge'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_url('/mod/decisionforge/editstage.php', ['id' => $id, 'stageid' => $stageid]);
$PAGE->set_title(get_string($stageid ? 'editstage' : 'addstage', 'mod_decisionforge'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('mod-decisionforge-stage-editor');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($stageid ? 'editstage' : 'addstage', 'mod_decisionforge'));
$form->display();
echo $OUTPUT->footer();
