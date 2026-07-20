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

/**
 * Tests for the SmartCards card_builder, focused on the defaultbgcolor/defaultlabelcolor/
 * defaultlabelfont course format option fallback (the full render pipeline is covered
 * by tests/output/courseformat/content_test.php).
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \format_smartcards\local\card_builder
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
     * Creates a course with one Label activity and returns it alongside the course.
     * Label is the reference cm_info::has_custom_cmlist_item() activity — its whole
     * point is showing its own content inline instead of behind a link (see
     * mod_label::label_cm_info_view()).
     *
     * @param array $moduleoptions Extra options passed to create_module() (e.g. completion
     *                              tracking).
     * @param array $courseoptions Extra options passed to create_course() (e.g. enablecompletion).
     * @return array{0: \stdClass, 1: \stdClass} Course record and course-module record.
     */
    private function create_course_with_label(array $moduleoptions = [], array $courseoptions = []): array {
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course($courseoptions);
        $label     = $generator->create_module('label', [
            'course' => $course->id,
            'intro' => '<p>Label content.</p>',
            'introformat' => FORMAT_HTML,
        ] + $moduleoptions);
        return [$course, $label];
    }

    /**
     * An activity's own labelcolor/labelfont must take priority over the course's
     * defaultlabelcolor/defaultlabelfont format option, never the other way round.
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
     * An activity with an expected completion date (and no availability restriction)
     * must get the 🕒 "timed" badge, with duedate/hasduedate/duedateformatted all
     * populated from status_resolver's own duedate — mirroring
     * status_resolver_test::test_activity_with_expected_completion_gets_timed_badge(),
     * but through the full card_builder pipeline instead of the resolver alone.
     */
    public function test_activity_with_expected_completion_shows_the_timed_badge(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $expected = (int)time() + DAYSECS;
        $DB->set_field('course_modules', 'completionexpected', $expected, ['id' => $page->cmid]);

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build($cm, $course, $renderer, null, [], (int)$student->id, '');

        $this->assertSame(status_resolver::BADGE_TIMED, $card['badge']);
        $this->assertTrue($card['istimed']);
        $this->assertFalse($card['islocked']);
        $this->assertSame($expected, $card['duedate']);
        $this->assertTrue($card['hasduedate']);
        $this->assertSame(
            userdate($expected, get_string('strftimedatefullshort', 'langconfig')),
            $card['duedateformatted']
        );
    }

    /**
     * An activity's own bgcolor must take priority over the course's defaultbgcolor
     * format option, never the other way round.
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
     * A restriction whose own "hide info" eye is off leaves cm_info::$availableinfo empty
     * for every viewer (core_availability\info::is_available() is not capability-aware —
     * see availability/classes/info.php's own docblock). But a viewer who can already
     * bypass the restriction (e.g. a teacher with moodle/course:ignoreavailabilityrestrictions)
     * and holds moodle/course:viewhiddenactivities must still see the full reason, exactly
     * like core's own standard course-page rendering does via
     * core_courseformat\output\local\content\cm\availability::conditional_availability_info().
     */
    public function test_teacher_with_viewhiddenactivities_sees_full_reason_when_eye_is_off(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $condition = tree::get_root_json(
            [condition::get_json(condition::DIRECTION_FROM, time() + DAYSECS)],
            tree::OP_AND,
            false
        );
        $DB->set_field('course_modules', 'availability', json_encode($condition), ['id' => $page->cmid]);
        rebuild_course_cache($course->id, true);

        $modinfo = get_fast_modinfo($course, $teacher->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build($cm, $course, $renderer, null, [], (int)$teacher->id, '');

        $this->assertSame(status_resolver::BADGE_LOCKED, $card['badge']);
        $this->assertTrue($card['hasreason']);
        $this->assertNotSame('', $card['reason']);
    }

    /**
     * The same eye-off restriction must leave the card entirely unrendered (null) for a
     * plain student — core's cm_info::update_user_visible() only re-reveals
     * uservisibleoncoursepage when availableinfo is non-empty, so with the eye off and no
     * bypass capability the activity vanishes from the course page altogether, not just a
     * badge with no reason. This locks in that pre-existing behaviour as a regression
     * guard alongside the teacher-sees-full-reason case above.
     */
    public function test_student_gets_no_card_at_all_when_eye_is_off(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $condition = tree::get_root_json(
            [condition::get_json(condition::DIRECTION_FROM, time() + DAYSECS)],
            tree::OP_AND,
            false
        );
        $DB->set_field('course_modules', 'availability', json_encode($condition), ['id' => $page->cmid]);
        rebuild_course_cache($course->id, true);

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($page->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build($cm, $course, $renderer, null, [], (int)$student->id, '');

        $this->assertNull($card);
    }

    /**
     * A manual-tracking activity not yet completed must open the sheet, carry the pending
     * completion badge, and offer the toggle button to a student who holds the manual
     * completion capability.
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

    /**
     * A Label with content must default to rendering inline and never open the sheet —
     * everything the sheet would have shown renders directly inline instead.
     */
    public function test_label_defaults_to_inline_and_never_opens_the_sheet(): void {
        $this->resetAfterTest();
        [$course, $label] = $this->create_course_with_label();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($label->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build(
            $cm,
            $course,
            $renderer,
            null,
            [],
            (int)$student->id,
            cm_description_resolver::resolve_one($cm)
        );

        $this->assertTrue($card['isinline']);
        $this->assertFalse($card['opensheet']);
        $this->assertStringContainsString('Label content.', $card['description']);
    }

    /**
     * Saving DISPLAYMODE_TILE on a Label must revert it to a normal clickable tile that
     * opens the sheet, exactly like any other activity with a description.
     */
    public function test_label_displaymode_tile_forces_a_normal_tile(): void {
        $this->resetAfterTest();
        [$course, $label] = $this->create_course_with_label();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        (new appearance_repository())->save_for_activity(
            $label->cmid,
            appearance_repository::TYPE_DEFAULT,
            '',
            null,
            null,
            null,
            null,
            appearance_repository::DISPLAYMODE_TILE
        );

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($label->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build(
            $cm,
            $course,
            $renderer,
            (new appearance_repository())->get_for_activity($label->cmid),
            [],
            (int)$student->id,
            cm_description_resolver::resolve_one($cm)
        );

        $this->assertFalse($card['isinline']);
        $this->assertTrue($card['opensheet']);
    }

    /**
     * A Label with toggleable manual completion stays inline, but exposes the manual
     * completion toggle — the tap that used to reveal it is gone, so the toggle itself
     * must render directly in the flow instead.
     */
    public function test_label_with_toggleable_manual_completion_stays_inline_with_a_toggle(): void {
        $this->resetAfterTest();
        [$course, $label] = $this->create_course_with_label(
            ['completion' => COMPLETION_TRACKING_MANUAL],
            ['enablecompletion' => 1]
        );
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($label->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build(
            $cm,
            $course,
            $renderer,
            null,
            [],
            (int)$student->id,
            cm_description_resolver::resolve_one($cm)
        );

        $this->assertTrue($card['isinline']);
        $this->assertTrue($card['showinlinemanualcompletion']);
        $this->assertTrue($card['cantoggle']);
    }

    /**
     * A Label with manual completion the current user cannot toggle (no
     * moodle/course:togglecompletion capability) must still expose
     * showinlinemanualcompletion, so the inline template can fall back to a read-only
     * status span instead of silently showing nothing.
     */
    public function test_label_with_non_toggleable_manual_completion_still_shows_inline_status(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $label] = $this->create_course_with_label(
            ['completion' => COMPLETION_TRACKING_MANUAL],
            ['enablecompletion' => 1]
        );
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $coursecontext = \context_course::instance($course->id);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        assign_capability('moodle/course:togglecompletion', CAP_PROHIBIT, $studentrole->id, $coursecontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($label->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build(
            $cm,
            $course,
            $renderer,
            null,
            [],
            (int)$student->id,
            cm_description_resolver::resolve_one($cm)
        );

        $this->assertTrue($card['isinline']);
        $this->assertTrue($card['showinlinemanualcompletion']);
        $this->assertFalse($card['cantoggle']);
    }

    /**
     * A Label restricted by an unmet availability condition stays inline but exposes
     * showinlinebadge, so the restriction is still visible without a tap.
     */
    public function test_label_with_availability_restriction_shows_inline_badge(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $label] = $this->create_course_with_label();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $condition = tree::get_root_json([
            condition::get_json(condition::DIRECTION_FROM, time() + DAYSECS),
        ]);
        $DB->set_field('course_modules', 'availability', json_encode($condition), ['id' => $label->cmid]);
        rebuild_course_cache($course->id, true);

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($label->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build(
            $cm,
            $course,
            $renderer,
            null,
            [],
            (int)$student->id,
            cm_description_resolver::resolve_one($cm)
        );

        $this->assertTrue($card['isinline']);
        $this->assertTrue($card['showinlinebadge']);
        $this->assertFalse($card['opensheet']);
    }

    /**
     * A Label with automatic completion tracking stays inline but exposes the
     * criteria list, shaped for core_course/completion_automatic.
     */
    public function test_label_with_automatic_completion_shows_inline_criteria(): void {
        $this->resetAfterTest();
        [$course, $label] = $this->create_course_with_label(
            [
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionview' => 1,
            ],
            ['enablecompletion' => 1]
        );
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $modinfo = get_fast_modinfo($course, $student->id);
        $cm      = $modinfo->get_cm($label->cmid);

        global $PAGE;
        $renderer = $PAGE->get_renderer('format_smartcards');

        $card = card_builder::build(
            $cm,
            $course,
            $renderer,
            null,
            [],
            (int)$student->id,
            cm_description_resolver::resolve_one($cm)
        );

        $this->assertTrue($card['isinline']);
        $this->assertTrue($card['showinlineautomaticcriteria']);
        $this->assertNotEmpty($card['criteria']);
    }

    /**
     * An ordinary module (no custom cmlist item) must never become inline, even with a
     * stray DISPLAYMODE_TILE saved on it — displaymode is only ever consulted once
     * has_custom_cmlist_item() is already true.
     */
    public function test_ordinary_module_never_becomes_inline_regardless_of_stray_displaymode(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        (new appearance_repository())->save_for_activity(
            $page->cmid,
            appearance_repository::TYPE_DEFAULT,
            '',
            null,
            null,
            null,
            null,
            appearance_repository::DISPLAYMODE_TILE
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

        $this->assertFalse($card['isinline']);
    }
}
