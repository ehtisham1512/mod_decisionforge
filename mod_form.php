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
 * Activity settings form.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Decision Forge module settings form.
 */
class mod_decisionforge_mod_form extends moodleform_mod {
    /**
     * Define the form.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('decisionforgename', 'mod_decisionforge'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $editoroptions = ['maxfiles' => 0, 'trusttext' => false];
        $mform->addElement('editor', 'instructions_editor', get_string('instructions', 'mod_decisionforge'), null, $editoroptions);
        $mform->setType('instructions_editor', PARAM_RAW);
        $mform->addHelpButton('instructions_editor', 'instructions', 'mod_decisionforge');

        $mform->addElement('header', 'experiencehdr', get_string('experiencedesign', 'mod_decisionforge'));
        $mform->addElement('select', 'experiencemode', get_string('experiencemode', 'mod_decisionforge'), [
            'simulation' => get_string('modeimmersive', 'mod_decisionforge'),
            'quick' => get_string('modequick', 'mod_decisionforge'),
        ]);
        $mform->setDefault('experiencemode', 'simulation');
        $mform->addHelpButton('experiencemode', 'experiencemode', 'mod_decisionforge');
        $mform->addElement('text', 'missionlabel', get_string('missionlabel', 'mod_decisionforge'), [
            'size' => 64,
            'maxlength' => 255,
        ]);
        $mform->setType('missionlabel', PARAM_TEXT);
        $mform->setDefault('missionlabel', get_string('missionlabeldefault', 'mod_decisionforge'));
        $mform->addRule('missionlabel', null, 'required', null, 'client');
        $mform->hideIf('missionlabel', 'experiencemode', 'neq', 'simulation');
        foreach (['safety' => 72, 'compliance' => 68, 'trust' => 74, 'resources' => 80] as $metric => $default) {
            $name = 'initial' . $metric;
            // Moodle does not register a QuickForm element named "number". Using it
            // makes PEAR raise a misleading getStaticProperty() exception on PHP 8.
            // A text field with an input hint is portable across supported Moodle
            // versions; the value is constrained again in validation() below.
            $mform->addElement('text', $name, get_string('initialmetric', 'mod_decisionforge',
                get_string('metric' . $metric, 'mod_decisionforge')), [
                    'size' => 5,
                    'maxlength' => 3,
                    'inputmode' => 'numeric',
                ]);
            $mform->setType($name, PARAM_INT);
            $mform->setDefault($name, $default);
            $mform->hideIf($name, 'experiencemode', 'neq', 'simulation');
        }

        $mform->addElement('header', 'availabilityhdr', get_string('availability', 'mod_decisionforge'));
        $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'mod_decisionforge'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'mod_decisionforge'), ['optional' => true]);
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'mod_decisionforge'), ['optional' => true]);
        $mform->setDefault('timelimit', 0);

        $attemptoptions = [0 => get_string('unlimited', 'mod_decisionforge')];
        for ($i = 1; $i <= 10; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('select', 'attemptsallowed', get_string('attemptsallowed', 'mod_decisionforge'), $attemptoptions);
        $mform->setDefault('attemptsallowed', 0);
        $mform->addHelpButton('attemptsallowed', 'attemptsallowed', 'mod_decisionforge');

        $mform->addElement('header', 'assessmenthdr', get_string('assessmentsettings', 'mod_decisionforge'));
        $mform->addElement('select', 'feedbackmode', get_string('feedbackmode', 'mod_decisionforge'), [
            'immediate' => get_string('feedbackimmediate', 'mod_decisionforge'),
            'deferred' => get_string('feedbackdeferred', 'mod_decisionforge'),
        ]);
        $mform->setDefault('feedbackmode', 'immediate');
        $mform->hideIf('feedbackmode', 'experiencemode', 'neq', 'quick');

        $mform->addElement('advcheckbox', 'shufflecases', get_string('shufflecases', 'mod_decisionforge'));
        $mform->setDefault('shufflecases', 0);
        $mform->hideIf('shufflecases', 'experiencemode', 'neq', 'quick');
        $mform->addElement('advcheckbox', 'shuffleanswers', get_string('shuffleanswers', 'mod_decisionforge'));
        $mform->setDefault('shuffleanswers', 1);
        $mform->hideIf('shuffleanswers', 'experiencemode', 'neq', 'quick');
        $mform->addElement('advcheckbox', 'showprogress', get_string('showprogress', 'mod_decisionforge'));
        $mform->setDefault('showprogress', 1);
        $mform->hideIf('showprogress', 'experiencemode', 'neq', 'quick');
        $mform->addElement('advcheckbox', 'requirejustification', get_string('requirejustification', 'mod_decisionforge'));
        $mform->setDefault('requirejustification', 0);
        $mform->hideIf('requirejustification', 'experiencemode', 'neq', 'quick');
        $mform->addElement('advcheckbox', 'requireconfidence', get_string('requireconfidence', 'mod_decisionforge'));
        $mform->setDefault('requireconfidence', 0);
        $mform->hideIf('requireconfidence', 'experiencemode', 'neq', 'quick');

        // Let Moodle add the grade item category, grade-to-pass and completion wiring.
        // Decision Forge only supports numeric grades, so replace the core points/scale
        // selector with a locale-aware numeric maximum-grade field.
        $this->standard_grading_coursemodule_elements();
        if ($mform->elementExists('gradepass')) {
            $mform->setDefault('gradepass', 0);
        }
        if ($mform->elementExists('grade')) {
            $mform->removeElement('grade');
        }
        $gradeelement = $mform->createElement(
            'float',
            'grade',
            get_string('maximumgrade', 'mod_decisionforge'),
            ['size' => 8]
        );
        if ($mform->elementExists('gradecat')) {
            $mform->insertElementBefore($gradeelement, 'gradecat');
        } else {
            $mform->addElement($gradeelement);
        }
        $mform->setDefault('grade', 100);
        $mform->addRule('grade', null, 'required', null, 'client');
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'mod_decisionforge'), [
            'highest' => get_string('highestgrade', 'mod_decisionforge'),
            'average' => get_string('averagegrade', 'mod_decisionforge'),
            'last' => get_string('lastgrade', 'mod_decisionforge'),
        ]);
        $mform->setDefault('grademethod', 'highest');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Prepare editor defaults.
     *
     * @param array $defaultvalues
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        if (isset($defaultvalues['instructions'])) {
            $defaultvalues['instructions_editor'] = [
                'text' => $defaultvalues['instructions'],
                'format' => $defaultvalues['instructionsformat'] ?? FORMAT_HTML,
            ];
        }
    }

    /**
     * Validate settings.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($data['timeopen']) && !empty($data['timeclose']) && $data['timeclose'] <= $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'mod_decisionforge');
        }
        if ((float) $data['grade'] <= 0) {
            $errors['grade'] = get_string('invalidgrade', 'error');
        }
        if ((float) $data['grade'] > 100000) {
            $errors['grade'] = get_string('grademaxlimit', 'mod_decisionforge');
        }
        $gradepass = (float) ($data['gradepass'] ?? 0);
        if ($gradepass < 0 || $gradepass > (float) $data['grade']) {
            $errors['gradepass'] = get_string('invalidgrade', 'error');
        }
        if (($data['experiencemode'] ?? 'quick') === 'simulation') {
            foreach (['initialsafety', 'initialcompliance', 'initialtrust', 'initialresources'] as $metric) {
                if ((int) ($data[$metric] ?? -1) < 0 || (int) ($data[$metric] ?? -1) > 100) {
                    $errors[$metric] = get_string('metricrangeerror', 'mod_decisionforge');
                }
            }
        }
        return $errors;
    }
}
