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
use format_smartcards\local\appearance_image_store;
use format_smartcards\local\appearance_palette;
use format_smartcards\local\appearance_repository;

/**
 * Tests for the SmartCards get_section_appearance external function.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\external\get_section_appearance
 */
final class get_section_appearance_test extends \advanced_testcase {
    /**
     * Creates a course with one section and a teacher enrolled in it.
     *
     * @return array{0: \stdClass, 1: int, 2: \stdClass} Course, sectionid, teacher.
     */
    private function create_course_with_teacher(): array {
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['numsections' => 1]);
        $teacher   = $generator->create_and_enrol($course, 'editingteacher');
        $sectionid = (int)get_fast_modinfo($course)->get_section_info(1)->id;
        return [$course, $sectionid, $teacher];
    }

    /**
     * A section with no saved appearance must return TYPE_DEFAULT and empty fields, with
     * a non-empty generic icon URL (a section has no "module type" default icon).
     *
     * @covers ::execute
     */
    public function test_returns_defaults_when_nothing_saved(): void {
        $this->resetAfterTest();
        [, $sectionid, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $result = get_section_appearance::execute($sectionid);
        $result = external_api::clean_returnvalue(get_section_appearance::execute_returns(), $result);

        $this->assertSame($sectionid, $result['sectionid']);
        $this->assertSame(appearance_repository::TYPE_DEFAULT, $result['type']);
        $this->assertSame('', $result['value']);
        $this->assertSame('', $result['iconcolor']);
        $this->assertNotSame('', $result['iconurl']);
        $this->assertStringContainsString('i/section', $result['iconurl']);
        $this->assertSame('', $result['imageurl']);
    }

    /**
     * A previously saved section appearance must round-trip exactly.
     *
     * @covers ::execute
     */
    public function test_returns_previously_saved_appearance(): void {
        $this->resetAfterTest();
        [, $sectionid, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        (new appearance_repository())->save_for_section(
            $sectionid,
            appearance_repository::TYPE_ICON,
            'rocket',
            '#fff3e0',
            appearance_palette::LABEL_COLORS['blue'],
            'fredoka',
            '#ffffff'
        );

        $result = get_section_appearance::execute($sectionid);
        $result = external_api::clean_returnvalue(get_section_appearance::execute_returns(), $result);

        $this->assertSame(appearance_repository::TYPE_ICON, $result['type']);
        $this->assertSame('rocket', $result['value']);
        $this->assertSame('#fff3e0', $result['bgcolor']);
        $this->assertSame(appearance_palette::LABEL_COLORS['blue'], $result['labelcolor']);
        $this->assertSame('fredoka', $result['labelfont']);
        $this->assertSame('#ffffff', $result['iconcolor']);
    }

    /**
     * When the saved appearance is an uploaded image, imageurl must point at the
     * section-scoped image (context_course + itemid=sectionid), not the activity one.
     *
     * @covers ::execute
     */
    public function test_returns_image_url_for_image_type(): void {
        $this->resetAfterTest();
        [$course, $sectionid, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $fileid = appearance_image_store::store_for_section(
            $sectionid,
            $course->id,
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );
        (new appearance_repository())->save_for_section(
            $sectionid,
            appearance_repository::TYPE_IMAGE,
            (string)$fileid,
            null,
            null,
            null
        );

        $result = get_section_appearance::execute($sectionid);
        $result = external_api::clean_returnvalue(get_section_appearance::execute_returns(), $result);

        $this->assertSame(
            appearance_image_store::url_for_section($sectionid, $course->id, $fileid)->out(false),
            $result['imageurl']
        );
    }

    /**
     * A user without access to the section's own course must be rejected.
     *
     * @covers ::execute
     */
    public function test_rejects_user_without_access_to_the_sections_course(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        [, $sectionidincourseb] = $this->create_course_with_teacher();
        $coursea  = $generator->create_course();
        $teachera = $generator->create_and_enrol($coursea, 'editingteacher');

        $this->setUser($teachera);
        $this->expectException(\require_login_exception::class);
        get_section_appearance::execute($sectionidincourseb);
    }

    /**
     * A plain student, who never has the capability, must be rejected.
     *
     * @covers ::execute
     */
    public function test_rejects_student(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['numsections' => 1]);
        $sectionid = (int)get_fast_modinfo($course)->get_section_info(1)->id;
        $student   = $generator->create_and_enrol($course, 'student');

        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        get_section_appearance::execute($sectionid);
    }

    /**
     * A non-existent sectionid must be rejected before any capability check.
     *
     * @covers ::execute
     * @covers ::get_section_or_fail
     */
    public function test_rejects_nonexistent_sectionid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\dml_exception::class);
        get_section_appearance::execute(999999);
    }
}
