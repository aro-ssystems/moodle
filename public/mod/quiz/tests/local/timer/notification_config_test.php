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
 * Unit tests for notification_config.
 *
 * @package   mod_quiz
 * @category  test
 * @copyright 2026 SSYSTEMS GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\local\timer\notification_config
 */
final class notification_config_test extends \advanced_testcase {

    /**
     * @param int $globaldegraded
     * @param int $globalsuccess
     */
    protected function set_global_config(int $globaldegraded, int $globalsuccess): void {
        set_config('timernotifydegraded', $globaldegraded, 'quiz');
        set_config('timernotifysuccess', $globalsuccess, 'quiz');
    }

    /**
     * @param int|null $degraded
     * @param int|null $success
     * @return \stdClass
     */
    protected function make_quiz(?int $degraded, ?int $success): \stdClass {
        $quiz = new \stdClass();
        $quiz->timernotifydegraded = $degraded;
        $quiz->timernotifysuccess = $success;
        return $quiz;
    }

    public function test_defaults_both_enabled(): void {
        $this->resetAfterTest();
        $this->set_global_config(1, 1);

        $result = notification_config::resolve_for_quiz($this->make_quiz(null, null));

        $this->assertTrue($result['showdegraded']);
        $this->assertTrue($result['showsuccess']);
    }

    public function test_global_success_off_inherit(): void {
        $this->resetAfterTest();
        $this->set_global_config(1, 0);

        $result = notification_config::resolve_for_quiz($this->make_quiz(null, null));

        $this->assertTrue($result['showdegraded']);
        $this->assertFalse($result['showsuccess']);
    }

    public function test_global_degraded_off_suppresses_success(): void {
        $this->resetAfterTest();
        $this->set_global_config(0, 1);

        $result = notification_config::resolve_for_quiz($this->make_quiz(null, null));

        $this->assertFalse($result['showdegraded']);
        $this->assertFalse($result['showsuccess']);
    }

    public function test_quiz_degraded_off_overrides_global_on(): void {
        $this->resetAfterTest();
        $this->set_global_config(1, 1);

        $result = notification_config::resolve_for_quiz($this->make_quiz(0, null));

        $this->assertFalse($result['showdegraded']);
        $this->assertFalse($result['showsuccess']);
    }

    public function test_quiz_success_off_keeps_degraded(): void {
        $this->resetAfterTest();
        $this->set_global_config(1, 1);

        $result = notification_config::resolve_for_quiz($this->make_quiz(1, 0));

        $this->assertTrue($result['showdegraded']);
        $this->assertFalse($result['showsuccess']);
    }

    public function test_quiz_success_on_with_global_success_off(): void {
        $this->resetAfterTest();
        $this->set_global_config(1, 0);

        $result = notification_config::resolve_for_quiz($this->make_quiz(null, 1));

        $this->assertTrue($result['showdegraded']);
        $this->assertFalse($result['showsuccess']);
    }

    public function test_normalize_form_value(): void {
        $this->assertNull(notification_config::normalize_form_value(-1));
        $this->assertSame(0, notification_config::normalize_form_value(0));
        $this->assertSame(1, notification_config::normalize_form_value(1));
    }
}
