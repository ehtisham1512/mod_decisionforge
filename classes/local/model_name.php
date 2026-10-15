<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_decisionforge\local;

/**
 * Shared provider model-name validation independent of administration libraries.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class model_name {
    /**
     * Check a provider model identifier without changing it.
     *
     * @param string $model
     * @return bool
     */
    public static function is_valid(string $model): bool {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/D', $model) === 1;
    }
}
