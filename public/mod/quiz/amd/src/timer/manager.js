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
 * Reactive state manager for the quiz timer.
 *
 * @module     mod_quiz/timer/manager
 * @copyright  2026 SSYSTEMS GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Reactive} from 'core/reactive';
import {eventTypes, dispatchStateChangedEvent} from 'mod_quiz/timer/events';

/**
 * Build initial display state from server payload.
 *
 * @param {object} serverState
 * @param {number} attemptid
 * @returns {object}
 */
export const buildInitialState = (serverState, attemptid) => {
    const stages = serverState.stages || [];
    const hastimestages = stages.length > 1 || (stages.length === 1 && stages[0].endtime > 0);
    // Normalize overdue (-N) to 0; keep exact -1 as no-timer (Spec 022/025).
    let timeleft = serverState.timeleft;
    if (typeof timeleft === 'number' && timeleft < 0 && timeleft !== -1) {
        timeleft = 0;
    }

    return {
        attemptid,
        ispreview: !!serverState.ispreview,
        timeleft,
        stages,
        mustsubmitby: serverState.mustsubmitby || '',
        grantedextra: !!serverState.grantedextra,
        canhidetimer: serverState.canhidetimer !== false,
        forceshowtimer: !!serverState.forceshowtimer,
        hastimestages,
        displaySecondsLeft: timeleft >= 0 ? timeleft : null,
        timerVisible: true,
        syncStatus: 'ok',
        syncStaleLabel: '',
        syncErrorLabel: '',
        timeupFired: false,
    };
};

const mutations = {
    /**
     * @param {import('core/local/reactive/statemanager').default} stateManager
     * @param {object} payload
     */
    tick(stateManager, payload) {
        const state = stateManager.state.display;
        stateManager.setReadOnly(false);
        state.displaySecondsLeft = payload.displaySecondsLeft;
        if (payload.forceshowtimer !== undefined) {
            state.forceshowtimer = payload.forceshowtimer;
            state.canhidetimer = !payload.forceshowtimer;
        }
        stateManager.setReadOnly(true);
    },

    /**
     * @param {import('core/local/reactive/statemanager').default} stateManager
     * @param {object} payload
     */
    applyServerPayload(stateManager, payload) {
        const state = stateManager.state.display;
        stateManager.setReadOnly(false);
        if (typeof payload.timeleft === 'number') {
            if (payload.timeleft >= 0) {
                state.timeleft = payload.timeleft;
                state.displaySecondsLeft = payload.timeleft;
            } else if (payload.timeleft === -1) {
                // Exact -1 = no timer: keep sentinel, clear countdown (Spec 022).
                state.timeleft = -1;
                state.displaySecondsLeft = null;
            } else {
                // Other negatives = overdue → expired 0 (Spec 025).
                state.timeleft = 0;
                state.displaySecondsLeft = 0;
            }
        }
        if (payload.stages) {
            state.stages = payload.stages;
            state.hastimestages = payload.stages.length > 1 ||
                (payload.stages.length === 1 && payload.stages[0].endtime > 0);
        }
        if (payload.mustsubmitby !== undefined) {
            state.mustsubmitby = payload.mustsubmitby;
        }
        if (payload.grantedextra !== undefined) {
            state.grantedextra = payload.grantedextra;
        }
        if (payload.canhidetimer !== undefined) {
            state.canhidetimer = payload.canhidetimer;
        }
        if (payload.forceshowtimer !== undefined) {
            state.forceshowtimer = payload.forceshowtimer;
        }
        state.syncStatus = 'ok';
        stateManager.setReadOnly(true);
    },

    /**
     * @param {import('core/local/reactive/statemanager').default} stateManager
     * @param {string} status
     */
    setSyncStatus(stateManager, status) {
        const state = stateManager.state.display;
        stateManager.setReadOnly(false);
        state.syncStatus = status;
        stateManager.setReadOnly(true);
    },

    /**
     * @param {import('core/local/reactive/statemanager').default} stateManager
     * @param {boolean} visible
     */
    setTimerVisible(stateManager, visible) {
        const state = stateManager.state.display;
        stateManager.setReadOnly(false);
        state.timerVisible = visible;
        stateManager.setReadOnly(true);
    },

    /**
     * @param {import('core/local/reactive/statemanager').default} stateManager
     */
    markTimeup(stateManager) {
        const state = stateManager.state.display;
        stateManager.setReadOnly(false);
        state.timeupFired = true;
        stateManager.setReadOnly(true);
    },

    /**
     * @param {import('core/local/reactive/statemanager').default} stateManager
     * @param {object} labels
     */
    setSyncLabels(stateManager, labels) {
        const state = stateManager.state.display;
        stateManager.setReadOnly(false);
        state.syncStaleLabel = labels.stale || '';
        state.syncErrorLabel = labels.error || '';
        stateManager.setReadOnly(true);
    },
};

/** @type {Reactive|null} */
let activeManager = null;

/**
 * Create or replace the timer reactive manager.
 *
 * @param {object} serverState
 * @param {number} attemptid
 * @returns {Reactive}
 */
export const createTimerManager = (serverState, attemptid) => {
    // Moodle 4.5 Reactive root state must be objects only — wrap scalars in `display`.
    const display = buildInitialState(serverState, attemptid);
    activeManager = new Reactive({
        name: 'QuizTimer',
        eventName: eventTypes.quizTimerStateChange,
        eventDispatch: dispatchStateChangedEvent,
        mutations,
        state: {display},
    });
    return activeManager;
};

/**
 * @returns {Reactive|null}
 */
export const getTimerManager = () => activeManager;

/**
 * @param {Reactive|null} manager
 */
export const setTimerManager = (manager) => {
    activeManager = manager;
};
