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
 * Add editable sample cases.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_decisionforge\local\manager;

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$manager = manager::from_cmid($id);
$activity = $manager->get_activity();
$cm = $manager->get_cm();
$context = $manager->get_context();
$course = get_course($activity->course);

require_login($course, true, $cm);
require_capability('mod/decisionforge:managecases', $context);
require_sesskey();

$returnurl = new moodle_url('/mod/decisionforge/manage.php', ['id' => $id]);
if (!$confirm) {
    $PAGE->set_url('/mod/decisionforge/seed.php', ['id' => $id, 'sesskey' => sesskey()]);
    $PAGE->set_title(get_string('addsamples', 'mod_decisionforge'));
    $PAGE->set_heading(format_string($course->fullname));
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('sampleconfirm', 'mod_decisionforge'),
        new moodle_url('/mod/decisionforge/seed.php', ['id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $returnurl
    );
    echo $OUTPUT->footer();
    exit;
}
if (!data_submitted()) {
    throw new moodle_exception('invalidrequest', 'error');
}

$samples = [
    [
        'title' => 'Chemical spill near a walkway',
        'scenario' => '<p>A chemical container has tipped over and liquid is spreading towards a busy walkway. No one appears injured.</p>',
        'question' => 'What should you do first?',
        'options' => [
            ['Clean it immediately', 25, 'Approaching an unknown chemical without isolating the area or checking its hazards could expose you and others.'],
            ['Ignore it until the next inspection', 0, 'The spill remains an immediate exposure and slip risk. Delaying action is unsafe.'],
            ['Isolate the area and report the spill', 100, 'You prevented access to the hazard and activated the approved response process.'],
            ['Move the damaged container by hand', 25, 'Moving a leaking container without the correct equipment can increase the spill and your exposure.'],
        ],
    ],
    [
        'title' => 'Damaged electrical cable',
        'scenario' => '<p>You notice a power cable with exposed wiring beside equipment that is currently operating.</p>',
        'question' => 'Which action provides the safest immediate response?',
        'options' => [
            ['Cover the cable with tape while it is powered', 25, 'A temporary repair on an energised cable does not remove the electrical hazard.'],
            ['Stop using the equipment and isolate the power', 100, 'Isolating the energy source removes the immediate risk before qualified repair.'],
            ['Move the cable with a metal tool', 0, 'Metal conducts electricity and creates a severe shock risk.'],
            ['Warn one colleague and continue working', 25, 'A warning alone does not control the active electrical hazard.'],
        ],
    ],
    [
        'title' => 'Blocked emergency exit',
        'scenario' => '<p>Delivery boxes have been stacked in front of a marked emergency exit during a busy shift.</p>',
        'question' => 'What is the best decision?',
        'options' => [
            ['Leave them until the shift ends', 0, 'An emergency could happen at any time, so the exit must remain available.'],
            ['Place a sign telling people to use another exit', 25, 'A sign does not correct the blocked evacuation route.'],
            ['Clear the exit immediately and report the obstruction', 100, 'The evacuation route is restored and the cause can be addressed.'],
            ['Move only enough boxes to create a narrow gap', 50, 'Partial access may still delay evacuation and fail safety requirements.'],
        ],
    ],
];

global $DB;
$configuredmax = (int) get_config('decisionforge', 'maxcases');
$maxcases = $configuredmax > 0 ? $configuredmax : 50;
if (count($manager->get_cases()) + count($samples) > $maxcases) {
    throw new moodle_exception('maxcasesreached', 'mod_decisionforge', '', $maxcases);
}
$position = count($manager->get_cases());
$transaction = $DB->start_delegated_transaction();
foreach ($samples as $sample) {
    $caseid = $DB->insert_record('decisionforge_cases', (object) [
        'decisionforgeid' => $activity->id,
        'title' => $sample['title'],
        'scenario' => $sample['scenario'],
        'scenarioformat' => FORMAT_HTML,
        'questiontext' => $sample['question'],
        'points' => 10,
        'position' => $position++,
        'active' => 1,
        'archived' => 0,
        'timecreated' => time(),
        'timemodified' => time(),
    ]);
    foreach ($sample['options'] as $optionposition => $option) {
        $DB->insert_record('decisionforge_options', (object) [
            'caseid' => $caseid,
            'optiontext' => $option[0],
            'optiontextformat' => FORMAT_PLAIN,
            'score' => $option[1],
            'consequence' => $option[2],
            'consequenceformat' => FORMAT_PLAIN,
            'position' => $optionposition,
        ]);
    }
}
$transaction->allow_commit();

redirect($returnurl, get_string('samplesadded', 'mod_decisionforge'), null, \core\output\notification::NOTIFY_SUCCESS);
