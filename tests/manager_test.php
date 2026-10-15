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

/**
 * Core manager tests.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_decisionforge\local\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Null optional grading data is normalised before an instance write.
     *
     * @return void
     * @covers ::decisionforge_normalise_instance_data
     */
    public function test_null_gradepass_is_normalised(): void {
        $data = (object) ['gradepass' => null];

        decisionforge_normalise_instance_data($data);

        $this->assertSame(0.0, $data->gradepass);
        $this->assertSame(100.0, $data->grade);
        $this->assertSame('simulation', $data->experiencemode);
        $this->assertSame(72, $data->initialsafety);
    }

    /**
     * Create a case and four scored actions.
     *
     * @param int $activityid
     * @return array Case and option records.
     */
    private function create_case(int $activityid): array {
        global $DB;

        $case = (object) [
            'decisionforgeid' => $activityid,
            'title' => 'A customer reports a data leak',
            'scenario' => '<p>A customer says private data was sent to the wrong address.</p>',
            'scenarioformat' => FORMAT_HTML,
            'questiontext' => 'What should you do first?',
            'points' => 10,
            'position' => 0,
            'active' => 1,
            'archived' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $case->id = $DB->insert_record('decisionforge_cases', $case);

        $options = [];
        foreach ([100, 75, 25, 0] as $position => $quality) {
            $option = (object) [
                'caseid' => $case->id,
                'optiontext' => 'Action ' . ($position + 1),
                'optiontextformat' => FORMAT_PLAIN,
                'score' => $quality,
                'consequence' => 'Consequence ' . ($position + 1),
                'consequenceformat' => FORMAT_PLAIN,
                'position' => $position,
            ];
            $option->id = $DB->insert_record('decisionforge_options', $option);
            $options[] = $option;
        }
        return [$case, $options];
    }

    /**
     * Starting an empty activity is rejected.
     *
     * @return void
     */
    public function test_empty_activity_cannot_start(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $activity = $this->getDataGenerator()->create_module('decisionforge', [
            'course' => $course->id,
            'name' => 'Safety decisions',
        ]);
        $cm = get_coursemodule_from_instance('decisionforge', $activity->id, $course->id, false, MUST_EXIST);
        $manager = new \mod_decisionforge\local\manager($activity, $cm);

        $this->expectException(\moodle_exception::class);
        $manager->start_attempt($user->id);
    }

    /**
     * Scores are derived from the stored option quality on the server.
     *
     * @return void
     */
    public function test_response_is_scored_and_attempt_is_finished(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('decisionforge', [
            'course' => $course->id,
            'name' => 'Security decisions',
            'shuffleanswers' => 0,
        ]);
        [$case, $options] = $this->create_case($activity->id);
        $cm = get_coursemodule_from_instance('decisionforge', $activity->id, $course->id, false, MUST_EXIST);
        $manager = new \mod_decisionforge\local\manager($activity, $cm);

        $attempt = $manager->start_attempt($user->id);
        $result = $manager->record_response($attempt, $options[1]->id, 'This limits further disclosure.', 80);

        $this->assertTrue($result['finished']);
        $this->assertEqualsWithDelta(7.5, $result['response']->score, 0.00001);
        $this->assertEqualsWithDelta(75, $result['attempt']->finalgrade, 0.00001);
        $this->assertEquals($case->id, $result['response']->caseid);
    }

    /**
     * A finished attempt is published to a correctly configured Gradebook item.
     *
     * @return void
     */
    public function test_finished_attempt_is_published_to_gradebook(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('decisionforge', [
            'course' => $course->id,
            'name' => 'Gradebook decisions',
            'grade' => 100,
            'gradepass' => 60,
            'shuffleanswers' => 0,
        ]);
        [, $options] = $this->create_case($activity->id);
        $cm = get_coursemodule_from_instance('decisionforge', $activity->id, $course->id, false, MUST_EXIST);
        $manager = new \mod_decisionforge\local\manager($activity, $cm);

        $attempt = $manager->start_attempt($user->id);
        $manager->record_response($attempt, $options[1]->id, '', 80);

        $gradeitem = $DB->get_record('grade_items', [
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'decisionforge',
            'iteminstance' => $activity->id,
            'itemnumber' => 0,
        ], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(100, $gradeitem->grademax, 0.00001);
        $this->assertEqualsWithDelta(60, $gradeitem->gradepass, 0.00001);

        $grade = $DB->get_record('grade_grades', [
            'itemid' => $gradeitem->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(75, $grade->rawgrade, 0.00001);
    }

    /**
     * An option outside the current case is rejected.
     *
     * @return void
     */
    public function test_foreign_option_is_rejected(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('decisionforge', [
            'course' => $course->id,
            'name' => 'Security decisions',
        ]);
        $this->create_case($activity->id);
        $cm = get_coursemodule_from_instance('decisionforge', $activity->id, $course->id, false, MUST_EXIST);
        $manager = new \mod_decisionforge\local\manager($activity, $cm);
        $attempt = $manager->start_attempt($user->id);

        $this->expectException(\moodle_exception::class);
        $manager->record_response($attempt, 999999, '', 50);
    }

    /**
     * Attempt ownership is enforced by the manager.
     *
     * @return void
     */
    public function test_attempt_owner_is_enforced(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('decisionforge', [
            'course' => $course->id,
            'name' => 'Security decisions',
        ]);
        $this->create_case($activity->id);
        $cm = get_coursemodule_from_instance('decisionforge', $activity->id, $course->id, false, MUST_EXIST);
        $manager = new \mod_decisionforge\local\manager($activity, $cm);
        $attempt = $manager->start_attempt($owner->id);

        $this->expectException(\moodle_exception::class);
        $manager->get_attempt($attempt->id, $other->id);
    }
}
