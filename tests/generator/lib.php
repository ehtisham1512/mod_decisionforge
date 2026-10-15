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
 * Data generator for mod_decisionforge.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Decision Forge generator.
 */
class mod_decisionforge_generator extends testing_module_generator {
    /**
     * Fill required defaults.
     *
     * @param stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null): stdClass {
        $record = (object) (array) $record;
        $record->instructions = $record->instructions ?? '';
        $record->instructionsformat = $record->instructionsformat ?? FORMAT_HTML;
        $record->timeopen = $record->timeopen ?? 0;
        $record->timeclose = $record->timeclose ?? 0;
        $record->timelimit = $record->timelimit ?? 0;
        $record->attemptsallowed = $record->attemptsallowed ?? 0;
        $record->grade = $record->grade ?? 100;
        $record->gradepass = $record->gradepass ?? 0;
        $record->grademethod = $record->grademethod ?? 'highest';
        $record->feedbackmode = $record->feedbackmode ?? 'immediate';
        $record->shufflecases = $record->shufflecases ?? 0;
        $record->shuffleanswers = $record->shuffleanswers ?? 1;
        $record->showprogress = $record->showprogress ?? 1;
        $record->requirejustification = $record->requirejustification ?? 0;
        $record->requireconfidence = $record->requireconfidence ?? 0;
        $record->experiencemode = $record->experiencemode ?? 'quick';
        $record->missionlabel = $record->missionlabel ?? 'Live decision simulation';
        $record->initialsafety = $record->initialsafety ?? 72;
        $record->initialcompliance = $record->initialcompliance ?? 68;
        $record->initialtrust = $record->initialtrust ?? 74;
        $record->initialresources = $record->initialresources ?? 80;
        return parent::create_instance($record, $options);
    }
}
