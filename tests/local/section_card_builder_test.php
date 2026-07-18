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

/**
 * Tests for the SmartCards section_card_builder, focused on the icon fallback chain
 * (SCOPE.md §4) and the locked-badge/completion fields (the full render pipeline,
 * including navstyle='sectioncards' wiring, is covered by
 * tests/output/courseformat/content_test.php).
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\local\section_card_builder
 */
final class section_card_builder_test extends \advanced_testcase {
    /**
     * The section's own appearance must be used when set, even when its activities also
     * have appearances of their own.
     *
     * @covers ::build
     */
    public function test_uses_the_sections_own_appearance_when_set(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['numsections' => 1]);
        $page      = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);

        $repository = new appearance_repository();
        $repository->save_for_section(
            $this->get_sectionid($course, 1),
            appearance_repository::TYPE_EMOJI,
            '📚',
            null,
            null,
            null
        );
        $repository->save_for_activity($page->cmid, appearance_repository::TYPE_EMOJI, '🎯', null, null, null);

        $sectioninfo = get_fast_modinfo($course)->get_section_info(1);
        $renderer    = $PAGE->get_renderer('format_smartcards');

        $card = section_card_builder::build(
            $sectioninfo,
            'Topic 1',
            $renderer,
            $repository->get_for_section($sectioninfo->id),
            $repository->get_many_for_activities([$page->cmid]),
            [$page->cmid],
            [],
            true,
            true,
            false,
            '',
            false,
            false
        );

        $this->assertTrue($card['isemoji']);
        $this->assertSame('📚', $card['emoji']);
    }

    /**
     * A section's own library icon and iconcolor must be reported as a colourable
     * bundled icon, mirroring card_builder's own behaviour for activities.
     *
     * @covers ::build
     */
    public function test_own_library_icon_reports_isbsicon_and_iconcolorstyle(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['numsections' => 1]);

        $repository = new appearance_repository();
        $repository->save_for_section(
            $this->get_sectionid($course, 1),
            appearance_repository::TYPE_ICON,
            'book',
            null,
            null,
            null,
            '#ff00aa'
        );

        $sectioninfo = get_fast_modinfo($course)->get_section_info(1);
        $renderer    = $PAGE->get_renderer('format_smartcards');

        $card = section_card_builder::build(
            $sectioninfo,
            'Topic 1',
            $renderer,
            $repository->get_for_section($sectioninfo->id),
            [],
            [],
            [],
            true,
            true,
            false,
            '',
            false,
            false
        );

        $this->assertTrue($card['isbsicon']);
        $this->assertStringContainsString('#ff00aa', $card['iconcolorstyle']);
    }

    /**
     * With no appearance of its own, the section falls back to the first of its visible
     * activities (in course order) that has one configured.
     *
     * @covers ::build
     */
    public function test_falls_back_to_first_activity_with_an_appearance(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['numsections' => 1]);
        $free      = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);
        $decorated = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);

        $repository = new appearance_repository();
        $repository->save_for_activity($decorated->cmid, appearance_repository::TYPE_EMOJI, '🚀', null, null, null);

        $sectioninfo = get_fast_modinfo($course)->get_section_info(1);
        $renderer    = $PAGE->get_renderer('format_smartcards');

        $card = section_card_builder::build(
            $sectioninfo,
            'Topic 1',
            $renderer,
            null,
            $repository->get_many_for_activities([$free->cmid, $decorated->cmid]),
            [$free->cmid, $decorated->cmid],
            [],
            true,
            true,
            false,
            '',
            false,
            false
        );

        $this->assertTrue($card['isemoji']);
        $this->assertSame('🚀', $card['emoji']);
    }

    /**
     * With no appearance anywhere (section or activities), the generic per-section icon
     * (core's own pix/i/section.svg) is used — never an activity default icon, since a
     * section has no "module type" to derive one from.
     *
     * @covers ::build
     */
    public function test_generic_icon_when_nothing_is_configured_anywhere(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['numsections' => 1]);
        $page      = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);

        $sectioninfo = get_fast_modinfo($course)->get_section_info(1);
        $renderer    = $PAGE->get_renderer('format_smartcards');

        $card = section_card_builder::build(
            $sectioninfo,
            'Topic 1',
            $renderer,
            null,
            [],
            [$page->cmid],
            [],
            true,
            true,
            false,
            '',
            false,
            false
        );

        $this->assertFalse($card['isemoji']);
        $this->assertFalse($card['iscustomicon']);
        $this->assertStringContainsString('i/section', $card['iconurl']);
    }

    /**
     * A restricted-but-visible section (sectionavailable = false) must carry the locked
     * badge — the exact same 'locked' concept card_builder derives from cm_info::$available,
     * just from section_info's own availability, never recomputed.
     *
     * @covers ::build
     */
    public function test_unavailable_section_carries_the_locked_badge(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['numsections' => 1]);

        $sectioninfo = get_fast_modinfo($course)->get_section_info(1);
        $renderer    = $PAGE->get_renderer('format_smartcards');

        $card = section_card_builder::build(
            $sectioninfo,
            'Topic 1',
            $renderer,
            null,
            [],
            [],
            [],
            false,
            false,
            false,
            '',
            false,
            false
        );

        $this->assertTrue($card['islocked']);
        $this->assertTrue($card['hasbadge']);
        $this->assertNotSame('', $card['badgelabel']);
    }

    /**
     * Progress and completion fields pass straight through from the caller, unchanged —
     * section_card_builder never recomputes them (content.php already does, once, shared
     * with the flat-grid rendering every other navstyle uses).
     *
     * @covers ::build
     */
    public function test_progress_and_completion_fields_pass_through(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['numsections' => 1]);
        $page      = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);

        $sectioninfo = get_fast_modinfo($course)->get_section_info(1);
        $renderer    = $PAGE->get_renderer('format_smartcards');

        $card = section_card_builder::build(
            $sectioninfo,
            'Topic 1',
            $renderer,
            null,
            [],
            [$page->cmid],
            [],
            true,
            true,
            true,
            'Progress: 1 / 2',
            true,
            false
        );

        $this->assertTrue($card['hasprogress']);
        $this->assertSame('Progress: 1 / 2', $card['progresslabel']);
        $this->assertTrue($card['hascompletionbadge']);
        $this->assertTrue($card['iscompletionpending']);
        $this->assertFalse($card['iscompletioncomplete']);
    }

    /**
     * Resolves the id of section $sectionnum in $course.
     *
     * @param \stdClass $course Course record.
     * @param int $sectionnum Section number.
     * @return int
     */
    private function get_sectionid(\stdClass $course, int $sectionnum): int {
        return get_fast_modinfo($course)->get_section_info($sectionnum)->id;
    }
}
