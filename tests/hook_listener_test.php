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

use format_smartcards\local\appearance_repository;

/**
 * Tests for the SmartCards before_course_deleted hook listener.
 *
 * Exercises the real delete_course() core API, not a hand-rolled hook dispatch, so the
 * test also proves the listener is actually wired up via db/hooks.php.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \format_smartcards\hook_listener
 */
final class hook_listener_test extends \advanced_testcase {
    /**
     * Deleting a whole course must remove the appearance rows of every activity and
     * section that belonged to it, and must not touch rows of an unrelated course.
     */
    public function test_deleting_course_removes_all_its_appearance_rows(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $course      = $generator->create_course(['numsections' => 2]);
        $page        = $generator->create_module('page', ['course' => $course->id]);
        $sectionid   = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1]);
        $othercourse = $generator->create_course();
        $otherpage   = $generator->create_module('page', ['course' => $othercourse->id]);

        $repository = new appearance_repository();
        $repository->save_for_activity($page->cmid, appearance_repository::TYPE_ICON, 'book', null, null, null);
        $repository->save_for_section((int)$sectionid, appearance_repository::TYPE_ICON, 'star', null, null, null);
        $repository->save_for_activity($otherpage->cmid, appearance_repository::TYPE_ICON, 'flag', null, null, null);

        delete_course($course, false);

        $this->assertNull($repository->get_for_activity($page->cmid));
        $this->assertNull($repository->get_for_section((int)$sectionid));
        $this->assertNotNull($repository->get_for_activity($otherpage->cmid));
    }
}
