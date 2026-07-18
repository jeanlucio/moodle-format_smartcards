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
 * Tests for the SmartCards card_builder, focused on the defaultbgcolor/defaultlabelcolor/
 * defaultlabelfont course format option fallback (the full render pipeline is covered
 * by tests/output/courseformat/content_test.php).
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\local\card_builder
 */
final class card_builder_test extends \advanced_testcase {
    /**
     * Creates a course with one page activity and returns it alongside the course.
     *
     * @param array $moduleoptions Extra options passed to create_module() (e.g. completion
     *                              tracking, showdescription).
     * @param array $courseoptions Extra options passed to create_course() (e.g. enablecompletion).
     * @return array{0: \stdClass, 1: \stdClass} Course record and course-module record.
     */
    private function create_course_with_page(array $moduleoptions = [], array $courseoptions = []): array {
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course($courseoptions);
        $page      = $generator->create_module('page', ['course' => $course->id] + $moduleoptions);
        return [$course, $page];
    }

    /**
     * An activity's own labelcolor/labelfont must take priority over the course's
     * defaultlabelcolor/defaultlabelfont format option, never the other way round.
     *
     * @covers ::build
     */
    public function test_activity_own_colour_and_font_override_course_defaults(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $ownlabelcolor = appearance_palette::LABEL_COLORS['pink'];
        (new appearance_repository())->save_for_activity(
            $page->cmid,
            appearance_repository::TYPE_DEFAULT,
            '',
            null,
            $ownlabelcolor,
            'comicneue'
        );

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $formatoptions = [
            'defaultlabelcolor' => appearance_palette::LABEL_COLORS['green'],
            'defaultlabelfont'  => 'nunito',
        ];

        $card = card_builder::build(
            $cm,
            $course,
            $renderer,
            (new appearance_repository())->get_for_activity($page->cmid),
            $formatoptions,
            (int)$student->id,
            ''
        );

        $this->assertStringContainsString('color: ' . $ownlabelcolor, $card['titlestyle']);
        $this->assertStringContainsString("'Comic Neue'", $card['titlestyle']);
        $this->assertStringNotContainsString(appearance_palette::LABEL_COLORS['green'], $card['titlestyle']);
    }

    /**
     * With no activity appearance at all, the course's defaultlabelcolor/
     * defaultlabelfont must still apply.
     *
     * @covers ::build
     */
    public function test_course_defaults_apply_with_no_activity_appearance(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $formatoptions = [
            'defaultlabelcolor' => appearance_palette::LABEL_COLORS['teal'],
            'defaultlabelfont'  => 'varelaround',
        ];

        $card = card_builder::build($cm, $course, $renderer, null, $formatoptions, (int)$student->id, '');

        $this->assertStringContainsString('color: ' . appearance_palette::LABEL_COLORS['teal'], $card['titlestyle']);
        $this->assertStringContainsString("'Varela Round'", $card['titlestyle']);
    }

    /**
     * An activity's own bgcolor must take priority over the course's defaultbgcolor
     * format option, never the other way round.
     *
     * @covers ::build
     */
    public function test_activity_own_bgcolor_overrides_course_default(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $ownbgcolor = appearance_palette::LABEL_COLORS['pink'];
        (new appearance_repository())->save_for_activity(
            $page->cmid,
            appearance_repository::TYPE_DEFAULT,
            '',
            $ownbgcolor,
            null,
            null
        );

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $formatoptions = ['defaultbgcolor' => appearance_palette::LABEL_COLORS['green']];

        $card = card_builder::build(
            $cm,
            $course,
            $renderer,
            (new appearance_repository())->get_for_activity($page->cmid),
            $formatoptions,
            (int)$student->id,
            ''
        );

        $this->assertStringContainsString('background-color: ' . $ownbgcolor, $card['iconstyle']);
        $this->assertStringNotContainsString(appearance_palette::LABEL_COLORS['green'], $card['iconstyle']);
    }

    /**
     * With no activity appearance at all, the course's defaultbgcolor must still apply,
     * including the special 'transparent' value.
     *
     * @covers ::build
     */
    public function test_course_default_bgcolor_applies_with_no_activity_appearance(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $formatoptions = ['defaultbgcolor' => appearance_repository::BGCOLOR_TRANSPARENT];

        $card = card_builder::build($cm, $course, $renderer, null, $formatoptions, (int)$student->id, '');

        $this->assertStringContainsString('background-color: transparent', $card['iconstyle']);
    }

    /**
     * An uploaded image appearance must render through the same iscustomicon/
     * customiconurl fields the library icon type uses, with a URL built by
     * appearance_image_store (no separate File API lookup at render time). It must
     * never be reported as a colourable bundled icon (isbsicon) — a photo has its own
     * real colours and cannot be CSS-masked like a bundled bsicon SVG.
     *
     * @covers ::build
     */
    public function test_image_type_renders_as_custom_icon(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $fileid = appearance_image_store::store(
            $page->cmid,
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );
        (new appearance_repository())->save_for_activity(
            $page->cmid,
            appearance_repository::TYPE_IMAGE,
            (string)$fileid,
            null,
            null,
            null,
            '#ff00aa'
        );

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build(
            $cm,
            $course,
            $renderer,
            (new appearance_repository())->get_for_activity($page->cmid),
            [],
            (int)$student->id,
            ''
        );

        $this->assertTrue($card['iscustomicon']);
        $this->assertSame(appearance_image_store::url($page->cmid, $fileid)->out(false), $card['customiconurl']);
        $this->assertFalse($card['isbsicon']);
        $this->assertSame('', $card['iconcolorstyle']);
    }

