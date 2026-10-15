<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_decisionforge;

use mod_decisionforge\local\model_name;

/**
 * Provider model-name validation tests.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_decisionforge\local\model_name
 */
final class model_setting_test extends \basic_testcase {
    /** Safe provider model identifiers are accepted without altering them. */
    public function test_valid_model_names_are_accepted(): void {
        $this->assertTrue(model_name::is_valid('gpt-5.6'));
        $this->assertTrue(model_name::is_valid('gemini-3.6-flash'));
        $this->assertTrue(model_name::is_valid('gpt-5.6:custom_01'));
        $this->assertTrue(model_name::is_valid('model.snapshot-2026_08_11'));
    }

    /** Whitespace, URLs, path separators and oversized values are rejected. */
    public function test_unsafe_or_malformed_model_names_are_rejected(): void {
        $this->assertFalse(model_name::is_valid(''));
        $this->assertFalse(model_name::is_valid('gemini 3.6 flash'));
        $this->assertFalse(model_name::is_valid('https://example.test/model'));
        $this->assertFalse(model_name::is_valid('models/gemini-3.6-flash'));
        $this->assertFalse(model_name::is_valid(str_repeat('a', 101)));
    }
}
