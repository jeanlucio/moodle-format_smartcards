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

use availability_date\condition;
use core_availability\tree;
use stdClass;

/**
 * Tests for the SmartCards status_resolver.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\local\status_resolver
 */
final class status_resolver_test extends \advanced_testcase {
    /**
     * Creates a course with one page activity and returns it alongside the course.
     *
     * @return array{0: stdClass, 1: stdClass} Course record and course-module record.
     */
    private function create_course_with_page(): array {
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $page      = $generator->create_module('page', ['course' => $course->id]);
        return [$course, $page];
    }

    /**
     * A fully hidden activity (visible = 0, not shown greyed out) must not be
     * rendered at all for a student.
     *
     * @covers ::resolve
     */
    public function test_hidden_activity_is_not_visible(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        set_coursemodule_visible($page->cmid, 0);
        rebuild_course_cache($course->id, true);

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        $status = status_resolver::resolve($cm);

        $this->assertFalse($status->isvisible);
    }

    /**
     * An activity restricted by an unmet date condition, shown greyed out, must
     * surface the 'locked' badge together with the core-generated reason text.
     *
     * @covers ::resolve
     */
    public function test_restricted_activity_gets_locked_badge_with_reason(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $condition = tree::get_root_json([
            condition::get_json(condition::DIRECTION_FROM, time() + DAYSECS),
        ]);
        $DB->set_field('course_modules', 'availability', json_encode($condition), ['id' => $page->cmid]);
        rebuild_course_cache($course->id, true);

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        $status = status_resolver::resolve($cm);

        $this->assertTrue($status->isvisible);
        $this->assertSame(status_resolver::BADGE_LOCKED, $status->badge);
        $this->assertNotSame('', $status->reason);
    }

    /**
     * An available activity with an expected completion date must surface the
     * 'timed' badge and the due date, without any restriction reason.
     *
     * @covers ::resolve
     */
    public function test_activity_with_expected_completion_gets_timed_badge(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $expected = time() + WEEKSECS;
        $DB->set_field('course_modules', 'completionexpected', $expected, ['id' => $page->cmid]);
        rebuild_course_cache($course->id, true);

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        $status = status_resolver::resolve($cm);

        $this->assertTrue($status->isvisible);
        $this->assertSame(status_resolver::BADGE_TIMED, $status->badge);
        $this->assertSame($expected, $status->duedate);
        $this->assertSame('', $status->reason);
    }

    /**
     * A freely available activity with no restriction and no expected completion
     * date must carry no badge at all.
     *
     * @covers ::resolve
     */
    public function test_freely_available_activity_has_no_badge(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        $status = status_resolver::resolve($cm);

        $this->assertTrue($status->isvisible);
        $this->assertNull($status->badge);
        $this->assertFalse($status->dimmed);
    }

    /**
     * A teacher able to view hidden activities must still see a hidden activity's
     * card, rendered dimmed, instead of it disappearing as it does for students.
     *
     * @covers ::resolve
     */
    public function test_teacher_sees_hidden_activity_dimmed(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        set_coursemodule_visible($page->cmid, 0);
        rebuild_course_cache($course->id, true);

        $modinfo = get_fast_modinfo($course, $teacher->id);
        $cm      = $modinfo->get_cm($page->cmid);

        $status = status_resolver::resolve($cm);

        $this->assertTrue($status->isvisible);
        $this->assertTrue($status->dimmed);
    }
}
