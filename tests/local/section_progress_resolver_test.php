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

namespace format_smartcards\local;

use completion_info;

/**
 * Tests for the SmartCards section_progress_resolver.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\local\section_progress_resolver
 */
final class section_progress_resolver_test extends \advanced_testcase {
    /**
     * Creates a course with completion enabled, a section with two manually-tracked
     * page activities, and a student enrolled in it.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass, 3: \stdClass} Course, page 1, page 2, student.
     */
    private function create_course_with_tracked_activities(): array {
        $generator = $this->getDataGenerator();
        $course  = $generator->create_course(['enablecompletion' => 1]);
        $page1   = $generator->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $page2   = $generator->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $student = $generator->create_and_enrol($course, 'student');
        return [$course, $page1, $page2, $student];
    }

    /**
     * A section with no completion-tracked activities must report zero total, so the
     * accordion never shows a progress badge for it.
     *
     * @covers ::resolve
     */
    public function test_section_without_tracking_has_zero_total(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course  = $generator->create_course();
        $page    = $generator->create_module('page', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $modinfo = get_fast_modinfo($course, $student->id);
        $section = $modinfo->get_section_info(0);
        $completioninfo = new completion_info($course);

        $progress = section_progress_resolver::resolve($completioninfo, $modinfo, $section);

        $this->assertSame(0, $progress->total);
        $this->assertFalse($progress->has_tracking());
        $this->assertFalse($progress->has_pending());
    }

    /**
     * A section with one completed and one incomplete tracked activity must count both
     * correctly and report pending completion.
     *
     * @covers ::resolve
     */
    public function test_counts_complete_and_incomplete_activities(): void {
        $this->resetAfterTest();
        [$course, $page1, $page2, $student] = $this->create_course_with_tracked_activities();
        $this->setUser($student);

        $modinfo = get_fast_modinfo($course, $student->id);
        $completioninfo = new completion_info($course);
        $completioninfo->update_state($modinfo->get_cm($page1->cmid), COMPLETION_COMPLETE, $student->id);

        $section = $modinfo->get_section_info(0);
        $progress = section_progress_resolver::resolve($completioninfo, $modinfo, $section);

        $this->assertSame(1, $progress->complete);
        $this->assertSame(2, $progress->total);
        $this->assertTrue($progress->has_tracking());
        $this->assertTrue($progress->has_pending());
    }

    /**
     * A section where every tracked activity is complete must report no pending
     * completion, so the accordion does not treat it as the "resume here" section.
     *
     * @covers ::resolve
     */
    public function test_fully_complete_section_has_no_pending(): void {
        $this->resetAfterTest();
        [$course, $page1, $page2, $student] = $this->create_course_with_tracked_activities();
        $this->setUser($student);

        $modinfo = get_fast_modinfo($course, $student->id);
        $completioninfo = new completion_info($course);
        $completioninfo->update_state($modinfo->get_cm($page1->cmid), COMPLETION_COMPLETE, $student->id);
        $completioninfo->update_state($modinfo->get_cm($page2->cmid), COMPLETION_COMPLETE, $student->id);

        $section = $modinfo->get_section_info(0);
        $progress = section_progress_resolver::resolve($completioninfo, $modinfo, $section);

        $this->assertSame(2, $progress->complete);
        $this->assertSame(2, $progress->total);
        $this->assertFalse($progress->has_pending());
    }

    /**
     * A guest (or logged-out) viewer never has meaningful completion state, so progress
     * must resolve to zero/zero rather than querying completion data for them.
     *
     * @covers ::resolve
     */
    public function test_guest_gets_no_progress(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_tracked_activities();
        $this->setGuestUser();

        $modinfo = get_fast_modinfo($course);
        $completioninfo = new completion_info($course);
        $section = $modinfo->get_section_info(0);

        $progress = section_progress_resolver::resolve($completioninfo, $modinfo, $section);

        $this->assertSame(0, $progress->total);
        $this->assertFalse($progress->has_tracking());
    }
}
