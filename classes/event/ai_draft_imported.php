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
 * AI draft imported event.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_draft_imported extends \core\event\base {
    /** @return void */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'decisionforge_aidrafts';
    }

    /** @return string */
    public static function get_name(): string {
        return get_string('eventaidraftimported', 'mod_decisionforge');
    }

    /** @return string */
    public function get_description(): string {
        return "The user with id '{$this->userid}' imported AI draft '{$this->objectid}' "
            . "into Decision Forge activity '{$this->contextinstanceid}'.";
    }

    /** @return \moodle_url */
    public function get_url(): \moodle_url {
        $page = ($this->other['drafttype'] ?? 'simulation') === 'quick'
            ? '/mod/decisionforge/manage.php' : '/mod/decisionforge/simulationstudio.php';
        return new \moodle_url($page, ['id' => $this->contextinstanceid]);
    }
}
