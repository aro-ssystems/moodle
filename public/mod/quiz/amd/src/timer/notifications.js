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
 * Timer network notifications via core/toast (.toast-wrapper overlay).
 *
 * Offline-safe display requires warming Moodle toast templates and ensuring
 * theme_boost/bootstrap/toast is already in the RequireJS registry before the
 * first sync failure: message.mustache {{#js}} require()s that module to call
 * jQuery(...).toast('show'). PrefetchTemplates is fire-and-forget — do not await it.
 *
 * @module     mod_quiz/timer/notifications
 * @copyright  2026 SSYSTEMS GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import {add as addToast, addToastRegion} from 'core/toast';
// Hard AMD dependency: loaded with this module (while the attempt page is online)
// so message.mustache {{#js}} can require it under full Offline.
import 'theme_boost/bootstrap/toast';

/** @type {number} */
const SUCCESS_DISMISS_MS = 5000;

/**
 * Minimal context so renderForPromise resolves {{#str}} / close-button branches.
 * HTML is discarded — we only warm the template (+ string) cache.
 *
 * @type {Object}
 */
const TOAST_MESSAGE_WARM_CONTEXT = {
    message: '',
    type: 'warning',
    autohide: false,
    closeButton: true,
    delay: 0,
};

/** @type {{showdegraded: boolean, showsuccess: boolean}} */
let config = {
    showdegraded: true,
    showsuccess: true,
};

/** @type {{stale: string, error: string, restored: string}} */
let strings = {
    stale: '',
    error: '',
    restored: '',
};

/** @type {'ok'|'stale'|'error'} */
let lastNotifiedStatus = 'ok';

/** @type {HTMLElement|null} */
let degradedElement = null;

/**
 * @returns {HTMLElement|null}
 */
const getToastWrapper = () => document.querySelector('.toast-wrapper');

/**
 * Remove the active degraded toast from the DOM.
 */
const removeDegraded = () => {
    if (degradedElement && degradedElement.parentNode) {
        degradedElement.remove();
    }
    degradedElement = null;
};

/**
 * Remember the most recently added degraded toast for later removal.
 */
const trackDegradedElement = () => {
    const wrapper = getToastWrapper();
    if (!wrapper) {
        return;
    }
    // Toasts are prepended; the first .toast is the one we just added.
    degradedElement = wrapper.querySelector('.toast');
};

/**
 * @param {'warning'|'error'} type
 * @param {string} message
 */
const showDegraded = async(type, message) => {
    removeDegraded();
    const toastType = type === 'warning' ? 'warning' : 'danger';
    await addToast(message, {
        type: toastType,
        autohide: false,
        closeButton: true,
    });
    trackDegradedElement();
};

/**
 * @param {string} message
 */
const showSuccess = async(message) => {
    removeDegraded();
    await addToast(message, {
        type: 'success',
        autohide: true,
        delay: SUCCESS_DISMISS_MS,
        closeButton: true,
    });
};

/**
 * Warm everything core/toast.add needs so the first degraded toast works offline.
 *
 * 1. renderForPromise for message (+ {{#str}} deps) — awaitable, fills template cache
 * 2. addToastRegion — inserts .toast-wrapper via core/toast (also warms wrapper template)
 * 3. Explicit require of jquery + bootstrap toast — same modules message.mustache {{#js}} uses
 *
 * Soft-fails on error — attempt bootstrap must continue (FR-005).
 *
 * @returns {Promise<void>}
 */
const warmToastPresentation = async() => {
    try {
        await Templates.renderForPromise('core/local/toast/message', TOAST_MESSAGE_WARM_CONTEXT);

        if (!getToastWrapper()) {
            await addToastRegion(document.body);
        }

        // Ensure the exact RequireJS modules from message.mustache {{#js}} are registered.
        await new Promise((resolve, reject) => {
            // eslint-disable-next-line no-undef
            require(['jquery', 'theme_boost/bootstrap/toast'], resolve, reject);
        });

        // Run the real core/toast.add → Bootstrap .toast('show') pipeline once while
        // online, then remove the node so learners do not keep a warm-up toast.
        await addToast('\u00a0', {
            type: 'warning',
            autohide: true,
            delay: 1,
            closeButton: false,
        });
        const wrapper = getToastWrapper();
        if (wrapper) {
            wrapper.querySelectorAll('.toast').forEach((node) => node.remove());
        }
    } catch (error) {
        // eslint-disable-next-line no-console
        console.warn('mod_quiz/timer/notifications: toast presentation warm-up failed', error);
    }
};

/**
 * Initialise notification module for an attempt.
 *
 * When showdegraded is effective, warms core/toast presentation before polling.
 *
 * @param {{showdegraded?: boolean, showsuccess?: boolean}} notificationConfig
 * @param {{stale: string, error: string, restored: string}} labels
 * @returns {Promise<void>}
 */
export const init = async(notificationConfig, labels) => {
    config = {
        showdegraded: notificationConfig?.showdegraded !== false,
        showsuccess: notificationConfig?.showsuccess !== false,
    };
    strings = {...labels};
    lastNotifiedStatus = 'ok';
    degradedElement = null;

    if (config.showdegraded) {
        await warmToastPresentation();
    }
};

/**
 * Handle a syncStatus transition edge.
 *
 * @param {'ok'|'stale'|'error'} previous
 * @param {'ok'|'stale'|'error'} current
 */
export const handleTransition = async(previous, current) => {
    if (previous === current) {
        return;
    }

    if (!config.showdegraded) {
        lastNotifiedStatus = current;
        return;
    }

    if (current === 'ok') {
        if (previous === 'stale' || previous === 'error') {
            removeDegraded();
            if (config.showsuccess) {
                await showSuccess(strings.restored);
            }
        }
        lastNotifiedStatus = 'ok';
        return;
    }

    if (current === 'stale') {
        if (lastNotifiedStatus !== 'stale' && lastNotifiedStatus !== 'error') {
            await showDegraded('warning', strings.stale);
            lastNotifiedStatus = 'stale';
        }
        return;
    }

    if (current === 'error' && lastNotifiedStatus !== 'error') {
        await showDegraded('error', strings.error);
        lastNotifiedStatus = 'error';
    }
};

/**
 * Tear down notifications on attempt end.
 */
export const destroy = () => {
    removeDegraded();
    lastNotifiedStatus = 'ok';
};