    /**
     * A library icon's own iconcolor must take priority over the course's
     * defaulticoncolor format option, and must be reported as a colourable bundled icon.
     *
     * @covers ::build
     */
    public function test_activity_own_iconcolor_overrides_course_default(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $owniconcolor = '#ff00aa';
        (new appearance_repository())->save_for_activity(
            $page->cmid,
            appearance_repository::TYPE_ICON,
            'book',
            null,
            null,
            null,
            $owniconcolor
        );

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $formatoptions = ['defaulticoncolor' => appearance_palette::LABEL_COLORS['green']];

        $card = card_builder::build(
            $cm,
            $course,
            $renderer,
            (new appearance_repository())->get_for_activity($page->cmid),
            $formatoptions,
            (int)$student->id,
            ''
        );

        $this->assertTrue($card['isbsicon']);
        $this->assertStringContainsString($owniconcolor, $card['iconcolorstyle']);
        $this->assertStringNotContainsString(appearance_palette::LABEL_COLORS['green'], $card['iconcolorstyle']);
    }

    /**
     * With no activity appearance at all, the course's defaulticoncolor must still
     * apply — same fallback chain already covered for bgcolor/labelcolor/labelfont.
     *
     * @covers ::build
     */
    public function test_course_default_iconcolor_applies_with_no_activity_appearance(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        (new appearance_repository())->save_for_activity(
            $page->cmid,
            appearance_repository::TYPE_ICON,
            'book',
            null,
            null,
            null
        );

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $formatoptions = ['defaulticoncolor' => appearance_palette::LABEL_COLORS['teal']];

        $card = card_builder::build(
            $cm,
            $course,
            $renderer,
            (new appearance_repository())->get_for_activity($page->cmid),
            $formatoptions,
            (int)$student->id,
            ''
        );

        $this->assertStringContainsString(appearance_palette::LABEL_COLORS['teal'], $card['iconcolorstyle']);
    }

    /**
     * With no course defaults and no activity appearance, the title must carry no
     * inline style at all — the system/theme default applies, exactly as before this
     * feature existed.
     *
     * @covers ::build
     */
    public function test_no_titlestyle_when_nothing_is_configured(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build($cm, $course, $renderer, null, [], (int)$student->id, '');

        $this->assertSame('', $card['titlestyle']);
        $this->assertFalse($card['hastitlestyle']);
    }

    /**
     * A manual-tracking activity not yet completed must open the sheet, carry the pending
     * completion badge, and offer the toggle button to a student who holds the manual
     * completion capability.
     *
     * @covers ::build
     */
    public function test_manual_completion_pending_opens_the_sheet_with_a_toggle_button(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page(
            ['completion' => COMPLETION_TRACKING_MANUAL],
            ['enablecompletion' => 1]
        );
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build($cm, $course, $renderer, null, [], (int)$student->id, '');

        $this->assertTrue($card['opensheet']);
        $this->assertTrue($card['hascompletionbadge']);
        $this->assertTrue($card['iscompletionpending']);
        $this->assertFalse($card['iscompletioncomplete']);
        $this->assertSame('manual', $card['completiontype']);
        $this->assertTrue($card['cantoggle']);
    }

    /**
     * A manual-tracking activity already completed, with no availability restriction and
     * no description, must NOT open the sheet — the badge alone already says "done", so
     * tapping the card goes straight to the activity (a behaviour explicitly confirmed
     * during design, see SCOPE.md).
     *
     * @covers ::build
     */
    public function test_manual_completion_complete_with_nothing_else_skips_the_sheet(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page(
            ['completion' => COMPLETION_TRACKING_MANUAL],
            ['enablecompletion' => 1]
        );
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $completion = new \completion_info($course);
        $cm         = get_fast_modinfo($course, $student->id)->get_cm($page->cmid);
        $completion->update_state($cm, COMPLETION_COMPLETE, $student->id);

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build($cm, $course, $renderer, null, [], (int)$student->id, '');

        $this->assertFalse($card['opensheet']);
        $this->assertTrue($card['hascompletionbadge']);
        $this->assertTrue($card['iscompletioncomplete']);
        $this->assertFalse($card['iscompletionpending']);
    }

    /**
     * An automatic-tracking activity exposes its criteria descriptions for the sheet, and
     * never offers the manual toggle (core does not allow overriding automatic criteria).
     *
     * @covers ::build
     */
    public function test_automatic_completion_exposes_criteria_and_never_offers_a_toggle(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page(
            [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionview' => 1,
            ],
            ['enablecompletion' => 1]
        );
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build($cm, $course, $renderer, null, [], (int)$student->id, '');

        $this->assertTrue($card['opensheet']);
        $this->assertSame('automatic', $card['completiontype']);
        $this->assertNotSame('[]', $card['completioncriteria']);
        $this->assertFalse($card['cantoggle']);
    }

    /**
     * A description passed in by the caller must open the sheet on its own, even when
     * there is no availability badge and no completion tracking at all.
     *
     * @covers ::build
     */
    public function test_description_alone_opens_the_sheet(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build($cm, $course, $renderer, null, [], (int)$student->id, '<p>Read first.</p>');

        $this->assertTrue($card['opensheet']);
        $this->assertTrue($card['hasdescription']);
        $this->assertSame('<p>Read first.</p>', $card['description']);
    }

    /**
     * With no badge, no completion tracking and no description, the sheet must not open
     * at all — the card stays a plain direct link, unchanged from before this feature.
     *
     * @covers ::build
     */
    public function test_nothing_to_show_skips_the_sheet(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build($cm, $course, $renderer, null, [], (int)$student->id, '');

        $this->assertFalse($card['opensheet']);
        $this->assertFalse($card['hascompletionbadge']);
        $this->assertFalse($card['hasdescription']);
    }
}
