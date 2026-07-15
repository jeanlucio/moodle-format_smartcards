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

use core_course\hook\before_course_deleted;
use format_smartcards\local\appearance_repository;

/**
 * Hook listener that cleans up format_smartcards_appearance when a whole course is deleted.
 *
 * \core\event\course_deleted fires only after delete_course() has already removed the
 * course's modules and sections (see lib/moodlelib.php), so by then their cmids/
 * sectionids can no longer be resolved to scope the cleanup — exactly the gap SCOPE.md
 * §5 flagged. before_course_deleted fires earlier, while the course's modules and
 * sections still exist, so this listener resolves and deletes them directly instead of
 * deferring to the (too late) event.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Deletes every appearance row belonging to the course about to be deleted.
     *
     * @param before_course_deleted $hook The dispatched hook, carrying the course record.
     * @return void
     */
    public static function before_course_deleted(before_course_deleted $hook): void {
        $modinfo = get_fast_modinfo($hook->course);

        $cmids = array_keys($modinfo->get_cms());
        $sectionids = array_map(
            static fn ($section): int => (int)$section->id,
            $modinfo->get_section_info_all()
        );

        $repository = new appearance_repository();
        $repository->delete_for_activities($cmids);
        $repository->delete_for_sections($sectionids);
    }
}
