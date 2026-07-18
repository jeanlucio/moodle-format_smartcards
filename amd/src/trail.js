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
 * navstyle = 'trail': on page load, jumps straight to the first activity the student
 * has not completed yet, in course order — the card-level equivalent of the "open the
 * pending section by default" rule the accordion/tabs navstyles already apply at
 * section level (see content.php's find_default_active_section_index()). Every card in
 * .sc-trail carries data-ispending (cm_button.mustache), so the first match in document
 * order is already course order (cm_trail.mustache keeps DOM order === course order).
 * Skipped entirely when the URL already carries a hash, so an explicit deep link or
 * anchor is never overridden by the automatic jump.
 *
 * @module     format_smartcards/trail
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    PENDING_CARD: '.sc-trail [data-region="smartcards-card"][data-ispending="1"]',
};

/**
 * Scrolls to the first pending activity card, if any. The card's own scroll-margin-top
 * (styles.css) keeps it clear of the sticky section header + Boost's fixed navbar above
 * it, so a plain scrollIntoView() is all that is needed here.
 *
 * @returns {void}
 */
const scrollToFirstPending = () => {
    if (window.location.hash) {
        return;
    }

    const target = document.querySelector(SELECTORS.PENDING_CARD);
    if (!target) {
        return;
    }

    target.scrollIntoView({block: 'start'});
};

/**
 * Initialises the trail navstyle's auto-scroll to the first pending activity.
 *
 * @returns {void}
 */
export const init = () => {
    scrollToFirstPending();
};
