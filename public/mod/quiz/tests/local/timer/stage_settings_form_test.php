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
 * Tests for stage_settings quiz settings form integration.
 *
 * @package   mod_quiz
 * @category  test
 * @copyright 2026 SSYSTEMS GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\local\timer\stage_settings
 */
final class stage_settings_form_test extends \advanced_testcase {

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
     * Build mocked quiz form with manage capability context.
     */
    private function create_quizform_mock(\stdClass $quiz, \context_module $context): \mod_quiz_mod_form {
        $quizform = $this->createMock(\mod_quiz_mod_form::class);
        $quizform->method('get_context')->willReturn($context);
        $quizform->method('get_instance')->willReturn($quiz->id);
        return $quizform;
    }

    public function test_add_settings_form_fields_registers_timerstagesetting(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);

        $this->setAdminUser();

        $mform = $this->create_form_with_timing_anchor();
        $quizform = $this->create_quizform_mock($quiz, $context);
        stage_settings::add_settings_form_fields($quizform, $mform);

        $this->assertTrue($mform->elementExists('timerstagesetting'));
    }

    public function test_add_settings_form_fields_attaches_help_button(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);

        $this->setAdminUser();

        $mform = $this->create_form_with_timing_anchor();
        $quizform = $this->create_quizform_mock($quiz, $context);
        stage_settings::add_settings_form_fields($quizform, $mform);

        $element = $mform->getElement('timerstagesetting');
        $this->assertNotEmpty($element->_helpbutton);
    }
}
