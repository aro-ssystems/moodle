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

namespace mod_quiz\local\timer;

use mod_quiz\quiz_attempt;
use stdClass;

/**
 * Builds the unified timer state payload for renderer bootstrap and webservices.
 *
 * @package   mod_quiz
 * @copyright 2026 SSYSTEMS GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timer_state_exporter {

    /** @var int Seconds remaining when force-show applies. */
    private const FORCE_SHOW_THRESHOLD = 100;

    /**
     * Export timer state for an in-progress or preview attempt.
     *
     * @param quiz_attempt $attemptobj
     * @param int $timenow
     * @return array Timer state matching contracts/get_timer_state.md
     */
    public static function export_for_attempt(quiz_attempt $attemptobj, int $timenow): array {
        $attempt = $attemptobj->get_attempt();
        $timeleft = $attemptobj->get_time_left_display($timenow);

        $state = [
            'attemptstate' => $attemptobj->get_state(),
            'ispreview' => (bool) $attemptobj->is_preview(),
        ];

        if ($timeleft === false) {
            return array_merge($state, [
                'timeleft' => -1,
                'stages' => [],
            ]);
        }

        // Overdue timed attempts: core may return -N seconds past end. Learner-facing
        // expiry/UI must see 0 (expired), never a negative conflated with -1 (no timer).
        if ($timeleft < 0) {
            $timeleft = 0;
        }

        $quiz = $attemptobj->get_quiz();
        [$stages, $mustsubmitby, $grantedextra] = self::build_stages(
            $quiz,
            $attempt,
            $timenow
        );

        $forceshowtimer = $timeleft < self::FORCE_SHOW_THRESHOLD;

        return array_merge($state, [
            'timeleft' => (int) $timeleft,
            'stages' => $stages,
            'mustsubmitby' => $mustsubmitby,
            'grantedextra' => $grantedextra,
            'canhidetimer' => !$forceshowtimer,
            'forceshowtimer' => $forceshowtimer,
        ]);
    }

    /**
     * Build stage list for standard or multi-stage quizzes.
     *
     * @param stdClass $quiz
     * @param stdClass $attempt
     * @param int $timenow
     * @return array [$stages, $mustsubmitby, $grantedextra]
     */
    protected static function build_stages(
        stdClass $quiz,
        stdClass $attempt,
        int $timenow
    ): array {
        $rawstages = stage_settings::load_for_quiz((int) $quiz->id);
        if (!$rawstages) {
            $label = get_string('timeleft', 'quiz');
            return [[
                [
                    'endtime' => 0,
                    'display' => $label,
                    'name' => $label,
                ],
            ], '', false];
        }

        $stagesconfig = stage_settings::db_to_array($rawstages);
        [$stagesconfig, $timeadjust] = stage_settings::change_total_time($stagesconfig, (int) $quiz->timelimit);

        $mustsubmitby = '';
        $timeclose = (int) ($quiz->timeclose ?? 0);
        if ($attempt->timestart &&
                $timeclose > $timenow &&
                $timeclose < $attempt->timestart + stage_settings::get_total_time($stagesconfig)) {
            $stagesconfig = stage_settings::change_total_time_at_end(
                $stagesconfig,
                $timeclose - $attempt->timestart
            );
            $mustsubmitby = get_string(
                'timerstagemustsubmitby',
                'quiz',
                userdate($timeclose, get_string('strftimedatetime', 'langconfig'))
            );
        }

        $displaylabels = self::format_stage_labels($stagesconfig, $timeadjust);

        $stages = [];
        $boundaryfromstart = stage_settings::get_total_time($stagesconfig);
        foreach ($stagesconfig as $index => [$duration]) {
            $boundaryfromstart -= $duration;
            $stages[] = [
                'endtime' => max(0, $boundaryfromstart),
                'display' => $displaylabels[$index],
                'name' => self::stage_short_name($stagesconfig[$index][1] ?? ''),
            ];
        }

        return [$stages, $mustsubmitby, $timeadjust > 0];
    }

    /**
     * Human-readable stage labels (OU timestage format).
     *
     * @param array $stagesconfig
     * @param int $timeadjust
     * @return string[]
     */
    protected static function format_stage_labels(array $stagesconfig, int $timeadjust): array {
        $labels = [];
        foreach ($stagesconfig as $index => [$time, $name]) {
            if ($time == 0) {
                $displaytime = get_string('timerstageunavailable', 'quiz');
            } else {
                $displaytime = format_time($time);
            }
            $label = get_string('timerstageline', 'quiz', [
                'name' => s($name),
                'duration' => $displaytime,
            ]);
            if ($index === 0 && $timeadjust > 0) {
                $label .= ' ' . get_string('timerstageextra', 'quiz', format_time($timeadjust));
            }
            $labels[] = $label;
        }
        return $labels;
    }

    /**
     * Short name for a stage (strip quotes from OU config).
     *
     * @param string $name
     * @return string
     */
    protected static function stage_short_name(string $name): string {
        return str_replace('"', '', $name);
    }
}
