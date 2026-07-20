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

use coding_exception;
use context_course;
use context_module;
use context_system;
use format_smartcards\local\appearance_image_store;
use format_smartcards\local\appearance_repository;

/**
 * Tests for the SmartCards course format's own lib.php: the format_smartcards class
 * itself, plus its two global-scope callback functions.
 *
 * format_smartcards is declared in the global namespace (lib.php, mirroring every core
 * course format), so this test class lives in the plugin's own namespace instead — the
 * same pattern core uses for format_topics\format_topics_test — and references the class
 * under test via its fully-qualified global name (\format_smartcards).
 *
 * format_smartcards_pluginfile()'s "file actually served" branches are deliberately not
 * exercised here: they end in send_stored_file(), which calls die() on the success path
 * with no 'dontdie' option passed — invoking that directly would terminate the PHPUnit
 * process. Only the access-control branches that return false before ever reaching
 * send_stored_file() are covered; the real "does the browser actually get the bytes"
 * path is exercised end to end by Behat instead.
 *
 * The two global functions each need their own bare "::functionName" target below: a
 * plain function is not part of the \format_smartcards class, so the class-level target
 * above does not reach it — PHPUnit's code-unit mapper (Mapper::stringToCodeUnits())
 * falls back to a function lookup whenever the part before "::" does not resolve to a
 * real method, which is exactly what happens here with no class name in front of it.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_smartcards
 * @covers     ::format_smartcards_inplace_editable
 * @covers     ::format_smartcards_pluginfile
 */
