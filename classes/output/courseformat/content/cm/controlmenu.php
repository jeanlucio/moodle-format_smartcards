<?php
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace format_smartcards\output\courseformat\content\cm;

use core\output\action_menu\link_secondary;
use core\output\pix_icon;
use core\url;
use core_courseformat\output\local\content\cm\controlmenu as controlmenu_base;
use format_smartcards\output\courseformat\content\controlmenu_insert;

/**
 * Adds a "Card appearance" entry to the native per-activity edit menu.
 *
 * The SmartCards grid (cm_button.mustache) only renders outside edit mode — while
 * editing, the format falls back entirely to the core section/cm renderers so drag-and
 * -drop and the standard add-activity UI keep working for free (see
 * content::export_for_template()). That means the emoji/icon/colour editor cannot live
 * on the card itself while editing; it is exposed instead through this menu, which core
 * already renders per activity regardless of that fallback.
 *
 * Reads the module context off {@see cm_info::$context} (never $this->modcontext, nor
 * core's own {@see \core_courseformat\output\local\content\basecontrolmenu::
 * add_control_after()}): both are Moodle 5.x-only additions to the base class the
 * plugin's minimum-supported Moodle 4.5 does not have, a real bug this class shipped
 * with since it was first added — fatal on every edit-mode page load on a 4.5 site,
 * never caught because nothing exercised this code path in a test until the section
 * counterpart's own test found the same landmine there.
 *
 * Overrides two differently-named methods, not one, for the same reason:
 * get_cm_control_items() (public) is Moodle 5.x's real path, taken whenever a format
 * opts into supports_components() (format_smartcards always does); cm_control_items()
 * (protected) is Moodle 4.5's *only* method for this — it has no get_cm_control_items()
 * at all — and is kept on 5.x too, deprecated, purely as a fallback for formats that
 * opt out of components (format_smartcards never does, so that override is simply dead
 * code there, but costs nothing and keeps Moodle 4.5 working without a second class).
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controlmenu extends controlmenu_base {
    use controlmenu_insert;

    /**
     * Returns the parent's edit control items plus a "Card appearance" entry, when the
     * current user can manage appearance in this module's course. Moodle 5.x path.
     *
     * Deliberately not marked #[\Override]: this method does not exist anywhere in the
     * Moodle 4.5 class hierarchy (see this class's docblock), so the attribute would be
     * a hard class-load error there under PHP 8.3+, where #[\Override] is actually
     * enforced (a no-op on the PHP 8.2 this plugin's own CI runs, which is exactly why
     * that risk went unnoticed until reasoned through here).
     *
     * @return array|null Edit control items, keyed by action name.
     */
    public function get_cm_control_items(): ?array {
        return $this->add_appearance_control(parent::get_cm_control_items());
    }

    /**
     * Same as {@see get_cm_control_items()}, but for Moodle 4.5's differently-named,
     * non-deprecated equivalent (and 5.x's deprecated fallback for the same method —
     * see this class's docblock).
     *
     * @return array|null Edit control items, keyed by action name.
     */
    #[\Override]
    protected function cm_control_items() {
        return $this->add_appearance_control(parent::cm_control_items());
    }

    /**
     * Adds the "Card appearance" entry to an already-built control array, when the
     * current user can manage appearance in this module's course.
     *
     * @param array|null $controls Edit control items, keyed by action name.
     * @return array|null
     */
    private function add_appearance_control(?array $controls): ?array {
        if (!has_capability('format/smartcards:manageappearance', $this->mod->context)) {
            return $controls;
        }

        $item = new link_secondary(
            url: new url('#'),
            icon: new pix_icon('t/edit', ''),
            text: get_string('editappearance', 'format_smartcards'),
            attributes: [
                'class' => 'sc-edit-appearance-action',
                'data-action' => 'smartcardsEditAppearance',
                'data-cmid' => (string)$this->mod->id,
                'data-name' => $this->mod->get_formatted_name(),
            ],
        );

        return $this->insert_control_after($controls ?? [], 'update', 'smartcardsappearance', $item);
    }
}
