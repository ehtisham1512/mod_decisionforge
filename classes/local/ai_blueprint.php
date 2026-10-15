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

namespace mod_decisionforge\local;

/**
 * Schema and defensive validation for AI-generated simulation blueprints.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_blueprint {
    /** @var array Supported simulation stage kinds. */
    private const STAGE_KINDS = ['briefing', 'evidence', 'decision', 'prioritise', 'reflection'];

    /** @var array Supported evidence presentation kinds. */
    private const EVIDENCE_KINDS = ['document', 'message', 'record', 'alert'];

    /**
     * Return the strict Structured Outputs schema sent to OpenAI.
     *
     * @return array
     */
    public static function get_json_schema(): array {
        $choiceproperties = [
            'text' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
            'quality' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
            'consequence' => ['type' => 'string', 'maxLength' => 1000],
            'next_key' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 40],
            'safety_impact' => ['type' => 'integer', 'minimum' => -100, 'maximum' => 100],
            'compliance_impact' => ['type' => 'integer', 'minimum' => -100, 'maximum' => 100],
            'trust_impact' => ['type' => 'integer', 'minimum' => -100, 'maximum' => 100],
            'resources_impact' => ['type' => 'integer', 'minimum' => -100, 'maximum' => 100],
            'target_rank' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 4],
        ];
        $evidenceproperties = [
            'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
            'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 3000],
            'kind' => ['type' => 'string', 'enum' => self::EVIDENCE_KINDS],
            'critical' => ['type' => 'boolean'],
        ];
        $stageproperties = [
            'key' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 40],
            'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
            'kind' => ['type' => 'string', 'enum' => self::STAGE_KINDS],
            'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 5000],
            'prompt' => ['type' => 'string', 'maxLength' => 2000],
            'points' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1000],
            'next_key' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 40],
            'require_all_critical' => ['type' => 'boolean'],
            'choices' => [
                'type' => 'array',
                'maxItems' => 4,
                'items' => [
                    'type' => 'object',
                    'properties' => $choiceproperties,
                    'required' => array_keys($choiceproperties),
                    'additionalProperties' => false,
                ],
            ],
            'evidence' => [
                'type' => 'array',
                'maxItems' => 4,
                'items' => [
                    'type' => 'object',
                    'properties' => $evidenceproperties,
                    'required' => array_keys($evidenceproperties),
                    'additionalProperties' => false,
                ],
            ],
        ];
        $rootproperties = [
            'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
            'summary' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2000],
            'mission_label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
            'stages' => [
                'type' => 'array',
                'minItems' => 2,
                'maxItems' => 10,
                'items' => [
                    'type' => 'object',
                    'properties' => $stageproperties,
                    'required' => array_keys($stageproperties),
                    'additionalProperties' => false,
                ],
            ],
        ];
        return [
            'type' => 'object',
            'properties' => $rootproperties,
            'required' => array_keys($rootproperties),
            'additionalProperties' => false,
        ];
    }

    /**
     * Validate and normalize an untrusted decoded blueprint.
     *
     * All returned content is plain text and all routes are forward-only.
     *
     * @param array $blueprint
     * @return array
     */
    public static function validate(array $blueprint): array {
        $stagesinput = $blueprint['stages'] ?? null;
        if (!is_array($stagesinput) || count($stagesinput) < 2 || count($stagesinput) > 10) {
            self::invalid('A simulation must contain between 2 and 10 stages.');
        }
        $normalized = [
            'title' => self::plain($blueprint['title'] ?? '', 255, 'simulation title'),
            'summary' => self::plain($blueprint['summary'] ?? '', 2000, 'simulation summary'),
            'mission_label' => self::plain($blueprint['mission_label'] ?? '', 255, 'mission label'),
            'stages' => [],
        ];
        $keys = [];
        foreach ($stagesinput as $index => $stageinput) {
            if (!is_array($stageinput)) {
                self::invalid('Every stage must be an object.');
            }
            $key = trim((string) ($stageinput['key'] ?? ''));
            if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key)) {
                self::invalid('Stage keys must use lowercase letters, numbers and underscores.');
            }
            if (isset($keys[$key])) {
                self::invalid('Every stage key must be unique.');
            }
            $keys[$key] = $index;
            $kind = (string) ($stageinput['kind'] ?? '');
            if (!in_array($kind, self::STAGE_KINDS, true)) {
                self::invalid('The blueprint contains an unsupported stage type.');
            }
            $points = self::number($stageinput['points'] ?? 0, 0, 1000, 'stage points');
            if (in_array($kind, ['briefing', 'reflection'], true)) {
                $points = 0.0;
            }
            $stage = [
                'key' => $key,
                'title' => self::plain($stageinput['title'] ?? '', 255, 'stage title'),
                'kind' => $kind,
                'body' => self::plain($stageinput['body'] ?? '', 5000, 'stage body'),
                'prompt' => self::optional_plain($stageinput['prompt'] ?? '', 2000),
                'points' => $points,
                'next_key' => trim((string) ($stageinput['next_key'] ?? 'NEXT')),
                'require_all_critical' => $kind === 'evidence'
                    && self::boolean($stageinput['require_all_critical'] ?? false),
                'choices' => [],
                'evidence' => [],
            ];
            if ($kind === 'decision' || $kind === 'prioritise') {
                $stage['choices'] = self::validate_choices($stageinput['choices'] ?? null, $kind);
            }
            if ($kind === 'evidence') {
                $stage['evidence'] = self::validate_evidence($stageinput['evidence'] ?? null);
                if ($stage['require_all_critical']
                        && !array_filter($stage['evidence'], static fn($item) => $item['critical'])) {
                    $stage['evidence'][0]['critical'] = true;
                }
            }
            $normalized['stages'][] = $stage;
        }
        $scored = array_filter($normalized['stages'], static fn($stage) => $stage['points'] > 0);
        if (!$scored) {
            self::invalid('At least one evidence, decision or prioritisation stage must award points.');
        }
        self::normalize_routes($normalized['stages'], $keys);
        return $normalized;
    }

    /**
     * Validate decision or prioritisation choices.
     *
     * @param mixed $choicesinput
     * @param string $kind
     * @return array
     */
    private static function validate_choices($choicesinput, string $kind): array {
        if (!is_array($choicesinput) || count($choicesinput) < 2 || count($choicesinput) > 4) {
            self::invalid('Decision and prioritisation stages require between 2 and 4 choices.');
        }
        $choices = [];
        foreach ($choicesinput as $choiceinput) {
            if (!is_array($choiceinput)) {
                self::invalid('Every choice must be an object.');
            }
            $choices[] = [
                'text' => self::plain($choiceinput['text'] ?? '', 500, 'choice text'),
                'quality' => $kind === 'decision'
                    ? self::number($choiceinput['quality'] ?? 0, 0, 100, 'choice quality') : 0.0,
                'consequence' => $kind === 'decision'
                    ? self::plain($choiceinput['consequence'] ?? '', 1000, 'choice consequence') : '',
                'next_key' => trim((string) ($choiceinput['next_key'] ?? 'NEXT')),
                'safety_impact' => $kind === 'decision'
                    ? self::integer($choiceinput['safety_impact'] ?? 0, -100, 100, 'safety impact') : 0,
                'compliance_impact' => $kind === 'decision'
                    ? self::integer($choiceinput['compliance_impact'] ?? 0, -100, 100, 'compliance impact') : 0,
                'trust_impact' => $kind === 'decision'
                    ? self::integer($choiceinput['trust_impact'] ?? 0, -100, 100, 'trust impact') : 0,
                'resources_impact' => $kind === 'decision'
                    ? self::integer($choiceinput['resources_impact'] ?? 0, -100, 100, 'resources impact') : 0,
                'target_rank' => $kind === 'prioritise'
                    ? self::integer($choiceinput['target_rank'] ?? 0, 1, count($choicesinput), 'correct priority') : 0,
            ];
        }
        if ($kind === 'decision' && max(array_column($choices, 'quality')) < 100) {
            self::invalid('Every decision stage requires one best choice with a quality of 100.');
        }
        if ($kind === 'prioritise') {
            $ranks = array_column($choices, 'target_rank');
            sort($ranks);
            if ($ranks !== range(1, count($choices))) {
                self::invalid('Correct priorities must use every rank exactly once.');
            }
        }
        return $choices;
    }

    /**
     * Validate evidence items.
     *
     * @param mixed $evidenceinput
     * @return array
     */
    private static function validate_evidence($evidenceinput): array {
        if (!is_array($evidenceinput) || count($evidenceinput) < 1 || count($evidenceinput) > 4) {
            self::invalid('Evidence stages require between 1 and 4 evidence items.');
        }
        $evidence = [];
        foreach ($evidenceinput as $item) {
            if (!is_array($item) || !in_array($item['kind'] ?? '', self::EVIDENCE_KINDS, true)) {
                self::invalid('The blueprint contains an unsupported evidence type.');
            }
            $evidence[] = [
                'title' => self::plain($item['title'] ?? '', 255, 'evidence title'),
                'body' => self::plain($item['body'] ?? '', 3000, 'evidence body'),
                'kind' => $item['kind'],
                'critical' => self::boolean($item['critical'] ?? false),
            ];
        }
        return $evidence;
    }

    /**
     * Convert unsafe or backward routes into deterministic forward routes.
     *
     * @param array $stages
     * @param array $keys
     * @return void
     */
    private static function normalize_routes(array &$stages, array $keys): void {
        $lastindex = count($stages) - 1;
        foreach ($stages as $index => &$stage) {
            $stage['next_key'] = self::normalize_route($stage['next_key'], $index, $lastindex, $keys);
            foreach ($stage['choices'] as &$choice) {
                $choice['next_key'] = self::normalize_route($choice['next_key'], $index, $lastindex, $keys);
            }
            unset($choice);
        }
        unset($stage);
    }

    /**
     * Normalize one route target.
     *
     * @param string $route
     * @param int $index
     * @param int $lastindex
     * @param array $keys
     * @return string
     */
    private static function normalize_route(string $route, int $index, int $lastindex, array $keys): string {
        if ($route === 'END') {
            return 'END';
        }
        if ($route === 'NEXT') {
            return $index < $lastindex ? 'NEXT' : 'END';
        }
        if (!isset($keys[$route]) || $keys[$route] <= $index) {
            return $index < $lastindex ? 'NEXT' : 'END';
        }
        return $route;
    }

    /** @param mixed $value @return bool */
    private static function boolean($value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    /**
     * Validate a plain-text value.
     *
     * @param mixed $value
     * @param int $maxlength
     * @param string $label
     * @return string
     */
    private static function plain($value, int $maxlength, string $label): string {
        $value = self::optional_plain($value, $maxlength);
        if ($value === '') {
            self::invalid('The ' . $label . ' cannot be empty.');
        }
        return $value;
    }

    /** @param mixed $value @param int $maxlength @return string */
    private static function optional_plain($value, int $maxlength): string {
        if (!is_scalar($value)) {
            self::invalid('Generated text has an invalid value type.');
        }
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');
        return \core_text::substr($value, 0, $maxlength);
    }

    /** @param mixed $value @param float $min @param float $max @param string $label @return float */
    private static function number($value, float $min, float $max, string $label): float {
        if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            self::invalid('The generated ' . $label . ' is outside the allowed range.');
        }
        return (float) $value;
    }

    /** @param mixed $value @param int $min @param int $max @param string $label @return int */
    private static function integer($value, int $min, int $max, string $label): int {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < $min || (int) $value > $max) {
            self::invalid('The generated ' . $label . ' is outside the allowed range.');
        }
        return (int) $value;
    }

    /** @param string $detail @return never */
    private static function invalid(string $detail): never {
        throw new \moodle_exception('aiblueprintinvalid', 'mod_decisionforge', '', $detail);
    }
}
