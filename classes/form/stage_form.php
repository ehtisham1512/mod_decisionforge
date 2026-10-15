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

namespace mod_decisionforge\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Simulation stage authoring form.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stage_form extends \moodleform {
    /** @return void */
    public function definition(): void {
        $mform = $this->_form;
        $targets = $this->_customdata['targets'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'stageid');
        $mform->setType('stageid', PARAM_INT);

        $mform->addElement('header', 'stageidentity', get_string('stagedesign', 'mod_decisionforge'));
        $mform->addElement('text', 'title', get_string('stagetitle', 'mod_decisionforge'), [
            'size' => 64,
            'maxlength' => 255,
        ]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addElement('select', 'stagekind', get_string('stagekind', 'mod_decisionforge'), [
            'briefing' => get_string('stagekindbriefing', 'mod_decisionforge'),
            'evidence' => get_string('stagekindevidence', 'mod_decisionforge'),
            'decision' => get_string('stagekinddecision', 'mod_decisionforge'),
            'prioritise' => get_string('stagekindprioritise', 'mod_decisionforge'),
            'reflection' => get_string('stagekindreflection', 'mod_decisionforge'),
        ]);
        $mform->setDefault('stagekind', 'decision');
        $mform->addHelpButton('stagekind', 'stagekind', 'mod_decisionforge');
        $mform->addElement('editor', 'body_editor', get_string('stagebody', 'mod_decisionforge'), null, [
            'maxfiles' => 0,
            'trusttext' => false,
        ]);
        $mform->setType('body_editor', PARAM_RAW);
        $mform->addRule('body_editor', null, 'required', null, 'client');
        $mform->addElement('editor', 'prompt_editor', get_string('stageprompt', 'mod_decisionforge'), null, [
            'maxfiles' => 0,
            'trusttext' => false,
        ]);
        $mform->setType('prompt_editor', PARAM_RAW);
        $mform->addElement('float', 'points', get_string('stagepoints', 'mod_decisionforge'));
        $mform->setDefault('points', 10);
        $mform->hideIf('points', 'stagekind', 'eq', 'briefing');
        $mform->hideIf('points', 'stagekind', 'eq', 'reflection');
        $mform->addElement('select', 'nextstageid', get_string('defaultnextstage', 'mod_decisionforge'), $targets);
        $mform->addElement('advcheckbox', 'active', get_string('stagepublished', 'mod_decisionforge'));
        $mform->setDefault('active', 1);
        $mform->addElement('advcheckbox', 'requireallcritical', get_string('requirecriticalevidence', 'mod_decisionforge'));
        $mform->setDefault('requireallcritical', 1);
        $mform->hideIf('requireallcritical', 'stagekind', 'neq', 'evidence');

        $mform->addElement('header', 'decisionarchitecture', get_string('decisionarchitecture', 'mod_decisionforge'));
        for ($i = 0; $i < 4; $i++) {
            $number = $i + 1;
            $elements = [
                $mform->createElement('text', 'text', get_string('choicetext', 'mod_decisionforge'), ['size' => 42]),
                $mform->createElement('text', 'quality', get_string('choicequality', 'mod_decisionforge'), [
                    'size' => 5,
                    'maxlength' => 6,
                    'inputmode' => 'decimal',
                ]),
                $mform->createElement('text', 'targetrank', get_string('targetrank', 'mod_decisionforge'), [
                    'size' => 3,
                    'maxlength' => 1,
                    'inputmode' => 'numeric',
                ]),
            ];
            $mform->addGroup($elements, 'choice_' . $i, get_string('simulationchoice', 'mod_decisionforge', $number), ' ', true);
            $mform->setType('choice_' . $i . '[text]', PARAM_TEXT);
            $mform->setType('choice_' . $i . '[quality]', PARAM_FLOAT);
            $mform->setType('choice_' . $i . '[targetrank]', PARAM_INT);
            $mform->setDefault('choice_' . $i . '[targetrank]', $number);
            $mform->hideIf('choice_' . $i, 'stagekind', 'in', ['briefing', 'evidence', 'reflection']);
            $mform->hideIf('choice_' . $i . '[quality]', 'stagekind', 'neq', 'decision');
            $mform->hideIf('choice_' . $i . '[targetrank]', 'stagekind', 'neq', 'prioritise');

            $mform->addElement('textarea', 'choice_consequence_' . $i,
                get_string('choiceconsequence', 'mod_decisionforge', $number), ['rows' => 2, 'cols' => 70]);
            $mform->setType('choice_consequence_' . $i, PARAM_TEXT);
            $mform->hideIf('choice_consequence_' . $i, 'stagekind', 'neq', 'decision');
            $mform->addElement('select', 'choice_next_' . $i,
                get_string('choicenextstage', 'mod_decisionforge', $number), $targets);
            $mform->hideIf('choice_next_' . $i, 'stagekind', 'neq', 'decision');

            $impacts = [];
            foreach (['safety', 'compliance', 'trust', 'resources'] as $metric) {
                $impacts[] = $mform->createElement('text', $metric, get_string('metric' . $metric, 'mod_decisionforge'), [
                    'size' => 5,
                    'maxlength' => 4,
                    'inputmode' => 'numeric',
                ]);
            }
            $mform->addGroup($impacts, 'choice_impacts_' . $i,
                get_string('choiceimpacts', 'mod_decisionforge', $number), ' ', true);
            foreach (['safety', 'compliance', 'trust', 'resources'] as $metric) {
                $mform->setType('choice_impacts_' . $i . '[' . $metric . ']', PARAM_INT);
            }
            $mform->hideIf('choice_impacts_' . $i, 'stagekind', 'neq', 'decision');
        }

        $mform->addElement('header', 'evidencearchitecture', get_string('evidencearchitecture', 'mod_decisionforge'));
        for ($i = 0; $i < 4; $i++) {
            $number = $i + 1;
            $elements = [
                $mform->createElement('text', 'title', get_string('evidencetitle', 'mod_decisionforge'), [
                    'size' => 34,
                    'maxlength' => 255,
                ]),
                $mform->createElement('select', 'kind', get_string('evidencekind', 'mod_decisionforge'), [
                    'document' => get_string('evidencekinddocument', 'mod_decisionforge'),
                    'message' => get_string('evidencekindmessage', 'mod_decisionforge'),
                    'record' => get_string('evidencekindrecord', 'mod_decisionforge'),
                    'alert' => get_string('evidencekindalert', 'mod_decisionforge'),
                ]),
                $mform->createElement('advcheckbox', 'critical', get_string('evidencecritical', 'mod_decisionforge')),
            ];
            $mform->addGroup($elements, 'evidence_' . $i, get_string('evidenceitem', 'mod_decisionforge', $number), ' ', true);
            $mform->setType('evidence_' . $i . '[title]', PARAM_TEXT);
            $mform->setType('evidence_' . $i . '[kind]', PARAM_ALPHANUMEXT);
            $mform->setType('evidence_' . $i . '[critical]', PARAM_BOOL);
            $mform->hideIf('evidence_' . $i, 'stagekind', 'neq', 'evidence');
            $mform->addElement('textarea', 'evidence_body_' . $i,
                get_string('evidencebody', 'mod_decisionforge', $number), ['rows' => 3, 'cols' => 70]);
            $mform->setType('evidence_body_' . $i, PARAM_TEXT);
            $mform->hideIf('evidence_body_' . $i, 'stagekind', 'neq', 'evidence');
        }
        $this->add_action_buttons(true, get_string('savestage', 'mod_decisionforge'));
    }

    /** @param array $data @param array $files @return array */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (isset($data['points']) && (!is_numeric($data['points']) || (float) $data['points'] < 0)) {
            $errors['points'] = get_string('nonnegativevalueerror', 'mod_decisionforge');
        }
        if (in_array($data['stagekind'], ['decision', 'prioritise'], true)) {
            $filled = 0;
            $ranks = [];
            $qualities = [];
            for ($i = 0; $i < 4; $i++) {
                if (trim($data['choice_' . $i]['text'] ?? '') !== '') {
                    $filled++;
                    $rank = $data['choice_' . $i]['targetrank'] ?? '';
                    $quality = $data['choice_' . $i]['quality'] ?? '';
                    if ($data['stagekind'] === 'prioritise'
                            && (!is_numeric($rank) || (int) $rank < 1 || (int) $rank > 4)) {
                        $errors['decisionarchitecture'] = get_string('rankrangeerror', 'mod_decisionforge');
                    }
                    if ($data['stagekind'] === 'decision'
                            && (!is_numeric($quality) || (float) $quality < 0 || (float) $quality > 100)) {
                        $errors['decisionarchitecture'] = get_string('qualityrangeerror', 'mod_decisionforge');
                    }
                    $ranks[] = (int) $rank;
                    $qualities[] = (float) $quality;
                    foreach (['safety', 'compliance', 'trust', 'resources'] as $metric) {
                        $impact = $data['choice_impacts_' . $i][$metric] ?? 0;
                        if ($impact !== ''
                                && (!is_numeric($impact) || (int) $impact < -100 || (int) $impact > 100)) {
                            $errors['decisionarchitecture'] = get_string('impactrangeerror', 'mod_decisionforge');
                        }
                    }
                }
            }
            if ($filled < 2) {
                $errors['decisionarchitecture'] = get_string('minimumsimulationchoices', 'mod_decisionforge');
            }
            if ($data['stagekind'] === 'prioritise') {
                sort($ranks);
                if ($ranks !== range(1, $filled)) {
                    $errors['decisionarchitecture'] = get_string('simulationrankvalidation', 'mod_decisionforge');
                }
            } elseif (!$qualities || max($qualities) < 100) {
                $errors['decisionarchitecture'] = get_string('bestsimulationchoicerequired', 'mod_decisionforge');
            }
        }
        if ($data['stagekind'] === 'evidence') {
            $filled = 0;
            for ($i = 0; $i < 4; $i++) {
                if (trim($data['evidence_' . $i]['title'] ?? '') !== ''
                        && trim($data['evidence_body_' . $i] ?? '') !== '') {
                    $filled++;
                }
            }
            if (!$filled) {
                $errors['evidencearchitecture'] = get_string('minimumevidence', 'mod_decisionforge');
            }
        }
        return $errors;
    }
}
