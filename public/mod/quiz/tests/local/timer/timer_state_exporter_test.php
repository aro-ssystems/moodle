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

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Tests for timer_state_exporter.
 *
 * @package   mod_quiz
 * @category  test
 * @copyright 2026 SSYSTEMS GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\local\timer\timer_state_exporter
 */
final class timer_state_exporter_test extends \advanced_testcase {

    /**
     * Create a quiz with one question and start an attempt.
     *
     * @param array $quizoptions
     * @return quiz_attempt
     */
    protected function create_attempt(array $quizoptions = []): quiz_attempt {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(array_merge([
            'course' => $course->id,
            'timelimit' => 0,
            'timeclose' => 0,
        ], $quizoptions));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz, 0, 1);
        $quizobj = quiz_settings::create($quiz->id);
        $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();

        $this->setUser($user);
        $quizobj = quiz_settings::create($quiz->id, $user->id);
        $attempt = quiz_prepare_and_start_new_attempt($quizobj, 1, null);
        return quiz_attempt::create($attempt->id);
    }

    public function test_synthetic_single_stage(): void {
        $attemptobj = $this->create_attempt(['timelimit' => 5400]);
        $timenow = $attemptobj->get_attempt()->timestart + 60;

        $state = timer_state_exporter::export_for_attempt($attemptobj, $timenow);

        $this->assertSame(5340, $state['timeleft']);
        $this->assertCount(1, $state['stages']);
        $this->assertSame(0, $state['stages'][0]['endtime']);
        $this->assertSame(get_string('timeleft', 'quiz'), $state['stages'][0]['name']);
        $this->assertSame(quiz_attempt::IN_PROGRESS, $state['attemptstate']);
        $this->assertFalse($state['ispreview']);
        $this->assertFalse($state['forceshowtimer']);
        $this->assertTrue($state['canhidetimer']);
    }

    public function test_no_timer(): void {
        $attemptobj = $this->create_attempt();
        $state = timer_state_exporter::export_for_attempt($attemptobj, time());

        $this->assertSame(-1, $state['timeleft']);
        $this->assertSame([], $state['stages']);
    }

    public function test_force_show_in_final_phase(): void {
        $attemptobj = $this->create_attempt(['timelimit' => 5400]);
        $timenow = $attemptobj->get_attempt()->timestart + 5400 - 50;

        $state = timer_state_exporter::export_for_attempt($attemptobj, $timenow);

        $this->assertSame(50, $state['timeleft']);
        $this->assertTrue($state['forceshowtimer']);
        $this->assertFalse($state['canhidetimer']);
    }

    public function test_multi_stage_timer(): void {
        global $DB;

        $attemptobj = $this->create_attempt(['timelimit' => 5400]);
        $quizid = $attemptobj->get_quizid();
        $DB->insert_record('quiz_timer_stages', (object) [
            'quizid' => $quizid,
            'stages' => '[[3600,"Main time"],[1800,"Upload time"]]',
        ]);

        $timenow = $attemptobj->get_attempt()->timestart + 60;
        $state = timer_state_exporter::export_for_attempt($attemptobj, $timenow);

        $this->assertCount(2, $state['stages']);
        $this->assertSame(1800, $state['stages'][0]['endtime']);
        $this->assertSame(0, $state['stages'][1]['endtime']);
        $this->assertSame('Main time', $state['stages'][0]['name']);
    }

    public function test_user_override_extra_time(): void {
        global $DB;

        $attemptobj = $this->create_attempt(['timelimit' => 3600]);
        $quizid = $attemptobj->get_quizid();
        $DB->insert_record('quiz_timer_stages', (object) [
            'quizid' => $quizid,
            'stages' => '[[3600,"Main time"]]',
        ]);

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quizgenerator->create_override([
            'quiz' => $quizid,
            'userid' => $attemptobj->get_userid(),
            'timelimit' => 7200,
        ]);

        $attemptobj = quiz_attempt::create($attemptobj->get_attemptid());
        $timenow = $attemptobj->get_attempt()->timestart + 60;
        $state = timer_state_exporter::export_for_attempt($attemptobj, $timenow);

        $this->assertTrue($state['grantedextra']);
        $this->assertGreaterThan(7000, $state['timeleft']);
    }

    public function test_close_date_must_submit_by(): void {
        global $DB;

        $attemptobj = $this->create_attempt(['timelimit' => 7200]);
        $timestart = $attemptobj->get_attempt()->timestart;
        $DB->set_field('quiz', 'timeclose', $timestart + 3600, ['id' => $attemptobj->get_quizid()]);
        $DB->insert_record('quiz_timer_stages', (object) [
            'quizid' => $attemptobj->get_quizid(),
            'stages' => '[[7200,"Main time"]]',
        ]);

        $attemptobj = quiz_attempt::create($attemptobj->get_attemptid());
        $state = timer_state_exporter::export_for_attempt($attemptobj, $timestart + 60);

        $this->assertNotEmpty($state['mustsubmitby']);
    }

    public function test_timeleft_zero_at_expiry_boundary(): void {
        $attemptobj = $this->create_attempt(['timelimit' => 120]);
        $timenow = $attemptobj->get_attempt()->timestart + 120;

        $state = timer_state_exporter::export_for_attempt($attemptobj, $timenow);

        $this->assertSame(0, $state['timeleft']);
        $this->assertSame(quiz_attempt::IN_PROGRESS, $state['attemptstate']);
        $this->assertFalse($state['ispreview']);
    }

    public function test_timeleft_overdue_clamped_to_zero(): void {
        $attemptobj = $this->create_attempt(['timelimit' => 120]);
        $timenow = $attemptobj->get_attempt()->timestart + 125;

        $state = timer_state_exporter::export_for_attempt($attemptobj, $timenow);

        // Spec 025: overdue timed inprogress exports 0 (expired), not -N.
        $this->assertSame(0, $state['timeleft']);
        $this->assertSame(quiz_attempt::IN_PROGRESS, $state['attemptstate']);
        $this->assertTrue($state['forceshowtimer']);
    }
}
