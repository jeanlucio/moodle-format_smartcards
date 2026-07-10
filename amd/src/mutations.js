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
 * SmartCards format mutations module.
 *
 * Registers format-specific mutations with the Moodle reactive course editor.
 * Inherits all default mutations (drag-and-drop, hide/show, delete, etc.)
 * from the core mutations class without adding custom ones in this version.
 *
 * @module     format_smartcards/mutations
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import DefaultMutations from 'core_courseformat/local/courseeditor/mutations';

/**
 * SmartCards mutations — extends the default set without additions for now.
 */
class SmartCardsMutations extends DefaultMutations {
    // Format-specific mutations can be added here in future phases.
}

/**
 * Initialises the course editor and registers SmartCards mutations.
 *
 * @returns {void}
 */
export const init = () => {
    const courseEditor = getCurrentCourseEditor();
    courseEditor.addMutations(new SmartCardsMutations());
};
