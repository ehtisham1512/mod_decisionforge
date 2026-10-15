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
 * Commit a simulation stage.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\simulation_manager;

$id = required_param('id', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);
$stageid = required_param('stageid', PARAM_INT);
$simulation = simulation_manager::from_cmid($id);
$activity = $simulation->get_activity();
$cm = $simulation->get_cm();
$context = $simulation->get_context();
$course = get_course($activity->course);
require_login($course, true, $cm);
require_capability('mod/decisionforge:attempt', $context);
require_sesskey();
if (!data_submitted()) {
    throw new moodle_exception('invalidrequest', 'error');
}
$attempt = $simulation->get_attempt($attemptid, $USER->id);
$resulturl = new moodle_url('/mod/decisionforge/simresult.php', ['id' => $id, 'attemptid' => $attemptid]);
$attempturl = new moodle_url('/mod/decisionforge/simattempt.php', ['id' => $id, 'attemptid' => $attemptid]);

// Serialise submissions for this attempt. This prevents a timer reload, a
// double-click, or two open tabs from committing the same stage concurrently.
$lockfactory = \core\lock\lock_config::get_lock_factory('mod_decisionforge');
$lock = $lockfactory->get_lock('simulation-attempt-' . $attemptid, 10);
if (!$lock) {
    redirect(
        $attempturl,
        get_string('submissionprocessing', 'mod_decisionforge'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

$redirecturl = null;
$redirectmessage = null;
try {
    // Reload inside the lock. The attempt may have changed after the initial
    // ownership check while this request waited for an earlier submission.
    $attempt = $simulation->get_attempt($attemptid, $USER->id);
    if ($attempt->state !== 'inprogress') {
        $redirecturl = $resulturl;
    } elseif ($simulation->enforce_time_limit($attempt)) {
        $redirecturl = $resulturl;
        $redirectmessage = get_string('expired', 'mod_decisionforge');
    } else {
        $stage = $simulation->get_current_stage($attempt);
        if (!$stage || (int) $stage['id'] !== $stageid) {
            $savedresponse = $simulation->get_latest_response_for_stage($attemptid, $stageid);
            if ($savedresponse) {
                $redirecturl = new moodle_url('/mod/decisionforge/simfeedback.php', [
                    'id' => $id,
                    'attemptid' => $attemptid,
                    'responseid' => $savedresponse->id,
                ]);
            } else {
                $redirecturl = $attempturl;
            }
            $redirectmessage = get_string('submissionalreadyprocessed', 'mod_decisionforge');
        } else {
            $input = [
                'choiceid' => optional_param('choiceid', 0, PARAM_INT),
                'response' => optional_param('response', '', PARAM_RAW_TRIMMED),
                'opened' => optional_param_array('opened', [], PARAM_INT),
                'ranking' => [],
            ];
            if ($stage['stagekind'] === 'prioritise') {
                $byrank = [];
                foreach ($stage['choices'] as $choice) {
                    $rank = optional_param('rank_' . (int) $choice['id'], 0, PARAM_INT);
                    if ($rank > 0) {
                        $byrank[$rank] = (int) $choice['id'];
                    }
                }
                ksort($byrank);
                $input['ranking'] = array_values($byrank);
            }
            try {
                $result = $simulation->submit_stage($attempt, $stageid, $input);
                $redirecturl = new moodle_url('/mod/decisionforge/simfeedback.php', [
                    'id' => $id,
                    'attemptid' => $attemptid,
                    'responseid' => $result['response']->id,
                ]);
            } catch (moodle_exception $exception) {
                // The second deadline check inside the simulation engine can
                // expire while this request is being validated. That is a
                // normal timeout outcome, not an exception page for learners.
                if ($exception->errorcode !== 'expired') {
                    throw $exception;
                }
                $redirecturl = $resulturl;
                $redirectmessage = get_string('expired', 'mod_decisionforge');
            }
        }
    }
} finally {
    $lock->release();
}

redirect(
    $redirecturl,
    $redirectmessage,
    null,
    $redirectmessage === null
        ? \core\output\notification::NOTIFY_INFO
        : \core\output\notification::NOTIFY_SUCCESS
);
