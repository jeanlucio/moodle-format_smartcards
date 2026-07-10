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

namespace format_smartcards\output;

use core_courseformat\output\section_renderer;
use moodle_page;

/**
 * Renderer for the SmartCards course format.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends section_renderer {
    /**
     * Constructor — registers the extra editing capability for sections.
     *
     * @param moodle_page $page The Moodle page object.
     * @param string $target One of the rendering target constants.
     */
    public function __construct(moodle_page $page, string $target) {
        parent::__construct($page, $target);
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Generates the section title wrapped in an in-place editable element.
     *
     * @param section_info|stdClass $section Section from the DB.
     * @param stdClass $course Course record.
     * @return string HTML output.
     */
    public function section_title($section, $course): string {
        return $this->render(
            course_get_format($course)->inplace_editable_render_section_name($section)
        );
    }

    /**
     * Generates the section title without a link (for single-section pages).
     *
     * @param section_info|stdClass $section Section from the DB.
     * @param int|stdClass $course Course record.
     * @return string HTML output.
     */
    public function section_title_without_link($section, $course): string {
        return $this->render(
            course_get_format($course)->inplace_editable_render_section_name($section, false)
        );
    }
}
