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
 * Start a learner attempt.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\manager;
use mod_decisionforge\local\simulation_manager;

$id = required_param('id', PARAM_INT);
$manager = manager::from_cmid($id);
$activity = $manager->get_activity();
$cm = $manager->get_cm();
$context = $manager->get_context();
$course = get_course($activity->course);

require_login($course, true, $cm);
require_capability('mod/decisionforge:attempt', $context);
require_sesskey();
if (!data_submitted()) {
    throw new moodle_exception('invalidrequest', 'error');
}

$existing = $manager->get_inprogress_attempt($USER->id);
if ($existing) {
    $target = ($existing->mode ?? 'quick') === 'simulation' ? 'simattempt.php' : 'attempt.php';
    redirect(new moodle_url('/mod/decisionforge/' . $target, [
        'id' => $id,
        'attemptid' => $existing->id,
    ]));
}
if (($activity->experiencemode ?? 'quick') === 'simulation') {
    $simulation = new simulation_manager($activity, $cm);
    $attempt = $simulation->start_attempt($USER->id);
    redirect(new moodle_url('/mod/decisionforge/simattempt.php', ['id' => $id, 'attemptid' => $attempt->id]));
}
$attempt = $manager->start_attempt($USER->id);
redirect(new moodle_url('/mod/decisionforge/attempt.php', ['id' => $id, 'attemptid' => $attempt->id]));
