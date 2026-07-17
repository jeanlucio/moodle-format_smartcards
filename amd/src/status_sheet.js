// This file is part of Moodle - https://moodle.org/
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
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Opens the SmartCards status sheet (a core/modal) when a card that needs it
 * is tapped, using only data already rendered server-side in the card's
 * data-* attributes — no extra network request to open the sheet itself.
 * Also wires the manual completion toggle button inside the sheet, which
 * does make one AJAX call (core_completion_update_activity_completion_status_manually)
 * and reflects the result back onto both the sheet and the underlying card.
 *
 * @module     format_smartcards/status_sheet
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import Notification from 'core/notification';
import * as Str from 'core/str';
import * as Templates from 'core/templates';

const SELECTORS = {
    CARD_BUTTON: '[data-region="smartcards-card"].sc-card-opensheet',
    TOGGLE_BUTTON: '[data-region="smartcards-sheet-toggle"]',
};

let stringsPromise = null;

/**
 * Fetches (and caches) the fixed UI strings used by every status sheet.
 *
 * @returns {Promise<object>}
 */
const getSheetStrings = () => {
    if (stringsPromise === null) {
        stringsPromise = Str.get_strings([
            {key: 'status_reason', component: 'format_smartcards'},
            {key: 'status_due', component: 'format_smartcards'},
            {key: 'status_completion', component: 'format_smartcards'},
            {key: 'status_description', component: 'format_smartcards'},
            {key: 'completion_markdone', component: 'format_smartcards'},
            {key: 'completion_markundone', component: 'format_smartcards'},
            {key: 'accessactivity', component: 'format_smartcards'},
        ]).then(([
            statusreasonlabel,
            statusduelabel,
            statuscompletionlabel,
            statusdescriptionlabel,
            markcompletelabel,
            markincompletelabel,
            accessactivitylabel,
        ]) => ({
            statusreasonlabel,
            statusduelabel,
            statuscompletionlabel,
            statusdescriptionlabel,
            markcompletelabel,
            markincompletelabel,
            accessactivitylabel,
        }));
    }
    return stringsPromise;
};

/**
 * Builds the template context for one card from its dataset.
 *
 * @param {HTMLElement} card The clicked card button.
 * @param {object} labels Fixed UI labels from getSheetStrings().
 * @returns {object} Context for the status_sheet template.
 */
const buildContext = (card, labels) => {
    const reason = card.dataset.reason ?? '';
    const duedateformatted = card.dataset.duedateformatted ?? '';
    const hasurl = card.dataset.hasurl === '1';
    const completiontype = card.dataset.completiontype ?? 'none';
    const iscompletioncomplete = card.dataset.iscompletioncomplete === '1';
    const cantoggle = card.dataset.cantoggle === '1';
    const criteria = JSON.parse(card.dataset.completioncriteria ?? '[]');
    const description = card.dataset.description ?? '';

    return {
        hasbadgelabel: (card.dataset.badgelabel ?? '') !== '',
        badgelabel: card.dataset.badgelabel ?? '',
        hasreason: reason !== '',
        reason,
        statusreasonlabel: labels.statusreasonlabel,
        hasduedate: duedateformatted !== '',
        duedateformatted,
        statusduelabel: labels.statusduelabel,
        hascompletion: completiontype !== 'none',
        statuscompletionlabel: labels.statuscompletionlabel,
        ismanual: completiontype === 'manual',
        isautomatic: completiontype === 'automatic',
        iscompletioncomplete,
        cmid: card.dataset.cmid ?? '',
        cantoggle,
        markcompletelabel: labels.markcompletelabel,
        markincompletelabel: labels.markincompletelabel,
        criteria,
        hasdescription: card.dataset.hasdescription === '1' && description !== '',
        statusdescriptionlabel: labels.statusdescriptionlabel,
        description,
        hasurl,
        url: card.dataset.url ?? '',
        accessactivitylabel: labels.accessactivitylabel,
    };
};

/**
 * Reflects a completion state change onto the toggle button itself.
 *
 * @param {HTMLElement} button The toggle button.
 * @param {boolean} iscomplete The new completion state.
 * @returns {void}
 */
const updateToggleButton = (button, iscomplete) => {
    button.dataset.iscompletioncomplete = iscomplete ? '1' : '0';
    button.textContent = iscomplete ? button.dataset.markincompletelabel : button.dataset.markcompletelabel;
};

/**
 * Reflects a completion state change onto the underlying grid card.
 *
 * @param {string} cmid Course module id.
 * @param {boolean} iscomplete The new completion state.
 * @returns {void}
 */
const updateCard = (cmid, iscomplete) => {
    const card = document.querySelector(`${SELECTORS.CARD_BUTTON}[data-cmid="${cmid}"]`);
    if (!card) {
        return;
    }
    card.dataset.iscompletioncomplete = iscomplete ? '1' : '0';
    const icon = card.querySelector('.sc-card-completionicon');
    if (icon) {
        icon.textContent = iscomplete ? '✅' : '⚪';
    }
};

/**
 * Toggles manual completion for one activity and reflects the result in the UI.
 *
 * @param {HTMLElement} button The toggle button that was clicked.
 * @returns {Promise<void>}
 */
const toggleCompletion = async(button) => {
    const cmid = Number(button.dataset.cmid);
    const completed = button.dataset.iscompletioncomplete !== '1';

    button.disabled = true;
    try {
        await Ajax.call([{
            methodname: 'core_completion_update_activity_completion_status_manually',
            args: {cmid, completed},
        }])[0];
        updateToggleButton(button, completed);
        updateCard(cmid, completed);
    } catch (error) {
        Notification.exception(error);
    } finally {
        button.disabled = false;
    }
};

/**
 * Opens the status sheet modal for the given card.
 *
 * @param {HTMLElement} card The clicked card button.
 * @returns {Promise<void>}
 */
const openSheet = async(card) => {
    const labels = await getSheetStrings();
    const context = buildContext(card, labels);
    const {html, js} = await Templates.renderForPromise('format_smartcards/local/status_sheet', context);

    const modal = await Modal.create({
        title: card.dataset.name ?? '',
        body: html,
        show: true,
        removeOnClose: true,
        // A description can embed arbitrary rich content (images, widgets); give it the
        // extra width so it isn't cramped, same as any other sheet content stays compact.
        large: context.hasdescription,
    });
    Templates.runTemplateJS(js);

    modal.getBody()[0].addEventListener('click', (event) => {
        const button = event.target.closest(SELECTORS.TOGGLE_BUTTON);
        if (button) {
            toggleCompletion(button);
        }
    });

    return modal;
};

/**
 * Initialises the status sheet click handler.
 *
 * @returns {void}
 */
export const init = () => {
    document.addEventListener('click', (event) => {
        const card = event.target.closest(SELECTORS.CARD_BUTTON);
        if (!card) {
            return;
        }
        openSheet(card);
    });
};
