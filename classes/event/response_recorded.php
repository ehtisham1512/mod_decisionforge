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

namespace mod_decisionforge\event;

/**
 * Response recorded event.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_recorded extends \core\event\base {
    /** @return void */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'decisionforge_responses';
    }

    /** @return string */
    public static function get_name(): string {
        return get_string('eventresponserecorded', 'mod_decisionforge');
    }

    /** @return string */
    public function get_description(): string {
        return "The user with id '{$this->userid}' recorded response '{$this->objectid}' "
            . "in Decision Forge activity '{$this->contextinstanceid}'.";
    }

    /** @return \moodle_url */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/decisionforge/view.php', ['id' => $this->contextinstanceid]);
    }
}
