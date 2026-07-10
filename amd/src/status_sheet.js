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
 * Opens the SmartCards status sheet (a core/modal) when a badged activity
 * card is tapped, using only data already rendered server-side in the
 * card's data-* attributes — no extra network request.
 *
 * @module     format_smartcards/status_sheet
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import * as Str from 'core/str';
import * as Templates from 'core/templates';

const SELECTORS = {
    CARD_BUTTON: '[data-region="smartcards-card"].sc-card-badge',
};

let stringsPromise = null;

/**
 * Fetches (and caches) the fixed UI strings used by every status sheet.
 *
 * @returns {Promise<{statusreasonlabel: string, statusduelabel: string, accessactivitylabel: string}>}
 */
const getSheetStrings = () => {
    if (stringsPromise === null) {
        stringsPromise = Str.get_strings([
            {key: 'status_reason', component: 'format_smartcards'},
            {key: 'status_due', component: 'format_smartcards'},
            {key: 'accessactivity', component: 'format_smartcards'},
        ]).then(([statusreasonlabel, statusduelabel, accessactivitylabel]) => ({
            statusreasonlabel,
            statusduelabel,
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

    return {
        badgelabel: card.dataset.badgelabel ?? '',
        hasreason: reason !== '',
        reason,
        statusreasonlabel: labels.statusreasonlabel,
        hasduedate: duedateformatted !== '',
        duedateformatted,
        statusduelabel: labels.statusduelabel,
        hasurl,
        url: card.dataset.url ?? '',
        accessactivitylabel: labels.accessactivitylabel,
    };
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
    });
    Templates.runTemplateJS(js);

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
