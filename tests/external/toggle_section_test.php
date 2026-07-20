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

namespace format_smartcards\external;

use core_external\external_api;

/**
 * Tests for the SmartCards toggle_section external function.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \format_smartcards\external\toggle_section
 */
final class toggle_section_test extends \advanced_testcase {
    /**
     * Creates a course with one extra section and a student enrolled in it.
     *
     * @return array{0: \stdClass, 1: int} Course record and section 1's id.
     */
    private function create_course_with_section(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $sectionid = (int)$DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1]);
        return [$course, $sectionid];
    }

    /**
     * Expanding a section must record it in PREFERENCE_EXPANDED and clear it from
     * PREFERENCE_COLLAPSED, so a previous collapse never lingers alongside a later
     * explicit expand.
     */
    public function test_expanding_a_section_records_it_as_expanded(): void {
        $this->resetAfterTest();
        [$course, $sectionid] = $this->create_course_with_section();
        $format = course_get_format($course);
        $format->add_section_preference_ids(toggle_section::PREFERENCE_COLLAPSED, [$sectionid]);

        toggle_section::execute($sectionid, true);

        $preferences = $format->get_sections_preferences_by_preference();
        $this->assertContains($sectionid, $preferences[toggle_section::PREFERENCE_EXPANDED] ?? []);
        $this->assertNotContains($sectionid, $preferences[toggle_section::PREFERENCE_COLLAPSED] ?? []);
    }

    /**
     * Collapsing a section must record it in PREFERENCE_COLLAPSED and clear it from
     * PREFERENCE_EXPANDED.
     */
    public function test_collapsing_a_section_records_it_as_collapsed(): void {
        $this->resetAfterTest();
        [$course, $sectionid] = $this->create_course_with_section();
        $format = course_get_format($course);
        $format->add_section_preference_ids(toggle_section::PREFERENCE_EXPANDED, [$sectionid]);

        toggle_section::execute($sectionid, false);

        $preferences = $format->get_sections_preferences_by_preference();
        $this->assertContains($sectionid, $preferences[toggle_section::PREFERENCE_COLLAPSED] ?? []);
        $this->assertNotContains($sectionid, $preferences[toggle_section::PREFERENCE_EXPANDED] ?? []);
    }

    /**
     * A user without access to the section's own course must be rejected.
     */
    public function test_rejects_user_without_access_to_the_sections_course(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        [, $sectionidincourseb] = $this->create_course_with_section();
        $coursea = $generator->create_course();
        $studenta = $generator->create_and_enrol($coursea, 'student');

        $this->setUser($studenta);
        $this->expectException(\require_login_exception::class);
        toggle_section::execute($sectionidincourseb, true);
    }
}
