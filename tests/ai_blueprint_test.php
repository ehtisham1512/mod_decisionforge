<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_decisionforge;

use mod_decisionforge\local\ai_blueprint;

/**
 * Defensive AI blueprint validation tests.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_decisionforge\local\ai_blueprint
 */
final class ai_blueprint_test extends \basic_testcase {
    /** @return array */
    private function valid_blueprint(): array {
        $choice = static function(string $text, float $quality, string $route): array {
            return [
                'text' => $text,
                'quality' => $quality,
                'consequence' => 'The operating position changes.',
                'next_key' => $route,
                'safety_impact' => 5,
                'compliance_impact' => 2,
                'trust_impact' => 1,
                'resources_impact' => -2,
                'target_rank' => 0,
            ];
        };
        return [
            'title' => 'Vessel incident command',
            'summary' => 'Make and defend a safe operational decision.',
            'mission_label' => 'Coastal incident simulation',
            'stages' => [
                [
                    'key' => 'briefing',
                    'title' => '<b>Incident briefing</b>',
                    'kind' => 'briefing',
                    'body' => 'Conditions are deteriorating.',
                    'prompt' => 'Review the operating picture.',
                    'points' => 10,
                    'next_key' => 'decision',
                    'require_all_critical' => false,
                    'choices' => [],
                    'evidence' => [],
                ],
                [
                    'key' => 'decision',
                    'title' => 'Choose the safe response',
                    'kind' => 'decision',
                    'body' => 'Visibility falls while traffic increases.',
                    'prompt' => 'Commit one action.',
                    'points' => 20,
                    'next_key' => 'END',
                    'require_all_critical' => false,
                    'choices' => [
                        $choice('Reduce speed and reassess', 100, 'END'),
                        $choice('Continue unchanged', 10, 'briefing'),
                    ],
                    'evidence' => [],
                ],
            ],
        ];
    }

    /** Plain text and forward-only routes are enforced. */
    public function test_valid_blueprint_is_normalized_safely(): void {
        $blueprint = ai_blueprint::validate($this->valid_blueprint());

        $this->assertSame('Incident briefing', $blueprint['stages'][0]['title']);
        $this->assertSame(0.0, $blueprint['stages'][0]['points']);
        $this->assertSame('END', $blueprint['stages'][1]['choices'][1]['next_key']);
        $this->assertSame('END', $blueprint['stages'][1]['next_key']);
    }

    /** Strict schemas must require every property and reject extras. */
    public function test_schema_is_strict_at_every_object_level(): void {
        $schema = ai_blueprint::get_json_schema();
        $stage = $schema['properties']['stages']['items'];
        $choice = $stage['properties']['choices']['items'];
        $evidence = $stage['properties']['evidence']['items'];

        $this->assertFalse($schema['additionalProperties']);
        $this->assertFalse($stage['additionalProperties']);
        $this->assertFalse($choice['additionalProperties']);
        $this->assertFalse($evidence['additionalProperties']);
        $this->assertEqualsCanonicalizing(array_keys($schema['properties']), $schema['required']);
        $this->assertEqualsCanonicalizing(array_keys($stage['properties']), $stage['required']);
    }

    /** A decision must contain a fully attainable best choice. */
    public function test_unattainable_decision_is_rejected(): void {
        $blueprint = $this->valid_blueprint();
        $blueprint['stages'][1]['choices'][0]['quality'] = 90;

        $this->expectException(\moodle_exception::class);
        ai_blueprint::validate($blueprint);
    }

    /** Prioritisation ranks must be a complete unique sequence. */
    public function test_duplicate_priority_ranks_are_rejected(): void {
        $blueprint = $this->valid_blueprint();
        $stage = &$blueprint['stages'][1];
        $stage['kind'] = 'prioritise';
        $stage['choices'][0]['target_rank'] = 1;
        $stage['choices'][1]['target_rank'] = 1;

        $this->expectException(\moodle_exception::class);
        ai_blueprint::validate($blueprint);
    }
}
