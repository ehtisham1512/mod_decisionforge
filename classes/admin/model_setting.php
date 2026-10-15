<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_decisionforge\admin;

/**
 * Admin setting that accepts safe provider model identifiers.
 *
 * Model aliases commonly contain dots and hyphens, while fine-tuned OpenAI
 * identifiers can also contain colons. URLs, spaces and shell metacharacters
 * are deliberately rejected.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_setting extends \admin_setting_configtext {
    /**
     * Validate a provider model identifier.
     *
     * @param mixed $data
     * @return true|string
     */
    public function validate($data) {
        $parentresult = parent::validate($data);
        if ($parentresult !== true) {
            return $parentresult;
        }
        if (!is_string($data) || !\mod_decisionforge\local\model_name::is_valid(trim($data))) {
            return get_string('aimodelnameinvalid', 'mod_decisionforge');
        }
        return true;
    }
}
