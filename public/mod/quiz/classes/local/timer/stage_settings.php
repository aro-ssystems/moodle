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

use mod_quiz_mod_form;
use MoodleQuickForm;
use stdClass;

/**
 * Utilities for quiz timer stage configuration.
 *
 * @package   mod_quiz
 * @copyright 2020 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stage_settings {

    /**
     * Load raw JSON stages for a quiz from the core table.
     *
     * @param int $quizid
     * @return string|null
     */
    public static function load_for_quiz(int $quizid): ?string {
        global $DB;
        return $DB->get_field('quiz_timer_stages', 'stages', ['quizid' => $quizid], IGNORE_MISSING) ?: null;
    }

    /**
     * Save stages for a quiz to the core table.
     *
     * @param int $quizid
     * @param array $stages setting in array form.
     */
    public static function save_for_quiz(int $quizid, array $stages): void {
        global $DB;
        if ($stages === []) {
            self::delete_for_quiz($quizid);
            return;
        }
        $tosave = self::array_to_db($stages);
        if ($DB->record_exists('quiz_timer_stages', ['quizid' => $quizid])) {
            $DB->set_field('quiz_timer_stages', 'stages', $tosave, ['quizid' => $quizid]);
        } else {
            $DB->insert_record('quiz_timer_stages', (object) [
                'quizid' => $quizid,
                'stages' => $tosave,
            ]);
        }
    }

    /**
     * Delete stage configuration for a quiz.
     *
     * @param int $quizid
     */
    public static function delete_for_quiz(int $quizid): void {
        global $DB;
        $DB->delete_records('quiz_timer_stages', ['quizid' => $quizid]);
    }

    /**
     * Extra settings for quiz forms and access manager.
     *
     * @param int $quizid
     * @return array
     */
    public static function get_extra_settings(int $quizid): array {
        $dbvalue = self::load_for_quiz($quizid);
        if (!$dbvalue) {
            return [];
        }
        return [
            'timerstages' => $dbvalue,
            'timerstagesetting' => self::db_to_input($dbvalue),
        ];
    }

    /**
     * Add multi-stage timer fields to the quiz settings form.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform): void {
        $context = $quizform->get_context();
        $canedit = has_capability('mod/quiz:manage', $context);

        $currentvalue = null;
        $quizid = $quizform->get_instance();
        if ($quizid) {
            $currentvalue = self::load_for_quiz($quizid);
        }

        if (!$canedit && !$currentvalue) {
            return;
        }

        $element = $mform->createElement('textarea', 'timerstagesetting',
                get_string('timerstagesetting', 'quiz'), ['cols' => '80', 'rows' => '4']);
        $mform->insertElementBefore($element, 'overduehandling');
        $mform->addHelpButton('timerstagesetting', 'timerstagesetting', 'quiz');
        if (!$currentvalue) {
            $mform->setAdvanced('timerstagesetting');
        }

        if ($canedit) {
            $mform->disabledIf('timelimit', 'timerstagesetting', 'neq', '');
        } else {
            // Preserve existing quiz timelimit — never force hidden 0 (Review H2 / Spec 023).
            global $DB;
            $currentlimit = 0;
            if ($quizid) {
                $currentlimit = (int) $DB->get_field('quiz', 'timelimit', ['id' => $quizid]);
            }
            if ($mform->elementExists('timelimit')) {
                $mform->removeElement('timelimit');
            }
            $mform->addElement('hidden', 'timelimit', $currentlimit);
            $mform->setType('timelimit', PARAM_INT);
            $mform->hardFreeze('timerstagesetting');
        }
    }

    /**
     * Validate timerstagesetting form field.
     *
     * @param array $errors
     * @param array $data
     * @return array
     */
    public static function validate_settings_form_fields(array $errors, array $data): array {
        if (!empty($data['timerstagesetting'])) {
            [, $error] = self::input_to_array_with_validation($data['timerstagesetting']);
            if ($error) {
                $errors['timerstagesetting'] = $error;
            }
        }
        return $errors;
    }

    /**
     * Save stage settings from quiz form submission.
     *
     * Requires mod/quiz:manage. Without it, stage rows and stage-driven timelimit
     * updates are skipped (soft no-op) so other quiz settings can still save.
     *
     * @param stdClass $quiz the data from the quiz form, including $quiz->id
     */
    public static function save_from_form(stdClass $quiz): void {
        global $DB;
        if (!isset($quiz->timerstagesetting)) {
            return;
        }

        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        if (!$cm) {
            return;
        }
        $context = \context_module::instance($cm->id);
        if (!has_capability('mod/quiz:manage', $context)) {
            return;
        }

        $stages = self::input_to_array($quiz->timerstagesetting);
        if (!$stages) {
            self::delete_for_quiz($quiz->id);
            return;
        }

        self::save_for_quiz($quiz->id, $stages);
        $DB->set_field('quiz', 'timelimit', self::get_total_time($stages), ['id' => $quiz->id]);
    }

    /**
     * Convert from DB form to array form.
     *
     * @param string|null $dbvalue setting value from the database.
     * @return array
     */
    public static function db_to_array(?string $dbvalue): array {
        if ($dbvalue === null) {
            return [];
        }
        return json_decode($dbvalue) ?? [];
    }

    /**
     * Convert from array form to DB form.
     *
     * @param array $stages setting value.
     * @return string|null
     */
    public static function array_to_db(array $stages): ?string {
        if ($stages === []) {
            return null;
        }
        return json_encode($stages);
    }

    /**
     * Convert the input form to array form, while reporting any errors.
     *
     * @param string $input
     * @return array [$stages, $error]
     */
    public static function input_to_array_with_validation(string $input): array {
        $lines = preg_split('~\s*?(?:[\r\n]|/-/)\s*~', $input, -1, PREG_SPLIT_NO_EMPTY);

        $stages = [];
        foreach ($lines as $key => $line) {
            if (preg_match('~
                   ^
                   (\d+)
                   [ \t]*:[ \t]*
                   (\d\d?)
                   [ \t]*:[ \t]*
                   (\d\d?)
                   [ \t]+
                   (.*)$
                   ~x', $line, $matches)) {
                [, $hours, $minutes, $seconds, $name] = $matches;
                $stages[] = [$hours * HOURSECS + $minutes * MINSECS + $seconds, $name];
            } else {
                return [[], get_string('timerstageparseerror', 'quiz',
                        ['line' => $key + 1, 'input' => s($line)])];
            }
        }
        return [$stages, ''];
    }

    /**
     * Parse validated input form to array.
     *
     * @param string $input
     * @return array
     */
    public static function input_to_array(string $input): array {
        [$stages, $error] = self::input_to_array_with_validation($input);
        if ($error) {
            throw new \coding_exception('Attempt to parse an invalid timer stage setting.');
        }
        return $stages;
    }

    /**
     * Convert array form to input form.
     *
     * @param array $stages
     * @return string
     */
    public static function array_to_input(array $stages): string {
        $output = [];
        foreach ($stages as [$time, $name]) {
            $output[] = self::display_time_period($time) . ' ' . $name;
        }
        return implode("\n", $output);
    }

    /**
     * Convert from DB form to input form.
     *
     * @param string $dbvalue
     * @return string
     */
    public static function db_to_input(string $dbvalue): string {
        return self::array_to_input(self::db_to_array($dbvalue));
    }

    /**
     * Get the overall time from all the stages.
     *
     * @param array $stages setting in array form.
     * @return int total time in seconds.
     */
    public static function get_total_time(array $stages): int {
        $total = 0;
        foreach ($stages as [$time]) {
            $total += $time;
        }
        return $total;
    }

    /**
     * Display a time period in seconds like HH:MM:SS.
     *
     * @param int $time time in seconds.
     * @return string formatted string.
     */
    public static function display_time_period(int $time): string {
        $seconds = $time % MINSECS;
        $time -= $seconds;
        $minutes = ($time / MINSECS) % HOURMINS;
        $time -= MINSECS * $minutes;
        $hours = $time / HOURSECS;
        return self::two_digits($hours) . ':' . self::two_digits($minutes) .
                ':' . self::two_digits($seconds);
    }

    /**
     * Display an integer with at least two digits.
     *
     * @param int $number
     * @return string
     */
    public static function two_digits(int $number): string {
        if ($number < 10) {
            return '0' . $number;
        }
        return '' . $number;
    }

    /**
     * Modify the times to take account of a new total time (e.g. user/group override).
     *
     * @param array $stages timer stages.
     * @param int $desiredtotaltime the new total time we want.
     * @return array [$stages, $extratime]
     */
    public static function change_total_time(array $stages, int $desiredtotaltime): array {
        $currenttotal = self::get_total_time($stages);
        $extratime = $desiredtotaltime - $currenttotal;

        if ($extratime === 0) {
            return [$stages, 0];
        }

        if ($extratime > 0) {
            $stages[0][0] += $extratime;
            return [$stages, $extratime];
        }

        $reductionrequired = -$extratime;
        while ($reductionrequired > 0 && $stages) {
            if ($reductionrequired >= $stages[0][0]) {
                $reductionrequired -= $stages[0][0];
                array_shift($stages);
            } else {
                $stages[0][0] -= $reductionrequired;
                $reductionrequired = 0;
            }
        }
        return [$stages, $extratime];
    }

    /**
     * Modify the times to take account of time being cut short (e.g. close date sooner than total time).
     *
     * @param array $stages timer stages.
     * @param int $timeavailable the new total time we want.
     * @return array updated stages array.
     */
    public static function change_total_time_at_end(array $stages, int $timeavailable): array {
        $currenttotal = self::get_total_time($stages);
        if ($currenttotal <= $timeavailable) {
            return $stages;
        }
        $timeshort = $currenttotal - $timeavailable;

        foreach (array_reverse($stages, true) as $i => $notused) {
            if ($i == 0) {
                break;
            }
            if ($timeshort < $stages[$i][0]) {
                $stages[$i][0] -= $timeshort;
                break;
            }
            $timeshort -= $stages[$i][0];
            $stages[$i][0] = 0;
        }
        return $stages;
    }
}
