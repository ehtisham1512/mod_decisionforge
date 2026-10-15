<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_decisionforge;

use mod_decisionforge\local\ai_generator;

/**
 * AI response and transactional import tests.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_decisionforge\local\ai_generator
 */
final class ai_generator_test extends \advanced_testcase {
    /** @return array */
    private function blueprint(): array {
        $choice = static function(string $text, float $quality, string $route): array {
            return [
                'text' => $text,
                'quality' => $quality,
                'consequence' => 'A measurable consequence follows.',
                'next_key' => $route,
                'safety_impact' => $quality === 100.0 ? 8 : -12,
                'compliance_impact' => $quality === 100.0 ? 4 : -8,
                'trust_impact' => $quality === 100.0 ? 3 : -6,
                'resources_impact' => -2,
                'target_rank' => 0,
            ];
        };
        return [
            'title' => 'Validated vessel simulation',
            'summary' => 'Assess operational judgement under changing conditions.',
            'mission_label' => 'Maritime command simulation',
            'stages' => [
                [
                    'key' => 'command_decision',
                    'title' => 'Control the immediate risk',
                    'kind' => 'decision',
                    'body' => 'A squall reduces visibility near a traffic separation route.',
                    'prompt' => 'Select the safest immediate action.',
                    'points' => 20,
                    'next_key' => 'professional_record',
                    'require_all_critical' => false,
                    'choices' => [
                        $choice('Reduce speed, post a lookout and reassess', 100.0, 'professional_record'),
                        $choice('Maintain speed to clear the area sooner', 20.0, 'END'),
                    ],
                    'evidence' => [],
                ],
                [
                    'key' => 'professional_record',
                    'title' => 'Defend the response',
                    'kind' => 'reflection',
                    'body' => 'The immediate risk is controlled.',
                    'prompt' => 'Document why your decision was defensible.',
                    'points' => 0,
                    'next_key' => 'END',
                    'require_all_critical' => false,
                    'choices' => [],
                    'evidence' => [],
                ],
            ],
        ];
    }

    /** @return array */
    private function quick_blueprint(): array {
        return [
            'title' => 'Validated quick assessment',
            'summary' => 'Independent cases for safe operational judgement.',
            'cases' => [[
                'title' => 'Falling visibility',
                'scenario' => 'Fog forms while a small vessel approaches traffic.',
                'question' => 'What is the safest immediate action?',
                'points' => 10,
                'options' => [
                    [
                        'text' => 'Reduce speed, post a lookout and reassess',
                        'quality' => 100,
                        'consequence' => 'The vessel gains time and improves situational awareness.',
                    ],
                    [
                        'text' => 'Maintain speed to pass the area sooner',
                        'quality' => 0,
                        'consequence' => 'Reaction time remains dangerously limited.',
                    ],
                ],
            ]],
        ];
    }

    /** Displayed model defaults are also available before settings are persisted. */
    public function test_model_defaults_and_saved_aliases_are_resolved(): void {
        $this->resetAfterTest();
        unset_config('aimodel', 'mod_decisionforge');
        unset_config('geminimodel', 'mod_decisionforge');

        $this->assertSame('gpt-5.6', ai_generator::get_model_name('openai'));
        $this->assertSame('gemini-3.6-flash', ai_generator::get_model_name('gemini'));

        set_config('geminimodel', 'gemini-3.6-flash-20260801', 'mod_decisionforge');
        $this->assertSame('gemini-3.6-flash-20260801', ai_generator::get_model_name('gemini'));
    }

