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
use format_smartcards\local\appearance_repository;
use format_smartcards\local\appearance_palette;
use invalid_parameter_exception;

/**
 * Tests for the SmartCards save_appearance external function.
 *
 * Coverage is declared once at class level (not per test method) so that execute_parameters()
 * and execute_returns() are correctly attributed to this test suite instead of being silently
 * excluded by php-code-coverage's per-method coverage-annotation line filtering.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \format_smartcards\external\save_appearance
 */
final class save_appearance_test extends \advanced_testcase {
    /** @var string Base64-encoded 1x1 transparent PNG, small enough to always pass the size check. */
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

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
     * A teacher saving an emoji appearance must get back a fully rendered card whose
     * emoji fields reflect what was just saved.
     */
    public function test_teacher_can_save_emoji_appearance(): void {
        $this->resetAfterTest();
        [, $page, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $result = save_appearance::execute($page->cmid, appearance_repository::TYPE_EMOJI, '🎉', '', '', '');
        $result = external_api::clean_returnvalue(save_appearance::execute_returns(), $result);

        $this->assertSame($page->cmid, $result['cmid']);
        $this->assertTrue($result['isemoji']);
        $this->assertSame('🎉', $result['emoji']);
    }

    /**
     * Colour and font fields must round-trip into the returned card's inline styles.
     */
    public function test_colour_and_font_are_reflected_in_returned_styles(): void {
        $this->resetAfterTest();
        [, $page, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $bgcolor    = '#e0f2ff';
        $labelcolor = appearance_palette::LABEL_COLORS['blue'];

        $result = save_appearance::execute(
            $page->cmid,
            appearance_repository::TYPE_ICON,
            'book',
            $bgcolor,
            $labelcolor,
            'nunito'
        );
        $result = external_api::clean_returnvalue(save_appearance::execute_returns(), $result);

        $this->assertStringContainsString($bgcolor, $result['iconstyle']);
        $this->assertStringContainsString($labelcolor, $result['titlestyle']);
        $this->assertStringContainsString('Nunito', $result['titlestyle']);
        // Icon glyph rendering is not wired into the card yet (bundled in a later
        // step), so the icon type must not be reported as an emoji.
        $this->assertFalse($result['isemoji']);
    }

    /**
     * A user with the capability in a completely different course must be rejected for
     * a cmid outside that course — the check must be bound to the specific course the
     * cmid resolves to server-side, never a global check. validate_context() rejects
     * this even earlier than a capability check would, since the teacher is not even
     * enrolled in the module's real course.
     */
    public function test_rejects_user_without_access_to_the_modules_course(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        [, $pageincourseb] = $this->create_course_with_teacher();
        $coursea  = $generator->create_course();
        $teachera = $generator->create_and_enrol($coursea, 'editingteacher');

        $this->setUser($teachera);
        $this->expectException(\require_login_exception::class);
        save_appearance::execute($pageincourseb->cmid, appearance_repository::TYPE_EMOJI, '🎉', '', '', '');
    }

    /**
     * A plain student, who never has the capability, must be rejected.
     */
    public function test_rejects_student(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $page      = $generator->create_module('page', ['course' => $course->id]);
        $student   = $generator->create_and_enrol($course, 'student');

        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        save_appearance::execute($page->cmid, appearance_repository::TYPE_EMOJI, '🎉', '', '', '');
    }

    /**
     * An invalid value for the given type (e.g. plain text for an emoji) must be
     * rejected server-side, not just trusted from the client.
     */
    public function test_rejects_invalid_value_for_type(): void {
        $this->resetAfterTest();
        [, $page, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        save_appearance::execute($page->cmid, appearance_repository::TYPE_EMOJI, 'not an emoji', '', '', '');
    }

    /**
     * A non-existent cmid must be rejected before any capability check or write.
     */
    public function test_rejects_nonexistent_cmid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\dml_exception::class);
        save_appearance::execute(999999, appearance_repository::TYPE_EMOJI, '🎉', '', '', '');
    }

    /**
     * Uploading an image must store it via the File API and return a card whose custom
     * icon fields point at it.
     */
    public function test_teacher_can_upload_image_appearance(): void {
        $this->resetAfterTest();
        [, $page, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $result = save_appearance::execute(
            $page->cmid,
            appearance_repository::TYPE_IMAGE,
            '',
            '',
            '',
            '',
            self::TINY_PNG_BASE64
        );
        $result = external_api::clean_returnvalue(save_appearance::execute_returns(), $result);

        $this->assertTrue($result['iscustomicon']);
        $this->assertNotSame('', $result['customiconurl']);
        $this->assertNotNull(appearance_image_store::resolve_for_serving($page->cmid, 'cardimage'));
    }

    /**
     * Re-saving an image appearance (e.g. only to tweak the title colour) without
     * uploading a new file must keep the previously stored image untouched.
     */
    public function test_resaving_without_a_new_upload_keeps_the_existing_image(): void {
        $this->resetAfterTest();
        [, $page, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        save_appearance::execute($page->cmid, appearance_repository::TYPE_IMAGE, '', '', '', '', self::TINY_PNG_BASE64);
        $firstfile = appearance_image_store::resolve_for_serving($page->cmid, 'cardimage');

        save_appearance::execute(
            $page->cmid,
            appearance_repository::TYPE_IMAGE,
            '',
            '',
            appearance_palette::LABEL_COLORS['blue'],
            '',
            ''
        );
        $secondfile = appearance_image_store::resolve_for_serving($page->cmid, 'cardimage');

        $this->assertSame($firstfile->get_id(), $secondfile->get_id());
    }

    /**
     * Choosing image type without ever uploading anything must be rejected — there is
     * nothing to render.
     */
    public function test_image_type_without_any_upload_is_rejected(): void {
        $this->resetAfterTest();
        [, $page, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        $this->expectException(invalid_parameter_exception::class);
        save_appearance::execute($page->cmid, appearance_repository::TYPE_IMAGE, '', '', '', '', '');
    }

    /**
     * Switching an activity away from image type must delete the now-orphaned stored
     * file, not leave it behind.
     */
    public function test_switching_away_from_image_deletes_the_stored_file(): void {
        $this->resetAfterTest();
        [, $page, $teacher] = $this->create_course_with_teacher();
        $this->setUser($teacher);

        save_appearance::execute($page->cmid, appearance_repository::TYPE_IMAGE, '', '', '', '', self::TINY_PNG_BASE64);
        $this->assertNotNull(appearance_image_store::resolve_for_serving($page->cmid, 'cardimage'));

        save_appearance::execute($page->cmid, appearance_repository::TYPE_EMOJI, '🎉', '', '', '', '');

        $this->assertNull(appearance_image_store::resolve_for_serving($page->cmid, 'cardimage'));
    }
}
