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
 * Backup structure for Decision Forge.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Backup structure step.
 */
class backup_decisionforge_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        $activity = new backup_nested_element('decisionforge', ['id'], [
            'name', 'intro', 'introformat', 'instructions', 'instructionsformat',
            'timeopen', 'timeclose', 'timelimit', 'attemptsallowed', 'grade', 'gradepass',
            'grademethod', 'feedbackmode', 'shufflecases', 'shuffleanswers', 'showprogress',
            'requirejustification', 'requireconfidence', 'experiencemode', 'missionlabel',
            'initialsafety', 'initialcompliance', 'initialtrust', 'initialresources',
            'timecreated', 'timemodified',
        ]);
        $cases = new backup_nested_element('cases');
        $case = new backup_nested_element('case', ['id'], [
            'title', 'scenario', 'scenarioformat', 'questiontext', 'points', 'position',
            'active', 'archived', 'timecreated', 'timemodified',
        ]);
        $options = new backup_nested_element('options');
        $option = new backup_nested_element('option', ['id'], [
            'optiontext', 'optiontextformat', 'score', 'consequence', 'consequenceformat', 'position',
        ]);
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'userid', 'attemptnumber', 'state', 'mode', 'currentcase', 'currentstageid',
            'snapshotjson', 'pathjson', 'rawscore', 'possiblepoints', 'finalgrade', 'safety',
            'compliance', 'trust', 'resources', 'outcomecode', 'timestart', 'timefinish', 'timemodified',
        ]);
        $attemptcases = new backup_nested_element('attempt_cases');
        $attemptcase = new backup_nested_element('attempt_case', ['id'], [
            'caseid', 'caseposition', 'optionorder',
        ]);
        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], [
            'caseid', 'optionid', 'justification', 'confidence', 'score', 'timeanswered',
        ]);
        $stages = new backup_nested_element('simulation_stages');
        $stage = new backup_nested_element('simulation_stage', ['id'], [
            'title', 'stagekind', 'body', 'bodyformat', 'prompt', 'promptformat', 'points',
            'position', 'nextstageid', 'active', 'requireallcritical', 'timecreated', 'timemodified',
        ]);
        $simchoices = new backup_nested_element('simulation_choices');
        $simchoice = new backup_nested_element('simulation_choice', ['id'], [
            'choicetext', 'choicetextformat', 'quality', 'consequence', 'consequenceformat',
            'nextstageid', 'safetyimpact', 'complianceimpact', 'trustimpact', 'resourcesimpact',
            'targetrank', 'position',
        ]);
        $evidenceitems = new backup_nested_element('simulation_evidence');
        $evidence = new backup_nested_element('evidence', ['id'], [
            'title', 'body', 'bodyformat', 'evidencekind', 'critical', 'position',
        ]);
        $simresponses = new backup_nested_element('simulation_responses');
        $simresponse = new backup_nested_element('simulation_response', ['id'], [
            'stageid', 'responsesequence', 'stagekind', 'choiceid', 'response', 'rankingjson',
            'openedjson', 'score', 'maxscore', 'consequence', 'safety', 'compliance', 'trust',
            'resources', 'timeanswered',
        ]);

        $activity->add_child($cases);
        $cases->add_child($case);
        $case->add_child($options);
        $options->add_child($option);
        $activity->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($attemptcases);
        $attemptcases->add_child($attemptcase);
        $attempt->add_child($responses);
        $responses->add_child($response);
        $activity->add_child($stages);
        $stages->add_child($stage);
        $stage->add_child($simchoices);
        $simchoices->add_child($simchoice);
        $stage->add_child($evidenceitems);
        $evidenceitems->add_child($evidence);
        $attempt->add_child($simresponses);
        $simresponses->add_child($simresponse);

        $activity->set_source_table('decisionforge', ['id' => backup::VAR_ACTIVITYID]);
        $case->set_source_table('decisionforge_cases', ['decisionforgeid' => backup::VAR_PARENTID], 'position ASC');
        $option->set_source_table('decisionforge_options', ['caseid' => backup::VAR_PARENTID], 'position ASC');
        $stage->set_source_table('decisionforge_simstages', ['decisionforgeid' => backup::VAR_PARENTID], 'position ASC');
        $simchoice->set_source_table('decisionforge_simchoices', ['stageid' => backup::VAR_PARENTID], 'position ASC');
        $evidence->set_source_table('decisionforge_simevidence', ['stageid' => backup::VAR_PARENTID], 'position ASC');

        if ($userinfo) {
            $attempt->set_source_table('decisionforge_attempts', ['decisionforgeid' => backup::VAR_PARENTID], 'id ASC');
            $attemptcase->set_source_table(
                'decisionforge_attempt_cases',
                ['attemptid' => backup::VAR_PARENTID],
                'caseposition ASC'
            );
            $response->set_source_table('decisionforge_responses', ['attemptid' => backup::VAR_PARENTID], 'id ASC');
            $simresponse->set_source_table('decisionforge_simresponses',
                ['attemptid' => backup::VAR_PARENTID], 'responsesequence ASC');
            $attempt->annotate_ids('user', 'userid');
        }

        $activity->annotate_files('mod_decisionforge', 'intro', null);
        $case->annotate_files('mod_decisionforge', 'caseimage', 'id');

        return $this->prepare_activity_structure($activity);
    }
}
