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
 * Guided AI Quick Assessment generation form.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quick_ai_builder_form extends \moodleform {
    /** @return void */
    public function definition(): void {
        $mform = $this->_form;
        $providers = $this->_customdata['providers'] ?? [];

        $mform->addElement('header', 'aibriefheader', get_string('aiquickbrief', 'mod_decisionforge'));
        $mform->addElement('select', 'provider', get_string('aiprovider', 'mod_decisionforge'), $providers);
        $mform->setDefault('provider', $this->_customdata['defaultprovider'] ?? 'openai');
        $mform->addElement('textarea', 'prompt', get_string('aiquickprompt', 'mod_decisionforge'), [
            'rows' => 7,
            'maxlength' => 4000,
            'placeholder' => get_string('aiquickpromptplaceholder', 'mod_decisionforge'),
        ]);
        $mform->setType('prompt', PARAM_TEXT);
        $mform->addRule('prompt', null, 'required', null, 'client');
        $mform->addHelpButton('prompt', 'aiquickprompt', 'mod_decisionforge');

        $mform->addElement('header', 'aiquickcontrols', get_string('aigenerationcontrols', 'mod_decisionforge'));
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

        $casecounts = [];
        for ($count = 1; $count <= 10; $count++) {
            $casecounts[$count] = $count;
        }
        $mform->addElement('select', 'casecount', get_string('aiquickcasecount', 'mod_decisionforge'), $casecounts);
        $mform->setDefault('casecount', 5);
        $mform->addElement('select', 'choicecount', get_string('aiquickchoicecount', 'mod_decisionforge'), [
            2 => 2,
            3 => 3,
            4 => 4,
        ]);
        $mform->setDefault('choicecount', 4);
        $mform->addElement('select', 'points', get_string('aiquickpoints', 'mod_decisionforge'), [
            5 => 5,
            10 => 10,
            20 => 20,
            25 => 25,
        ]);
        $mform->setDefault('points', 10);
        $mform->addElement('select', 'difficulty', get_string('aidifficulty', 'mod_decisionforge'), [
            'foundation' => get_string('aidifficultyfoundation', 'mod_decisionforge'),
            'intermediate' => get_string('aidifficultyintermediate', 'mod_decisionforge'),
            'advanced' => get_string('aidifficultyadvanced', 'mod_decisionforge'),
        ]);
        $mform->setDefault('difficulty', 'intermediate');
        $mform->addElement('advcheckbox', 'unsafechoices', get_string('aiquickunsafechoices', 'mod_decisionforge'));
        $mform->setDefault('unsafechoices', 1);
        $mform->addElement('advcheckbox', 'detailedfeedback', get_string('aiquickdetailedfeedback', 'mod_decisionforge'));
        $mform->setDefault('detailedfeedback', 1);
        $mform->addElement('static', 'aireviewnotice', '', get_string('aiquickreviewnotice', 'mod_decisionforge'));
        $this->add_action_buttons(true, get_string('aiquickgenerate', 'mod_decisionforge'));
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
        if (($data['language'] ?? '') === 'custom' && trim($data['customlanguage'] ?? '') === '') {
            $errors['customlanguage'] = get_string('required');
        }
        return $errors;
    }
}
