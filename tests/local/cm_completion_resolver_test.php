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

use stdClass;

/**
 * Tests for the SmartCards cm_completion_resolver.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \format_smartcards\local\cm_completion_resolver
 */
final class cm_completion_resolver_test extends \advanced_testcase {
    /**
     * Creates a course with one page activity and returns it alongside the course.
     *
     * @param array $moduleoptions Extra options passed to create_module().
     * @param array $courseoptions Extra options passed to create_course().
     * @return array{0: stdClass, 1: stdClass} Course record and course-module record.
     */
    private function create_course_with_page(array $moduleoptions = [], array $courseoptions = []): array {
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course($courseoptions);
        $page      = $generator->create_module('page', ['course' => $course->id] + $moduleoptions);
        return [$course, $page];
    }

    /**
     * An activity with no completion tracking configured resolves to TRACKING_NONE with
     * no criteria, regardless of who is asking.
     */
    public function test_untracked_activity_resolves_to_tracking_none(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page([], ['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $cm = get_fast_modinfo($course, $student->id)->get_cm($page->cmid);

        $completion = cm_completion_resolver::resolve($cm, (int)$student->id);

        $this->assertSame(cm_completion::TRACKING_NONE, $completion->tracking);
        $this->assertFalse($completion->is_tracked());
        $this->assertFalse($completion->iscomplete);
        $this->assertSame([], $completion->criteria);
    }

    /**
     * A guest user (or userid 0) never has meaningful completion state, even on an
     * activity that does track completion for real users.
     */
    public function test_guest_user_resolves_to_tracking_none(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page(
            ['completion' => COMPLETION_TRACKING_MANUAL],
            ['enablecompletion' => 1]
        );

        $guest = guest_user();
        $cm    = get_fast_modinfo($course, $guest->id)->get_cm($page->cmid);

        $completion = cm_completion_resolver::resolve($cm, (int)$guest->id);

        $this->assertSame(cm_completion::TRACKING_NONE, $completion->tracking);
        $this->assertFalse($completion->is_tracked());
        $this->assertSame([], $completion->criteria);
    }

    /**
     * A manual-tracking activity resolves TRACKING_MANUAL and reflects the real
     * completion state, without ever populating criteria (core has none to describe for
     * a manual toggle).
     */
    public function test_manual_tracking_resolves_state_with_no_criteria(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page(
            ['completion' => COMPLETION_TRACKING_MANUAL],
            ['enablecompletion' => 1]
        );
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $cm = get_fast_modinfo($course, $student->id)->get_cm($page->cmid);
        $completion = cm_completion_resolver::resolve($cm, (int)$student->id);

        $this->assertSame(cm_completion::TRACKING_MANUAL, $completion->tracking);
        $this->assertFalse($completion->iscomplete);
        $this->assertSame([], $completion->criteria);

        $completioninfo = new \completion_info($course);
        $completioninfo->update_state($cm, COMPLETION_COMPLETE, $student->id);

        $cm = get_fast_modinfo($course, $student->id)->get_cm($page->cmid);
        $completion = cm_completion_resolver::resolve($cm, (int)$student->id);
        $this->assertTrue($completion->iscomplete);
    }

    /**
     * An automatic-tracking activity resolves TRACKING_AUTOMATIC and populates criteria
     * with core's own already-localised descriptions, shaped exactly like
     * core_course/completion_automatic's template context so the sheet can reuse it as-is.
     */
    public function test_automatic_tracking_resolves_criteria_descriptions(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page(
            [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionview' => 1,
            ],
            ['enablecompletion' => 1]
        );
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $cm = get_fast_modinfo($course, $student->id)->get_cm($page->cmid);
        $completion = cm_completion_resolver::resolve($cm, (int)$student->id);

        $this->assertSame(cm_completion::TRACKING_AUTOMATIC, $completion->tracking);
        $this->assertNotEmpty($completion->criteria);

        $criterion = $completion->criteria[0];
        $this->assertArrayHasKey('description', $criterion);
        $this->assertArrayHasKey('statuscomplete', $criterion);
        $this->assertArrayHasKey('statuscompletefail', $criterion);
        $this->assertArrayHasKey('statusincomplete', $criterion);
        $this->assertTrue($criterion['istrackeduser']);
        // The student has not viewed the activity yet, so "View" starts incomplete.
        $this->assertFalse($criterion['statuscomplete']);
        $this->assertTrue($criterion['statusincomplete']);
        $this->assertFalse($completion->iscomplete);
    }
}
