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
 * Reactive quiz timer component.
 *
 * @module     mod_quiz/timer/component
 * @copyright  2026 SSYSTEMS GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {getString} from 'core/str';
import Notification from 'core/notification';
import {
    applyServerPayload,
    getDisplaySecondsLeft,
    isExpiredFromServer,
    startPolling,
    stopPolling,
} from 'mod_quiz/timer/sync';
import {refreshDisplay} from 'mod_quiz/timer/display';
import {handleTransition} from 'mod_quiz/timer/notifications';
import {
    applyVisibility,
    loadVisibilityPreference,
    saveVisibilityPreference,
} from 'mod_quiz/timer/visibility';

const FORCE_SHOW_THRESHOLD = 100;
const TICK_MS = 1000;

/** @type {number|null} */
let tickIntervalId = null;

/** @type {(() => void)|null} */
let onTimeupCallback = null;

export default class TimerComponent extends BaseComponent {

    create() {
        this.name = 'mod_quiz_timer';
        this.selectors = {
            ROOT: '#quiz-timer-wrapper',
            TOGGLE: '#toggle-timer',
        };
        /** @type {'ok'|'stale'|'error'} */
        this._lastSyncStatus = 'ok';
    }

    /**
     * @returns {Array}
     */
    getWatchers() {
        return [
            {watch: `display.displaySecondsLeft:updated`, handler: this._onDisplayUpdated},
            {watch: `display.forceshowtimer:updated`, handler: this._onDisplayUpdated},
            {watch: `display.timerVisible:updated`, handler: this._onVisibilityUpdated},
            {watch: `display.syncStatus:updated`, handler: this._onSyncStatusUpdated},
        ];
    }

    /**
     * Timer display state (nested for Moodle 4.5 Reactive root constraints).
     *
     * @returns {object}
     * @private
     */
    _displayState() {
        return this.reactive.state.display;
    }

    /**
     * Initial state ready — wire UI and start ticking.
     */
    async stateReady() {
        const state = this._displayState();
        const root = this.element;

        root.classList.remove('d-none');
        root.style.display = 'flex';

        const toggle = this.getElement(this.selectors.TOGGLE);
        if (toggle) {
            this.addEventListener(toggle, 'click', this._toggleVisibility);
        }

        let visible = true;
        if (!state.forceshowtimer) {
            visible = await loadVisibilityPreference();
        }
        this.reactive.dispatch('setTimerVisible', visible);
        await this._applyVisibility(visible, false);

        this._lastSyncStatus = state.syncStatus || 'ok';
        refreshDisplay(root, state);
        this._startTick();
        startPolling(state.attemptid, state.timeleft);
    }

    /**
     * Start 1s display tick.
     *
     * @private
     */
    _startTick() {
        if (tickIntervalId !== null) {
            clearInterval(tickIntervalId);
        }
        tickIntervalId = window.setInterval(() => {
            this._tick();
        }, TICK_MS);
        this._tick();
    }

    /**
     * @private
     */
    _tick() {
        const state = this._displayState();
        if (state.timeupFired) {
            return;
        }

        const displaySeconds = getDisplaySecondsLeft();
        if (displaySeconds === null) {
            return;
        }

        const forceshow = displaySeconds < FORCE_SHOW_THRESHOLD || state.forceshowtimer;
        this.reactive.dispatch('tick', {
            displaySecondsLeft: displaySeconds,
            forceshowtimer: forceshow,
        });

        if (forceshow && !state.timerVisible) {
            this.reactive.dispatch('setTimerVisible', true);
            this._applyVisibility(true, false);
        }

        if (displaySeconds <= 0) {
            this._triggerTimeup();
        }
    }

    /**
     * @private
     */
    _triggerTimeup() {
        this.reactive.dispatch('markTimeup');
        stopPolling();
        if (tickIntervalId !== null) {
            clearInterval(tickIntervalId);
            tickIntervalId = null;
        }
        if (onTimeupCallback) {
            onTimeupCallback();
        }
    }

    /**
     * @private
     */
    _toggleVisibility() {
        const state = this._displayState();
        if (!state.canhidetimer && state.timerVisible) {
            return;
        }
        const visible = !state.timerVisible;
        this.reactive.dispatch('setTimerVisible', visible);
        this._applyVisibility(visible, true);
    }

