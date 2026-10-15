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

namespace mod_decisionforge;

use mod_decisionforge\local\simulation_manager;

/**
 * Branching simulation engine tests.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_decisionforge\local\simulation_manager
 */
final class simulation_manager_test extends \advanced_testcase {
    /**
     * Create a stage record.
     *
     * @param int $activityid
     * @param string $title
     * @param string $kind
     * @param int $position
     * @param float $points
     * @return \stdClass
     */
    private function create_stage(
        int $activityid,
        string $title,
        string $kind,
        int $position,
        float $points = 0
    ): \stdClass {
        global $DB;

        $stage = (object) [
            'decisionforgeid' => $activityid,
            'title' => $title,
            'stagekind' => $kind,
            'body' => '<p>' . $title . ' body</p>',
            'bodyformat' => FORMAT_HTML,
            'prompt' => '<p>' . $title . ' prompt</p>',
            'promptformat' => FORMAT_HTML,
            'points' => $points,
            'position' => $position,
            'nextstageid' => 0,
            'active' => 1,
            'requireallcritical' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $stage->id = $DB->insert_record('decisionforge_simstages', $stage);
        return $stage;
    }

    /**
     * Create one decision choice.
     *
     * @param int $stageid
     * @param string $text
     * @param float $quality
     * @param int $nextstageid
     * @param int $position
     * @return \stdClass
     */
    private function create_choice(
        int $stageid,
        string $text,
        float $quality,
        int $nextstageid,
        int $position
    ): \stdClass {
        global $DB;

        $choice = (object) [
            'stageid' => $stageid,
            'choicetext' => $text,
            'choicetextformat' => FORMAT_PLAIN,
            'quality' => $quality,
            'consequence' => 'Recorded consequence for ' . $text,
            'consequenceformat' => FORMAT_PLAIN,
            'nextstageid' => $nextstageid,
            'safetyimpact' => $position === 0 ? 10 : -10,
            'complianceimpact' => $position === 0 ? 5 : -5,
            'trustimpact' => $position === 0 ? 8 : -8,
            'resourcesimpact' => -4,
            'targetrank' => 0,
            'position' => $position,
        ];
        $choice->id = $DB->insert_record('decisionforge_simchoices', $choice);
        return $choice;
    }

    /**
     * Create an evidence item.
     *
     * @param int $stageid
     * @param string $title
     * @param bool $critical
     * @param int $position
     * @return \stdClass
     */
    private function create_evidence(int $stageid, string $title, bool $critical, int $position): \stdClass {
        global $DB;

        $evidence = (object) [
            'stageid' => $stageid,
            'title' => $title,
            'body' => $title . ' evidence body',
            'bodyformat' => FORMAT_PLAIN,
            'evidencekind' => 'document',
            'critical' => (int) $critical,
            'position' => $position,
        ];
        $evidence->id = $DB->insert_record('decisionforge_simevidence', $evidence);
        return $evidence;
    }

    /**
     * Every critical evidence item is required and valid reviewed IDs are recorded.
     *
     * @return void
     */
    public function test_critical_evidence_must_be_reviewed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('decisionforge', [
            'course' => $course->id,
            'name' => 'Evidence review simulation',
            'experiencemode' => 'simulation',
        ]);
        $stage = $this->create_stage($activity->id, 'Review incident evidence', 'evidence', 0, 10);
        $criticalone = $this->create_evidence($stage->id, 'Critical record one', true, 0);
        $criticaltwo = $this->create_evidence($stage->id, 'Critical record two', true, 1);
        $optional = $this->create_evidence($stage->id, 'Optional context', false, 2);
        $cm = get_coursemodule_from_instance('decisionforge', $activity->id, $course->id, false, MUST_EXIST);
        $manager = new simulation_manager($activity, $cm);
        $attempt = $manager->start_attempt($user->id);

        try {
            $manager->submit_stage($attempt, $stage->id, ['opened' => [$criticalone->id]]);
            $this->fail('Submitting without every critical item must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('criticalevidencerequired', $exception->errorcode);
        }

        $result = $manager->submit_stage($attempt, $stage->id, [
            'opened' => [$criticalone->id, $criticaltwo->id, $optional->id],
        ]);
        $this->assertTrue($result['finished']);
        $this->assertEqualsWithDelta(100, $result['attempt']->finalgrade, 0.00001);
        $this->assertEqualsCanonicalizing(
            [$criticalone->id, $criticaltwo->id, $optional->id],
            json_decode($result['response']->openedjson, true)
        );
    }

    /**
     * A selected action determines the route, metrics, grade and completion data.
     *
     * @return void
     */
    public function test_branch_is_scored_and_published_to_gradebook(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('decisionforge', [
            'course' => $course->id,
            'name' => 'Live incident command',
            'experiencemode' => 'simulation',
            'grade' => 100,
            'gradepass' => 60,
            'initialsafety' => 70,
            'initialcompliance' => 65,
            'initialtrust' => 60,
            'initialresources' => 80,
        ]);
        $decision = $this->create_stage($activity->id, 'Disclosure decision', 'decision', 0, 20);
        $controlled = $this->create_stage($activity->id, 'Controlled review', 'reflection', 1);
        $escalated = $this->create_stage($activity->id, 'Escalated review', 'reflection', 2);
        $DB->set_field('decisionforge_simstages', 'nextstageid', -1, ['id' => $controlled->id]);
        $DB->set_field('decisionforge_simstages', 'nextstageid', -1, ['id' => $escalated->id]);
        $best = $this->create_choice($decision->id, 'Disclose and stabilise', 100, $controlled->id, 0);
        $this->create_choice($decision->id, 'Delay disclosure', 25, $escalated->id, 1);

        $cm = get_coursemodule_from_instance('decisionforge', $activity->id, $course->id, false, MUST_EXIST);
        $manager = new simulation_manager($activity, $cm);
        $attempt = $manager->start_attempt($user->id);
        $firstresult = $manager->submit_stage($attempt, $decision->id, ['choiceid' => $best->id]);

        $this->assertFalse($firstresult['finished']);
        $savedresponse = $manager->get_latest_response_for_stage($attempt->id, $decision->id);
        $this->assertNotNull($savedresponse);
        $this->assertSame((int) $firstresult['response']->id, (int) $savedresponse->id);
        $this->assertNull($manager->get_latest_response_for_stage($attempt->id, $controlled->id));
        $this->assertEquals($controlled->id, $firstresult['attempt']->currentstageid);
        $this->assertEquals(80, $firstresult['attempt']->safety);
        $this->assertEquals(70, $firstresult['attempt']->compliance);
        $this->assertEquals(68, $firstresult['attempt']->trust);
        $this->assertEquals(76, $firstresult['attempt']->resources);

        $secondresult = $manager->submit_stage($firstresult['attempt'], $controlled->id, [
            'response' => 'I would document the facts, ownership and next control actions.',
        ]);
        $this->assertTrue($secondresult['finished']);
        $this->assertEqualsWithDelta(100, $secondresult['attempt']->finalgrade, 0.00001);
        $this->assertContains($secondresult['attempt']->outcomecode, ['command', 'controlled']);

        $gradeitem = $DB->get_record('grade_items', [
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'decisionforge',
            'iteminstance' => $activity->id,
            'itemnumber' => 0,
        ], '*', MUST_EXIST);
        $grade = $DB->get_record('grade_grades', [
            'itemid' => $gradeitem->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(100, $grade->rawgrade, 0.00001);
    }

    /**
     * Content edits cannot rewrite an attempt already in progress.
     *
     * @return void
     */
    public function test_attempt_uses_immutable_snapshot_and_rejects_foreign_choice(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('decisionforge', [
            'course' => $course->id,
            'name' => 'Snapshot simulation',
            'experiencemode' => 'simulation',
        ]);
        $decision = $this->create_stage($activity->id, 'Original incident', 'decision', 0, 10);
        $choice = $this->create_choice($decision->id, 'Safe response', 100, -1, 0);
        $this->create_choice($decision->id, 'Unsafe response', 0, -1, 1);
        $cm = get_coursemodule_from_instance('decisionforge', $activity->id, $course->id, false, MUST_EXIST);
        $manager = new simulation_manager($activity, $cm);
        $attempt = $manager->start_attempt($user->id);

        $DB->set_field('decisionforge_simstages', 'title', 'Rewritten incident', ['id' => $decision->id]);
        $snapshotstage = $manager->get_current_stage($attempt);
        $this->assertSame('Original incident', $snapshotstage['title']);

        try {
            $manager->submit_stage($attempt, $decision->id, ['choiceid' => 999999]);
            $this->fail('A choice outside the immutable stage snapshot must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('decisionrequired', $exception->errorcode);
        }

        $result = $manager->submit_stage($attempt, $decision->id, ['choiceid' => $choice->id]);
        $this->assertTrue($result['finished']);
        $this->assertEqualsWithDelta(100, $result['attempt']->finalgrade, 0.00001);
    }
}
