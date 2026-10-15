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
 * Delete a learner attempt.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\manager;

$id = required_param('id', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$manager = manager::from_cmid($id);
$activity = $manager->get_activity();
$cm = $manager->get_cm();
$context = $manager->get_context();
$course = get_course($activity->course);

require_login($course, true, $cm);
require_capability('mod/decisionforge:deleteattempts', $context);
require_sesskey();

$returnurl = new moodle_url('/mod/decisionforge/report.php', ['id' => $id]);
if (!$confirm) {
    $PAGE->set_url('/mod/decisionforge/deleteattempt.php', [
        'id' => $id,
        'attemptid' => $attemptid,
        'sesskey' => sesskey(),
    ]);
    $PAGE->set_title(get_string('deleteattempt', 'mod_decisionforge'));
    $PAGE->set_heading(format_string($course->fullname));
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeleteattempt', 'mod_decisionforge'),
        new moodle_url('/mod/decisionforge/deleteattempt.php', [
            'id' => $id,
            'attemptid' => $attemptid,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]),
        $returnurl
    );
    echo $OUTPUT->footer();
    exit;
}
if (!data_submitted()) {
    throw new moodle_exception('invalidrequest', 'error');
}

$manager->delete_attempt($attemptid);
redirect($returnurl, get_string('attemptdeleted', 'mod_decisionforge'), null, \core\output\notification::NOTIFY_SUCCESS);
