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
 * Tests for stage_settings CRUD on quiz_timer_stages.
 *
 * @package   mod_quiz
 * @category  test
 * @copyright 2026 SSYSTEMS GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\local\timer\stage_settings
 */
final class stage_settings_crud_test extends \advanced_testcase {

    public function test_save_load_delete(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $stages = [[3600, 'Main time'], [1800, 'Upload time']];
        stage_settings::save_for_quiz($quiz->id, $stages);

        $this->assertTrue($DB->record_exists('quiz_timer_stages', ['quizid' => $quiz->id]));
        $this->assertSame('[[3600,"Main time"],[1800,"Upload time"]]', stage_settings::load_for_quiz($quiz->id));

        $extra = stage_settings::get_extra_settings($quiz->id);
        $this->assertArrayHasKey('timerstagesetting', $extra);
        $this->assertStringContainsString('Main time', $extra['timerstagesetting']);

        stage_settings::delete_for_quiz($quiz->id);
        $this->assertFalse($DB->record_exists('quiz_timer_stages', ['quizid' => $quiz->id]));
    }

    public function test_input_validation(): void {
        [$stages, $error] = stage_settings::input_to_array_with_validation("01:00:00 Main time\n00:30:00 Upload");
        $this->assertSame('', $error);
        $this->assertCount(2, $stages);

        [, $error] = stage_settings::input_to_array_with_validation("not valid");
        $this->assertNotEmpty($error);
    }
}
