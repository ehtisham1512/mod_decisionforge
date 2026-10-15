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
 * Backup task for Decision Forge.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/backup_decisionforge_stepslib.php');

/**
 * Decision Forge backup task.
 */
class backup_decisionforge_activity_task extends backup_activity_task {
    /** @return void */
    protected function define_my_settings(): void {
    }

    /** @return void */
    protected function define_my_steps(): void {
        $this->add_step(new backup_decisionforge_activity_structure_step(
            'decisionforge_structure',
            'decisionforge.xml'
        ));
    }

    /**
     * Encode links in activity content.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content): string {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace(
            "/({$base}\\/mod\\/decisionforge\\/index.php\\?id=)([0-9]+)/",
            '$@DECISIONFORGEINDEX*$2@$',
            $content
        );
        return preg_replace(
            "/({$base}\\/mod\\/decisionforge\\/view.php\\?id=)([0-9]+)/",
            '$@DECISIONFORGEVIEWBYID*$2@$',
            $content
        );
    }
}
