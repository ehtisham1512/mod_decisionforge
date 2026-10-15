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

defined('MOODLE_INTERNAL') || die();

/**
 * Strict contract and defensive validator for AI-generated quick cases.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quick_ai_blueprint {
    /** @var array Allowed option quality values. */
    private const QUALITIES = [0.0, 25.0, 50.0, 75.0, 100.0];

    /**
     * Return the strict provider contract.
     *
     * @return array
     */
    public static function get_json_schema(): array {
        $optionproperties = [
            'text' => ['type' => 'string'],
            'quality' => ['type' => 'number', 'enum' => self::QUALITIES],
            'consequence' => ['type' => 'string'],
        ];
        $caseproperties = [
            'title' => ['type' => 'string'],
            'scenario' => ['type' => 'string'],
            'question' => ['type' => 'string'],
            'points' => ['type' => 'number', 'minimum' => 0.01, 'maximum' => 10000],
            'options' => [
                'type' => 'array',
                'minItems' => 2,
                'maxItems' => 4,
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => $optionproperties,
                    'required' => array_keys($optionproperties),
                ],
            ],
        ];
        $rootproperties = [
            'title' => ['type' => 'string'],
            'summary' => ['type' => 'string'],
            'cases' => [
                'type' => 'array',
                'minItems' => 1,
                'maxItems' => 10,
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => $caseproperties,
                    'required' => array_keys($caseproperties),
                ],
            ],
        ];
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $rootproperties,
            'required' => array_keys($rootproperties),
        ];
    }

    /**
     * Validate, clean and normalize one quick-assessment blueprint.
     *
     * @param array $input
     * @return array
     */
    public static function validate(array $input): array {
        if (!is_array($input['cases'] ?? null)
                || count($input['cases']) < 1 || count($input['cases']) > 10) {
            self::invalid('A quick assessment requires between 1 and 10 cases.');
        }
        $normalized = [
            'title' => self::plain($input['title'] ?? '', 255, 'draft title'),
            'summary' => self::plain($input['summary'] ?? '', 1000, 'draft summary'),
            'cases' => [],
        ];
        foreach ($input['cases'] as $caseinput) {
            if (!is_array($caseinput)) {
                self::invalid('Every case must be an object.');
            }
            $optionsinput = $caseinput['options'] ?? null;
            if (!is_array($optionsinput) || count($optionsinput) < 2 || count($optionsinput) > 4) {
                self::invalid('Every quick case requires between 2 and 4 actions.');
            }
            $options = [];
            foreach ($optionsinput as $optioninput) {
                if (!is_array($optioninput)) {
                    self::invalid('Every action must be an object.');
                }
                $quality = self::number($optioninput['quality'] ?? null, 'action quality');
                if (!in_array($quality, self::QUALITIES, true)) {
                    self::invalid('Action quality must be 0, 25, 50, 75 or 100.');
                }
                $options[] = [
                    'text' => self::plain($optioninput['text'] ?? '', 1000, 'action text'),
                    'quality' => $quality,
                    'consequence' => self::plain(
                        $optioninput['consequence'] ?? '',
                        1500,
                        'action consequence'
                    ),
                ];
            }
            if (max(array_column($options, 'quality')) < 100) {
                self::invalid('Every quick case requires at least one best action worth 100%.');
            }
            $points = self::number($caseinput['points'] ?? null, 'case points');
            if ($points <= 0 || $points > 10000) {
                self::invalid('Case points must be greater than zero and no more than 10000.');
            }
            $normalized['cases'][] = [
                'title' => self::plain($caseinput['title'] ?? '', 255, 'case title'),
                'scenario' => self::plain($caseinput['scenario'] ?? '', 5000, 'case scenario'),
                'question' => self::plain($caseinput['question'] ?? '', 500, 'case question'),
                'points' => $points,
                'options' => $options,
            ];
        }
        return $normalized;
    }

    /**
     * Return safe non-empty plain text within a length limit.
     *
     * @param mixed $value
     * @param int $maxlength
     * @param string $label
     * @return string
     */
    private static function plain($value, int $maxlength, string $label): string {
        if (!is_scalar($value)) {
            self::invalid('Invalid ' . $label . '.');
        }
        $text = clean_param(html_entity_decode(strip_tags((string) $value)), PARAM_TEXT);
        $text = trim($text);
        if ($text === '' || \core_text::strlen($text) > $maxlength) {
            self::invalid('Invalid ' . $label . '.');
        }
        return $text;
    }

    /** @param mixed $value @param string $label @return float */
    private static function number($value, string $label): float {
        if (!is_numeric($value) || !is_finite((float) $value)) {
            self::invalid('Invalid ' . $label . '.');
        }
        return (float) $value;
    }

    /** @param string $detail @return never */
    private static function invalid(string $detail): never {
        throw new \moodle_exception('aiquickblueprintinvalid', 'mod_decisionforge', '', $detail);
    }
}
