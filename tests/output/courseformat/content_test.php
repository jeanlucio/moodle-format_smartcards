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

namespace format_smartcards\output\courseformat;

use availability_date\condition;
use core_availability\tree;

/**
 * Full-pipeline render tests for the SmartCards content output class.
 *
 * These exercise the real course_get_format() + renderer + Mustache template
 * pipeline (not just status_resolver in isolation), to catch mistakes that
 * only surface when cm_info methods are actually called during export.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\output\courseformat\content
 */
final class content_test extends \advanced_testcase {
    /**
     * Renders a SmartCards course page for a student with one activity of each
     * kind (free, restricted, with an expected completion date, hidden) and
     * asserts each renders exactly as its badge state requires.
     *
     * @covers ::export_for_template
     * @covers ::build_cards_data
     */
    public function test_grid_renders_one_card_per_visible_activity(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);

        $free = $generator->create_module('page', ['course' => $course->id]);

        $restricted = $generator->create_module('page', ['course' => $course->id]);
        $condition  = tree::get_root_json([
            condition::get_json(condition::DIRECTION_FROM, time() + DAYSECS),
        ]);
        $DB->set_field('course_modules', 'availability', json_encode($condition), ['id' => $restricted->cmid]);

        $timed = $generator->create_module('page', ['course' => $course->id]);
        $DB->set_field('course_modules', 'completionexpected', time() + WEEKSECS, ['id' => $timed->cmid]);

        $hidden = $generator->create_module('page', ['course' => $course->id]);
        set_coursemodule_visible($hidden->cmid, 0);

        rebuild_course_cache($course->id, true);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        // The free activity links directly, with no badge.
        $this->assertMatchesRegularExpression(
            '~<a\s[^>]*href="[^"]*mod/page/view\.php\?id=' . $free->cmid . '"[^>]*class="sc-card"~',
            $html
        );

        // The restricted activity is a badged button with a non-empty reason, and no direct URL.
        $this->assertMatchesRegularExpression(
            '~data-cmid="' . $restricted->cmid . '"[^>]*data-badgelabel="Restricted"[^>]*data-reason="[^"]+"[^>]*data-hasurl="0"~',
            $html
        );

        // The timed activity is a badged button that still carries a direct URL and a due date.
        $this->assertMatchesRegularExpression(
            '~data-cmid="' . $timed->cmid . '"[^>]*data-badgelabel="Has a deadline"[^>]*data-reason=""[^>]*data-hasurl="1"~',
            $html
        );

