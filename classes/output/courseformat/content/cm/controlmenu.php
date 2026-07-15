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
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controlmenu extends controlmenu_base {
    /**
     * Returns the parent's edit control items plus a "Card appearance" entry, when the
     * current user can manage appearance in this module's course.
     *
     * @return array|null Edit control items, keyed by action name.
     */
    #[\Override]
    public function get_cm_control_items(): ?array {
        $controls = parent::get_cm_control_items();

        if (!has_capability('format/smartcards:manageappearance', $this->modcontext)) {
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

        return $this->add_control_after($controls, 'update', 'smartcardsappearance', $item);
    }
}
