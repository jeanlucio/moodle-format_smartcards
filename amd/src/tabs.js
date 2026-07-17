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
 * Activates the tabs navstyle's tab switching.
 *
 * Unlike the accordion, a tab switch has nothing to persist (confirmed during design —
 * every page load simply recomputes the same "first pending" default server-side, see
 * content.php's find_default_active_section_index()), so this module needs no code of
 * its own beyond activating Bootstrap's native tab handling: importing
 * theme_boost/bootstrap/tab registers its own document-level [data-bs-toggle="tab"]
 * click delegation as a side effect of the module load, exactly like
 * theme_boost/bootstrap/collapse does for the accordion.
 *
 * @module     format_smartcards/tabs
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import 'theme_boost/bootstrap/tab';

/**
 * Initialises the tabs navstyle. Exists only so content.php has a function to call via
 * js_call_amd() — the import above already did all the real work at module-load time.
 *
 * @returns {void}
 */
export const init = () => {
    // Intentionally empty — see module docblock.
};
