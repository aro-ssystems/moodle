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

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves effective timer network notification settings for a quiz attempt.
 *
 * @package   mod_quiz
 * @copyright 2026 SSYSTEMS GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_config {

    /**
     * Resolve effective notification flags for an attempt page render.
     *
     * @param \stdClass $quiz Quiz row (may include timernotifydegraded / timernotifysuccess).
     * @return array{showdegraded: bool, showsuccess: bool}
     */
    public static function resolve_for_quiz(\stdClass $quiz): array {
        $globaldegraded = get_config('quiz', 'timernotifydegraded');
        if ($globaldegraded === false) {
            $globaldegraded = 1;
        }
        $globalsuccess = get_config('quiz', 'timernotifysuccess');
        if ($globalsuccess === false) {
            $globalsuccess = 1;
        }

        $showdegraded = self::resolve_override($quiz->timernotifydegraded ?? null, (int) $globaldegraded);
        $globalsuccessbool = (bool) (int) $globalsuccess;
        if (!$showdegraded || !$globalsuccessbool) {
            $showsuccess = false;
        } else {
            $showsuccess = self::resolve_override($quiz->timernotifysuccess ?? null, 1);
        }

        return [
            'showdegraded' => $showdegraded,
            'showsuccess' => $showsuccess,
        ];
    }

    /**
     * Map a form inherit/enabled/disabled value to a nullable DB override.
     *
     * @param int $value -1 inherit, 0 disabled, 1 enabled.
     * @return int|null
     */
    public static function normalize_form_value(int $value): ?int {
        if ($value < 0) {
            return null;
        }
        return $value ? 1 : 0;
    }

    /**
     * @param int|null $override Quiz column value (null = inherit).
     * @param int $global Global default (0 or 1).
     * @return bool
     */
    private static function resolve_override($override, int $global): bool {
        if ($override === null || $override === '') {
            return (bool) $global;
        }
        return (bool) (int) $override;
    }
}
