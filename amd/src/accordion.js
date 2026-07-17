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
 * Persists the accordion navstyle's manual expand/collapse choices, so a student's own
 * decision survives a page reload instead of always resetting to the "resume where you
 * left off" default content.php computes for sections nobody has touched yet (see its
 * find_default_active_section_index()).
 *
 * The toggle click itself needs no code here: importing theme_boost/bootstrap/collapse
 * activates Bootstrap's own [data-bs-toggle="collapse"] handling for the whole page (a
 * side effect of its own module-load-time data-api registration).
 *
 * Persistence calls format_smartcards_toggle_section rather than core's own
 * core_courseformat_update_course: core's single 'contentcollapsed' preference only
 * records explicit collapses, so an explicit *expand* of a section this accordion had
 * closed by its own default would be a no-op on that list — indistinguishable from a
 * section never touched at all, and silently reverting on the next reload. See
 * toggle_section.php's docblock for the two-preference fix.
 *
 * @module     format_smartcards/accordion
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import 'theme_boost/bootstrap/collapse';

const SELECTORS = {
    ACCORDION_BODY: '[data-region="smartcards-accordion-body"]',
};

/**
 * Persists one section's collapsed/expanded state for the current user.
 *
 * @param {string} sectionid Section id, from the toggled element's dataset.
 * @param {boolean} open Whether the section was just expanded (true) or collapsed (false).
 * @returns {Promise<void>}
 */
const persistToggle = (sectionid, open) => Ajax.call([{
    methodname: 'format_smartcards_toggle_section',
    args: {
        sectionid: Number(sectionid),
        open,
    },
}])[0];

/**
 * Handles a Bootstrap collapse lifecycle event on one accordion section body.
 *
 * @param {Event} event The shown.bs.collapse or hidden.bs.collapse event.
 * @param {boolean} open Whether this handler is for the shown (true) or hidden (false) event.
 * @returns {void}
 */
const handleToggle = (event, open) => {
    if (!event.target.matches(SELECTORS.ACCORDION_BODY)) {
        return;
    }
    persistToggle(event.target.dataset.sectionid, open);
};

/**
 * Initialises the accordion's toggle persistence.
 *
 * @returns {void}
 */
export const init = () => {
    document.addEventListener('shown.bs.collapse', (event) => handleToggle(event, true));
    document.addEventListener('hidden.bs.collapse', (event) => handleToggle(event, false));
};
