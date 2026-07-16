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
 * left off" default content.php computes on a first-ever visit (see its
 * find_default_open_section_index()).
 *
 * The toggle click itself needs no code here: importing theme_boost/bootstrap/collapse
 * activates Bootstrap's own [data-bs-toggle="collapse"] handling for the whole page (a
 * side effect of its own module-load-time data-api registration).
 *
 * Persistence reuses core_courseformat_update_course, the exact web service and section
 * preference (course_format::get_sections_preferences(), 'contentcollapsed') the
 * standard Moodle course page already uses for its own collapsible sections — not a
 * bespoke storage mechanism — so content.php's read side and this write side agree with
 * core's own semantics.
 *
 * @module     format_smartcards/accordion
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import 'theme_boost/bootstrap/collapse';

const SELECTORS = {
    CONTENT: '[data-region="smartcards-content"]',
    ACCORDION_BODY: '[data-region="smartcards-accordion-body"]',
};

/**
 * Persists one section's collapsed/expanded state for the current user.
 *
 * @param {string} courseid Course id, from the content root's dataset.
 * @param {string} sectionid Section id, from the toggled element's dataset.
 * @param {boolean} collapsed Whether the section was just collapsed (true) or expanded (false).
 * @returns {Promise<void>}
 */
const persistToggle = (courseid, sectionid, collapsed) => Ajax.call([{
    methodname: 'core_courseformat_update_course',
    args: {
        action: collapsed ? 'section_content_collapsed' : 'section_content_expanded',
        courseid: Number(courseid),
        ids: [Number(sectionid)],
    },
}])[0];

/**
 * Handles a Bootstrap collapse lifecycle event on one accordion section body.
 *
 * @param {Event} event The shown.bs.collapse or hidden.bs.collapse event.
 * @param {boolean} collapsed Whether this handler is for the collapsed (true) or shown (false) event.
 * @returns {void}
 */
const handleToggle = (event, collapsed) => {
    if (!event.target.matches(SELECTORS.ACCORDION_BODY)) {
        return;
    }
    const content = event.target.closest(SELECTORS.CONTENT);
    if (!content) {
        return;
    }
    persistToggle(content.dataset.courseid, event.target.dataset.sectionid, collapsed);
};

/**
 * Initialises the accordion's toggle persistence.
 *
 * @returns {void}
 */
export const init = () => {
    document.addEventListener('hidden.bs.collapse', (event) => handleToggle(event, true));
    document.addEventListener('shown.bs.collapse', (event) => handleToggle(event, false));
};
