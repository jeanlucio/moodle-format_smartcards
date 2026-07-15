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

namespace format_smartcards;

use core\event\course_module_deleted;
use core\event\course_section_deleted;
use format_smartcards\local\appearance_repository;

/**
 * Event observers that keep format_smartcards_appearance free of orphaned rows.
 *
 * format_smartcards_appearance has no instance id or direct courseid of its own — it is
 * keyed by itemid (cmid or sectionid), an indirect reference into whatever course that
 * module/section happens to belong to. Deleting a single activity or section therefore
 * needs its own observer; the third case (deleting the whole course) is handled
 * separately in {@see hook_listener::before_course_deleted()}, since by the time
 * \core\event\course_deleted fires, the course's modules and sections are already gone
 * and their ids can no longer be resolved (see SCOPE.md §5).
 *
 * Both handlers below are safe to call unconditionally for every course on the site,
 * regardless of its format: deleting a row that was never created is a harmless no-op.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Deletes the appearance row of a single deleted course module, if any.
     *
     * @param course_module_deleted $event The triggered event.
     * @return void
     */
    public static function course_module_deleted(course_module_deleted $event): void {
        (new appearance_repository())->delete_for_activity((int)$event->objectid);
    }

    /**
     * Deletes the appearance row of a single deleted course section, if any.
     *
     * @param course_section_deleted $event The triggered event.
     * @return void
     */
    public static function course_section_deleted(course_section_deleted $event): void {
        (new appearance_repository())->delete_for_section((int)$event->objectid);
    }
}
