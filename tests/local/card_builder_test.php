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
 * Tests for the SmartCards card_builder, focused on the defaultlabelcolor/
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
     * @return array{0: \stdClass, 1: \stdClass} Course record and course-module record.
     */
    private function create_course_with_page(): array {
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $page      = $generator->create_module('page', ['course' => $course->id]);
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
            $formatoptions
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

        $card = card_builder::build($cm, $course, $renderer, null, $formatoptions);

        $this->assertStringContainsString('color: ' . appearance_palette::LABEL_COLORS['teal'], $card['titlestyle']);
        $this->assertStringContainsString("'Varela Round'", $card['titlestyle']);
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

        $card = card_builder::build($cm, $course, $renderer, null, []);

        $this->assertSame('', $card['titlestyle']);
        $this->assertFalse($card['hastitlestyle']);
    }
}
