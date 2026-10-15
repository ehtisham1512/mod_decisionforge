<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Guided AI Quick Assessment Builder.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\form\quick_ai_builder_form;
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

if (($activity->experiencemode ?? 'quick') !== 'quick') {
    redirect(new moodle_url('/mod/decisionforge/aibuilder.php', ['id' => $id]));
}

$returnurl = new moodle_url('/mod/decisionforge/manage.php', ['id' => $id]);
$builderurl = new moodle_url('/mod/decisionforge/quickaibuilder.php', ['id' => $id]);
$existingcount = $DB->count_records('decisionforge_cases', [
    'decisionforgeid' => $activity->id,
    'archived' => 0,
]);
$providers = ai_generator::get_configured_providers();
$defaultprovider = ai_generator::get_default_provider();

if ($action !== '') {
    require_sesskey();
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest', 'error');
    }
    if ($action === 'importappend' || $action === 'importreplace') {
        $mode = $action === 'importreplace' ? 'replace' : 'append';
        $replaceconfirmed = optional_param('replaceconfirmed', 0, PARAM_BOOL);
        try {
            $casecount = $generator->import_quick_draft(
                $draftid,
                $USER->id,
                $mode,
                (bool) $replaceconfirmed
            );
            redirect($returnurl, get_string('aiquickimportsuccess', 'mod_decisionforge', $casecount), null,
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

$form = new quick_ai_builder_form($builderurl->out(false), [
    'providers' => $providers,
    'defaultprovider' => $defaultprovider,
]);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if (!$draftid && ($formdata = $form->get_data())) {
    try {
        $draft = $generator->generate_quick($formdata, $USER->id);
        redirect(new moodle_url('/mod/decisionforge/quickaibuilder.php', [
            'id' => $id,
            'draftid' => $draft->id,
        ]), get_string('aiquickdraftready', 'mod_decisionforge'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $exception) {
        \core\notification::error($exception->getMessage());
    }
}

$draftdata = null;
if ($draftid) {
    $draft = $generator->get_draft($draftid, $USER->id);
    $blueprint = $generator->decode_quick_draft($draft);
    $draftsettings = json_decode($draft->settingsjson, true);
    $keysource = is_array($draftsettings) ? (string) ($draftsettings['keysource'] ?? 'unknown') : 'unknown';
    $cases = [];
    $totalpoints = 0;
    foreach ($blueprint['cases'] as $position => $case) {
        $options = [];
        foreach ($case['options'] as $option) {
            $options[] = [
                'text' => $option['text'],
                'quality' => format_float($option['quality'], 0),
                'consequence' => $option['consequence'],
                'best' => (float) $option['quality'] === 100.0,
            ];
        }
        $totalpoints += $case['points'];
        $cases[] = [
            'number' => str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT),
            'title' => $case['title'],
            'scenario' => $case['scenario'],
            'question' => $case['question'],
            'points' => format_float($case['points'], 0),
            'options' => $options,
        ];
    }
    $draftdata = [
        'id' => $draft->id,
        'title' => $blueprint['title'],
        'summary' => $blueprint['summary'],
        'model' => s($draft->model),
        'provider' => ai_generator::get_provider_label($draft->provider),
        'keysource' => ai_generator::get_key_source_label($keysource),
        'casecount' => count($cases),
        'totalpoints' => format_float($totalpoints, 0),
        'cases' => $cases,
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
    'importurl' => $builderurl->out(false),
    'casesurl' => $returnurl->out(false),
    'id' => $id,
    'draftid' => $draftid,
    'sesskey' => sesskey(),
];

$PAGE->set_url('/mod/decisionforge/quickaibuilder.php', ['id' => $id, 'draftid' => $draftid]);
$PAGE->set_title(get_string('aiquickbuilder', 'mod_decisionforge'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('mod-decisionforge-ai-builder');
$PAGE->requires->js_call_amd('mod_decisionforge/aibuilder', 'init', [['root' => '#df-quick-ai-builder']]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_decisionforge/quick_ai_builder', $data);
echo $OUTPUT->footer();
