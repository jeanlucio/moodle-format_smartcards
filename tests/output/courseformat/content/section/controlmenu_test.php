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

namespace format_smartcards\output\courseformat\content\section;

/**
 * Tests for the SmartCards section controlmenu — the "Card appearance" entry it adds to
 * the native per-section edit menu.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\output\courseformat\content\section\controlmenu
 */
final class controlmenu_test extends \advanced_testcase {
    /**
     * Section 0 (General) must never get the "Card appearance" entry — it always
     * renders as a plain inline heading, in every navstyle, never as a card, so nothing
     * the entry would let a teacher configure could ever be seen. Real bug found in
     * production: an emoji set through this menu for "Geral" saved successfully but was
     * never visible anywhere, because the section itself is never rendered as a card.
     *
     * @covers ::section_control_items
     */
    public function test_section_zero_never_gets_the_appearance_entry(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $format = course_get_format($course);
        $sectionzero = get_fast_modinfo($course)->get_section_info(0);

        $menu = new controlmenu($format, $sectionzero);
        $controls = $menu->section_control_items();

        $this->assertArrayNotHasKey('smartcardsappearance', $controls);
    }

    /**
     * A real (non-General) section, with the manage-appearance capability, must get the
     * "Card appearance" entry, carrying its own sectionid.
     *
     * @covers ::section_control_items
     */
    public function test_real_section_gets_the_appearance_entry(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $format = course_get_format($course);
        $sectionone = get_fast_modinfo($course)->get_section_info(1);

        $menu = new controlmenu($format, $sectionone);
        $controls = $menu->section_control_items();

        $this->assertArrayHasKey('smartcardsappearance', $controls);
    }

    /**
     * When the course's generalinstyle option opts section 0 into the active navstyle,
     * it becomes a real card too — so the entry that lets a teacher configure its
     * appearance must become available, the opposite of the default-off behaviour
     * covered by test_section_zero_never_gets_the_appearance_entry().
     *
     * @covers ::section_control_items
     */
    public function test_section_zero_gets_the_appearance_entry_when_generalinstyle_enabled(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        course_get_format($course)->update_course_format_options(['generalinstyle' => 1]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $format = course_get_format($course);
        $sectionzero = get_fast_modinfo($course)->get_section_info(0);

        $menu = new controlmenu($format, $sectionzero);
        $controls = $menu->section_control_items();

        $this->assertArrayHasKey('smartcardsappearance', $controls);
    }
}
