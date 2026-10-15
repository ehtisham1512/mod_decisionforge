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

namespace mod_decisionforge\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for Decision Forge.
 *
 * @package    mod_decisionforge
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe stored user data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('decisionforge_attempts', [
            'userid' => 'privacy:metadata:decisionforge_attempts:userid',
            'attemptnumber' => 'privacy:metadata:decisionforge_attempts:attemptnumber',
            'state' => 'privacy:metadata:decisionforge_attempts:state',
            'mode' => 'privacy:metadata:decisionforge_attempts:mode',
            'pathjson' => 'privacy:metadata:decisionforge_attempts:path',
            'rawscore' => 'privacy:metadata:decisionforge_attempts:rawscore',
            'finalgrade' => 'privacy:metadata:decisionforge_attempts:finalgrade',
            'safety' => 'privacy:metadata:decisionforge_attempts:metrics',
            'compliance' => 'privacy:metadata:decisionforge_attempts:metrics',
            'trust' => 'privacy:metadata:decisionforge_attempts:metrics',
            'resources' => 'privacy:metadata:decisionforge_attempts:metrics',
            'outcomecode' => 'privacy:metadata:decisionforge_attempts:outcome',
            'timestart' => 'privacy:metadata:decisionforge_attempts:timestart',
            'timefinish' => 'privacy:metadata:decisionforge_attempts:timefinish',
        ], 'privacy:metadata:decisionforge_attempts');
        $collection->add_database_table('decisionforge_responses', [
            'justification' => 'privacy:metadata:decisionforge_responses:justification',
            'confidence' => 'privacy:metadata:decisionforge_responses:confidence',
            'score' => 'privacy:metadata:decisionforge_responses:score',
            'timeanswered' => 'privacy:metadata:decisionforge_responses:timeanswered',
        ], 'privacy:metadata:decisionforge_responses');
        $collection->add_database_table('decisionforge_simresponses', [
            'response' => 'privacy:metadata:decisionforge_simresponses:response',
            'rankingjson' => 'privacy:metadata:decisionforge_simresponses:ranking',
            'openedjson' => 'privacy:metadata:decisionforge_simresponses:opened',
            'score' => 'privacy:metadata:decisionforge_simresponses:score',
            'timeanswered' => 'privacy:metadata:decisionforge_simresponses:timeanswered',
        ], 'privacy:metadata:decisionforge_simresponses');
        $collection->add_database_table('decisionforge_aidrafts', [
            'userid' => 'privacy:metadata:decisionforge_aidrafts:userid',
            'prompt' => 'privacy:metadata:decisionforge_aidrafts:prompt',
            'settingsjson' => 'privacy:metadata:decisionforge_aidrafts:settings',
            'draftjson' => 'privacy:metadata:decisionforge_aidrafts:draft',
            'provider' => 'privacy:metadata:decisionforge_aidrafts:provider',
            'model' => 'privacy:metadata:decisionforge_aidrafts:model',
            'status' => 'privacy:metadata:decisionforge_aidrafts:status',
            'responseid' => 'privacy:metadata:decisionforge_aidrafts:responseid',
            'errormessage' => 'privacy:metadata:decisionforge_aidrafts:error',
            'timecreated' => 'privacy:metadata:decisionforge_aidrafts:timecreated',
        ], 'privacy:metadata:decisionforge_aidrafts');
        $collection->add_external_location_link('ai_provider', [
            'prompt' => 'privacy:metadata:ai_provider:prompt',
            'coursecontext' => 'privacy:metadata:ai_provider:coursecontext',
            'generationoptions' => 'privacy:metadata:ai_provider:generationoptions',
        ], 'privacy:metadata:ai_provider');
        $collection->add_subsystem_link('core_grades', [], 'privacy:metadata:core_grades');
        return $collection;
    }

    /**
     * Find module contexts containing data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid($userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {decisionforge} d ON d.id = cm.instance
                  JOIN {decisionforge_attempts} a ON a.decisionforgeid = d.id
                 WHERE a.userid = :userid";
        $params = ['contextlevel' => CONTEXT_MODULE, 'modname' => 'decisionforge', 'userid' => $userid];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        $draftsql = "SELECT ctx.id
                       FROM {context} ctx
                       JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                       JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                       JOIN {decisionforge} d ON d.id = cm.instance
                       JOIN {decisionforge_aidrafts} ad ON ad.decisionforgeid = d.id
                      WHERE ad.userid = :userid";
        $contextlist->add_from_sql($draftsql, $params);
        return $contextlist;
    }

    /**
     * Add users whose data exists in a module context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $params = ['contextid' => $context->id, 'contextlevel' => CONTEXT_MODULE, 'modname' => 'decisionforge'];
        $sql = "SELECT a.userid
                  FROM {decisionforge_attempts} a
                  JOIN {decisionforge} d ON d.id = a.decisionforgeid
                  JOIN {course_modules} cm ON cm.instance = d.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE ctx.id = :contextid";
        $userlist->add_from_sql('userid', $sql, $params);
        $draftsql = "SELECT ad.userid
                       FROM {decisionforge_aidrafts} ad
                       JOIN {decisionforge} d ON d.id = ad.decisionforgeid
                       JOIN {course_modules} cm ON cm.instance = d.id
                       JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                       JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                      WHERE ctx.id = :contextid";
        $userlist->add_from_sql('userid', $draftsql, $params);
    }

    /**
     * Export a user's attempts and responses.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('decisionforge', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $activity = $DB->get_record('decisionforge', ['id' => $cm->instance]);
            if (!$activity) {
                continue;
            }
            $attempts = $DB->get_records('decisionforge_attempts', [
                'decisionforgeid' => $activity->id,
                'userid' => $userid,
            ], 'attemptnumber ASC');
            $export = [];
            foreach ($attempts as $attempt) {
                $responses = $DB->get_records('decisionforge_responses', ['attemptid' => $attempt->id], 'timeanswered ASC');
                $simresponses = $DB->get_records('decisionforge_simresponses',
                    ['attemptid' => $attempt->id], 'responsesequence ASC');
                $export[] = (object) [
                    'attemptnumber' => $attempt->attemptnumber,
                    'state' => $attempt->state,
                    'mode' => $attempt->mode ?? 'quick',
                    'simulationpath' => $attempt->pathjson,
                    'rawscore' => $attempt->rawscore,
                    'finalgrade' => $attempt->finalgrade,
                    'safety' => $attempt->safety,
                    'compliance' => $attempt->compliance,
                    'trust' => $attempt->trust,
                    'resources' => $attempt->resources,
                    'outcome' => $attempt->outcomecode,
                    'timestart' => transform::datetime($attempt->timestart),
                    'timefinish' => $attempt->timefinish ? transform::datetime($attempt->timefinish) : null,
                    'responses' => array_values(array_map(static function($response) {
                        return (object) [
                            'caseid' => $response->caseid,
                            'optionid' => $response->optionid,
                            'justification' => $response->justification,
                            'confidence' => $response->confidence,
                            'score' => $response->score,
                            'timeanswered' => transform::datetime($response->timeanswered),
                        ];
                    }, $responses)),
                    'simulationresponses' => array_values(array_map(static function($response) {
                        return (object) [
                            'stageid' => $response->stageid,
                            'stagekind' => $response->stagekind,
                            'choiceid' => $response->choiceid,
                            'response' => $response->response,
                            'ranking' => $response->rankingjson,
                            'openedevidence' => $response->openedjson,
                            'score' => $response->score,
                            'timeanswered' => transform::datetime($response->timeanswered),
                        ];
                    }, $simresponses)),
                ];
            }
            $draftrecords = $DB->get_records('decisionforge_aidrafts', [
                'decisionforgeid' => $activity->id,
                'userid' => $userid,
            ], 'timecreated ASC');
            $drafts = array_values(array_map(static function($draft) {
                return (object) [
                    'prompt' => $draft->prompt,
                    'settings' => $draft->settingsjson,
                    'draft' => $draft->draftjson,
                    'provider' => $draft->provider,
                    'model' => $draft->model,
                    'status' => $draft->status,
                    'responseid' => $draft->responseid,
                    'error' => $draft->errormessage,
                    'timecreated' => transform::datetime($draft->timecreated),
                    'timemodified' => transform::datetime($draft->timemodified),
                ];
            }, $draftrecords));
            $data = (object) [
                'activity' => format_string($activity->name),
                'attempts' => $export,
                'aidrafts' => $drafts,
            ];
            writer::with_context($context)->export_data([get_string('attempts', 'mod_decisionforge')], $data);
        }
    }

    /**
     * Delete all user data in one module context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        self::delete_context_user_data($context, []);
    }

    /**
     * Delete data for one user in approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_context_user_data($context, [$contextlist->get_user()->id]);
        }
    }

    /**
     * Delete data for a list of users in one context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::delete_context_user_data($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Delete attempt trees in a module context, optionally restricted by user ids.
     *
     * @param context $context
     * @param array $userids Empty means all users.
     * @return void
     */
    private static function delete_context_user_data(context $context, array $userids): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('decisionforge', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $conditions = 'decisionforgeid = :activityid';
        $params = ['activityid' => $cm->instance];
        if ($userids) {
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
            $conditions .= " AND userid $usersql";
            $params += $userparams;
        }
        $DB->delete_records_select('decisionforge_aidrafts', $conditions, $params);
        $attemptids = $DB->get_fieldset_select('decisionforge_attempts', 'id', $conditions, $params);
        if (!$attemptids) {
            return;
        }
        $affectedusers = $DB->get_fieldset_select('decisionforge_attempts', 'DISTINCT userid', $conditions, $params);
        [$attemptsql, $attemptparams] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'attempt');
        $DB->delete_records_select('decisionforge_responses', "attemptid $attemptsql", $attemptparams);
        $DB->delete_records_select('decisionforge_simresponses', "attemptid $attemptsql", $attemptparams);
        $DB->delete_records_select('decisionforge_attempt_cases', "attemptid $attemptsql", $attemptparams);
        $DB->delete_records_select('decisionforge_attempts', "id $attemptsql", $attemptparams);

        require_once(__DIR__ . '/../../lib.php');
        $activity = $DB->get_record('decisionforge', ['id' => $cm->instance], '*', MUST_EXIST);
        foreach ($affectedusers as $userid) {
            decisionforge_update_grades($activity, (int) $userid);
            decisionforge_recalculate_completion($activity, (int) $userid);
        }
    }
}
