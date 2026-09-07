<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_quiz\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_quiz\local\timer\timer_state_exporter;
use mod_quiz\quiz_attempt;

/**
 * Webservice returning server-authoritative quiz timer state.
 *
 * @package   mod_quiz
 * @copyright 2026 SSYSTEMS GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_timer_state extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
            'page' => new external_value(PARAM_INT, 'Current page (reserved)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * @param int $attemptid
     * @param int $page
     * @return array
     */
    public static function execute(int $attemptid, int $page = 0): array {
        ['attemptid' => $attemptid, 'page' => $page] = self::validate_parameters(
            self::execute_parameters(),
            ['attemptid' => $attemptid, 'page' => $page]
        );
        unset($page);

        $attemptobj = quiz_attempt::create($attemptid);
        self::validate_context($attemptobj->get_context());

        if (!$attemptobj->is_own_attempt() && !$attemptobj->is_preview_user()) {
            throw new \moodle_exception('notyourattempt', 'quiz', $attemptobj->view_url());
        }
        if (!$attemptobj->is_preview_user()) {
            $attemptobj->require_capability('mod/quiz:attempt');
        }

        return timer_state_exporter::export_for_attempt($attemptobj, time());
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $stagestructure = new external_single_structure([
            'endtime' => new external_value(PARAM_INT, 'Seconds remaining when this stage ends'),
            'display' => new external_value(PARAM_TEXT, 'Display label'),
            'name' => new external_value(PARAM_TEXT, 'Short stage name'),
        ]);

        return new external_single_structure([
            'timeleft' => new external_value(PARAM_INT, 'Seconds remaining, -1 if hidden'),
            'stages' => new external_multiple_structure($stagestructure, 'Stage list', VALUE_DEFAULT, []),
            'mustsubmitby' => new external_value(PARAM_TEXT, 'Close date hint', VALUE_DEFAULT, ''),
            'grantedextra' => new external_value(PARAM_BOOL, 'Extra time granted', VALUE_DEFAULT, false),
            'canhidetimer' => new external_value(PARAM_BOOL, 'Hide toggle allowed', VALUE_DEFAULT, true),
            'forceshowtimer' => new external_value(PARAM_BOOL, 'Force timer visible', VALUE_DEFAULT, false),
            'attemptstate' => new external_value(PARAM_ALPHA, 'Attempt state'),
            'ispreview' => new external_value(PARAM_BOOL, 'Preview attempt'),
        ]);
    }
}