final class lib_test extends \advanced_testcase {
    /**
     * Creates a course with one section and one activity, and returns them.
     *
     * @return array{0: \stdClass, 1: \stdClass} Course and page cm record.
     */
    private function create_course_with_page(): array {
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $page      = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);
        return [$course, $page];
    }

    /**
     * The format's fixed capability flags must all report their documented, constant
     * value — none of them branch on course/user state.
     */
    public function test_format_capability_flags_are_constant(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $format = course_get_format($course);

        $this->assertTrue($format->uses_sections());
        $this->assertTrue($format->uses_course_index());
        $this->assertFalse($format->uses_indentation());
        $this->assertTrue($format->supports_components());
        $this->assertTrue($format->can_delete_section(1));
        $this->assertTrue($format->supports_news());
        $this->assertFalse($format->allow_stealth_module_visibility(new \stdClass(), new \stdClass()));

        $ajaxsupport = $format->supports_ajax();
        $this->assertTrue($ajaxsupport->capable);
    }

    /**
     * page_title() must return the shared "Section outline" string, not a
     * format-specific one — this format never introduced its own page title string.
     */
    public function test_page_title_returns_the_shared_section_outline_string(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $format = course_get_format($course);

        $this->assertSame(get_string('sectionoutline'), $format->page_title());
    }

    /**
     * A section with a custom name set by the teacher must render that name, formatted
     * through format_string() (not the default "Topic N" fallback).
     */
    public function test_get_section_name_uses_the_custom_name_when_set(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $format = course_get_format($course);

        course_update_section($course, $format->get_section(1), ['name' => 'My custom section']);

        $this->assertSame('My custom section', $format->get_section_name(1));
    }

    /**
     * A section with no custom name must fall back to get_default_section_name().
     */
    public function test_get_section_name_falls_back_to_the_default_when_empty(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $format = course_get_format($course);

        $this->assertSame($format->get_default_section_name(1), $format->get_section_name(1));
    }

    /**
     * Section 0 must default to "General" (section0name), never "Topic 0".
     */
    public function test_get_default_section_name_for_section_zero(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $format = course_get_format($course);

        $this->assertSame(
            get_string('section0name', 'format_smartcards'),
            $format->get_default_section_name(0)
        );
    }

    /**
     * Any other section must default to "Topic N" (sectionname + the section number).
     */
    public function test_get_default_section_name_for_a_real_section(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $format = course_get_format($course);

        $this->assertSame(
            get_string('sectionname', 'format_smartcards') . ' 1',
            $format->get_default_section_name(1)
        );
    }

    /**
     * With no options, get_view_url() must point at the plain course page.
     */
    public function test_get_view_url_defaults_to_the_course_page(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $format = course_get_format($course);

        $url = $format->get_view_url(1);

        $this->assertStringContainsString('/course/view.php', $url->out(false));
        $this->assertSame((string)$course->id, $url->get_param('id'));
    }

    /**
     * With the 'sr' option set, get_view_url() must point at that section's own page
     * (course/section.php), not the plain course page.
     */
    public function test_get_view_url_with_sr_option_points_at_the_section_page(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $format = course_get_format($course);
        $sectionid = $format->get_section(1)->id;

        $url = $format->get_view_url(1, ['sr' => 1]);

        $this->assertStringContainsString('/course/section.php', $url->out(false));
        $this->assertSame((string)$sectionid, $url->get_param('id'));
    }

    /**
     * With the 'navigation' option truthy, get_view_url() must also point at the
     * section's own page, mirroring the 'sr' case above.
     */
    public function test_get_view_url_with_navigation_option_points_at_the_section_page(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $format = course_get_format($course);
        $sectionid = $format->get_section(1)->id;

        $url = $format->get_view_url(1, ['navigation' => true]);

        $this->assertStringContainsString('/course/section.php', $url->out(false));
        $this->assertSame((string)$sectionid, $url->get_param('id'));
    }

    /**
     * course_format_options() must return every expected option, each with a 'default'
     * and 'type' — the shape the format options API relies on to persist/read them,
     * regardless of $foreditform.
     *
     * Does not assert the plain call omits 'label'/'element_type': $courseformatoptions
     * is cached in a function-static variable shared by every format_smartcards instance
     * for the process's whole lifetime (confirmed empirically — some earlier, unrelated
     * course-creation code path already triggers the $foreditform=true branch before this
     * test method even starts), so that absence cannot be asserted reliably from a test.
     */
    public function test_course_format_options_lists_every_option_with_a_default(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $format = course_get_format($course);

        $plainoptions = $format->course_format_options();
        foreach (['cardsize', 'showcardframe', 'navstyle', 'modaleffect', 'progressdisplay'] as $name) {
            $this->assertArrayHasKey($name, $plainoptions, "Missing option '$name'");
            $this->assertArrayHasKey('default', $plainoptions[$name]);
            $this->assertArrayHasKey('type', $plainoptions[$name]);
        }
    }

    /**
     * With $foreditform=true, every option must gain a 'label' and a 'select'
     * element_type for the course edit form to render a real select element from.
     */
    public function test_course_format_options_with_editform_adds_labels_to_every_option(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $format = course_get_format($course);

        $editoptions = $format->course_format_options(true);

        foreach ($editoptions as $name => $option) {
            $this->assertArrayHasKey('label', $option, "Missing 'label' for option '$name'");
            $this->assertSame('select', $option['element_type'], "Missing select type for option '$name'");
        }
    }

    /**
     * page_set_course() must load the appearance_picker AMD module only for a user who
     * can actually manage card appearance — never unconditionally.
     */
    public function test_page_set_course_loads_the_appearance_picker_only_with_capability(): void {
        global $PAGE;

        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $format = course_get_format($course);

        $this->setUser($teacher);
        $format->page_set_course($PAGE);
        $this->assertStringContainsString('format_smartcards/appearance_picker', $PAGE->requires->get_end_code());

        // A fresh $PAGE avoids the previous call's AMD footer code still being present.
        $PAGE = new \moodle_page();
        $PAGE->set_context(context_system::instance());
        $this->setUser($student);
        $format->page_set_course($PAGE);
        $this->assertStringNotContainsString('format_smartcards/appearance_picker', $PAGE->requires->get_end_code());
    }

    /**
     * format_smartcards_inplace_editable() must update the section's name and return a
     * matching inplace_editable, for a user with the capability to do so.
     */
    public function test_inplace_editable_updates_the_section_name(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $sectionid = course_get_format($course)->get_section(1)->id;

        $result = format_smartcards_inplace_editable('sectionname', $sectionid, 'Renamed section');

        $this->assertSame('Renamed section', get_fast_modinfo($course)->get_section_info(1)->name);
        $exported = $result->export_for_template($this->createMock(\renderer_base::class));
        $this->assertSame('Renamed section', $exported['value']);
    }

    /**
     * An unknown itemtype must be rejected with a coding_exception, not silently
     * ignored — this callback is core_courseformat's own dispatch point for every
     * course-format's inplace_editable, so an unrecognised type is a programming error.
     */
    public function test_inplace_editable_rejects_an_unknown_itemtype(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $this->setAdminUser();
        $sectionid = course_get_format($course)->get_section(1)->id;

        $this->expectException(coding_exception::class);
        format_smartcards_inplace_editable('bogustype', $sectionid, 'value');
    }

    /**
     * A hidden activity's card image must never be served — is_visible_on_course_page()
     * gates the file the same way it gates the card itself.
     */
    public function test_pluginfile_activity_returns_false_when_not_visible_on_course_page(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        set_coursemodule_visible($page->cmid, 0);
        rebuild_course_cache($course->id, true);

        $cm = get_coursemodule_from_id('', $page->cmid);
        $context = context_module::instance($page->cmid);

        $result = format_smartcards_pluginfile($course, $cm, $context, 'cardimage', [], false);

        $this->assertFalse($result);
    }

    /**
     * A visible activity with no card image ever stored must return false, not a fatal
     * error resolving a nonexistent file.
     */
    public function test_pluginfile_activity_returns_false_when_no_file_is_stored(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $cm = get_coursemodule_from_id('', $page->cmid);
        $context = context_module::instance($page->cmid);

        $result = format_smartcards_pluginfile($course, $cm, $context, 'cardimage', [], false);

        $this->assertFalse($result);
    }

    /**
     * A section hidden by the teacher must never serve its card image either, mirroring
     * the activity case above.
     */
    public function test_pluginfile_section_returns_false_when_section_not_visible(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $sectionid = course_get_format($course)->get_section(1)->id;

        appearance_image_store::store_for_section(
            $sectionid,
            $course->id,
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );
        (new appearance_repository())->save_for_section($sectionid, appearance_repository::TYPE_ICON, 'book', null, null, null);

        set_section_visible($course->id, 1, 0);
        rebuild_course_cache($course->id, true);
        $this->setUser($student);

        $context = context_course::instance($course->id);

        $result = format_smartcards_pluginfile($course, null, $context, 'sectioncardimage', [(string)$sectionid], false);

        $this->assertFalse($result);
    }

    /**
     * A course-context request for a section id that does not resolve to a real section
     * must return false, not throw.
     */
    public function test_pluginfile_section_returns_false_for_an_unknown_sectionid(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $this->setAdminUser();

        $context = context_course::instance($course->id);

        $result = format_smartcards_pluginfile($course, null, $context, 'sectioncardimage', ['0'], false);

        $this->assertFalse($result);
    }

    /**
     * Neither a module nor a course context is ever a valid request for this plugin's
     * pluginfile — the final catch-all must return false.
     */
    public function test_pluginfile_returns_false_for_an_unsupported_context_level(): void {
        $this->resetAfterTest();
        [$course] = $this->create_course_with_page();
        $this->setAdminUser();

        $result = format_smartcards_pluginfile($course, null, context_system::instance(), 'cardimage', [], false);

        $this->assertFalse($result);
    }
}