        // The hidden activity leaves no trace for the student.
        $this->assertStringNotContainsString((string)$hidden->cmid, $html);
    }

    /**
     * A teacher who can bypass the restriction must still see the 'locked' badge on a
     * restricted activity (the transparency the plugin exists to provide, instead of
     * hiding restriction status like the stealth-activity workaround), while still
     * being able to follow the link — data-hasurl="1" alongside the badge, a combination
     * that previously only happened for the 'timed' badge.
     *
     * @covers ::export_for_template
     * @covers ::build_cards_data
     */
    public function test_teacher_sees_locked_badge_with_working_link(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);

        $restricted = $generator->create_module('page', ['course' => $course->id]);
        $condition  = tree::get_root_json([
            condition::get_json(condition::DIRECTION_FROM, time() + DAYSECS),
        ]);
        $DB->set_field('course_modules', 'availability', json_encode($condition), ['id' => $restricted->cmid]);
        rebuild_course_cache($course->id, true);

        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertMatchesRegularExpression(
            '~data-cmid="' . $restricted->cmid . '"[^>]*data-badgelabel="Restricted"[^>]*data-reason="[^"]+"[^>]*data-hasurl="1"~',
            $html
        );
    }

    /**
     * An activity with appearance type=default and only a labelcolor set must render
     * with the ordinary per-module-type icon (not an emoji span or a custom icon img),
     * while still applying the custom title colour — the "keep the default icon, only
     * customise colour/font" combination the appearance editor exists to support.
     *
     * @covers ::export_for_template
     * @covers ::build_cards_data
     */
    public function test_default_type_keeps_module_icon_with_custom_title_colour(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $page      = $generator->create_module('page', ['course' => $course->id]);

        (new \format_smartcards\local\appearance_repository())->save_for_activity(
            $page->cmid,
            \format_smartcards\local\appearance_repository::TYPE_DEFAULT,
            '',
            null,
            \format_smartcards\local\appearance_palette::LABEL_COLORS['blue'],
            null
        );

        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringNotContainsString('sc-card-emoji', $html);
        $this->assertMatchesRegularExpression('~<img src="[^"]*/page/[^"]*icon"~', $html);
        $this->assertStringContainsString(
            'style="color: ' . \format_smartcards\local\appearance_palette::LABEL_COLORS['blue'] . '"',
            $html
        );
    }

    /**
     * The cardsize and showcardframe format options must control the grid container's
     * CSS classes, which styles.css keys its size presets and borderless mode off.
     *
     * @covers ::export_for_template
     */
    public function test_cardsize_and_showcardframe_options_control_grid_classes(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $generator->create_module('page', ['course' => $course->id]);

        $format = course_get_format($course);
        $format->update_course_format_options(['cardsize' => 'large', 'showcardframe' => 0]);

        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertMatchesRegularExpression('~class="sc-course[^"]*sc-size-large~', $html);
        $this->assertMatchesRegularExpression('~class="sc-course[^"]*sc-noframe~', $html);
    }

    /**
     * The course's defaultlabelcolor format option must style an activity that does not
     * set its own labelcolor.
     *
     * @covers ::export_for_template
     * @covers ::build_cards_data
     */
    public function test_defaultlabelcolor_applies_when_activity_has_no_own_colour(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $generator->create_module('page', ['course' => $course->id]);

        $coursedefault = \format_smartcards\local\appearance_palette::LABEL_COLORS['green'];
        course_get_format($course)->update_course_format_options(['defaultlabelcolor' => $coursedefault]);

        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringContainsString('style="color: ' . $coursedefault . '"', $html);
    }

    /**
     * With navstyle=accordion, the section with a pending completion-tracked activity
     * must render expanded (aria-expanded="true", collapse "show"), and a fully-complete
     * section must render collapsed — the "resume where you left off" behaviour the
     * accordion style exists to provide.
     *
     * @covers ::export_for_template
     * @covers ::find_default_active_section_index
     */
    public function test_accordion_opens_the_section_with_a_pending_activity(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'smartcards',
            'numsections' => 2,
            'enablecompletion' => 1,
        ]);
        course_get_format($course)->update_course_format_options([
            'navstyle' => 'accordion',
            'progressdisplay' => 'count',
        ]);

        $pending = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $done = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 2,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $completioninfo = new \completion_info($course);
        $completioninfo->update_state(
            get_fast_modinfo($course, $student->id)->get_cm($done->cmid),
            COMPLETION_COMPLETE,
            $student->id
        );
        $section1id = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1]);
        $section2id = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 2]);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertMatchesRegularExpression(
            '~aria-expanded="true"[^>]*aria-controls="sc-accordion-body-' . $section1id . '"~',
            $html
        );
        $this->assertMatchesRegularExpression(
            '~aria-expanded="false"[^>]*aria-controls="sc-accordion-body-' . $section2id . '"~',
            $html
        );
        $this->assertStringContainsString('Progress: 0 / 1', $html);
        $this->assertStringContainsString('Progress: 1 / 1', $html);
    }

    /**
     * A section the student has explicitly collapsed must stay collapsed even though it
     * still has a pending activity — the student's own choice always wins over the
     * "open the pending section" smart default, which only applies to sections nobody
     * has touched yet.
     *
     * @covers ::export_for_template
     * @covers ::find_default_active_section_index
     */
    public function test_accordion_respects_a_manually_collapsed_section_over_pending_progress(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'smartcards',
            'numsections' => 2,
            'enablecompletion' => 1,
        ]);
        $format = course_get_format($course);
        $format->update_course_format_options(['navstyle' => 'accordion']);

        $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $generator->create_module('page', [
            'course' => $course->id,
            'section' => 2,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $section1id = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1]);
        $section2id = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 2]);

        // The student manually collapses section 1, even though it still has a pending
        // activity — the same preference format_smartcards_toggle_section writes.
        $format->add_section_preference_ids(\format_smartcards\external\toggle_section::PREFERENCE_COLLAPSED, [$section1id]);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertMatchesRegularExpression(
            '~aria-expanded="false"[^>]*aria-controls="sc-accordion-body-' . $section1id . '"~',
            $html
        );
        $this->assertMatchesRegularExpression(
            '~aria-expanded="true"[^>]*aria-controls="sc-accordion-body-' . $section2id . '"~',
            $html
        );
    }

    /**
     * A section the accordion closed by its own "resume where you left off" default
     * (not because it was ever explicitly collapsed) must stay OPEN after the student
     * manually expands it and the page reloads. This is the exact bug a real course
     * hit: expanding a section the accordion had closed by default was a no-op against
     * course_format's own 'contentcollapsed' list (the section was never in it to begin
     * with), so the expand silently reverted on the next render — fixed by tracking
     * explicit expands in their own preference (toggle_section::PREFERENCE_EXPANDED)
     * instead of only tracking explicit collapses.
     *
     * @covers ::export_for_template
     * @covers ::find_default_active_section_index
     */
    public function test_accordion_keeps_a_manually_expanded_default_closed_section_open(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'smartcards',
            'numsections' => 2,
            'enablecompletion' => 1,
        ]);
        $format = course_get_format($course);
        $format->update_course_format_options(['navstyle' => 'accordion']);

        $pending = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $done = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 2,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $completioninfo = new \completion_info($course);
        $completioninfo->update_state(
            get_fast_modinfo($course, $student->id)->get_cm($done->cmid),
            COMPLETION_COMPLETE,
            $student->id
        );

        $section1id = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1]);
        $section2id = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 2]);

        // Confirm section 2 really is closed by the accordion's own default first
        // (it has no pending activity, so it is not the auto-picked section) — this
        // reproduces the exact starting point of the reported bug. course_get_format()
        // is re-fetched fresh before each render rather than reusing the $format
        // obtained above: create_module() resets the course format singleton cache, so
        // holding onto a reference from before that point risks a render building its
        // context off a stale, no-longer-registered format instance.
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = course_get_format($course)->get_output_classname('content');
        $htmlbefore  = $renderer->render(new $outputclass(course_get_format($course)));
        $this->assertMatchesRegularExpression(
            '~aria-expanded="false"[^>]*aria-controls="sc-accordion-body-' . $section2id . '"~',
            $htmlbefore
        );

        // The student manually expands section 2 — exactly what
        // format_smartcards_toggle_section persists when Bootstrap's shown.bs.collapse
        // event fires client-side.
        \format_smartcards\external\toggle_section::execute((int)$section2id, true);

        $html = $renderer->render(new $outputclass(course_get_format($course)));

        $this->assertMatchesRegularExpression(
            '~aria-expanded="true"[^>]*aria-controls="sc-accordion-body-' . $section2id . '"~',
            $html
        );
        // Section 1 (still untouched, still pending) must remain the auto-picked
        // default-open section — expanding section 2 must not steal that.
        $this->assertMatchesRegularExpression(
            '~aria-expanded="true"[^>]*aria-controls="sc-accordion-body-' . $section1id . '"~',
            $html
        );
    }

    /**
     * A section with no activities and no summary must still render its own heading in
     * the grid, matching the standard Moodle course index sidebar (core_courseformat's
     * own courseindex output), which always lists every visible section regardless of
     * whether it has content yet. Silently dropping empty sections from the grid while
     * the sidebar still promised them left a real click-to-nowhere gap for students.
     *
     * @covers ::export_for_template
     */
    public function test_a_section_with_no_activities_still_renders_its_heading(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['format' => 'smartcards', 'numsections' => 2]);

        $generator->create_module('page', ['course' => $course->id, 'section' => 1]);
        // Section 2 is deliberately left with no activities and no summary.

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringContainsString(get_string('sectionname', 'format_smartcards') . ' 2', $html);
    }

    /**
     * With navstyle=tabs, section 0 (General) always renders outside the tab system, and
     * the tab for the section with a pending completion-tracked activity opens active by
     * default — same "resume where you left off" rule as the accordion (§18 v2.7), just
     * with no per-section preference to respect since a tab switch is never persisted.
     *
     * @covers ::export_for_template
     * @covers ::find_default_active_section_index
     */
    public function test_tabs_activates_the_tab_with_a_pending_activity(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'smartcards',
            'numsections' => 2,
            'enablecompletion' => 1,
        ]);
        course_get_format($course)->update_course_format_options(['navstyle' => 'tabs']);

        $generator->create_module('forum', ['course' => $course->id, 'section' => 0]);
        $pending = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $done = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 2,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $completioninfo = new \completion_info($course);
        $completioninfo->update_state(
            get_fast_modinfo($course, $student->id)->get_cm($done->cmid),
            COMPLETION_COMPLETE,
            $student->id
        );
        $section0id = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 0]);
        $section1id = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1]);
        $section2id = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 2]);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        // Section 0 has no tab of its own: no nav-link/tab-pane id references it.
        $this->assertStringNotContainsString('sc-tab-' . $section0id . '"', $html);
        $this->assertStringNotContainsString('sc-tab-pane-' . $section0id . '"', $html);

        $this->assertMatchesRegularExpression(
            '~class="nav-link active"[^>]*id="sc-tab-' . $section1id . '"~',
            $html
        );
        $this->assertMatchesRegularExpression(
            '~class="nav-link "[^>]*id="sc-tab-' . $section2id . '"~',
            $html
        );
        $this->assertMatchesRegularExpression(
            '~class="tab-pane fade show active"[^>]*id="sc-tab-pane-' . $section1id . '"~',
            $html
        );
    }

    /**
     * With navstyle=sticky, the grid renders exactly like the default plain layout (every
     * section always expanded, no collapsing, no tabs) — the only difference is the
     * sc-sticky wrapper class that makes each section heading stick while scrolling
     * through it (CSS-only behaviour, not something a server-rendered HTML string can
     * assert beyond the class itself being present).
     *
     * @covers ::export_for_template
     */
    public function test_sticky_renders_the_wrapper_class_and_every_section_expanded(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 2]);
        course_get_format($course)->update_course_format_options(['navstyle' => 'sticky']);

        $generator->create_module('page', ['course' => $course->id, 'section' => 1]);
        $generator->create_module('page', ['course' => $course->id, 'section' => 2]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertMatchesRegularExpression('~class="sc-course[^"]*\bsc-sticky\b~', $html);
        // No accordion/tab markup at all — a plain, always-expanded grid underneath.
        $this->assertStringNotContainsString('sc-accordion-toggle', $html);
        $this->assertStringNotContainsString('nav-tabs', $html);
        $this->assertSame(2, substr_count($html, 'class="sc-grid"'));
    }

    /**
     * With navstyle=trail, every section's cards render inside a single sc-trail column
     * (cm_trail.mustache) instead of the wrapping sc-grid — the zigzag positioning itself
     * is pure CSS (styles.css), so this only asserts the right container/partial was
     * chosen and that every card is still present, in course order (the DOM order the
     * CSS-only zigzag relies on for tab/screen-reader order to stay correct).
     *
     * @covers ::export_for_template
     */
    public function test_trail_renders_every_card_in_a_single_column(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        course_get_format($course)->update_course_format_options(['navstyle' => 'trail']);

        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'First stop']);
        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Second stop']);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringContainsString('class="sc-trail"', $html);
        $this->assertStringNotContainsString('class="sc-grid"', $html);
        // No accordion/tab/section-card markup at all — trail is a plain, always-visible
        // layout, same as default/sticky in that respect.
        $this->assertStringNotContainsString('sc-accordion-toggle', $html);
        $this->assertStringNotContainsString('nav-tabs', $html);
        $this->assertStringNotContainsString('smartcards-section-card', $html);

        $firstpos = strpos($html, 'First stop');
        $secondpos = strpos($html, 'Second stop');
        $this->assertIsInt($firstpos);
        $this->assertIsInt($secondpos);
        $this->assertLessThan($secondpos, $firstpos);
    }

    /**
     * progressdisplay defaults to hidden when the course never set it explicitly — this
     * plugin has no installed base yet (confirmed with the user), so there is no legacy
     * behaviour to preserve; a section with pending completion-tracked activities must
     * not leak any "Progress:" text into the accordion heading without an explicit
     * opt-in.
     *
     * @covers ::export_for_template
     */
    public function test_progressdisplay_defaults_to_hidden(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'smartcards',
            'numsections' => 1,
            'enablecompletion' => 1,
        ]);
        course_get_format($course)->update_course_format_options(['navstyle' => 'accordion']);

        $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringNotContainsString('sc-progress-label', $html);
        $this->assertStringNotContainsString('Progress:', $html);
    }

    /**
     * With progressdisplay=count, the plain/default navstyle (which never showed progress
     * at all before this feature) must now render the same core progresstotal string the
     * accordion already used, right next to the section heading.
     *
     * @covers ::export_for_template
     */
    public function test_progressdisplay_count_shows_progress_in_default_navstyle(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'smartcards',
            'numsections' => 1,
            'enablecompletion' => 1,
        ]);
        course_get_format($course)->update_course_format_options(['progressdisplay' => 'count']);

        $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringContainsString('sc-progress-label', $html);
        $this->assertStringContainsString('Progress: 0 / 1', $html);
    }

    /**
     * With progressdisplay=percent, the label is a rounded percentage instead of the
     * core "Progress: X / Y" string.
     *
     * @covers ::export_for_template
     */
    public function test_progressdisplay_percent_shows_a_rounded_percentage(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'format' => 'smartcards',
            'numsections' => 1,
            'enablecompletion' => 1,
        ]);
        course_get_format($course)->update_course_format_options(['progressdisplay' => 'percent']);

        $done = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $completioninfo = new \completion_info($course);
        $completioninfo->update_state(
            get_fast_modinfo($course, $student->id)->get_cm($done->cmid),
            COMPLETION_COMPLETE,
            $student->id
        );

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringContainsString('>50%<', $html);
        $this->assertStringNotContainsString('Progress:', $html);
    }

    /**
     * A section the teacher explicitly hid (the section's own visibility toggle, not a
     * restriction) must leave no trace for a student — same "fully hidden" contract a
     * hidden activity already has (test_grid_renders_one_card_per_visible_activity).
     *
     * @covers ::export_for_template
     */
    public function test_section_hidden_by_teacher_is_not_visible_to_student(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $page      = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);

        set_section_visible($course->id, 1, 0);
        rebuild_course_cache($course->id, true);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);
        $sectionid = get_fast_modinfo($course, $student->id)->get_section_info(1)->id;

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringNotContainsString((string)$page->cmid, $html);
        $this->assertStringNotContainsString('sc-section-title-' . $sectionid, $html);
    }

    /**
     * A section restricted by an unmet condition, with the condition's own "eye" left
     * open, must still render its header and explain the restriction to the student —
     * the bug reported by the user: the previous uservisible-only check could not tell
     * this case apart from a fully hidden section (visible = 0) and silently dropped the
     * whole section instead of showing it greyed out with the reason, the way core
     * course formats already do (core_courseformat\output\local\base::
     * is_section_visible()). No cards render for the section either: core gates the
     * activity list the same way (section::add_cm_data()'s $showcmlist = uservisible).
     *
     * @covers ::export_for_template
     */
    public function test_restricted_section_shows_header_and_reason_but_no_cards(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $page      = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);

        $condition = tree::get_root_json([
            condition::get_json(condition::DIRECTION_FROM, time() + DAYSECS),
        ]);
        $DB->set_field('course_sections', 'availability', json_encode($condition), [
            'course' => $course->id,
            'section' => 1,
        ]);
        rebuild_course_cache($course->id, true);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringContainsString('sc-section-title-', $html);
        $this->assertStringContainsString('availabilityinfo', $html);
        $this->assertStringNotContainsString((string)$page->cmid, $html);
        $this->assertStringNotContainsString('sc-grid', $html);
    }

    /**
     * A teacher bypasses a section's restriction (moodle/course:
     * ignoreavailabilityrestrictions) the same way they already bypass an activity's own
     * restriction (test_teacher_sees_locked_badge_with_working_link): the section's cards
     * still render normally for them, on top of core's own restriction preview banner.
     *
     * @covers ::export_for_template
     */
    public function test_teacher_bypasses_section_restriction_and_still_sees_cards(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $page      = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);

        $condition = tree::get_root_json([
            condition::get_json(condition::DIRECTION_FROM, time() + DAYSECS),
        ]);
        $DB->set_field('course_sections', 'availability', json_encode($condition), [
            'course' => $course->id,
            'section' => 1,
        ]);
        rebuild_course_cache($course->id, true);

        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringContainsString((string)$page->cmid, $html);
        $this->assertStringContainsString('sc-grid', $html);
    }

    /**
     * navstyle = 'sectioncards' (SCOPE.md §16 Fase 4): one card per available section,
     * a hidden section leaves no trace, and a restricted-but-visible section renders a
     * locked card with the availability reason but no nested activity grid — the exact
     * same three-way distinction the other navstyles already make (§18 v2.22), now
     * applied to a single card instead of a heading + inline grid.
     *
     * @covers ::export_for_template
     */
    public function test_sectioncards_renders_one_card_per_available_section(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['format' => 'smartcards', 'numsections' => 3]);
        course_get_format($course)->update_course_format_options(['navstyle' => 'sectioncards']);

        $free = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);

        $restricted = $generator->create_module('page', ['course' => $course->id, 'section' => 2]);
        $condition  = tree::get_root_json([
            condition::get_json(condition::DIRECTION_FROM, time() + DAYSECS),
        ]);
        $DB->set_field('course_sections', 'availability', json_encode($condition), [
            'course' => $course->id,
            'section' => 2,
        ]);

        $hidden = $generator->create_module('page', ['course' => $course->id, 'section' => 3]);
        set_section_visible($course->id, 3, 0);

        rebuild_course_cache($course->id, true);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);
        $hiddensectionid = get_fast_modinfo($course, $student->id)->get_section_info(3)->id;

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringContainsString('sc-section-card', $html);
        $this->assertSame(2, substr_count($html, 'data-region="smartcards-section-card"'));

        // The free section's activity is reachable inside its (hidden) nested grid.
        $this->assertStringContainsString((string)$free->cmid, $html);

        // The restricted section shows its lock reason but never its activity.
        $this->assertStringContainsString('availabilityinfo', $html);
        $this->assertStringNotContainsString((string)$restricted->cmid, $html);

        // The hidden section leaves no trace at all — no card, no modal source, no cmid.
        $this->assertStringNotContainsString('data-sectionid="' . $hiddensectionid . '"', $html);
        $this->assertStringNotContainsString((string)$hidden->cmid, $html);
    }

    /**
     * navstyle = 'sectioncards' never renders the accordion/tabs/plain-grid markup —
     * only the new section-cards grid.
     *
     * @covers ::export_for_template
     */
    public function test_sectioncards_renders_no_accordion_or_tabs_markup(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        course_get_format($course)->update_course_format_options(['navstyle' => 'sectioncards']);
        $generator->create_module('page', ['course' => $course->id, 'section' => 1]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringNotContainsString('sc-accordion-toggle', $html);
        $this->assertStringNotContainsString('nav-tabs', $html);
    }

    /**
     * With generalinstyle enabled, section 0 (General) becomes collapsible in the
     * accordion just like any other section — instead of always rendering as the plain,
     * non-collapsible heading it gets by default (test_accordion_opens_the_section_with_
     * a_pending_activity() and friends, which never set this option, cover that default).
     *
     * @covers ::export_for_template
     */
    public function test_accordion_includes_general_when_generalinstyle_enabled(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        course_get_format($course)->update_course_format_options([
            'navstyle' => 'accordion',
            'generalinstyle' => 1,
        ]);
        $generator->create_module('forum', ['course' => $course->id, 'section' => 0]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);
        $sectionzeroid = get_fast_modinfo($course)->get_section_info(0)->id;

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringContainsString('sc-accordion-toggle', $html);
        $this->assertStringContainsString('data-target="#sc-accordion-body-' . $sectionzeroid . '"', $html);
    }

    /**
     * With generalinstyle enabled, section 0 (General) becomes a real, selectable tab
     * instead of always rendering above the tab bar — the opposite of
     * test_tabs_activates_the_tab_with_a_pending_activity()'s default-off assertion that
     * no tab/pane id ever references section 0.
     *
     * @covers ::export_for_template
     */
    public function test_tabs_includes_general_as_a_real_tab_when_generalinstyle_enabled(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        course_get_format($course)->update_course_format_options([
            'navstyle' => 'tabs',
            'generalinstyle' => 1,
        ]);
        $generator->create_module('forum', ['course' => $course->id, 'section' => 0]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);
        $sectionzeroid = get_fast_modinfo($course)->get_section_info(0)->id;

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringContainsString('id="sc-tab-' . $sectionzeroid . '"', $html);
        $this->assertStringContainsString('id="sc-tab-pane-' . $sectionzeroid . '"', $html);
    }

    /**
     * With generalinstyle enabled, section 0 (General) gets its own card and modal
     * source in sectioncards mode, on top of every other available section — the
     * opposite of test_sectioncards_renders_one_card_per_available_section()'s
     * default-off count.
     *
     * @covers ::export_for_template
     */
    public function test_sectioncards_includes_general_as_a_card_when_generalinstyle_enabled(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        course_get_format($course)->update_course_format_options([
            'navstyle' => 'sectioncards',
            'generalinstyle' => 1,
        ]);
        $generator->create_module('forum', ['course' => $course->id, 'section' => 0]);
        $generator->create_module('page', ['course' => $course->id, 'section' => 1]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);
        $sectionzeroid = get_fast_modinfo($course)->get_section_info(0)->id;

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertSame(2, substr_count($html, 'data-region="smartcards-section-card"'));
        $this->assertStringContainsString('data-sectionid="' . $sectionzeroid . '"', $html);
    }

    /**
     * With generalinstyle enabled, section 0 (General)'s activities render inside the
     * winding sc-trail column, the same as any other section in navstyle=trail, instead
     * of the always-plain top block test_trail_renders_every_card_in_a_single_column()
     * covers for its default-off sibling section.
     *
     * @covers ::export_for_template
     */
    public function test_trail_includes_general_in_the_zigzag_when_generalinstyle_enabled(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 0]);
        course_get_format($course)->update_course_format_options([
            'navstyle' => 'trail',
            'generalinstyle' => 1,
        ]);
        $generator->create_module('page', ['course' => $course->id, 'section' => 0, 'name' => 'Announcement page']);

        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $format      = course_get_format($course);
        $renderer    = $PAGE->get_renderer('format_smartcards');
        $outputclass = $format->get_output_classname('content');
        $widget      = new $outputclass($format);

        $html = $renderer->render($widget);

        $this->assertStringContainsString('class="sc-trail"', $html);
        $this->assertStringContainsString('Announcement page', $html);
        // The always-plain top block never fires: section 0's own cm_grid rendering is
        // gone, only the trail's cm_trail one remains.
        $this->assertStringNotContainsString('class="sc-grid"', $html);
    }
}
