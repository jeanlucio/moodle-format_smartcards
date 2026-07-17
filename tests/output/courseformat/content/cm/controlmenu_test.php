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

namespace format_smartcards\output\courseformat\content\cm;

/**
 * Tests for the SmartCards cm controlmenu — the "Card appearance" entry it adds to the
 * native per-activity edit menu.
 *
 * No test at all existed for this class until now (found via moodle-coverage): the
 * section-level counterpart (content/section/controlmenu_test.php) has had tests since
 * the Moodle 4.5 cross-version fix, but this cm-level sibling — the class that fix
 * touched just as heavily (dual cm_control_items()/get_cm_control_items() override,
 * $this->mod->context instead of the 5.x-only $modcontext) — was never covered by an
 * automated test, only by a one-off manual verification script during that debugging
 * session. Exercised via get_action_menu() (the real entry point core itself calls),
 * never a direct call to get_cm_control_items()/cm_control_items() — calling the wrong
 * entry point is exactly what let a real bug through undetected during that fix.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\output\courseformat\content\cm\controlmenu
 */
final class controlmenu_test extends \advanced_testcase {
    /**
     * An editing teacher (who holds format/smartcards:manageappearance) must see the
     * "Card appearance" entry in a real activity's action menu.
     *
     * @covers ::get_cm_control_items
     * @covers ::cm_control_items
     * @covers ::add_appearance_control
     */
    public function test_teacher_gets_the_appearance_entry(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $cm = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);
        $renderer = $PAGE->get_renderer('core');

        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course);
        $sectionone = $modinfo->get_section_info(1);
        $cminfo = $modinfo->get_cm($cm->cmid);

        $classname = $format->get_output_classname('content\\cm\\controlmenu');
        $menu = new $classname($format, $sectionone, $cminfo);
        $actionmenu = $menu->get_action_menu($renderer);

        $this->assertNotNull($actionmenu);
        $html = $renderer->render($actionmenu);
        $this->assertStringContainsString('smartcardsEditAppearance', $html);
        $this->assertStringContainsString('data-cmid="' . $cm->cmid . '"', $html);
    }

    /**
     * A student, who never holds format/smartcards:manageappearance, must never see the
     * "Card appearance" entry — mirrors the same capability gate the section-level menu
     * enforces.
     *
     * @covers ::get_cm_control_items
     * @covers ::cm_control_items
     * @covers ::add_appearance_control
     */
    public function test_student_never_gets_the_appearance_entry(): void {
        global $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $cm = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);
        $renderer = $PAGE->get_renderer('core');

        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course);
        $sectionone = $modinfo->get_section_info(1);
        $cminfo = $modinfo->get_cm($cm->cmid);

        $classname = $format->get_output_classname('content\\cm\\controlmenu');
        $menu = new $classname($format, $sectionone, $cminfo);
        $actionmenu = $menu->get_action_menu($renderer);

        // A student still gets a minimal menu (e.g. Permalink), just never the
        // appearance entry — this is a capability gate on one specific item, not on
        // whether the menu exists at all.
        $this->assertNotNull($actionmenu);
        $html = $renderer->render($actionmenu);
        $this->assertStringNotContainsString('smartcardsEditAppearance', $html);
    }
}
