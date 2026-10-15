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

use mod_decisionforge\local\editor_value;

/**
 * Editor value normalisation tests.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_decisionforge\local\editor_value
 */
final class editor_value_test extends \basic_testcase {
    /**
     * Moodle editor arrays retain their content and format.
     *
     * @return void
     */
    public function test_array_value_is_normalised(): void {
        $value = ['text' => '<p>Call the coastguard.</p>', 'format' => FORMAT_HTML];

        $this->assertSame('<p>Call the coastguard.</p>', editor_value::text($value));
        $this->assertSame(FORMAT_HTML, editor_value::format($value));
        $this->assertTrue(editor_value::has_content($value));
    }

    /**
     * Object and scalar values remain compatible with alternate form output.
     *
     * @return void
     */
    public function test_object_and_scalar_values_are_supported(): void {
        $object = (object) ['text' => 'Stop the vessel.', 'format' => FORMAT_PLAIN];

        $this->assertSame('Stop the vessel.', editor_value::text($object));
        $this->assertSame(FORMAT_PLAIN, editor_value::format($object));
        $this->assertTrue(editor_value::has_content('Alert the crew.'));
    }

    /**
     * Media-only editor values are valid assessment content.
     *
     * @return void
     */
    public function test_media_only_values_are_supported(): void {
        $this->assertTrue(editor_value::has_content(
            ['text' => '<iframe src="https://example.com/video" title="Safety video"></iframe>',
                'format' => FORMAT_HTML]
        ));
        $this->assertTrue(editor_value::has_content('<video controls><source src="briefing.mp4"></video>'));
        $this->assertTrue(editor_value::has_content('<audio controls src="briefing.mp3"></audio>'));
        $this->assertTrue(editor_value::has_content('<p><img src="diagram.png" alt="Safety diagram"></p>'));
    }

    /**
     * Empty rich-editor markup must not create blank actions.
     *
     * @return void
     */
    public function test_empty_editor_markup_is_rejected(): void {
        $this->assertFalse(editor_value::has_content(['text' => '<p><br></p>', 'format' => FORMAT_HTML]));
        $this->assertFalse(editor_value::has_content(['text' => '<p>&nbsp;&#8203;</p>', 'format' => FORMAT_HTML]));
        $this->assertFalse(editor_value::has_content(null));
    }

    /**
     * Invalid formats safely fall back to HTML.
     *
     * @return void
     */
    public function test_invalid_format_uses_default(): void {
        $this->assertSame(FORMAT_HTML, editor_value::format(['text' => 'Action', 'format' => 999]));
    }
}
