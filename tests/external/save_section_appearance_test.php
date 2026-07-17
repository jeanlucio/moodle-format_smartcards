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
use invalid_parameter_exception;

/**
 * Tests for the SmartCards save_section_appearance external function.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\external\save_section_appearance
 */
final class save_section_appearance_test extends \advanced_testcase {
    /** @var string Base64-encoded 1x1 transparent PNG, small enough to always pass the size check. */
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

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
     * A teacher saving an emoji appearance must get back a fully rendered section card
     * whose emoji fields reflect what was just saved.
     *
     * @covers ::execute
     */
    public function test_teacher_can_save_emoji_appearance(): void {
        $this->resetAfterTest();
        [, $sectionid, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $result = save_section_appearance::execute($sectionid, appearance_repository::TYPE_EMOJI, '🎉', '', '', '');
        $result = external_api::clean_returnvalue(save_section_appearance::execute_returns(), $result);

        $this->assertSame($sectionid, $result['id']);
        $this->assertTrue($result['isemoji']);
        $this->assertSame('🎉', $result['emoji']);
        $this->assertFalse($result['islocked']);
    }

    /**
     * Colour and font fields must round-trip into the returned card's inline styles.
     *
     * @covers ::execute
     */
    public function test_colour_and_font_are_reflected_in_returned_styles(): void {
        $this->resetAfterTest();
        [, $sectionid, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $bgcolor    = '#e0f2ff';
        $labelcolor = appearance_palette::LABEL_COLORS['blue'];

        $result = save_section_appearance::execute(
            $sectionid,
            appearance_repository::TYPE_ICON,
            'book',
            $bgcolor,
            $labelcolor,
            'nunito'
        );
        $result = external_api::clean_returnvalue(save_section_appearance::execute_returns(), $result);

        $this->assertStringContainsString($bgcolor, $result['iconstyle']);
        $this->assertStringContainsString($labelcolor, $result['titlestyle']);
        $this->assertStringContainsString('Nunito', $result['titlestyle']);
    }

    /**
     * A user with the capability in a completely different course must be rejected for a
     * sectionid outside that course.
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
        save_section_appearance::execute($sectionidincourseb, appearance_repository::TYPE_EMOJI, '🎉', '', '', '');
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
        save_section_appearance::execute($sectionid, appearance_repository::TYPE_EMOJI, '🎉', '', '', '');
    }

    /**
     * Section 0 (General) must be rejected by default — it never renders as a card in
     * any navstyle unless generalinstyle opts it in, so nothing this action configures
     * could ever be seen otherwise. The section-0 edit menu never offers this action in
     * the first place (content/section/controlmenu.php), but that is a UI-only
     * guarantee; this proves the server itself refuses a direct call too, not just the
     * menu hiding the entry.
     *
     * @covers ::execute
     */
    public function test_rejects_section_zero(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course      = $generator->create_course(['numsections' => 1]);
        $teacher     = $generator->create_and_enrol($course, 'editingteacher');
        $sectionzero = (int)get_fast_modinfo($course)->get_section_info(0)->id;
        $this->setUser($teacher);

        $this->expectException(invalid_parameter_exception::class);
        save_section_appearance::execute($sectionzero, appearance_repository::TYPE_EMOJI, '🎉', '', '', '');
    }

    /**
     * When the course's generalinstyle option opts section 0 into the active navstyle,
     * saving its appearance must succeed — the opposite of test_rejects_section_zero()'s
     * default-off behaviour.
     *
     * @covers ::execute
     */
    public function test_allows_section_zero_when_generalinstyle_enabled(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        course_get_format($course)->update_course_format_options(['generalinstyle' => 1]);
        $teacher     = $generator->create_and_enrol($course, 'editingteacher');
        $sectionzero = (int)get_fast_modinfo($course)->get_section_info(0)->id;
        $this->setUser($teacher);

        $result = save_section_appearance::execute($sectionzero, appearance_repository::TYPE_EMOJI, '🎉', '', '', '');

        $this->assertSame($sectionzero, (int)$result['id']);
        $this->assertTrue($result['isemoji']);
        $this->assertSame('🎉', $result['emoji']);
    }

    /**
     * An invalid value for the given type must be rejected server-side.
     *
     * @covers ::execute
     */
    public function test_rejects_invalid_value_for_type(): void {
        $this->resetAfterTest();
        [, $sectionid, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        save_section_appearance::execute($sectionid, appearance_repository::TYPE_EMOJI, 'not an emoji', '', '', '');
    }

    /**
     * A non-existent sectionid must be rejected before any capability check or write.
     *
     * @covers ::execute
     */
    public function test_rejects_nonexistent_sectionid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\dml_exception::class);
        save_section_appearance::execute(999999, appearance_repository::TYPE_EMOJI, '🎉', '', '', '');
    }

    /**
     * Uploading an image must store it via the File API (course context, section-scoped
     * file area) and return a card whose custom icon fields point at it.
     *
     * @covers ::execute
     */
    public function test_teacher_can_upload_image_appearance(): void {
        $this->resetAfterTest();
        [$course, $sectionid, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $result = save_section_appearance::execute(
            $sectionid,
            appearance_repository::TYPE_IMAGE,
            '',
            '',
            '',
            '',
            self::TINY_PNG_BASE64
        );
        $result = external_api::clean_returnvalue(save_section_appearance::execute_returns(), $result);

        $this->assertTrue($result['iscustomicon']);
        $this->assertNotSame('', $result['customiconurl']);
        $this->assertNotNull(
            appearance_image_store::resolve_for_serving_section($sectionid, $course->id, 'sectioncardimage')
        );
    }

    /**
     * Re-saving an image appearance without uploading a new file must keep the previously
     * stored image untouched.
     *
     * @covers ::execute
     */
    public function test_resaving_without_a_new_upload_keeps_the_existing_image(): void {
        $this->resetAfterTest();
        [$course, $sectionid, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        save_section_appearance::execute(
            $sectionid,
            appearance_repository::TYPE_IMAGE,
            '',
            '',
            '',
            '',
            self::TINY_PNG_BASE64
        );
        $firstfile = appearance_image_store::resolve_for_serving_section($sectionid, $course->id, 'sectioncardimage');

        save_section_appearance::execute(
            $sectionid,
            appearance_repository::TYPE_IMAGE,
            '',
            '',
            appearance_palette::LABEL_COLORS['blue'],
            '',
            ''
        );
        $secondfile = appearance_image_store::resolve_for_serving_section($sectionid, $course->id, 'sectioncardimage');

        $this->assertSame($firstfile->get_id(), $secondfile->get_id());
    }

    /**
     * Choosing image type without ever uploading anything must be rejected.
     *
     * @covers ::execute
     */
    public function test_image_type_without_any_upload_is_rejected(): void {
        $this->resetAfterTest();
        [, $sectionid, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $this->expectException(invalid_parameter_exception::class);
        save_section_appearance::execute($sectionid, appearance_repository::TYPE_IMAGE, '', '', '', '', '');
    }

    /**
     * Switching a section away from image type must delete the now-orphaned stored file.
     *
     * @covers ::execute
     */
    public function test_switching_away_from_image_deletes_the_stored_file(): void {
        $this->resetAfterTest();
        [$course, $sectionid, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        save_section_appearance::execute(
            $sectionid,
            appearance_repository::TYPE_IMAGE,
            '',
            '',
            '',
            '',
            self::TINY_PNG_BASE64
        );
        $this->assertNotNull(
            appearance_image_store::resolve_for_serving_section($sectionid, $course->id, 'sectioncardimage')
        );

        save_section_appearance::execute($sectionid, appearance_repository::TYPE_EMOJI, '🎉', '', '', '', '');

        $this->assertNull(
            appearance_image_store::resolve_for_serving_section($sectionid, $course->id, 'sectioncardimage')
        );
    }
}
