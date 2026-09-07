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
 * Quiz timer visibility toggle and user preference.
 *
 * @module     mod_quiz/timer/visibility
 * @copyright  2026 SSYSTEMS GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import * as UserRepository from 'core_user/repository';
import Notification from 'core/notification';

/**
 * Apply visibility to timer content and toggle button.
 *
 * @param {HTMLElement} root
 * @param {boolean} visible
 * @param {boolean} canHide
 */
export const applyVisibility = async(root, visible, canHide) => {
    const button = root.querySelector('#toggle-timer');
    const simpleTimer = root.querySelector('#quiz-time-left');
    const stageTimer = root.querySelector('ul.stagetimer');
    const timerBody = root.querySelector('[data-region="timer-body"]');

    const action = visible ? 'hide' : 'show';

    if (button) {
        try {
            const label = await getString(action, 'core');
            button.textContent = label;
            button.disabled = !canHide && visible;
            button.classList.toggle('d-none', !canHide && !visible);
        } catch (error) {
            Notification.exception(error);
        }
    }

    if (simpleTimer) {
        if (visible) {
            simpleTimer.removeAttribute('hidden');
        } else {
            simpleTimer.setAttribute('hidden', 'hidden');
        }
    }
    if (stageTimer) {
        if (visible) {
            stageTimer.removeAttribute('hidden');
        } else {
            stageTimer.setAttribute('hidden', 'hidden');
        }
    }
    if (timerBody) {
        timerBody.hidden = !visible;
    }
};

/**
 * Load quiz_timerhidden preference (default visible).
 *
 * @returns {Promise<boolean>} true if timer should be visible
 */
export const loadVisibilityPreference = async() => {
    try {
        const response = await UserRepository.getUserPreference('quiz_timerhidden');
        return response !== '1';
    } catch (error) {
        window.console.warn('Could not load quiz_timerhidden preference', error);
        return true;
    }
};

/**
 * Persist visibility preference.
 *
 * @param {boolean} visible
 */
export const saveVisibilityPreference = (visible) => {
    UserRepository.setUserPreference('quiz_timerhidden', visible ? '0' : '1');
};
