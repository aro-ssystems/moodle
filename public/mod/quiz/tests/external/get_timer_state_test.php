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

use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Tests for get_timer_state webservice.
 *
 * @package   mod_quiz
 * @category  test
 * @copyright 2026 SSYSTEMS GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\external\get_timer_state
 */
final class get_timer_state_test extends \core_external\tests\externallib_testcase {

    /**
     * @param array $quizoptions
     * @return quiz_attempt
     */
    protected function create_timed_attempt(array $quizoptions = []): quiz_attempt {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(array_merge([
            'course' => $course->id,
            'timelimit' => 3600,
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

    public function test_execute_returns_contract_shape(): void {
        $attemptobj = $this->create_timed_attempt();

        $result = get_timer_state::execute($attemptobj->get_attemptid());

        $this->assertArrayHasKey('timeleft', $result);
        $this->assertArrayHasKey('stages', $result);
        $this->assertArrayHasKey('attemptstate', $result);
        $this->assertArrayHasKey('ispreview', $result);
        $this->assertGreaterThan(0, $result['timeleft']);
        $this->assertSame(quiz_attempt::IN_PROGRESS, $result['attemptstate']);
    }

    public function test_execute_checks_permissions(): void {
        $attemptobj = $this->create_timed_attempt();

        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $other->id,
            $attemptobj->get_courseid(),
            'student'
        );
        $this->setUser($other);

        $this->expectException(\moodle_exception::class);
        get_timer_state::execute($attemptobj->get_attemptid());
    }
}
