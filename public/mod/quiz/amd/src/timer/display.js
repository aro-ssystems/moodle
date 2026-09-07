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
 * DOM updates for quiz timer display.
 *
 * @module     mod_quiz/timer/display
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {displayTime} from 'mod_quiz/timer/format';

const WARNING_THRESHOLD = 300;
const DANGER_THRESHOLD = 60;
const FORCE_SHOW_THRESHOLD = 100;

/**
 * Apply stock Boost timeleftN gradient classes (theme/modules.scss).
 *
 * @param {HTMLElement} timerEl #quiz-timer element
 * @param {number} secondsLeft Total seconds remaining
 */
const updateTimeleftClasses = (timerEl, secondsLeft) => {
    if (secondsLeft < FORCE_SHOW_THRESHOLD) {
        timerEl.classList.remove('timeleft' + (secondsLeft + 2));
        timerEl.classList.remove('timeleft' + (secondsLeft + 1));
        timerEl.classList.add('timeleft' + secondsLeft);
    } else {
        for (let i = 0; i < FORCE_SHOW_THRESHOLD; i++) {
            timerEl.classList.remove('timeleft' + i);
        }
    }
};

/**
 * Apply IU Option A warn/danger/forceshow classes on #quiz-timer.
 *
 * Danger is time-only (≤ DANGER_THRESHOLD). Forceshow is visibility/UX only
 * and must not imply danger colour.
 *
 * @param {HTMLElement} timerEl #quiz-timer element
 * @param {number} secondsLeft Total seconds remaining
 * @param {boolean} forceshow Whether force-show styling applies
 */
const updateOptionAClasses = (timerEl, secondsLeft, forceshow) => {
    timerEl.classList.remove('warning', 'danger', 'forceshow');
    if (secondsLeft <= DANGER_THRESHOLD) {
        timerEl.classList.add('danger');
    } else if (secondsLeft <= WARNING_THRESHOLD) {
        timerEl.classList.add('warning');
    }
    if (forceshow) {
        timerEl.classList.add('forceshow');
    }
};

/**
 * Update simple single-line timer display.
 *
 * @param {HTMLElement} root Timer wrapper element
 * @param {number} secondsLeft Total seconds remaining
 * @param {boolean} forceshow Whether force-show styling applies
 */
export const updateSimpleTimer = (root, secondsLeft, forceshow) => {
    const timerEl = root.querySelector('#quiz-timer');
    const timeLeftEl = root.querySelector('#quiz-time-left');
    if (!timerEl || !timeLeftEl) {
        return;
    }

    timeLeftEl.textContent = displayTime(secondsLeft);
    updateTimeleftClasses(timerEl, secondsLeft);
    updateOptionAClasses(timerEl, secondsLeft, forceshow);
};

/**
 * Update multi-stage timer list.
 *
 * @param {HTMLElement} root Timer wrapper element
 * @param {number} secondsLeft Total seconds remaining
 * @param {boolean} forceshow Whether force-show styling applies
 */
export const updateStageTimer = (root, secondsLeft, forceshow) => {
    // Attempt-level forceshow is visibility/UX only; stage colour uses stageSeconds.
    void forceshow;

    const stagesList = root.querySelector('ul.stagetimer');
    if (!stagesList) {
        return;
    }

    let foundCurrent = false;
    stagesList.querySelectorAll('li').forEach((li) => {
        li.classList.remove('past', 'current', 'future', 'warning', 'danger');
        const endTime = parseInt(li.dataset.endTime, 10);

        if (foundCurrent) {
            li.classList.add('future');
            return;
        }

        if (endTime > secondsLeft) {
            li.classList.add('past');
            return;
        }

        foundCurrent = true;
        li.classList.add('current');
        const stageSeconds = Math.max(0, secondsLeft - endTime);
        const timeLeftSpan = li.querySelector('.timeleft');
        if (timeLeftSpan) {
            timeLeftSpan.textContent = displayTime(stageSeconds);
        }

        // Option A: danger only ≤60s; warning ≤300s when not danger.
        if (stageSeconds <= DANGER_THRESHOLD) {
            li.classList.add('danger');
        } else if (stageSeconds <= WARNING_THRESHOLD) {
            li.classList.add('warning');
        }
    });
};

/**
 * Refresh timer DOM from reactive display state.
 *
 * @param {HTMLElement} root
 * @param {object} displayState
 */
export const refreshDisplay = (root, displayState) => {
    if (!root || displayState.displaySecondsLeft === null) {
        return;
    }

    const secondsLeft = displayState.displaySecondsLeft;
    const forceshow = displayState.forceshowtimer;

    if (displayState.hastimestages) {
        updateStageTimer(root, secondsLeft, forceshow);
    } else {
        updateSimpleTimer(root, secondsLeft, forceshow);
    }

    const syncBadge = root.querySelector('[data-region="sync-status"]');
    if (syncBadge) {
        syncBadge.classList.toggle('d-none', displayState.syncStatus === 'ok');
        syncBadge.textContent = displayState.syncStatus === 'stale' ?
            displayState.syncStaleLabel : displayState.syncErrorLabel;
    }
};
