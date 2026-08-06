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

namespace mod_quiz;

use mod_quiz\local\timer\stage_settings;
use restore_date_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');

/**
 * Test backup and restore of quiz timer stages.
 *
 * @package   mod_quiz
 * @copyright 2026 SSYSTEMS GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \backup_quiz_activity_structure_step
 * @covers    \restore_quiz_activity_structure_step
 */
final class restore_quiz_timer_stages_test extends restore_date_testcase {

    public function test_restore_quiz_timer_stages(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timelimit' => 5400,
        ]);

        $stagesjson = '[[3600,"Main time"],[1800,"Upload time"]]';
        stage_settings::save_for_quiz($quiz->id, [[3600, 'Main time'], [1800, 'Upload time']]);

        $newcourseid = $this->backup_and_restore($course);

        $newquiz = $DB->get_record('quiz', ['course' => $newcourseid]);
        $this->assertSame($stagesjson, stage_settings::load_for_quiz($newquiz->id));
    }
}
