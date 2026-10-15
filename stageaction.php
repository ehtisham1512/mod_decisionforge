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
 * Stage authoring actions.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\simulation_manager;

$id = required_param('id', PARAM_INT);
$stageid = required_param('stageid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$confirmed = optional_param('confirmed', 0, PARAM_BOOL);
$manager = simulation_manager::from_cmid($id);
$activity = $manager->get_activity();
$cm = $manager->get_cm();
$context = $manager->get_context();
$course = get_course($activity->course);
require_login($course, true, $cm);
require_capability('mod/decisionforge:managecases', $context);
require_sesskey();
if (!data_submitted()) {
    throw new moodle_exception('invalidrequest', 'error');
}
if ($action === 'delete' && !$confirmed) {
    $stage = $manager->get_stage($stageid);
    $continueurl = new moodle_url('/mod/decisionforge/stageaction.php', [
        'id' => $id,
        'stageid' => $stageid,
        'action' => 'delete',
        'confirmed' => 1,
        'sesskey' => sesskey(),
    ]);
    $cancelurl = new moodle_url('/mod/decisionforge/simulationstudio.php', ['id' => $id]);
    $PAGE->set_url('/mod/decisionforge/stageaction.php', [
        'id' => $id,
        'stageid' => $stageid,
        'action' => 'delete',
    ]);
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('deletestage', 'mod_decisionforge'));
    $PAGE->set_heading(format_string($course->fullname));
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeletestage', 'mod_decisionforge', format_string($stage->title)),
        $continueurl,
        $cancelurl
    );
    echo $OUTPUT->footer();
    exit;
}
if ($action === 'moveup') {
    $manager->move_stage($stageid, -1);
} elseif ($action === 'movedown') {
    $manager->move_stage($stageid, 1);
} elseif ($action === 'delete') {
    $manager->delete_stage($stageid);
} else {
    throw new moodle_exception('invalidaction', 'error');
}
redirect(new moodle_url('/mod/decisionforge/simulationstudio.php', ['id' => $id]));
