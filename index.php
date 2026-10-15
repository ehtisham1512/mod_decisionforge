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
 * List Decision Forge activities in a course.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_course_login($course);

$PAGE->set_url('/mod/decisionforge/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_decisionforge'));
$PAGE->set_heading(format_string($course->fullname));

$instances = get_all_instances_in_course('decisionforge', $course);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_decisionforge'));

if (!$instances) {
    notice(
        get_string('thereareno', 'moodle', get_string('modulenameplural', 'mod_decisionforge')),
        new moodle_url('/course/view.php', ['id' => $course->id])
    );
}

$table = new html_table();
$table->head = [get_string('name'), get_string('experiencecontent', 'mod_decisionforge')];
foreach ($instances as $instance) {
    if (!$instance->visible) {
        $link = html_writer::link(
            new moodle_url('/mod/decisionforge/view.php', ['id' => $instance->coursemodule]),
            format_string($instance->name),
            ['class' => 'dimmed']
        );
    } else {
        $link = html_writer::link(
            new moodle_url('/mod/decisionforge/view.php', ['id' => $instance->coursemodule]),
            format_string($instance->name)
        );
    }
    if (($instance->experiencemode ?? 'quick') === 'simulation') {
        $contentcount = $DB->count_records('decisionforge_simstages', [
            'decisionforgeid' => $instance->id,
            'active' => 1,
        ]);
        $content = get_string('stagecountlabel', 'mod_decisionforge', $contentcount);
    } else {
        $contentcount = $DB->count_records('decisionforge_cases', [
            'decisionforgeid' => $instance->id,
            'active' => 1,
            'archived' => 0,
        ]);
        $content = get_string('casecountlabel', 'mod_decisionforge', $contentcount);
    }
    $table->data[] = [$link, $content];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
