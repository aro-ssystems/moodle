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

/**
 * Events for the quiz timer reactive module.
 *
 * @module     mod_quiz/timer/events
 * @copyright  2026 SSYSTEMS GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @constant
 * @property {String} quizTimerStateChange
 */
export const eventTypes = {
    quizTimerStateChange: 'mod_quiz/timerStateChange',
};

/**
 * Dispatch a timer state change event.
 *
 * @param {Object} detail Full reactive state snapshot
 * @param {Object} [target] Event target (defaults to document)
 */
export function dispatchStateChangedEvent(detail, target) {
    if (target === undefined) {
        target = document;
    }
    target.dispatchEvent(new CustomEvent(
        eventTypes.quizTimerStateChange,
        {
            bubbles: true,
            detail: detail,
        }
    ));
}
