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
 * Decision Forge database upgrades.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade Decision Forge.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_decisionforge_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026080704) {
        // The current upgrade's final block queues the Gradebook repair after
        // every schema change, avoiding duplicate tasks on a multi-version jump.
        upgrade_mod_savepoint(true, 2026080704, 'decisionforge');
    }

    if ($oldversion < 2026081000) {
        $dbman = $DB->get_manager();
        $activitytable = new xmldb_table('decisionforge');
        $activityfields = [
            new xmldb_field('experiencemode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'quick', 'instructionsformat'),
            new xmldb_field('missionlabel', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null,
                'Live decision simulation', 'experiencemode'),
            new xmldb_field('initialsafety', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '72', 'requireconfidence'),
            new xmldb_field('initialcompliance', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '68', 'initialsafety'),
            new xmldb_field('initialtrust', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '74', 'initialcompliance'),
            new xmldb_field('initialresources', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '80', 'initialtrust'),
        ];
        foreach ($activityfields as $field) {
            if (!$dbman->field_exists($activitytable, $field)) {
                $dbman->add_field($activitytable, $field);
            }
        }

        $attempttable = new xmldb_table('decisionforge_attempts');
        $attemptfields = [
            new xmldb_field('mode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'quick', 'state'),
            new xmldb_field('currentstageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'currentcase'),
            new xmldb_field('snapshotjson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'currentstageid'),
            new xmldb_field('pathjson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'snapshotjson'),
            new xmldb_field('possiblepoints', XMLDB_TYPE_NUMBER, '12, 5', null, XMLDB_NOTNULL, null, '0', 'rawscore'),
            new xmldb_field('safety', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '72', 'finalgrade'),
            new xmldb_field('compliance', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '68', 'safety'),
            new xmldb_field('trust', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '74', 'compliance'),
            new xmldb_field('resources', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '80', 'trust'),
            new xmldb_field('outcomecode', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, '', 'resources'),
        ];
        foreach ($attemptfields as $field) {
            if (!$dbman->field_exists($attempttable, $field)) {
                $dbman->add_field($attempttable, $field);
            }
        }

        $stagestable = new xmldb_table('decisionforge_simstages');
        if (!$dbman->table_exists($stagestable)) {
            $stagestable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $stagestable->add_field('decisionforgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $stagestable->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $stagestable->add_field('stagekind', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'decision');
            $stagestable->add_field('body', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $stagestable->add_field('bodyformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $stagestable->add_field('prompt', XMLDB_TYPE_TEXT, null, null, null);
            $stagestable->add_field('promptformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $stagestable->add_field('points', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '10');
            $stagestable->add_field('position', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $stagestable->add_field('nextstageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $stagestable->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $stagestable->add_field('requireallcritical', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $stagestable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $stagestable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $stagestable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $stagestable->add_key('activity', XMLDB_KEY_FOREIGN, ['decisionforgeid'], 'decisionforge', ['id']);
            $stagestable->add_index('activityposition', XMLDB_INDEX_NOTUNIQUE, ['decisionforgeid', 'position']);
            $dbman->create_table($stagestable);
        }

        $choicestable = new xmldb_table('decisionforge_simchoices');
        if (!$dbman->table_exists($choicestable)) {
            $choicestable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $choicestable->add_field('stageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $choicestable->add_field('choicetext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $choicestable->add_field('choicetextformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $choicestable->add_field('quality', XMLDB_TYPE_NUMBER, '6, 2', null, XMLDB_NOTNULL, null, '0');
            $choicestable->add_field('consequence', XMLDB_TYPE_TEXT, null, null, null);
            $choicestable->add_field('consequenceformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $choicestable->add_field('nextstageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            foreach (['safetyimpact', 'complianceimpact', 'trustimpact', 'resourcesimpact'] as $name) {
                $choicestable->add_field($name, XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            }
            $choicestable->add_field('targetrank', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $choicestable->add_field('position', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $choicestable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $choicestable->add_key('stage', XMLDB_KEY_FOREIGN, ['stageid'], 'decisionforge_simstages', ['id']);
            $choicestable->add_index('stageposition', XMLDB_INDEX_NOTUNIQUE, ['stageid', 'position']);
            $dbman->create_table($choicestable);
        }

        $evidencetable = new xmldb_table('decisionforge_simevidence');
        if (!$dbman->table_exists($evidencetable)) {
            $evidencetable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $evidencetable->add_field('stageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $evidencetable->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $evidencetable->add_field('body', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $evidencetable->add_field('bodyformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $evidencetable->add_field('evidencekind', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'document');
            $evidencetable->add_field('critical', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $evidencetable->add_field('position', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $evidencetable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $evidencetable->add_key('stage', XMLDB_KEY_FOREIGN, ['stageid'], 'decisionforge_simstages', ['id']);
            $evidencetable->add_index('stageposition', XMLDB_INDEX_NOTUNIQUE, ['stageid', 'position']);
            $dbman->create_table($evidencetable);
        }

        $responsetable = new xmldb_table('decisionforge_simresponses');
        if (!$dbman->table_exists($responsetable)) {
            $responsetable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $responsetable->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $responsetable->add_field('stageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $responsetable->add_field('responsesequence', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $responsetable->add_field('stagekind', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'decision');
            $responsetable->add_field('choiceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            foreach (['response', 'rankingjson', 'openedjson'] as $name) {
                $responsetable->add_field($name, XMLDB_TYPE_TEXT, null, null, null);
            }
            $responsetable->add_field('score', XMLDB_TYPE_NUMBER, '12, 5', null, XMLDB_NOTNULL, null, '0');
            $responsetable->add_field('maxscore', XMLDB_TYPE_NUMBER, '12, 5', null, XMLDB_NOTNULL, null, '0');
            $responsetable->add_field('consequence', XMLDB_TYPE_TEXT, null, null, null);
            foreach (['safety', 'compliance', 'trust', 'resources'] as $name) {
                $responsetable->add_field($name, XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
            }
            $responsetable->add_field('timeanswered', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $responsetable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $responsetable->add_key('attempt', XMLDB_KEY_FOREIGN, ['attemptid'], 'decisionforge_attempts', ['id']);
            $responsetable->add_index('attemptsequence', XMLDB_INDEX_UNIQUE, ['attemptid', 'responsesequence']);
            $responsetable->add_index('attemptstage', XMLDB_INDEX_NOTUNIQUE, ['attemptid', 'stageid']);
            $dbman->create_table($responsetable);
        }

        $task = new \mod_decisionforge\task\repair_gradebook();
        $task->set_custom_data(['reason' => 'simulationupgrade']);
        \core\task\manager::queue_adhoc_task($task, true);
        upgrade_mod_savepoint(true, 2026081000, 'decisionforge');
    }

    if ($oldversion < 2026081001) {
        // No database change is required. This release replaces unsupported
        // QuickForm "number" elements with Moodle-compatible text controls.
        upgrade_mod_savepoint(true, 2026081001, 'decisionforge');
    }

    if ($oldversion < 2026081002) {
        // No database change is required. This release normalises optional
        // activity settings before writes so null core form values remain safe.
        upgrade_mod_savepoint(true, 2026081002, 'decisionforge');
    }

    if ($oldversion < 2026081003) {
        // No database change is required. This release improves evidence-card
        // review state and validation feedback in the learner workspace.
        upgrade_mod_savepoint(true, 2026081003, 'decisionforge');
    }

    if ($oldversion < 2026081004) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('decisionforge_aidrafts');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('decisionforgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('prompt', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('settingsjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('draftjson', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('provider', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'openai');
            $table->add_field('model', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('responseid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('errormessage', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('activity', XMLDB_KEY_FOREIGN, ['decisionforgeid'], 'decisionforge', ['id']);
            $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('activitystatus', XMLDB_INDEX_NOTUNIQUE, ['decisionforgeid', 'status']);
            $table->add_index('usertime', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026081004, 'decisionforge');
    }

    if ($oldversion < 2026081005) {
        // No database change is required. This release adds selectable OpenAI
        // and Gemini providers plus guided multilingual generation controls.
        upgrade_mod_savepoint(true, 2026081005, 'decisionforge');
    }

    if ($oldversion < 2026081100) {
        // No database change is required. This release accepts current provider
        // model aliases and reports the selected provider, model and key source.
        upgrade_mod_savepoint(true, 2026081100, 'decisionforge');
    }

    if ($oldversion < 2026081101) {
        // No database change is required. This release aligns Gemini structured
        // output requests and adds complete Spanish and Russian language packs.
        upgrade_mod_savepoint(true, 2026081101, 'decisionforge');
    }

    if ($oldversion < 2026081102) {
        // No database change is required. This release moves Gemini to JSON
        // mode plus strict local blueprint validation to avoid schema limits.
        upgrade_mod_savepoint(true, 2026081102, 'decisionforge');
    }

    if ($oldversion < 2026081103) {
        // No database change is required. This release serialises simulation
        // submissions and safely redirects repeated or timeout-raced requests.
        upgrade_mod_savepoint(true, 2026081103, 'decisionforge');
    }

    if ($oldversion < 2026081200) {
        // No database change is required. This release normalises rich-editor
        // action values before Quick Decision Check validation and storage.
        upgrade_mod_savepoint(true, 2026081200, 'decisionforge');
    }

    if ($oldversion < 2026081201) {
        // No database change is required. This release recognises secure
        // media-only Quick Decision Check actions and displays them responsively.
        upgrade_mod_savepoint(true, 2026081201, 'decisionforge');
    }

    if ($oldversion < 2026081202) {
        // No database change is required. This release prevents an edited
        // case record ID from replacing the course-module ID on form submit.
        upgrade_mod_savepoint(true, 2026081202, 'decisionforge');
    }

    if ($oldversion < 2026081203) {
        // No database change is required. This release adds secure AI-assisted
        // authoring, review and transactional import for Quick Assessments.
        upgrade_mod_savepoint(true, 2026081203, 'decisionforge');
    }

    return true;
}
