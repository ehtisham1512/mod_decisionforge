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

use mod_decisionforge\local\editor_value;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating and editing one mini-case.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class case_form extends \moodleform {
    /**
     * Define form elements.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $customdata = $this->_customdata;
        $editoroptions = ['maxfiles' => 0, 'trusttext' => false];
        $fileoptions = [
            'subdirs' => 0,
            'maxbytes' => 8 * 1024 * 1024,
            'maxfiles' => 1,
            'accepted_types' => ['.jpg', '.jpeg', '.png', '.gif', '.webp'],
        ];

        $mform->addElement('header', 'caseheader', get_string('case', 'mod_decisionforge'));
        $mform->addElement('text', 'title', get_string('casetitle', 'mod_decisionforge'), ['size' => 64]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('editor', 'scenario_editor', get_string('scenario', 'mod_decisionforge'), null, $editoroptions);
        $mform->setType('scenario_editor', PARAM_RAW);
        $mform->addRule('scenario_editor', null, 'required', null, 'client');
        $mform->addHelpButton('scenario_editor', 'scenario', 'mod_decisionforge');

        $mform->addElement('filemanager', 'caseimage', get_string('caseimage', 'mod_decisionforge'), null, $fileoptions);
        $mform->addHelpButton('caseimage', 'caseimage', 'mod_decisionforge');

        $mform->addElement('text', 'questiontext', get_string('questiontext', 'mod_decisionforge'), ['size' => 64]);
        $mform->setType('questiontext', PARAM_TEXT);
        $mform->setDefault('questiontext', get_string('questiontext_default', 'mod_decisionforge'));
        $mform->addRule('questiontext', null, 'required', null, 'client');
        $mform->addRule('questiontext', get_string('maximumchars', '', 500), 'maxlength', 500, 'client');

        $mform->addElement('text', 'points', get_string('casepoints', 'mod_decisionforge'), ['size' => 8]);
        $mform->setType('points', PARAM_FLOAT);
        $mform->setDefault('points', 10);
        $mform->addRule('points', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'active', get_string('caseactive', 'mod_decisionforge'));
        $mform->setDefault('active', 1);

        $mform->addElement('header', 'actionsheader', get_string('actions', 'mod_decisionforge'));
        $scores = [
            100 => '100% — ' . get_string('bestdecision', 'mod_decisionforge'),
            75 => '75% — ' . get_string('gooddecision', 'mod_decisionforge'),
            50 => '50% — ' . get_string('riskydecision', 'mod_decisionforge'),
            25 => '25% — ' . get_string('riskydecision', 'mod_decisionforge'),
            0 => '0% — ' . get_string('unsafedecision', 'mod_decisionforge'),
        ];
        $defaultscore = [100, 50, 25, 0];
        for ($i = 1; $i <= 4; $i++) {
            $mform->addElement('html', '<div class="decisionforge-form-option">');
            $mform->addElement('static', 'actionheading' . $i, '', get_string('actionnumber', 'mod_decisionforge', $i));
            $mform->addElement(
                'editor',
                'option' . $i . '_editor',
                get_string('actiontext', 'mod_decisionforge'),
                null,
                $editoroptions
            );
            $mform->setType('option' . $i . '_editor', PARAM_RAW);
            $mform->addElement('select', 'score' . $i, get_string('actionscore', 'mod_decisionforge'), $scores);
            $mform->setDefault('score' . $i, $defaultscore[$i - 1]);
            $mform->addHelpButton('score' . $i, 'actionscore', 'mod_decisionforge');
            $mform->addElement(
                'editor',
                'consequence' . $i . '_editor',
                get_string('consequence', 'mod_decisionforge'),
                null,
                $editoroptions
            );
            $mform->setType('consequence' . $i . '_editor', PARAM_RAW);
            $mform->addHelpButton('consequence' . $i . '_editor', 'consequence', 'mod_decisionforge');
            $mform->addElement('html', '</div>');
        }

        $mform->addElement('hidden', 'caseid', $customdata['caseid'] ?? 0);
        $mform->setType('caseid', PARAM_INT);
        $mform->addElement('hidden', 'returnurl', $customdata['returnurl']);
        $mform->setType('returnurl', PARAM_LOCALURL);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validate case and actions.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $actioncount = 0;
        $hasbest = false;
        for ($i = 1; $i <= 4; $i++) {
            $editor = $data['option' . $i . '_editor'] ?? '';
            if (editor_value::has_content($editor)) {
                $actioncount++;
                if ((float) ($data['score' . $i] ?? -1) === 100.0) {
                    $hasbest = true;
                }
            }
        }
        if ($actioncount < 2) {
            $errors['option1_editor'] = get_string('minimumactions', 'mod_decisionforge');
        }
        if (!$hasbest) {
            $errors['score1'] = get_string('bestdecisionrequired', 'mod_decisionforge');
        }
        if ((float) ($data['points'] ?? 0) <= 0) {
            $errors['points'] = get_string('invalidnum', 'error');
        }
        if ((float) ($data['points'] ?? 0) > 10000) {
            $errors['points'] = get_string('casepointslimit', 'mod_decisionforge');
        }
        if (\core_text::strlen(trim($data['title'] ?? '')) > 255) {
            $errors['title'] = get_string('maximumchars', '', 255);
        }
        if (trim($data['title'] ?? '') === '') {
            $errors['title'] = get_string('required');
        }
        if (\core_text::strlen(trim($data['questiontext'] ?? '')) > 500) {
            $errors['questiontext'] = get_string('maximumchars', '', 500);
        }
        if (trim($data['questiontext'] ?? '') === '') {
            $errors['questiontext'] = get_string('required');
        }
        if (!editor_value::has_content($data['scenario_editor'] ?? '')) {
            $errors['scenario_editor'] = get_string('required');
        }
        return $errors;
    }
}
