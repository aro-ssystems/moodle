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

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for stage_timelimit.
 *
 * @package   mod_quiz
 * @category  test
 * @copyright 2020 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\local\timer\stage_timelimit
 */
final class stage_timelimit_test extends \advanced_testcase {

    protected function get_rule_instance($quiztimelimit = null, $canignoretimelimits = false): ?stage_timelimit {
        $quiz = new \stdClass();
        $quiz->timelimit = $quiztimelimit ?? 14400;
        $quiz->timeclose = 2000000;
        $quiz->timerstages = '[[10800,"Main time"],[1800,"Upload time"],[1800,"Late penalty period"]]';
        $cm = new \stdClass();
        $cm->id = 0;
        $quizobj = new quiz_settings($quiz, $cm, null);
        return stage_timelimit::make($quizobj, 10000, $canignoretimelimits);
    }

    protected function get_attempt(int $timestart, bool $ispreview = false): \stdClass {
        $attempt = new \stdClass();
        $attempt->timestart = $timestart;
        $attempt->preview = $ispreview;
        return $attempt;
    }

    public function test_make_user_exempt(): void {
        $this->resetAfterTest();
        $this->assertNull($this->get_rule_instance(null, true));
    }

    public function test_no_limit(): void {
        $this->assertNull($this->get_rule_instance(0));
    }

    public function test_description(): void {
        $rule = $this->get_rule_instance();
        $this->assertEquals('<b>Time limits</b><br>Main time: 3 hours<br>' .
                'Upload time: 30 mins<br>Late penalty period: 30 mins', $rule->description());
    }

    public function test_description_with_extra_time(): void {
        $rule = $this->get_rule_instance(14400 + HOURSECS);
        $this->assertEquals('<b>Time limits</b> (You have been granted an extra 1 hour)<br>Main time: 4 hours<br>' .
                'Upload time: 30 mins<br>Late penalty period: 30 mins', $rule->description());
    }

    public function test_end_time(): void {
        $rule = $this->get_rule_instance();
        $this->assertEquals(1000000 + 4 * HOURSECS, $rule->end_time($this->get_attempt(1000000)));
        $this->assertEquals(2000000, $rule->end_time($this->get_attempt(2000000 - 3 * HOURSECS)));
    }

    public function test_time_left_display_normal(): void {
        $rule = $this->get_rule_instance();
        $timestart = 1000000;
        $this->assertEquals(4 * HOURSECS - 100,
                $rule->time_left_display($this->get_attempt($timestart), $timestart + 100));
        $this->assertEquals(-HOURSECS,
                $rule->time_left_display($this->get_attempt($timestart), $timestart + 5 * HOURSECS));
    }

    public function test_time_left_display_preview(): void {
        $rule = $this->get_rule_instance();
        $timestart = 1000000;
        $this->assertEquals(4 * HOURSECS - 100,
                $rule->time_left_display($this->get_attempt($timestart, true), $timestart + 100));
        $this->assertFalse($rule->time_left_display($this->get_attempt($timestart, true),
                $timestart + 5 * HOURSECS));
    }

    /**
     * @return array
     */
    public static function get_stage_submitted_in_data_provider(): array {
        $timestart = 1671631000;
        return [
            'After 30 minutes' => [$timestart, $timestart + (30 * MINSECS), 'Main time'],
            'After 1 hour 1 second' => [$timestart, $timestart + HOURSECS + 1, 'Submission time'],
            'After 1 hour 16 minutes' => [$timestart, $timestart + HOURSECS + (16 * MINSECS), 'Emergency extra time'],
        ];
    }

    /**
     * @dataProvider get_stage_submitted_in_data_provider
     */
    public function test_get_stage_submitted_in(int $timestart, int $timefinished, ?string $expectedtagename): void {
        $quiz = new \stdClass();
        $quiz->timelimit = 5400;
        $quiz->timerstages = '[[3600,"Main time"],[900,"Submission time"],[900,"Emergency extra time"]]';

        $attempt = new \stdClass();
        $attempt->state = quiz_attempt::FINISHED;
        $attempt->timestart = $timestart;
        $attempt->timefinish = $timefinished;

        $this->assertEquals($expectedtagename, stage_timelimit::get_stage_submitted_in($quiz, $attempt));
    }
}
