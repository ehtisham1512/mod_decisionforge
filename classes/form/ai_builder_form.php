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
 * Guided AI simulation generation form.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_builder_form extends \moodleform {
    /** @return void */
    public function definition(): void {
        $mform = $this->_form;
        $providers = $this->_customdata['providers'] ?? [];
        $defaultprovider = $this->_customdata['defaultprovider'] ?? 'openai';

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('header', 'aibriefheader', get_string('aibrief', 'mod_decisionforge'));
        $mform->addElement('select', 'provider', get_string('aiprovider', 'mod_decisionforge'), $providers);
        $mform->setDefault('provider', $defaultprovider);
        $mform->addElement('textarea', 'prompt', get_string('aiprompt', 'mod_decisionforge'), [
            'rows' => 8,
            'maxlength' => 4000,
            'placeholder' => get_string('aipromptplaceholder', 'mod_decisionforge'),
        ]);
        $mform->setType('prompt', PARAM_TEXT);
        $mform->addRule('prompt', null, 'required', null, 'client');
        $mform->addHelpButton('prompt', 'aiprompt', 'mod_decisionforge');
        $mform->addElement('select', 'language', get_string('ailanguage', 'mod_decisionforge'), [
            'site' => get_string('ailanguagesite', 'mod_decisionforge'),
            'en' => get_string('languageenglish', 'mod_decisionforge'),
            'es' => get_string('languagespanish', 'mod_decisionforge'),
            'fr' => get_string('languagefrench', 'mod_decisionforge'),
            'ru' => get_string('languagerussian', 'mod_decisionforge'),
            'de' => get_string('languagegerman', 'mod_decisionforge'),
            'it' => get_string('languageitalian', 'mod_decisionforge'),
            'pt' => get_string('languageportuguese', 'mod_decisionforge'),
            'ar' => get_string('languagearabic', 'mod_decisionforge'),
            'zh_cn' => get_string('languagechinese', 'mod_decisionforge'),
            'ja' => get_string('languagejapanese', 'mod_decisionforge'),
            'custom' => get_string('languagecustom', 'mod_decisionforge'),
        ]);
        $mform->setDefault('language', 'site');
        $mform->addElement('text', 'customlanguage', get_string('customlanguage', 'mod_decisionforge'), [
            'size' => 30,
            'maxlength' => 50,
        ]);
        $mform->setType('customlanguage', PARAM_TEXT);
        $mform->hideIf('customlanguage', 'language', 'neq', 'custom');

        $mform->addElement('header', 'aistagesheader', get_string('aistages', 'mod_decisionforge'));
        foreach (['briefing', 'evidence', 'prioritise', 'decision', 'reflection'] as $kind) {
            $field = 'include' . $kind;
            $mform->addElement('advcheckbox', $field, get_string('aiinclude' . $kind, 'mod_decisionforge'));
            $mform->setDefault($field, 1);
        }
        $stageoptions = [];
        for ($count = 3; $count <= 10; $count++) {
            $stageoptions[$count] = $count;
        }
        $mform->addElement('select', 'stagecount', get_string('aistagecount', 'mod_decisionforge'), $stageoptions);
        $mform->setDefault('stagecount', 7);
        $mform->addElement('select', 'choicecount', get_string('aichoicecount', 'mod_decisionforge'), [
            2 => 2,
            3 => 3,
            4 => 4,
        ]);
        $mform->setDefault('choicecount', 4);
        $mform->addElement('select', 'evidencecount', get_string('aievidencecount', 'mod_decisionforge'), [
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
        ]);
        $mform->setDefault('evidencecount', 4);

        $mform->addElement('header', 'aicontrolsheader', get_string('aigenerationcontrols', 'mod_decisionforge'));
        $mform->addElement('select', 'difficulty', get_string('aidifficulty', 'mod_decisionforge'), [
            'foundation' => get_string('aidifficultyfoundation', 'mod_decisionforge'),
            'intermediate' => get_string('aidifficultyintermediate', 'mod_decisionforge'),
            'advanced' => get_string('aidifficultyadvanced', 'mod_decisionforge'),
        ]);
        $mform->setDefault('difficulty', 'intermediate');
        foreach (['branching', 'criticalevidence', 'indicators', 'unsafechoices'] as $control) {
            $mform->addElement('advcheckbox', $control, get_string('ai' . $control, 'mod_decisionforge'));
            $mform->setDefault($control, 1);
        }
        $mform->addElement('static', 'aireviewnotice', '', get_string('aireviewnotice', 'mod_decisionforge'));
        $this->add_action_buttons(true, get_string('aigenerate', 'mod_decisionforge'));
    }

    /** @param array $data @param array $files @return array */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $providers = $this->_customdata['providers'] ?? [];
        if (!isset($providers[$data['provider'] ?? ''])) {
            $errors['provider'] = get_string('aiproviderinvalid', 'mod_decisionforge');
        }
        $promptlength = \core_text::strlen(trim($data['prompt'] ?? ''));
        if ($promptlength < 30 || $promptlength > 4000) {
            $errors['prompt'] = get_string('aipromptlength', 'mod_decisionforge');
        }
        $selected = [];
        foreach (['briefing', 'evidence', 'prioritise', 'decision', 'reflection'] as $kind) {
            if (!empty($data['include' . $kind])) {
                $selected[] = $kind;
            }
        }
        if (count($selected) < 2) {
            $errors['aistagesheader'] = get_string('aiminimumstagetypes', 'mod_decisionforge');
        }
        if ((int) ($data['stagecount'] ?? 0) < count($selected)) {
            $errors['stagecount'] = get_string('aistagecounttoosmall', 'mod_decisionforge', count($selected));
        }
        if (!array_intersect($selected, ['evidence', 'prioritise', 'decision'])) {
            $errors['aistagesheader'] = get_string('aiscoredstagerequired', 'mod_decisionforge');
        }
        if ((!empty($data['branching']) || !empty($data['unsafechoices'])) && !in_array('decision', $selected, true)) {
            $errors['includedecision'] = get_string('aidecisioncontrolrequired', 'mod_decisionforge');
        }
        if (!empty($data['criticalevidence']) && !in_array('evidence', $selected, true)) {
            $errors['includeevidence'] = get_string('aievidencecontrolrequired', 'mod_decisionforge');
        }
        if (($data['language'] ?? '') === 'custom' && trim($data['customlanguage'] ?? '') === '') {
            $errors['customlanguage'] = get_string('required');
        }
        return $errors;
    }
}
