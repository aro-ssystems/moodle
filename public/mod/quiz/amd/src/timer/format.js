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
 * Time formatting for quiz timer display.
 *
 * @module     mod_quiz/timer/format
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {string|null} */
let hourMinuteTemplate = null;

/**
 * Configure the hours/minutes display template from lang.
 *
 * @param {string} template e.g. "%%HH%% hours %%MM%% minutes"
 */
export const setHourMinuteTemplate = (template) => {
    hourMinuteTemplate = template;
};

/**
 * Convert 0–99 to two-digit string.
 *
 * @param {number} num
 * @returns {string}
 */
export const twoDigit = (num) => {
    if (num < 10) {
        return '0' + num;
    }
    return '' + num;
};

/**
 * Format seconds for display.
 *
 * @param {number} seconds
 * @returns {string}
 */
export const displayTime = (seconds) => {
    const safeSeconds = Math.max(0, Math.floor(seconds));
    if (safeSeconds >= 3600 && hourMinuteTemplate) {
        const hours = Math.floor(safeSeconds / 3600);
        const minutes = Math.floor(safeSeconds / 60) - hours * 60;
        return hourMinuteTemplate.replace('%%HH%%', hours).replace('%%MM%%', minutes);
    }
    const minutes = Math.floor(safeSeconds / 60);
    return twoDigit(minutes) + ':' + twoDigit(safeSeconds - minutes * 60);
};
