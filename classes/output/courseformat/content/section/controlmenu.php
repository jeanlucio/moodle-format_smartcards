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
use format_smartcards\output\courseformat\content\controlmenu_insert;

/**
 * Adds a "Card appearance" entry to the native per-section edit menu.
 *
 * The section-level counterpart of {@see \format_smartcards\output\courseformat\
 * content\cm\controlmenu} — same rationale: the SmartCards grid only renders outside
 * edit mode, so the appearance editor is exposed through this menu instead, which core
 * already renders per section regardless of that fallback.
 *
 * Computes the course context via {@see \core_courseformat\base::get_context()} (never
 * $this->coursecontext, nor core's own {@see \core_courseformat\output\local\content\
 * basecontrolmenu::add_control_after()}): both are Moodle 5.x-only additions to the
 * base class the plugin's minimum-supported Moodle 4.5 does not have — this class was
 * fatal on every edit-mode page load on a 4.5 site since it was first added, never
 * caught because no test exercised this code path until the one added alongside this
 * fix.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controlmenu extends controlmenu_base {
    use controlmenu_insert;

    /**
     * Returns the parent's edit control items plus a "Card appearance" entry, when the
     * current user can manage appearance in this section's course.
     *
     * Not added for section 0 (General) unless the course's generalinstyle option opted
     * it into the active navstyle: by default that section always renders as a plain
     * inline heading, in every navstyle, never as a card of its own — the same reason
     * core itself excludes section 0 from its own section-specific actions
     * (get_section_duplicate_item()/get_section_visibility_item()/
     * get_section_movesection_item() all check sectionnum == 0). Configuring an
     * appearance nothing would ever display misled a teacher before this guard existed
     * (a real report: an emoji set via this menu for "Geral" saved correctly, but could
     * never be seen anywhere) — generalinstyle lets that stop being true, deliberately,
     * per course.
     *
     * @return array Edit control items, keyed by action name.
     */
    #[\Override]
    public function section_control_items(): array {
        $controls = parent::section_control_items();

        $hascapability = has_capability('format/smartcards:manageappearance', $this->format->get_context());
        $includegeneral = !empty($this->format->get_format_options()['generalinstyle']);
        if (($this->section->sectionnum == 0 && !$includegeneral) || !$hascapability) {
            return $controls;
        }

        // Deliberately not 'data-action': core/amd/src/actions.js binds a body-level click
        // handler on every '[data-sectionid] a[data-action]' (the section actions menu
        // core itself marks with data-sectionid) and forwards *any* data-action value,
        // unrecognised or not, straight to the core_course_edit_section web service —
        // unlike the equivalent cm-level handler, which only acts on an explicit allow-
        // list and silently ignores anything else. Without this, clicking the item threw
        // a fatal "sectionactionnotsupported" exception (course/format/classes/base.php)
        // before format_smartcards's own click handler ever ran.
        $item = $this->build_appearance_item([
            'class' => 'sc-edit-appearance-action',
            'data-smartcards-action' => 'editAppearance',
            'data-sectionid' => (string)$this->section->id,
            'data-name' => $this->format->get_section_name($this->section),
        ]);

        return $this->insert_control_after($controls, 'edit', 'smartcardsappearance', $item);
    }

    /**
     * Builds the "Card appearance" menu item, in whichever shape the running Moodle
     * version's control-menu pipeline actually understands.
     *
     * Moodle 5.x's {@see \core_courseformat\output\local\content\basecontrolmenu::
     * format_controls()} accepts a real menu-item object, normalizing a legacy array
     * only as a fallback (with a debugging() notice — which fails PHPUnit's
     * unexpected-debugging-call check, so it cannot be relied on here). Moodle 4.5's
     * version of the same method has no such normalization: it unconditionally treats
     * every item as an array (`$value['url']`), so passing an object there fails with
     * "Cannot use object ... as array". method_exists() on add_control_after() (itself
     * a Moodle 5.x-only addition to basecontrolmenu) is used as the version signal,
     * since it correlates exactly with which format_controls() implementation is
     * active — unlike the cm-level menu, which never goes through format_controls()
     * and therefore always needs the object form regardless of version.
     *
     * @param array $attributes HTML attributes for the menu item's link.
     * @return array|link_secondary
     */
    private function build_appearance_item(array $attributes): array|link_secondary {
        if (method_exists($this, 'add_control_after')) {
            return new link_secondary(
                url: new url('#'),
                icon: new pix_icon('t/edit', ''),
                text: get_string('editappearance', 'format_smartcards'),
                attributes: $attributes,
            );
        }

        return [
            'url' => '#',
            'icon' => 't/edit',
            'name' => get_string('editappearance', 'format_smartcards'),
            'pixattr' => ['class' => ''],
            'attr' => $attributes,
        ];
    }
}
