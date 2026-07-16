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
use format_smartcards\local\appearance_palette;
use format_smartcards\local\appearance_repository;

/**
 * Tests for the SmartCards get_appearance external function.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\external\get_appearance
 */
final class get_appearance_test extends \advanced_testcase {
    /**
     * Creates a course with one page activity and a teacher enrolled in it.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass} Course, page cm record, teacher.
     */
    private function create_course_with_teacher(): array {
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $page      = $generator->create_module('page', ['course' => $course->id]);
        $teacher   = $generator->create_and_enrol($course, 'editingteacher');
        return [$course, $page, $teacher];
    }

    /**
     * An activity with no saved appearance must return TYPE_DEFAULT and empty fields,
     * matching the editor form's own blank state — the modal never has to guess.
     *
     * @covers ::execute
     */
    public function test_returns_defaults_when_nothing_saved(): void {
        $this->resetAfterTest();
        [, $page, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $result = get_appearance::execute($page->cmid);
        $result = external_api::clean_returnvalue(get_appearance::execute_returns(), $result);

        $this->assertSame($page->cmid, $result['cmid']);
        $this->assertSame(appearance_repository::TYPE_DEFAULT, $result['type']);
        $this->assertSame('', $result['value']);
        $this->assertSame('', $result['bgcolor']);
        $this->assertSame('', $result['labelcolor']);
        $this->assertSame('', $result['labelfont']);
        $this->assertNotSame('', $result['iconurl']);
    }

    /**
     * A previously saved appearance must round-trip exactly, so reopening the editor
     * can pre-fill the form instead of always starting blank.
     *
     * @covers ::execute
     */
    public function test_returns_previously_saved_appearance(): void {
        $this->resetAfterTest();
        [, $page, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        (new appearance_repository())->save_for_activity(
            $page->cmid,
            appearance_repository::TYPE_ICON,
            'rocket',
            '#fff3e0',
            appearance_palette::LABEL_COLORS['blue'],
            'fredoka'
        );

        $result = get_appearance::execute($page->cmid);
        $result = external_api::clean_returnvalue(get_appearance::execute_returns(), $result);

        $this->assertSame(appearance_repository::TYPE_ICON, $result['type']);
        $this->assertSame('rocket', $result['value']);
        $this->assertSame('#fff3e0', $result['bgcolor']);
        $this->assertSame(appearance_palette::LABEL_COLORS['blue'], $result['labelcolor']);
        $this->assertSame('fredoka', $result['labelfont']);
    }

    /**
     * The returned icon list must cover every curated slug, each with a resolved URL
     * the browser can load directly (not just the bare slug the picker used to show).
     *
     * @covers ::execute
     */
    public function test_returns_every_curated_icon_with_a_resolved_url(): void {
        $this->resetAfterTest();
        [, $page, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $result = get_appearance::execute($page->cmid);
        $result = external_api::clean_returnvalue(get_appearance::execute_returns(), $result);

        $this->assertCount(count(appearance_palette::ICONS), $result['icons']);
        foreach ($result['icons'] as $icon) {
            $this->assertContains($icon['slug'], appearance_palette::ICONS);
            $this->assertStringContainsString($icon['slug'], $icon['url']);
        }
    }

    /**
     * A user without access to the module's own course must be rejected, the same
     * isolation guarantee save_appearance enforces.
     *
     * @covers ::execute
     */
    public function test_rejects_user_without_access_to_the_modules_course(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        [, $pageincourseb] = $this->create_course_with_teacher();
        $coursea  = $generator->create_course();
        $teachera = $generator->create_and_enrol($coursea, 'editingteacher');

        $this->setUser($teachera);
        $this->expectException(\require_login_exception::class);
        get_appearance::execute($pageincourseb->cmid);
    }

    /**
     * A plain student, who never has the capability, must be rejected.
     *
     * @covers ::execute
     */
    public function test_rejects_student(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $page      = $generator->create_module('page', ['course' => $course->id]);
        $student   = $generator->create_and_enrol($course, 'student');

        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        get_appearance::execute($page->cmid);
    }
}
