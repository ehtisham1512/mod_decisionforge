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
 * Restore structure for Decision Forge.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore structure step.
 */
class restore_decisionforge_activity_structure_step extends restore_activity_structure_step {
    /** @var array Pending simulation stage routes keyed by new stage ID. */
    private $pendingstageroutes = [];

    /** @var array Pending choice routes keyed by new choice ID. */
    private $pendingchoiceroutes = [];

    /**
     * Restore paths.
     *
     * @return array
     */
    protected function define_structure(): array {
        $paths = [
            new restore_path_element('decisionforge', '/activity/decisionforge'),
            new restore_path_element('decisionforge_case', '/activity/decisionforge/cases/case'),
            new restore_path_element('decisionforge_option', '/activity/decisionforge/cases/case/options/option'),
            new restore_path_element('decisionforge_simulation_stage',
                '/activity/decisionforge/simulation_stages/simulation_stage'),
            new restore_path_element('decisionforge_simulation_choice',
                '/activity/decisionforge/simulation_stages/simulation_stage/simulation_choices/simulation_choice'),
            new restore_path_element('decisionforge_simulation_evidence',
                '/activity/decisionforge/simulation_stages/simulation_stage/simulation_evidence/evidence'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('decisionforge_attempt', '/activity/decisionforge/attempts/attempt');
            $paths[] = new restore_path_element(
                'decisionforge_attempt_case',
                '/activity/decisionforge/attempts/attempt/attempt_cases/attempt_case'
            );
            $paths[] = new restore_path_element(
                'decisionforge_response',
                '/activity/decisionforge/attempts/attempt/responses/response'
            );
            $paths[] = new restore_path_element(
                'decisionforge_simulation_response',
                '/activity/decisionforge/attempts/attempt/simulation_responses/simulation_response'
            );
        }
        return $this->prepare_activity_structure($paths);
    }

    /** @param array $data @return void */
    protected function process_decisionforge($data): void {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $newid = $DB->insert_record('decisionforge', $data);
        $this->apply_activity_instance($newid);
        $this->set_mapping('decisionforge', $oldid, $newid, true);
    }

    /** @param array $data @return void */
    protected function process_decisionforge_case($data): void {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->decisionforgeid = $this->get_new_parentid('decisionforge');
        $newid = $DB->insert_record('decisionforge_cases', $data);
        $this->set_mapping('decisionforge_case', $oldid, $newid, true);
    }

    /** @param array $data @return void */
    protected function process_decisionforge_option($data): void {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->caseid = $this->get_new_parentid('decisionforge_case');
        $newid = $DB->insert_record('decisionforge_options', $data);
        $this->set_mapping('decisionforge_option', $oldid, $newid);
    }

    /** @param array $data @return void */
    protected function process_decisionforge_simulation_stage($data): void {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $oldnext = (int) $data->nextstageid;
        $data->decisionforgeid = $this->get_new_parentid('decisionforge');
        $data->nextstageid = $oldnext === -1 ? -1 : 0;
        $newid = $DB->insert_record('decisionforge_simstages', $data);
        $this->pendingstageroutes[$newid] = $oldnext;
        $this->set_mapping('decisionforge_simulation_stage', $oldid, $newid);
    }

    /** @param array $data @return void */
    protected function process_decisionforge_simulation_choice($data): void {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $oldnext = (int) $data->nextstageid;
        $data->stageid = $this->get_new_parentid('decisionforge_simulation_stage');
        $data->nextstageid = $oldnext === -1 ? -1 : 0;
        $newid = $DB->insert_record('decisionforge_simchoices', $data);
        $this->pendingchoiceroutes[$newid] = $oldnext;
        $this->set_mapping('decisionforge_simulation_choice', $oldid, $newid);
    }

    /** @param array $data @return void */
    protected function process_decisionforge_simulation_evidence($data): void {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->stageid = $this->get_new_parentid('decisionforge_simulation_stage');
        $newid = $DB->insert_record('decisionforge_simevidence', $data);
        $this->set_mapping('decisionforge_simulation_evidence', $oldid, $newid);
    }

    /** @param array $data @return void */
    protected function process_decisionforge_attempt($data): void {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->decisionforgeid = $this->get_new_parentid('decisionforge');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        if (!$data->userid) {
            return;
        }
        $newid = $DB->insert_record('decisionforge_attempts', $data);
        $this->set_mapping('decisionforge_attempt', $oldid, $newid);
    }

    /** @param array $data @return void */
    protected function process_decisionforge_attempt_case($data): void {
        global $DB;
        $data = (object) $data;
        $data->attemptid = $this->get_new_parentid('decisionforge_attempt');
        $data->caseid = $this->get_mappingid('decisionforge_case', $data->caseid);
        if (!$data->attemptid || !$data->caseid) {
            return;
        }
        $optionids = array_filter(array_map('intval', explode(',', $data->optionorder)));
        $data->optionorder = implode(',', array_filter(array_map(
            fn($optionid) => $this->get_mappingid('decisionforge_option', $optionid, 0),
            $optionids
        )));
        $DB->insert_record('decisionforge_attempt_cases', $data);
    }

    /** @param array $data @return void */
    protected function process_decisionforge_response($data): void {
        global $DB;
        $data = (object) $data;
        $data->attemptid = $this->get_new_parentid('decisionforge_attempt');
        $data->caseid = $this->get_mappingid('decisionforge_case', $data->caseid);
        $data->optionid = $this->get_mappingid('decisionforge_option', $data->optionid);
        if (!$data->attemptid || !$data->caseid || !$data->optionid) {
            return;
        }
        $DB->insert_record('decisionforge_responses', $data);
    }

    /** @param array $data @return void */
    protected function process_decisionforge_simulation_response($data): void {
        global $DB;
        $data = (object) $data;
        $data->attemptid = $this->get_new_parentid('decisionforge_attempt');
        if ($data->attemptid) {
            $DB->insert_record('decisionforge_simresponses', $data);
        }
    }

    /** @return void */
    protected function after_execute(): void {
        global $DB;
        foreach ($this->pendingstageroutes as $newid => $oldnext) {
            if ($oldnext > 0) {
                $DB->set_field('decisionforge_simstages', 'nextstageid',
                    $this->get_mappingid('decisionforge_simulation_stage', $oldnext, 0), ['id' => $newid]);
            }
        }
        foreach ($this->pendingchoiceroutes as $newid => $oldnext) {
            if ($oldnext > 0) {
                $DB->set_field('decisionforge_simchoices', 'nextstageid',
                    $this->get_mappingid('decisionforge_simulation_stage', $oldnext, 0), ['id' => $newid]);
            }
        }
        $this->add_related_files('mod_decisionforge', 'intro', null);
        $this->add_related_files('mod_decisionforge', 'caseimage', 'decisionforge_case');
    }
}
