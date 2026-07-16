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
     * @covers ::find_default_open_section_index
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
        course_get_format($course)->update_course_format_options(['navstyle' => 'accordion']);

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
}
