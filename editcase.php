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
 * Create or edit a mini-case.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

use mod_decisionforge\form\case_form;
use mod_decisionforge\local\editor_value;
use mod_decisionforge\local\manager;

$id = required_param('id', PARAM_INT);
$caseid = optional_param('caseid', 0, PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$manager = manager::from_cmid($id);
$activity = $manager->get_activity();
$cm = $manager->get_cm();
$context = $manager->get_context();
$course = get_course($activity->course);

require_login($course, true, $cm);
require_capability('mod/decisionforge:managecases', $context);

$returnurl = $returnurlparam ?: (new moodle_url('/mod/decisionforge/manage.php', ['id' => $id]))->out(false);
$PAGE->set_url('/mod/decisionforge/editcase.php', ['id' => $id, 'caseid' => $caseid]);
$PAGE->set_title($caseid ? get_string('editcase', 'mod_decisionforge') : get_string('addcase', 'mod_decisionforge'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->activityheader->disable();
$PAGE->add_body_class('mod-decisionforge-authoring');

$fileoptions = [
    'subdirs' => 0,
    'maxbytes' => 8 * 1024 * 1024,
    'maxfiles' => 1,
    'accepted_types' => ['.jpg', '.jpeg', '.png', '.gif', '.webp'],
];

$case = null;
$defaults = new stdClass();
$draftitemid = file_get_submitted_draft_itemid('caseimage');
if ($caseid) {
    $case = $manager->get_case($caseid);
    if (!empty($case->archived)) {
        throw new moodle_exception('errorarchivedcase', 'mod_decisionforge');
    }
    $defaults = clone $case;
    $defaults->scenario_editor = ['text' => $case->scenario, 'format' => $case->scenarioformat];
    file_prepare_draft_area(
        $draftitemid,
        $context->id,
        'mod_decisionforge',
        'caseimage',
        $caseid,
        $fileoptions
    );
    $defaults->caseimage = $draftitemid;
    $options = array_values($manager->get_options($caseid));
    for ($i = 1; $i <= 4; $i++) {
        $option = $options[$i - 1] ?? null;
        if ($option) {
            $defaults->{'option' . $i . '_editor'} = [
                'text' => $option->optiontext,
                'format' => $option->optiontextformat,
            ];
            $defaults->{'score' . $i} = (int) $option->score;
            $defaults->{'consequence' . $i . '_editor'} = [
                'text' => $option->consequence,
                'format' => $option->consequenceformat,
            ];
        }
    }
} else {
    $defaults->caseimage = $draftitemid;
}

$formurl = new moodle_url('/mod/decisionforge/editcase.php', ['id' => $id]);
$mform = new case_form($formurl->out(false), [
    'caseid' => $caseid,
    'returnurl' => $returnurl,
]);
$mform->set_data($defaults);

if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $mform->get_data()) {
    global $DB;

    $scenario = $data->scenario_editor ?? '';

    $configuredmax = (int) get_config('decisionforge', 'maxcases');
    $maxcases = $configuredmax > 0 ? $configuredmax : 50;
    if (!$caseid && count($manager->get_cases()) >= $maxcases) {
        throw new moodle_exception('maxcasesreached', 'mod_decisionforge', '', $maxcases);
    }

    $record = (object) [
        'decisionforgeid' => $activity->id,
        'title' => trim($data->title),
        'scenario' => editor_value::text($scenario),
        'scenarioformat' => editor_value::format($scenario),
        'questiontext' => trim($data->questiontext),
        'points' => (float) $data->points,
        'active' => empty($data->active) ? 0 : 1,
        'archived' => 0,
        'timemodified' => time(),
    ];

    $transaction = $DB->start_delegated_transaction();
    $versioned = false;
    if ($caseid) {
        $hasattempts = $DB->record_exists('decisionforge_attempt_cases', ['caseid' => $caseid]);
        if ($hasattempts) {
            if (count($manager->get_cases()) >= $maxcases) {
                throw new moodle_exception('maxcasesreached', 'mod_decisionforge', '', $maxcases);
            }
            // Preserve the exact content used by existing attempts and create a new live version.
            $oldcase = $manager->get_case($caseid);
            $oldcase->active = 0;
            $oldcase->archived = 1;
            $oldcase->title .= ' (' . get_string('archived', 'mod_decisionforge') . ')';
            $oldcase->position = count($manager->get_cases());
            $oldcase->timemodified = time();
            $DB->update_record('decisionforge_cases', $oldcase);

            $record->position = $case->position;
            $record->timecreated = time();
            $caseid = $DB->insert_record('decisionforge_cases', $record);
            $versioned = true;
        } else {
            $record->id = $caseid;
            $DB->update_record('decisionforge_cases', $record);
            $DB->delete_records('decisionforge_options', ['caseid' => $caseid]);
        }
    } else {
        $record->position = count($manager->get_cases());
        $record->timecreated = time();
        $caseid = $DB->insert_record('decisionforge_cases', $record);
    }

    $optionposition = 0;
    for ($i = 1; $i <= 4; $i++) {
        $textdata = $data->{'option' . $i . '_editor'} ?? '';
        if (!editor_value::has_content($textdata)) {
            continue;
        }
        $consequence = $data->{'consequence' . $i . '_editor'} ?? '';
        $DB->insert_record('decisionforge_options', (object) [
            'caseid' => $caseid,
            'optiontext' => editor_value::text($textdata),
            'optiontextformat' => editor_value::format($textdata),
            'score' => (float) $data->{'score' . $i},
            'consequence' => editor_value::text($consequence),
            'consequenceformat' => editor_value::format($consequence),
            'position' => $optionposition++,
        ]);
    }

    file_save_draft_area_files(
        $data->caseimage,
        $context->id,
        'mod_decisionforge',
        'caseimage',
        $caseid,
        $fileoptions
    );
    $transaction->allow_commit();
    $manager->normalize_positions();

    redirect(
        $returnurl,
        get_string($versioned ? 'caseversioned' : ($case ? 'caseupdated' : 'casecreated'), 'mod_decisionforge'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo html_writer::start_div('decisionforge-shell decisionforge-authoring-shell');
echo html_writer::tag('div', '', ['class' => 'decisionforge-brandmark', 'aria-hidden' => 'true']);
echo $OUTPUT->heading($caseid ? get_string('editcase', 'mod_decisionforge') : get_string('addcase', 'mod_decisionforge'));
echo html_writer::tag('p', format_string($activity->name), ['class' => 'text-muted mb-4']);
$mform->display();
echo html_writer::end_div();
echo $OUTPUT->footer();
