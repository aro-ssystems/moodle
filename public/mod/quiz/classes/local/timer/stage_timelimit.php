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
use mod_quiz\quiz_settings;
use mod_quiz\form\preflight_check_form;
use mod_quiz\local\access_rule_base;
use MoodleQuickForm;
use ReflectionMethod;
use stdClass;

/**
 * Core access rule for multi-stage quiz time limits.
 *
 * @package   mod_quiz
 * @copyright 2020 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stage_timelimit extends access_rule_base {

    /** @var array the timer stages for this quiz, in array form. */
    protected array $stages;

    /** @var int if the user has a time limit override, this is how much their time differs from standard. */
    protected int $timeadjust;

    public function __construct(quiz_settings $quizobj, int $timenow, array $stages, int $timeadjust) {
        parent::__construct($quizobj, $timenow);
        $this->stages = $stages;
        $this->timeadjust = $timeadjust;
    }

    /**
     * Create a stage timelimit rule if this quiz has multi-stage configuration.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits): ?stage_timelimit {
        if (empty($quizobj->get_quiz()->timerstages) || $canignoretimelimits) {
            return null;
        }
        [$stages, $timeadjust] = self::get_stages_and_extratime($quizobj->get_quiz());

        if (!$stages) {
            return null;
        }
        return new self($quizobj, $timenow, $stages, $timeadjust);
    }

    public function get_superceded_rules(): array {
        return ['timelimit'];
    }

    /**
     * @param array $stages
     * @return array [$stages, $extratimemessage]
     */
    protected function get_description_bits(array $stages): array {
        $stagelabels = [];
        foreach ($stages as [$time, $name]) {
            if ($time == 0) {
                $displaytime = get_string('timerstageunavailable', 'quiz');
            } else {
                $displaytime = format_time($time);
            }
            $stagelabels[] = get_string('timerstageline', 'quiz',
                    ['name' => s($name), 'duration' => $displaytime]);
        }

        $extratimemessage = '';
        if ($this->timeadjust > 0) {
            $extratimemessage = ' ' . get_string('timerstageextra', 'quiz',
                    format_time($this->timeadjust));
        }

        return [$stagelabels, $extratimemessage];
    }

    public function description(): string {
        [$stagelabels, $extratimemessage] = $this->get_description_bits($this->stages);
        return \html_writer::tag('b', get_string('timerstagelimits', 'quiz')) .
                $extratimemessage . '<br>' . implode('<br>', $stagelabels);
    }

    public function is_preflight_check_required($attemptid): bool {
        return $attemptid === null;
    }

    public function add_preflight_check_form_fields(preflight_check_form $quizform,
            MoodleQuickForm $mform, $attemptid): void {
        [$stagelabels, $extratimemessage] = $this->get_description_bits($this->stages);

        if ($extratimemessage) {
            $stagelabels[0] .= $extratimemessage;
        }

        $mform->addElement('header', 'timerstageheader',
                get_string('timerstagelimits', 'quiz'));
        $mform->addElement('static', 'timerstagemessage', '',
                \html_writer::tag('p', get_string('timerstageconfirmstart', 'quiz')) .
                \html_writer::alist($stagelabels));
    }

    public function end_time($attempt): int {
        global $attemptobj, $timeup, $DB;
        if (!empty($timeup) && $this->quiz->overduehandling === 'autoabandon') {
            $attemptobj->process_submitted_actions($this->timenow);
            $attemptobj->process_abandon($this->timenow, true);
            $commitmethod = new ReflectionMethod($DB, 'commit_transaction');
            $commitmethod->setAccessible(true);
            $commitmethod->invoke($DB);
            redirect($attemptobj->review_url());
        }
        $timedue = $attempt->timestart + stage_settings::get_total_time($this->stages);
        if ($this->quiz->timeclose) {
            $timedue = min($timedue, $this->quiz->timeclose);
        }
        return $timedue;
    }

    public function time_left_display($attempt, $timenow) {
        $endtime = $this->end_time($attempt);
        if ($attempt->preview && $timenow > $endtime) {
            return false;
        }
        return $endtime - $timenow;
    }

    /**
     * Return the timer stages and the extratime adjustment.
     *
     * @param stdClass $quiz
     * @return array
     */
    public static function get_stages_and_extratime(stdClass $quiz): array {
        $stages = stage_settings::db_to_array($quiz->timerstages ?? null);
        return stage_settings::change_total_time($stages, (int) $quiz->timelimit);
    }

    /**
     * Return the stage-name in which the user has submitted the quiz attempt.
     *
     * @param stdClass $quiz
     * @param stdClass $attempt
     * @return string
     */
    public static function get_stage_submitted_in(stdClass $quiz, stdClass $attempt): string {
        if (!isset($quiz->timerstages) || !$quiz->timerstages) {
            return '';
        }

        if ($attempt->state === quiz_attempt::FINISHED) {
            $timetaken = $attempt->timefinish - $attempt->timestart;
            [$stages] = self::get_stages_and_extratime($quiz);
            $stagetimes = 0;
            $laststagename = '';
            foreach ($stages as [$seconds, $stagename]) {
                $laststagename = str_replace('"', '', $stagename);
                $stagetimes += $seconds;
                if ($timetaken <= $stagetimes) {
                    return $laststagename;
                }
            }
            return $laststagename;
        }
        return '';
    }
}
