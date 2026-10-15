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

namespace mod_decisionforge\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Repair existing Gradebook items after an upgrade has completed.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repair_gradebook extends \core\task\adhoc_task {
    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskrepairgradebook', 'mod_decisionforge');
    }

    /**
     * Repair grade items, republish grades and recalculate completion.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/decisionforge/lib.php');

        $activities = $DB->get_records('decisionforge', null, 'id ASC');
        foreach ($activities as $activity) {
            mtrace('Repairing Decision Forge Gradebook item ' . $activity->id . '.');
            decisionforge_grade_item_update($activity);
            decisionforge_update_grades($activity);
            decisionforge_recalculate_completion($activity);
        }
    }
}
