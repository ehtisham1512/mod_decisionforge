<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_decisionforge;

use mod_decisionforge\local\quick_ai_blueprint;

/**
 * Defensive Quick Assessment AI blueprint validation tests.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_decisionforge\local\quick_ai_blueprint
 */
final class quick_ai_blueprint_test extends \basic_testcase {
    /** @return array */
    private function blueprint(): array {
        return [
            'title' => '<b>Small-vessel safety</b>',
            'summary' => 'Practise safe decisions in short situations.',
            'cases' => [[
                'title' => 'Sudden fog',
                'scenario' => 'Visibility quickly falls while the vessel is moving.',
                'question' => 'What should you do first?',
                'points' => 10,
                'options' => [
                    [
                        'text' => 'Slow down, post a lookout and reassess',
                        'quality' => 100,
                        'consequence' => 'The immediate collision risk is reduced.',
                    ],
                    [
                        'text' => 'Keep the same speed',
                        'quality' => 0,
                        'consequence' => 'The vessel continues with inadequate reaction time.',
                    ],
                ],
            ]],
        ];
    }

    /** Valid content is normalized to safe plain text. */
    public function test_valid_blueprint_is_normalized(): void {
        $blueprint = quick_ai_blueprint::validate($this->blueprint());

        $this->assertSame('Small-vessel safety', $blueprint['title']);
        $this->assertSame(10.0, $blueprint['cases'][0]['points']);
        $this->assertSame(100.0, $blueprint['cases'][0]['options'][0]['quality']);
    }

    /** Every provider object is strict and every property is required. */
    public function test_schema_is_strict(): void {
        $schema = quick_ai_blueprint::get_json_schema();
        $case = $schema['properties']['cases']['items'];
        $option = $case['properties']['options']['items'];

        $this->assertFalse($schema['additionalProperties']);
        $this->assertFalse($case['additionalProperties']);
        $this->assertFalse($option['additionalProperties']);
        $this->assertEqualsCanonicalizing(array_keys($schema['properties']), $schema['required']);
        $this->assertEqualsCanonicalizing(array_keys($case['properties']), $case['required']);
        $this->assertEqualsCanonicalizing(array_keys($option['properties']), $option['required']);
    }

    /** A learner must always be able to earn the full case score. */
    public function test_case_without_best_action_is_rejected(): void {
        $blueprint = $this->blueprint();
        $blueprint['cases'][0]['options'][0]['quality'] = 75;

        $this->expectException(\moodle_exception::class);
        quick_ai_blueprint::validate($blueprint);
    }

    /** Only the values supported by the Quick Assessment UI can be imported. */
    public function test_unsupported_quality_is_rejected(): void {
        $blueprint = $this->blueprint();
        $blueprint['cases'][0]['options'][1]['quality'] = 60;

        $this->expectException(\moodle_exception::class);
        quick_ai_blueprint::validate($blueprint);
    }
}
