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
 * Tests for the SmartCards event observers.
 *
 * Exercises the real course_delete_module()/course_delete_section() core APIs, not a
 * hand-rolled event trigger, so the test also proves the observers are actually wired
 * up via db/events.php, not just correct in isolation.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Deleting a single course module (course still exists) must remove its
     * appearance row.
     *
     * @covers ::course_module_deleted
     */
    public function test_deleting_module_removes_its_appearance(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $page      = $generator->create_module('page', ['course' => $course->id]);

        $repository = new appearance_repository();
        $repository->save_for_activity($page->cmid, appearance_repository::TYPE_ICON, 'book', null, null, null);

        course_delete_module($page->cmid, false);

        $this->assertNull($repository->get_for_activity($page->cmid));
    }

    /**
     * Deleting a single course section (course still exists) must remove its
     * appearance row.
     *
     * @covers ::course_section_deleted
     */
    public function test_deleting_section_removes_its_appearance(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['numsections' => 2]);
        $sectionid = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1]);

        $repository = new appearance_repository();
        $repository->save_for_section((int)$sectionid, appearance_repository::TYPE_ICON, 'book', null, null, null);

        course_delete_section($course, 1, true, false);

        $this->assertNull($repository->get_for_section((int)$sectionid));
    }
}
