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
 * Normalise values returned by Moodle editor form elements.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_value {
    /**
     * Extract editor text from supported Moodle form representations.
     *
     * @param mixed $value
     * @return string
     */
    public static function text(mixed $value): string {
        if (is_array($value)) {
            $value = $value['text'] ?? '';
        } elseif (is_object($value)) {
            $value = $value->text ?? '';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Extract a valid Moodle text format.
     *
     * @param mixed $value
     * @param int $default
     * @return int
     */
    public static function format(mixed $value, int $default = FORMAT_HTML): int {
        if (is_array($value)) {
            $value = $value['format'] ?? $default;
        } elseif (is_object($value)) {
            $value = $value->format ?? $default;
        } else {
            return $default;
        }

        $format = is_numeric($value) ? (int) $value : $default;
        return in_array($format, [FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN, FORMAT_MARKDOWN], true)
            ? $format
            : $default;
    }

    /**
     * Whether editor HTML contains meaningful text or media.
     *
     * Empty editor markup, non-breaking spaces and zero-width characters do
     * not count as content. Standard media elements count even without a text
     * caption; Moodle still cleans the stored HTML when rendering it.
     *
     * @param mixed $value
     * @return bool
     */
    public static function has_content(mixed $value): bool {
        $html = self::text($value);
        if (preg_match('/<(?:audio|embed|iframe|img|object|picture|video)\b/i', $html)) {
            return true;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $text);
        return $text !== null && $text !== '';
    }

    /**
     * Backwards-compatible alias for content checks.
     *
     * @param mixed $value
     * @return bool
     */
    public static function has_text(mixed $value): bool {
        return self::has_content($value);
    }
}
