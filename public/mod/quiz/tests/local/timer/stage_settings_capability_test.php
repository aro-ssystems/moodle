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

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/mod_form.php');

/**
 * Capability and timelimit-preservation tests for stage_settings (Review H2 / Spec 023).
 *
 * @package   mod_quiz
 * @category  test
 * @copyright 2026 SSYSTEMS GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\local\timer\stage_settings
 */
final class stage_settings_capability_test extends \advanced_testcase {

    /**
     * Create a user enrolled in the course without mod/quiz:manage on the quiz.
     */
    private function create_user_without_manage(\stdClass $course, \context_module $context): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->assertFalse(has_capability('mod/quiz:manage', $context, $user));
        return $user;
    }

    /**
     * Build a minimal timing section with overduehandling anchor present.
     */
    private function create_form_with_timing_anchor(): \MoodleQuickForm {
        $mform = new \MoodleQuickForm('test', 'post', '');
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'quiz'), ['optional' => true]);
        $mform->addElement('select', 'overduehandling', get_string('overduehandling', 'quiz'),
                quiz_get_overdue_handling_options());
        return $mform;
    }

    /**
     * Build mocked quiz form.
     */
    private function create_quizform_mock(\stdClass $quiz, \context_module $context): \mod_quiz_mod_form {
        $quizform = $this->createMock(\mod_quiz_mod_form::class);
        $quizform->method('get_context')->willReturn($context);
        $quizform->method('get_instance')->willReturn($quiz->id);
        return $quizform;
    }

    public function test_save_from_form_without_manage_is_noop(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timelimit' => 3600,
        ]);
        $context = \context_module::instance($quiz->cmid);

        $originalstages = [[3600, 'Main time'], [1800, 'Upload time']];
        stage_settings::save_for_quiz($quiz->id, $originalstages);
        $originaljson = stage_settings::load_for_quiz($quiz->id);

        $user = $this->create_user_without_manage($course, $context);
        $this->setUser($user);

        $payload = (object) [
            'id' => $quiz->id,
            'timerstagesetting' => "00:10:00 Hacked\n00:05:00 Stage",
        ];
        stage_settings::save_from_form($payload);

        $this->assertSame($originaljson, stage_settings::load_for_quiz($quiz->id));
        $this->assertEquals(3600, (int) $DB->get_field('quiz', 'timelimit', ['id' => $quiz->id]));
    }

    public function test_save_from_form_with_manage_persists_stages(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timelimit' => 100,
        ]);

        $this->setAdminUser();

        $payload = (object) [
            'id' => $quiz->id,
            'timerstagesetting' => "01:00:00 Main time\n00:30:00 Upload time",
        ];
        stage_settings::save_from_form($payload);

        $this->assertSame('[[3600,"Main time"],[1800,"Upload time"]]', stage_settings::load_for_quiz($quiz->id));
        $this->assertEquals(5400, (int) $DB->get_field('quiz', 'timelimit', ['id' => $quiz->id]));
    }

    public function test_save_from_form_with_manage_clears_stages(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        stage_settings::save_for_quiz($quiz->id, [[3600, 'Main time']]);

        $this->setAdminUser();
        stage_settings::save_from_form((object) [
            'id' => $quiz->id,
            'timerstagesetting' => '',
        ]);

        $this->assertFalse($DB->record_exists('quiz_timer_stages', ['quizid' => $quiz->id]));
    }

    public function test_non_manage_form_preserves_positive_timelimit(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timelimit' => 2700,
        ]);
        $context = \context_module::instance($quiz->cmid);

        stage_settings::save_for_quiz($quiz->id, [[2700, 'Only stage']]);

        $user = $this->create_user_without_manage($course, $context);
        $this->setUser($user);

        $mform = $this->create_form_with_timing_anchor();
        $quizform = $this->create_quizform_mock($quiz, $context);
        stage_settings::add_settings_form_fields($quizform, $mform);

        $this->assertTrue($mform->elementExists('timelimit'));
        $element = $mform->getElement('timelimit');
        $this->assertSame('hidden', $element->getType());
        $this->assertEquals(2700, (int) $element->getValue());
        $this->assertEquals(2700, (int) $DB->get_field('quiz', 'timelimit', ['id' => $quiz->id]));
        $this->assertNotEquals(0, (int) $element->getValue());
    }
}
