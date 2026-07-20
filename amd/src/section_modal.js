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
 * Opens a core/modal, at its native size, for a section card (navstyle =
 * 'sectioncards'), showing that section's own nested activity grid — or, for a
 * restricted section, the availability reason instead (see content.mustache: both are
 * already rendered server-side into a hidden sibling of the card button, so this
 * module only ever moves already-rendered HTML into the modal body; it never
 * re-renders anything client-side). Cards inside the nested grid keep working exactly
 * as they do inline — status_sheet.js listens on document, not on the grid's original
 * location, so a card cloned into the modal still opens its own sheet correctly.
 *
 * @module     format_smartcards/section_modal
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';

const SELECTORS = {
    CARD: '[data-region="smartcards-section-card"]',
    SOURCE: '[data-region="smartcards-section-modal-source"]',
    CONTENT: '[data-region="smartcards-content"]',
};

// Matches the cardsize/showcardframe modifier classes content.mustache puts on
// .sc-course (sc-size-medium, sc-size-large, sc-noframe) — the only ones the cards
// moved into the modal need to keep looking the same as they do inline.
const SIZE_FRAME_CLASS = /^sc-(size-\w+|noframe)$/;

// Course-configured opening animation (format_smartcards/modaleffect, see lib.php).
// 'default' plays core/modal's own Bootstrap fade untouched (no keyframes below).
// Both use the same duration/easing — a springy overshoot past the resting position,
// picked because a plain ease-out at this scale/offset still read as barely
// noticeable; the overshoot is what makes the motion register.
const EFFECTS = {
    zoom: {
        keyframes: [
            {transform: 'scale(0.5)', opacity: 0},
            {transform: 'scale(1)', opacity: 1},
        ],
        options: {duration: 450, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)'},
    },
    slideup: {
        keyframes: [
            {transform: 'translateY(160px)', opacity: 0},
            {transform: 'translateY(0)', opacity: 1},
        ],
        options: {duration: 450, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)'},
    },
};

let modaleffect = 'default';

/**
 * Opens the modal for one section card, at core/modal's own native size.
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
    });

    const modalRoot = modal.getRoot()[0];
    modalRoot.classList.add('sc-section-modal');

    // The core/modal module hoists its root to <body>, so the cm_grid moved into the
    // modal body no longer sits under the page's own .sc-course — losing both its cardsize/
    // showcardframe modifier classes and the --sc-card-size/--sc-card-icon-size custom
    // properties that only .sc-course defines (styles.css), which made every card in
    // the modal render at the small/framed default regardless of the course's real
    // settings. Re-adding .sc-course plus those same modifier classes to the modal's
    // own root restores that CSS scope without duplicating any rule.
    const content = document.querySelector(SELECTORS.CONTENT);
    if (content) {
        modalRoot.classList.add('sc-course');
        content.classList.forEach((className) => {
            if (SIZE_FRAME_CLASS.test(className)) {
                modalRoot.classList.add(className);
            }
        });
    }

    const effect = EFFECTS[modaleffect];
    if (!effect) {
        return;
    }

    // Element.animate() (Web Animations API) is used instead of a CSS transition
    // triggered by a class toggle: two earlier CSS-transition attempts both broke in
    // ways specific to *when* they ran — one never painted a "before" frame because
    // .show flips display:none to block in the same step a transition needs two
    // separate steps for, the other worked everywhere except a page's very first
    // modal, because two requestAnimationFrame calls back to back are not guaranteed
    // to span two real frames before the browser's rendering loop is "warmed up".
    // animate() has neither failure mode: it is handed explicit keyframes and a
    // duration and always plays them, regardless of display state or how "cold" the
    // page's render loop is — there is no separate trigger step to mistime at all.
    const dialog = modalRoot.querySelector('.modal-dialog');
    const reducedmotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (dialog && !reducedmotion) {
        dialog.animate(effect.keyframes, effect.options);
    }
};

/**
 * Initialises the delegated click handler for every section card.
 *
 * @param {string} effect The course's configured modal opening effect
 *                         (format_smartcards/modaleffect: 'default', 'zoom' or
 *                         'slideup'), passed by content.php's export_for_template().
 * @returns {void}
 */
export const init = (effect = 'default') => {
    modaleffect = effect;
    document.addEventListener('click', (event) => {
        const card = event.target.closest(SELECTORS.CARD);
        if (!card) {
            return;
        }
        openModal(card);
    });
};
