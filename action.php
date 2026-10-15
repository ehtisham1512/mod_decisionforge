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
 * Secure case management actions.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\manager;

$id = required_param('id', PARAM_INT);
$caseid = required_param('caseid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

$manager = manager::from_cmid($id);
$activity = $manager->get_activity();
$cm = $manager->get_cm();
$context = $manager->get_context();
$course = get_course($activity->course);

require_login($course, true, $cm);
require_capability('mod/decisionforge:managecases', $context);
require_sesskey();

$returnurl = new moodle_url('/mod/decisionforge/manage.php', ['id' => $id]);
$case = $manager->get_case($caseid);

if ($action === 'delete') {
    $confirm = optional_param('confirm', 0, PARAM_BOOL);
    if (!$confirm) {
        $PAGE->set_url('/mod/decisionforge/action.php', [
            'id' => $id,
            'caseid' => $caseid,
            'action' => 'delete',
            'sesskey' => sesskey(),
        ]);
        $PAGE->set_title(get_string('deletecase', 'mod_decisionforge'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $OUTPUT->header();
        $confirmurl = new moodle_url('/mod/decisionforge/action.php', [
            'id' => $id,
            'caseid' => $caseid,
            'action' => 'delete',
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->confirm(
            get_string('confirmdeletecase', 'mod_decisionforge', format_string($case->title)),
            $confirmurl,
            $returnurl
        );
        echo $OUTPUT->footer();
        exit;
    }
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest', 'error');
    }
    $manager->delete_case($caseid);
    redirect($returnurl, get_string('casedeleted', 'mod_decisionforge'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if (!data_submitted()) {
    throw new moodle_exception('invalidrequest', 'error');
}

switch ($action) {
    case 'moveup':
        $manager->move_case($caseid, -1);
        break;
    case 'movedown':
        $manager->move_case($caseid, 1);
        break;
    case 'toggle':
        $manager->toggle_case($caseid);
        break;
    case 'duplicate':
        $manager->duplicate_case($caseid);
        redirect($returnurl, get_string('caseduplicated', 'mod_decisionforge'), null, \core\output\notification::NOTIFY_SUCCESS);
        break;
    default:
        throw new moodle_exception('invalidaction', 'error');
}

redirect($returnurl);
