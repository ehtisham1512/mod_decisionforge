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
 * Guided AI Simulation Builder.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\form\ai_builder_form;
use mod_decisionforge\local\ai_generator;

$id = required_param('id', PARAM_INT);
$draftid = optional_param('draftid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$generator = ai_generator::from_cmid($id);
global $DB, $USER;
$cm = get_coursemodule_from_id('decisionforge', $id, 0, false, MUST_EXIST);
$activity = $DB->get_record('decisionforge', ['id' => $cm->instance], '*', MUST_EXIST);
$course = get_course($activity->course);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/decisionforge:useai', $context);
if (($activity->experiencemode ?? 'quick') !== 'simulation') {
    redirect(new moodle_url('/mod/decisionforge/quickaibuilder.php', ['id' => $id]));
}

$returnurl = new moodle_url('/mod/decisionforge/simulationstudio.php', ['id' => $id]);
$builderurl = new moodle_url('/mod/decisionforge/aibuilder.php', ['id' => $id]);
$existingcount = $DB->count_records('decisionforge_simstages', ['decisionforgeid' => $activity->id]);
$providers = ai_generator::get_configured_providers();
$defaultprovider = ai_generator::get_default_provider();

if ($action !== '') {
    require_sesskey();
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest', 'error');
    }
    if ($action === 'import') {
        $replaceconfirmed = optional_param('replaceconfirmed', 0, PARAM_BOOL);
        try {
            $stagecount = $generator->import_draft($draftid, $USER->id, (bool) $replaceconfirmed);
            redirect($returnurl, get_string('aiimportsuccess', 'mod_decisionforge', $stagecount), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } catch (moodle_exception $exception) {
            \core\notification::error($exception->getMessage());
        }
    } elseif ($action === 'discard') {
        $generator->discard_draft($draftid, $USER->id);
        redirect($builderurl, get_string('aidraftdiscarded', 'mod_decisionforge'));
    } else {
        throw new moodle_exception('invalidrequest', 'error');
    }
}

$form = new ai_builder_form(null, [
    'providers' => $providers,
    'defaultprovider' => $defaultprovider,
]);
$form->set_data((object) ['id' => $id]);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if (!$draftid && ($formdata = $form->get_data())) {
    try {
        $draft = $generator->generate($formdata, $USER->id);
        redirect(new moodle_url('/mod/decisionforge/aibuilder.php', [
            'id' => $id,
            'draftid' => $draft->id,
        ]), get_string('aidraftready', 'mod_decisionforge'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $exception) {
        \core\notification::error($exception->getMessage());
    }
}

$draftdata = null;
if ($draftid) {
    $draft = $generator->get_draft($draftid, $USER->id);
    $blueprint = $generator->decode_draft($draft);
    $draftsettings = json_decode($draft->settingsjson, true);
    $keysource = is_array($draftsettings) ? (string) ($draftsettings['keysource'] ?? 'unknown') : 'unknown';
    $stages = [];
    $totalpoints = 0;
    foreach ($blueprint['stages'] as $position => $stage) {
        $choices = [];
        foreach ($stage['choices'] as $choice) {
            $choices[] = [
                'text' => $choice['text'],
                'quality' => format_float($choice['quality'], 0),
                'consequence' => $choice['consequence'],
                'route' => $choice['next_key'],
                'safety' => $choice['safety_impact'],
                'compliance' => $choice['compliance_impact'],
                'trust' => $choice['trust_impact'],
                'resources' => $choice['resources_impact'],
                'rank' => $choice['target_rank'],
                'isdecision' => $stage['kind'] === 'decision',
                'isprioritise' => $stage['kind'] === 'prioritise',
            ];
        }
        $evidence = [];
        foreach ($stage['evidence'] as $item) {
            $evidence[] = [
                'title' => $item['title'],
                'body' => $item['body'],
                'kindlabel' => get_string('evidencekind' . $item['kind'], 'mod_decisionforge'),
                'critical' => $item['critical'],
            ];
        }
        $totalpoints += $stage['points'];
        $stages[] = [
            'number' => str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT),
            'title' => $stage['title'],
            'kind' => $stage['kind'],
            'kindlabel' => get_string('stagekind' . $stage['kind'], 'mod_decisionforge'),
            'body' => $stage['body'],
            'prompt' => $stage['prompt'],
            'hasprompt' => $stage['prompt'] !== '',
            'points' => format_float($stage['points'], 0),
            'hasscore' => $stage['points'] > 0,
            'route' => $stage['next_key'],
            'choices' => $choices,
            'haschoices' => !empty($choices),
            'evidence' => $evidence,
            'hasevidence' => !empty($evidence),
        ];
    }
    $draftdata = [
        'id' => $draft->id,
        'title' => $blueprint['title'],
        'summary' => $blueprint['summary'],
        'missionlabel' => $blueprint['mission_label'],
        'model' => s($draft->model),
        'provider' => ai_generator::get_provider_label($draft->provider),
        'keysource' => ai_generator::get_key_source_label($keysource),
        'stagecount' => count($stages),
        'totalpoints' => format_float($totalpoints, 0),
        'stages' => $stages,
    ];
}

$enabled = ai_generator::is_enabled();
$haskey = !empty($providers);
$configured = $enabled && $haskey;
$data = [
    'name' => format_string($activity->name),
    'configured' => $configured,
    'notconfigured' => !$configured,
    'disabled' => !$enabled,
    'missingkey' => $enabled && !$haskey,
    'canconfigure' => has_capability('moodle/site:config', context_system::instance()),
    'settingsurl' => (new moodle_url('/admin/settings.php', ['section' => 'modsettingdecisionforge']))->out(false),
    'hasdraft' => !empty($draftdata),
    'draft' => $draftdata,
    'showform' => empty($draftdata),
    'formhtml' => empty($draftdata) && $configured ? $form->render() : '',
    'existingcount' => $existingcount,
    'hasexisting' => $existingcount > 0,
    'importurl' => (new moodle_url('/mod/decisionforge/aibuilder.php'))->out(false),
    'builderurl' => $builderurl->out(false),
    'studiourl' => $returnurl->out(false),
    'id' => $id,
    'draftid' => $draftid,
    'sesskey' => sesskey(),
];

$PAGE->set_url('/mod/decisionforge/aibuilder.php', ['id' => $id, 'draftid' => $draftid]);
$PAGE->set_title(get_string('aibuilder', 'mod_decisionforge'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('mod-decisionforge-ai-builder');
$PAGE->requires->js_call_amd('mod_decisionforge/aibuilder', 'init', [['root' => '#df-ai-builder']]);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/ai_builder', $data);
echo $OUTPUT->footer();
