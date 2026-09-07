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
 * Monotone quiz timer sync — server timeleft without wall clock.
 *
 * Display code must use {@link getDisplaySecondsLeft} only; never Date.now() / new Date().
 *
 * @module     mod_quiz/timer/sync
 * @copyright  2026 SSYSTEMS GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';

/** Default poll interval when plenty of time remains (ms). */
const POLL_INTERVAL_DEFAULT_MS = 30000;

/** Fast poll interval when under two minutes remain (ms). */
const POLL_INTERVAL_FAST_MS = 5000;

/** Threshold for fast polling (seconds). */
const FAST_POLL_THRESHOLD_SECONDS = 120;

/** @type {number|null} */
let serverTimeleft = null;

/** @type {number|null} performance.now() at last server sync */
let syncedAtMonotonic = null;

/** @type {number|null} */
let pollTimerId = null;

/** @type {number|null} */
let currentAttemptId = null;

/** @type {((payload: object|null) => void)|null} */
let onPollComplete = null;

/**
 * Whether the server signals an expired in-progress attempt (US-04).
 *
 * @param {object|null} payload Timer state from exporter / WS
 * @returns {boolean}
 */
export const isExpiredFromServer = (payload) => {
    if (!payload || payload.attemptstate !== 'inprogress') {
        return false;
    }
    // Server: >0 remaining, 0 expired (incl. overdue after clamp), -1 no timer.
    return typeof payload.timeleft === 'number' && payload.timeleft === 0;
};

/**
 * Apply authoritative server remaining seconds from autosave or poll.
 *
 * @param {number} seconds Seconds left from server (-1 = no timer; other negatives = overdue → 0).
 */
export const applyServerTimeleft = (seconds) => {
    // Exact -1 only: no timer (Spec 022). Other negatives (autosave overdue) → expired 0.
    if (seconds === -1) {
        serverTimeleft = null;
        syncedAtMonotonic = null;
        return;
    }
    if (seconds < 0) {
        seconds = 0;
    }
    serverTimeleft = seconds;
    syncedAtMonotonic = performance.now();
};

/**
 * Apply full server timer payload (poll / reconnect).
 *
 * @param {object|null} payload Timer state from exporter / WS
 */
export const applyServerPayload = (payload) => {
    if (!payload || typeof payload.timeleft !== 'number') {
        return;
    }
    // Exact -1 = no timer: clear sync; never coerce to expired zero (Spec 022).
    if (payload.timeleft === -1) {
        applyServerTimeleft(-1);
        return;
    }
    // Other negatives = overdue raw channel: coerce to 0 for expiry/sync (Spec 025).
    if (payload.timeleft < 0) {
        payload.timeleft = 0;
    }
    if (isExpiredFromServer(payload)) {
        serverTimeleft = 0;
        syncedAtMonotonic = performance.now();
        return;
    }
    applyServerTimeleft(payload.timeleft);
};

/**
 * Derived display value — monotone countdown without wall clock.
 *
 * @returns {number|null} Seconds left for display, or null if no timer.
 */
export const getDisplaySecondsLeft = () => {
    if (serverTimeleft === null || syncedAtMonotonic === null) {
        return null;
    }
    const elapsed = (performance.now() - syncedAtMonotonic) / 1000;
    return Math.max(0, Math.floor(serverTimeleft - elapsed));
};

/**
 * Poll interval based on current derived display.
 *
 * @returns {number} Milliseconds until next poll.
 */
export const getPollIntervalMs = () => {
    const display = getDisplaySecondsLeft();
    if (display !== null && display < FAST_POLL_THRESHOLD_SECONDS) {
        return POLL_INTERVAL_FAST_MS;
    }
    return POLL_INTERVAL_DEFAULT_MS;
};

/**
 * Fetch timer state from mod_quiz_get_timer_state.
 *
 * @param {number} attemptid
 * @returns {Promise<object>}
 */
export const fetchTimerState = async(attemptid) => {
    const [result] = await fetchMany([{
        methodname: 'mod_quiz_get_timer_state',
        args: {
            attemptid,
        },
    }]);
    return result;
};

/**
 * Poll server and update monotone state.
 *
 * @param {number} attemptid
 * @returns {Promise<object|null>} Latest server payload, or null on failure.
 */
export const pollTimerState = async(attemptid) => {
    try {
        const result = await fetchTimerState(attemptid);
        applyServerPayload(result);
        if (onPollComplete) {
            onPollComplete(result);
        }
        return result;
    } catch (error) {
        window.console.warn('Quiz timer sync failed', error);
        if (onPollComplete) {
            onPollComplete(null);
        }
        return null;
    }
};

/**
 * Register callback invoked after each poll (success or failure).
 *
 * @param {((payload: object|null) => void)|null} callback
 */
export const setPollCallback = (callback) => {
    onPollComplete = callback;
};

/**
 * Schedule the next poll using adaptive interval.
 *
 * @param {number} attemptid
 */
const schedulePoll = (attemptid) => {
    if (pollTimerId !== null) {
        clearTimeout(pollTimerId);
    }
    pollTimerId = window.setTimeout(async() => {
        await pollTimerState(attemptid);
        if (currentAttemptId === attemptid) {
            schedulePoll(attemptid);
        }
    }, getPollIntervalMs());
};

/**
 * Start adaptive polling for an attempt.
 *
 * @param {number} attemptid
 * @param {number} [initialTimeleft] Optional bootstrap from PHP render.
 */
export const startPolling = (attemptid, initialTimeleft = null) => {
    stopPolling();
    currentAttemptId = attemptid;
    if (typeof initialTimeleft === 'number') {
        applyServerTimeleft(initialTimeleft);
    }
    schedulePoll(attemptid);
};

/**
 * Stop polling.
 */
export const stopPolling = () => {
    if (pollTimerId !== null) {
        clearTimeout(pollTimerId);
        pollTimerId = null;
    }
    currentAttemptId = null;
};

/**
 * Reset all sync state (e.g. on page unload).
 */
export const resetSyncState = () => {
    stopPolling();
    serverTimeleft = null;
    syncedAtMonotonic = null;
    onPollComplete = null;
};

/**
 * Whether display countdown has reached zero (monotone model).
 *
 * @returns {boolean}
 */
export const isTimeExpired = () => {
    const display = getDisplaySecondsLeft();
    return display !== null && display <= 0;
};

/**
 * Register reconnect resync handlers.
 *
 * @param {number} attemptid
 * @param {() => Promise<void>} onResync
 */
export const wireReconnectHandlers = (attemptid, onResync) => {
    const handler = async() => {
        if (document.visibilityState === 'hidden' && !navigator.onLine) {
            return;
        }
        await onResync();
    };
    document.addEventListener('visibilitychange', handler);
    window.addEventListener('online', handler);
    return () => {
        document.removeEventListener('visibilitychange', handler);
        window.removeEventListener('online', handler);
    };
};
