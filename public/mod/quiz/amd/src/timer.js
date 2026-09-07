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
 * Quiz timer bootstrap — server-authoritative countdown.
 *
 * @module     mod_quiz/timer
 * @copyright  2026 SSYSTEMS GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import Notification from 'core/notification';
import {
    applyServerPayload,
    isExpiredFromServer,
    pollTimerState,
    resetSyncState,
    setPollCallback,
    stopPolling,
    wireReconnectHandlers as wireSyncReconnect,
} from 'mod_quiz/timer/sync';
import {setHourMinuteTemplate} from 'mod_quiz/timer/format';
import {createTimerManager, getTimerManager} from 'mod_quiz/timer/manager';
import TimerComponent from 'mod_quiz/timer/component';
import {
    destroy as destroyNotifications,
    init as initNotifications,
} from 'mod_quiz/timer/notifications';

/** @type {TimerComponent|null} */
let activeComponent = null;

/** @type {(() => void)|null} */
let unwiredReconnect = null;

/**
 * Apply server-authoritative remaining seconds from autosave or poll payload.
 *
 * @param {number} timeleft Seconds remaining from server
 */
export const applyServerTimeleft = (timeleft) => {
    const manager = getTimerManager();
    const ispreview = manager?.state?.display?.ispreview ?? false;
    const payload = {
        timeleft: timeleft,
        attemptstate: 'inprogress',
        ispreview,
    };
    applyServerPayload(payload);
    if (manager) {
        manager.dispatch('applyServerPayload', payload);
    }
    if (activeComponent) {
        activeComponent.applyPollResult(payload);
        return;
    }
    if (isExpiredFromServer(payload)) {
        TimerComponent.setTimeupCallback(() => {
            TimerComponent.submitOnTimeup().catch(Notification.exception);
        });
        TimerComponent.submitOnTimeup().catch(Notification.exception);
    }
};

/**
 * Wire reconnect resync on tab focus / online.
 *
 * @param {number} attemptid
 */
const wireReconnectHandlers = (attemptid) => {
    if (unwiredReconnect) {
        unwiredReconnect();
    }
    unwiredReconnect = wireSyncReconnect(attemptid, async() => {
        const result = await pollTimerState(attemptid);
        if (activeComponent) {
            await activeComponent.applyPollResult(result);
        }
    });
};

/**
 * Initialise the quiz timer on attempt pages.
 *
 * @param {number} attemptid
 * @param {object} initialState Server timer_state_exporter payload
 * @param {boolean} ispreview
 * @param {{showdegraded?: boolean, showsuccess?: boolean}} [notificationConfig] Effective sync toast flags
 */
export const init = async(attemptid, initialState, ispreview, notificationConfig = {}) => {
    const wrapper = document.querySelector('#quiz-timer-wrapper');

    // Defense: overdue -N → 0 before expiry checks (Spec 025); keep exact -1 (Spec 022).
    if (typeof initialState.timeleft === 'number' && initialState.timeleft < 0 && initialState.timeleft !== -1) {
        initialState = {...initialState, timeleft: 0};
    }

    // Page loaded after time expired: no timer UI, but attempt still open (R-003).
    if (!wrapper && isExpiredFromServer({...initialState, ispreview})) {
        TimerComponent.setTimeupCallback(() => {
            TimerComponent.submitOnTimeup().catch(Notification.exception);
        });
        TimerComponent.submitOnTimeup().catch(Notification.exception);
        return;
    }

    // No timer (-1) or missing wrapper without expiry: nothing to drive.
    if (!wrapper || initialState.timeleft === -1 || initialState.timeleft < 0) {
        return;
    }

    try {
        const timeString = await getString('timestring', 'quiz');
        setHourMinuteTemplate(timeString);
    } catch (error) {
        setHourMinuteTemplate('%%HH%%:%%MM%%');
    }

    const manager = createTimerManager({...initialState, ispreview}, attemptid);

    try {
        const [staleLabel, errorLabel, restoredLabel] = await Promise.all([
            getString('timersyncstale', 'quiz'),
            getString('timersyncerror', 'quiz'),
            getString('timersyncrestored', 'quiz'),
        ]);
        manager.dispatch('setSyncLabels', {stale: staleLabel, error: errorLabel});
        await initNotifications(notificationConfig || {}, {
            stale: staleLabel,
            error: errorLabel,
            restored: restoredLabel,
        });
    } catch (error) {
        // Strings optional during upgrade — still init with empty labels / defaults.
        await initNotifications(notificationConfig || {}, {stale: '', error: '', restored: ''});
    }

    applyServerPayload({...initialState, ispreview});

    TimerComponent.setTimeupCallback(() => {
        TimerComponent.submitOnTimeup().catch(Notification.exception);
    });

    // After notifications.init (incl. toast prefetch) so first offline toast can render from cache.
    activeComponent = TimerComponent.init('#quiz-timer-wrapper', manager);
    setPollCallback((result) => {
        if (activeComponent) {
            activeComponent.applyPollResult(result);
        }
    });
    wireReconnectHandlers(attemptid);
};

/**
 * Stop timer (form submit).
 */
export const stop = () => {
    TimerComponent.stop();
    stopPolling();
    if (unwiredReconnect) {
        unwiredReconnect();
        unwiredReconnect = null;
    }
    activeComponent = null;
    destroyNotifications();
    resetSyncState();
};
