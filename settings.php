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

/**
 * Administration settings.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configselect(
        'decisionforge/maxcases',
        get_string('maxcases', 'mod_decisionforge'),
        get_string('maxcases_help', 'mod_decisionforge'),
        50,
        [
            10 => 10,
            25 => 25,
            50 => 50,
            100 => 100,
            200 => 200,
        ]
    ));

    $settings->add(new admin_setting_heading(
        'mod_decisionforge/aiheading',
        get_string('aisettings', 'mod_decisionforge'),
        get_string('aisettings_help', 'mod_decisionforge')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'mod_decisionforge/aienabled',
        get_string('aienabled', 'mod_decisionforge'),
        get_string('aienabled_help', 'mod_decisionforge'),
        0
    ));
    $settings->add(new admin_setting_configselect(
        'mod_decisionforge/aidefaultprovider',
        get_string('aidefaultprovider', 'mod_decisionforge'),
        get_string('aidefaultprovider_help', 'mod_decisionforge'),
        'openai',
        [
            'openai' => get_string('aiprovideropenai', 'mod_decisionforge'),
            'gemini' => get_string('aiprovidergemini', 'mod_decisionforge'),
        ]
    ));
    $settings->add(new admin_setting_heading(
        'mod_decisionforge/openaiheading',
        get_string('openaisettings', 'mod_decisionforge'),
        get_string('openaisettings_help', 'mod_decisionforge')
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_decisionforge/aiapikey',
        get_string('aiapikey', 'mod_decisionforge'),
        get_string('aiapikey_help', 'mod_decisionforge'),
        ''
    ));
    $settings->add(new \mod_decisionforge\admin\model_setting(
        'mod_decisionforge/aimodel',
        get_string('aimodel', 'mod_decisionforge'),
        get_string('aimodel_help', 'mod_decisionforge'),
        'gpt-5.6',
        PARAM_RAW_TRIMMED
    ));
    $settings->add(new admin_setting_heading(
        'mod_decisionforge/geminiheading',
        get_string('geminisettings', 'mod_decisionforge'),
        get_string('geminisettings_help', 'mod_decisionforge')
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_decisionforge/geminiapikey',
        get_string('geminiapikey', 'mod_decisionforge'),
        get_string('geminiapikey_help', 'mod_decisionforge'),
        ''
    ));
    $settings->add(new \mod_decisionforge\admin\model_setting(
        'mod_decisionforge/geminimodel',
        get_string('geminimodel', 'mod_decisionforge'),
        get_string('geminimodel_help', 'mod_decisionforge'),
        'gemini-3.6-flash',
        PARAM_RAW_TRIMMED
    ));
    $settings->add(new admin_setting_configselect(
        'mod_decisionforge/aimaxperhour',
        get_string('aimaxperhour', 'mod_decisionforge'),
        get_string('aimaxperhour_help', 'mod_decisionforge'),
        10,
        [1 => 1, 3 => 3, 5 => 5, 10 => 10, 20 => 20, 50 => 50]
    ));
    $settings->add(new admin_setting_configselect(
        'mod_decisionforge/aitimeout',
        get_string('aitimeout', 'mod_decisionforge'),
        get_string('aitimeout_help', 'mod_decisionforge'),
        90,
        [30 => '30', 60 => '60', 90 => '90', 120 => '120']
    ));
}
