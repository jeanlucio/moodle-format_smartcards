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

namespace format_smartcards\output\courseformat\content\section;

use core\output\action_menu\link_secondary;
use core\output\pix_icon;
use core\url;
use core_courseformat\output\local\content\section\controlmenu as controlmenu_base;

/**
 * Adds a "Card appearance" entry to the native per-section edit menu.
 *
 * The section-level counterpart of {@see \format_smartcards\output\courseformat\
 * content\cm\controlmenu} — same rationale: the SmartCards grid only renders outside
 * edit mode, so the appearance editor is exposed through this menu instead, which core
 * already renders per section regardless of that fallback.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controlmenu extends controlmenu_base {
    /**
     * Returns the parent's edit control items plus a "Card appearance" entry, when the
     * current user can manage appearance in this section's course.
     *
     * @return array Edit control items, keyed by action name.
     */
    #[\Override]
    public function section_control_items(): array {
        $controls = parent::section_control_items();

        if (!has_capability('format/smartcards:manageappearance', $this->coursecontext)) {
            return $controls;
        }

        $item = new link_secondary(
            url: new url('#'),
            icon: new pix_icon('t/edit', ''),
            text: get_string('editappearance', 'format_smartcards'),
            attributes: [
                'class' => 'sc-edit-appearance-action',
                'data-action' => 'smartcardsEditAppearance',
                'data-sectionid' => (string)$this->section->id,
                'data-name' => $this->format->get_section_name($this->section),
            ],
        );

        return $this->add_control_after($controls, 'edit', 'smartcardsappearance', $item);
    }
}