    /** A malformed legacy model setting cannot reach an outbound provider request. */
    public function test_invalid_saved_model_alias_is_rejected(): void {
        $this->resetAfterTest();
        set_config('geminimodel', 'https://unsafe.example/model', 'mod_decisionforge');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('aimodelmissing', 'mod_decisionforge'));
        ai_generator::get_model_name('gemini');
    }

    /** Gemini's HTTP 400 invalid-key response is reported as authentication failure. */
    public function test_gemini_invalid_key_error_is_classified(): void {
        $body = '{"error":{"code":400,"message":"API key not valid.","status":"INVALID_ARGUMENT"}}';
        $this->assertSame('aiauthenticationfailed', ai_generator::classify_provider_error('gemini', 400, $body));
    }

    /** Bad model and request responses use actionable non-sensitive errors. */
    public function test_provider_request_errors_are_classified(): void {
        $modelbody = '{"error":{"message":"The model is not found","status":"NOT_FOUND"}}';
        $schemabody = '{"error":{"message":"Invalid response schema","status":"INVALID_ARGUMENT"}}';
        $this->assertSame('aimodelunavailable', ai_generator::classify_provider_error('gemini', 404, $modelbody));
        $this->assertSame(
            'aiinvalidproviderrequest',
            ai_generator::classify_provider_error('gemini', 400, $schemabody)
        );
    }

    /** Gemini GenerateContent receives the contract as text in JSON mode. */
    public function test_gemini_request_avoids_schema_complexity_rejection(): void {
        $request = ai_generator::build_gemini_request('Create a safety simulation.');

        $this->assertFalse($request['store']);
        $this->assertSame('application/json', $request['generationConfig']['responseMimeType']);
        $this->assertSame(12000, $request['generationConfig']['maxOutputTokens']);
        $this->assertArrayNotHasKey('responseSchema', $request['generationConfig']);
        $input = $request['contents'][0]['parts'][0]['text'];
        $this->assertStringContainsString('"stages"', $input);
        $this->assertStringContainsString('"additionalProperties":false', $input);
    }

    /** The Gemini Quick contract uses the same compatible JSON-mode boundary. */
    public function test_gemini_quick_request_contains_case_contract(): void {
        $request = ai_generator::build_gemini_quick_request('Create simple safety cases.');
        $input = $request['contents'][0]['parts'][0]['text'];

        $this->assertSame('application/json', $request['generationConfig']['responseMimeType']);
        $this->assertArrayNotHasKey('responseSchema', $request['generationConfig']);
        $this->assertStringContainsString('"cases"', $input);
        $this->assertStringContainsString('"quality"', $input);
    }

    /** A Gemini GenerateContent JSON candidate is extracted safely. */
    public function test_gemini_generate_content_response_is_extracted(): void {
        $output = json_encode($this->blueprint(), JSON_THROW_ON_ERROR);
        $payload = json_encode([
            'responseId' => 'gemini_response_123',
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['role' => 'model', 'parts' => [['text' => $output]]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $result = ai_generator::extract_gemini_generate_content_response($payload);
        $this->assertSame('gemini_response_123', $result['responseid']);
        $this->assertSame($output, $result['output']);
    }

    /** A raw Responses API payload is parsed without SDK-only helpers. */
    public function test_structured_response_is_extracted(): void {
        $output = json_encode($this->blueprint(), JSON_THROW_ON_ERROR);
        $payload = json_encode([
            'id' => 'resp_test_123',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => $output]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $result = ai_generator::extract_response($payload);
        $this->assertSame('resp_test_123', $result['responseid']);
        $this->assertSame($output, $result['output']);
    }

    /** The importer creates editable live records and resolves routes atomically. */
    public function test_validated_draft_is_imported(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $this->setUser($author);
        $activity = $this->getDataGenerator()->create_module('decisionforge', [
            'course' => $course->id,
            'name' => 'AI import activity',
            'experiencemode' => 'simulation',
        ]);
        $cm = get_coursemodule_from_instance('decisionforge', $activity->id, $course->id, false, MUST_EXIST);
        $now = time();
        $draftid = $DB->insert_record('decisionforge_aidrafts', (object) [
            'decisionforgeid' => $activity->id,
            'userid' => $author->id,
            'prompt' => 'Create a vessel incident simulation for qualified assessors.',
            'settingsjson' => '{}',
            'draftjson' => json_encode($this->blueprint(), JSON_THROW_ON_ERROR),
            'provider' => 'openai',
            'model' => 'test-model',
            'status' => 'ready',
            'responseid' => 'resp_test',
            'errormessage' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $generator = new ai_generator($activity, $cm);
        $this->assertSame(2, $generator->import_draft($draftid, $author->id, false));

        $stages = array_values($DB->get_records('decisionforge_simstages', [
            'decisionforgeid' => $activity->id,
        ], 'position ASC'));
        $this->assertCount(2, $stages);
        $this->assertSame((int) $stages[1]->id, (int) $stages[0]->nextstageid);
        $this->assertSame(-1, (int) $stages[1]->nextstageid);
        $this->assertSame(FORMAT_PLAIN, (int) $stages[0]->bodyformat);
        $this->assertCount(2, $DB->get_records('decisionforge_simchoices', ['stageid' => $stages[0]->id]));
        $this->assertSame('imported', $DB->get_field('decisionforge_aidrafts', 'status', ['id' => $draftid]));
    }

    /** Existing author content can only be replaced after explicit confirmation. */
    public function test_existing_stages_require_replace_confirmation(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('decisionforge', [
            'course' => $course->id,
            'name' => 'Protected author content',
            'experiencemode' => 'simulation',
        ]);
        $cm = get_coursemodule_from_instance('decisionforge', $activity->id, $course->id, false, MUST_EXIST);
        $DB->insert_record('decisionforge_simstages', (object) [
            'decisionforgeid' => $activity->id,
            'title' => 'Existing stage',
            'stagekind' => 'briefing',
            'body' => 'Existing content',
            'bodyformat' => FORMAT_PLAIN,
            'prompt' => '',
            'promptformat' => FORMAT_PLAIN,
            'points' => 0,
            'position' => 0,
            'nextstageid' => -1,
            'active' => 1,
            'requireallcritical' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $draftid = $DB->insert_record('decisionforge_aidrafts', (object) [
            'decisionforgeid' => $activity->id,
            'userid' => $author->id,
            'prompt' => 'Create a replacement vessel simulation for review.',
            'settingsjson' => '{}',
            'draftjson' => json_encode($this->blueprint(), JSON_THROW_ON_ERROR),
            'provider' => 'openai',
            'model' => 'test-model',
            'status' => 'ready',
            'responseid' => '',
            'errormessage' => null,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        try {
            (new ai_generator($activity, $cm))->import_draft($draftid, $author->id, false);
            $this->fail('Replacing existing stages without confirmation must fail.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('aireplaceconfirmationrequired', $exception->errorcode);
        }
        $this->assertSame(1, $DB->count_records('decisionforge_simstages', [
            'decisionforgeid' => $activity->id,
        ]));
    }

    /** Quick drafts append editable cases without changing existing author content. */
    public function test_quick_draft_is_appended_transactionally(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('decisionforge', [
            'course' => $course->id,
            'name' => 'Quick AI import activity',
            'experiencemode' => 'quick',
        ]);
        $cm = get_coursemodule_from_instance('decisionforge', $activity->id, $course->id, false, MUST_EXIST);
        $existingid = $DB->insert_record('decisionforge_cases', (object) [
            'decisionforgeid' => $activity->id,
            'title' => 'Existing case',
            'scenario' => 'Existing scenario',
            'scenarioformat' => FORMAT_PLAIN,
            'questiontext' => 'What should happen?',
            'points' => 10,
            'position' => 0,
            'active' => 1,
            'archived' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $draftid = $DB->insert_record('decisionforge_aidrafts', (object) [
            'decisionforgeid' => $activity->id,
            'userid' => $author->id,
            'prompt' => 'Create independent vessel safety cases for young learners.',
            'settingsjson' => json_encode(['drafttype' => 'quick'], JSON_THROW_ON_ERROR),
            'draftjson' => json_encode($this->quick_blueprint(), JSON_THROW_ON_ERROR),
            'provider' => 'openai',
            'model' => 'test-model',
            'status' => 'ready',
            'responseid' => '',
            'errormessage' => null,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $generator = new ai_generator($activity, $cm);
        $this->assertSame(1, $generator->import_quick_draft($draftid, $author->id));
        $cases = array_values($DB->get_records('decisionforge_cases', [
            'decisionforgeid' => $activity->id,
        ], 'position ASC'));
        $this->assertCount(2, $cases);
        $this->assertSame($existingid, (int) $cases[0]->id);
        $this->assertSame('Falling visibility', $cases[1]->title);
        $this->assertSame(FORMAT_PLAIN, (int) $cases[1]->scenarioformat);
        $this->assertCount(2, $DB->get_records('decisionforge_options', ['caseid' => $cases[1]->id]));
        $this->assertSame('imported', $DB->get_field('decisionforge_aidrafts', 'status', ['id' => $draftid]));
    }
}
