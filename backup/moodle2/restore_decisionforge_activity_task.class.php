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
 * Restore task for Decision Forge.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/restore_decisionforge_stepslib.php');

/**
 * Decision Forge restore task.
 */
class restore_decisionforge_activity_task extends restore_activity_task {
    /** @return void */
    protected function define_my_settings(): void {
    }

    /** @return void */
    protected function define_my_steps(): void {
        $this->add_step(new restore_decisionforge_activity_structure_step(
            'decisionforge_structure',
            'decisionforge.xml'
        ));
    }

    /**
     * Define content decoding rules.
     *
     * @return array
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('decisionforge', ['intro', 'instructions'], 'decisionforge'),
            new restore_decode_content('decisionforge_cases', ['scenario'], 'decisionforge_case'),
            new restore_decode_content('decisionforge_options', ['optiontext', 'consequence'], 'decisionforge_option'),
            new restore_decode_content('decisionforge_simstages', ['body', 'prompt'],
                'decisionforge_simulation_stage'),
            new restore_decode_content('decisionforge_simchoices', ['choicetext', 'consequence'],
                'decisionforge_simulation_choice'),
            new restore_decode_content('decisionforge_simevidence', ['body'], 'decisionforge_simulation_evidence'),
            new restore_decode_content('decisionforge_attempts', ['snapshotjson'], 'decisionforge_attempt'),
        ];
    }

    /**
     * Define URL decoding rules.
     *
     * @return array
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule('DECISIONFORGEVIEWBYID', '/mod/decisionforge/view.php?id=$1', 'course_module'),
            new restore_decode_rule('DECISIONFORGEINDEX', '/mod/decisionforge/index.php?id=$1', 'course'),
        ];
    }
}
