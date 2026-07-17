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
 * Opens a full-screen core/modal for a section card (navstyle = 'sectioncards'),
 * showing that section's own nested activity grid — or, for a restricted section, the
 * availability reason instead (see content.mustache: both are already rendered
 * server-side into a hidden sibling of the card button, so this module only ever moves
 * already-rendered HTML into the modal body; it never re-renders anything client-side).
 * Cards inside the nested grid keep working exactly as they do inline — status_sheet.js
 * listens on document, not on the grid's original location, so a card cloned into the
 * modal still opens its own sheet correctly.
 *
 * @module     format_smartcards/section_modal
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';

const SELECTORS = {
    CARD: '[data-region="smartcards-section-card"]',
    SOURCE: '[data-region="smartcards-section-modal-source"]',
};

/**
 * Opens the full-screen modal for one section card.
 *
 * @param {HTMLElement} card The clicked section card button.
 * @returns {Promise<void>}
 */
const openModal = async(card) => {
    const source = document.querySelector(`${SELECTORS.SOURCE}[data-sectionid="${card.dataset.sectionid}"]`);
    if (!source) {
        return;
    }

    const modal = await Modal.create({
        title: card.dataset.name ?? '',
        body: source.innerHTML,
        show: true,
        removeOnClose: true,
        large: true,
    });
    modal.getRoot()[0].classList.add('sc-section-modal');
};

/**
 * Initialises the delegated click handler for every section card.
 *
 * @returns {void}
 */
export const init = () => {
    document.addEventListener('click', (event) => {
        const card = event.target.closest(SELECTORS.CARD);
        if (!card) {
            return;
        }
        openModal(card);
    });
};