    /**
     * @param {boolean} visible
     * @param {boolean} updatePref
     * @private
     */
    async _applyVisibility(visible, updatePref) {
        const state = this._displayState();
        const canHide = state.canhidetimer && !state.forceshowtimer;
        try {
            await applyVisibility(this.element, visible, canHide);
            if (updatePref && canHide) {
                saveVisibilityPreference(visible);
            }
        } catch (error) {
            Notification.exception(error);
        }
    }

    /** Refresh DOM when display state changes. */
    _onDisplayUpdated() {
        refreshDisplay(this.element, this._displayState());
    }

    /**
     * Sync status edge → toast notifications + optional badge refresh.
     *
     * @private
     */
    _onSyncStatusUpdated() {
        const state = this._displayState();
        const current = state.syncStatus || 'ok';
        const previous = this._lastSyncStatus;
        this._lastSyncStatus = current;
        handleTransition(previous, current).catch(Notification.exception);
        refreshDisplay(this.element, state);
    }

    /**
     * @private
     */
    _onVisibilityUpdated() {
        const state = this._displayState();
        const canHide = state.canhidetimer && !state.forceshowtimer;
        applyVisibility(this.element, state.timerVisible, canHide);
    }

    /**
     * Whether the client should submit the attempt for timeup.
     *
     * @param {object|null} payload Latest server payload (optional)
     * @returns {boolean}
     * @private
     */
    _shouldTriggerTimeup(payload = null) {
        const state = this._displayState();
        if (state.timeupFired) {
            return false;
        }
        if (payload && isExpiredFromServer(payload)) {
            return true;
        }
        const displaySeconds = getDisplaySecondsLeft();
        return displaySeconds !== null && displaySeconds <= 0;
    }

    /**
     * Apply server poll result to monotone sync + reactive state.
     *
     * @param {object|null} payload
     */
    async applyPollResult(payload) {
        if (!payload) {
            // Call handleTransition here (not only via syncStatus watcher): sync fail
            // was observed with "Quiz timer sync failed" but no toast when relying solely
            // on display.syncStatus:updated.
            const previous = this._lastSyncStatus;
            this._lastSyncStatus = 'error';
            this.reactive.dispatch('setSyncStatus', 'error');
            await handleTransition(previous, 'error');
            refreshDisplay(this.element, this._displayState());
            return;
        }
        applyServerPayload(payload);
        const previous = this._lastSyncStatus;
        this.reactive.dispatch('applyServerPayload', payload);
        const current = this._displayState().syncStatus || 'ok';
        if (previous !== current) {
            this._lastSyncStatus = current;
            await handleTransition(previous, current);
        } else {
            this._lastSyncStatus = current;
        }

        if (this._shouldTriggerTimeup(payload)) {
            this._triggerTimeup();
        }
    }

    /**
     * @param {() => void} callback
     */
    static setTimeupCallback(callback) {
        onTimeupCallback = callback;
    }

    /**
     * Stop tick and polling.
     */
    static stop() {
        stopPolling();
        if (tickIntervalId !== null) {
            clearInterval(tickIntervalId);
            tickIntervalId = null;
        }
    }

    /**
     * @param {string} target Query selector for timer root
     * @param {import('core/reactive').Reactive} reactive
     * @returns {TimerComponent}
     */
    static init(target, reactive) {
        const element = document.querySelector(target);
        if (!element) {
            throw new Error('Quiz timer wrapper not found');
        }
        return new TimerComponent({
            element,
            reactive,
        });
    }

    /**
     * Submit quiz on timeup (core contract).
     */
    static async submitOnTimeup() {
        try {
            const timeLeftEl = document.querySelector('#quiz-time-left');
            if (timeLeftEl) {
                try {
                    timeLeftEl.textContent = await getString('timesup', 'quiz');
                } catch (error) {
                    timeLeftEl.textContent = '';
                }
            }

            const input = document.querySelector('input[name=timeup]');
            if (!input) {
                return;
            }
            input.value = '1';
            const form = input.closest('form');
            if (!form) {
                return;
            }
            const finishAttempt = form.querySelector('input[name=finishattempt]');
            if (finishAttempt) {
                finishAttempt.value = '0';
            }

            const FormChangeChecker = await import('core_form/changechecker');
            FormChangeChecker.markFormSubmitted(input);
            form.submit();
        } catch (error) {
            Notification.exception(error);
        }
    }
}
