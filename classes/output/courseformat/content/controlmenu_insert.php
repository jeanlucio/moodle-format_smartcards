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

namespace format_smartcards\output\courseformat\content;

/**
 * Inserts a new edit-menu control item right after a named one, falling back to
 * prepending it when that name is not present.
 *
 * Mirrors core's own {@see \core_courseformat\output\local\content\basecontrolmenu::
 * add_control_after()} exactly, but is never called on that class: it only exists on
 * Moodle 5.x's basecontrolmenu, not on the plugin's minimum-supported Moodle 4.5, where
 * both the cm- and section-level controlmenu overrides need this same behaviour. Shared
 * by both instead of duplicated, since the two call sites need to stay in lockstep with
 * whatever core's own 5.x version does.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait controlmenu_insert {
    /**
     * Inserts $newcontrol right after $aftername in $controls, or at the start when
     * $aftername is not a key of $controls.
     *
     * @param array $controls Existing edit control items, keyed by action name.
     * @param string $aftername Key after which the new control is inserted.
     * @param string $newkey Key of the new control.
     * @param mixed $newcontrol The new control item itself.
     * @return array
     */
    private function insert_control_after(array $controls, string $aftername, string $newkey, mixed $newcontrol): array {
        if (!array_key_exists($aftername, $controls)) {
            return array_merge([$newkey => $newcontrol], $controls);
        }

        $newcontrols = [];
        foreach ($controls as $keyname => $control) {
            $newcontrols[$keyname] = $control;
            if ($keyname === $aftername) {
                $newcontrols[$newkey] = $newcontrol;
            }
        }
        return $newcontrols;
    }
}
