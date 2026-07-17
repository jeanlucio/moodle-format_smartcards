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

namespace format_smartcards\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

/**
 * Persists a manual expand/collapse of one section in the "Acordeão com progresso"
 * navigation style (navstyle=accordion), so the choice survives a page reload instead of
 * always resetting to content::find_default_active_section_index()'s "resume where you
 * left off" default.
 *
 * Deliberately its own preference pair (scaccordioncollapsed/scaccordionexpanded)
 * rather than reusing course_format's own 'contentcollapsed' preference: that single
 * list only records explicit collapses, so an explicit *expand* of a section the
 * accordion had closed by its own default is indistinguishable from a section the
 * student never touched at all — the exact gap that let a manually expanded section
 * silently revert on the next reload. Tracking both directions removes the ambiguity:
 * every section is either explicitly collapsed, explicitly expanded, or genuinely
 * untouched, with no case conflated into another.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toggle_section extends external_api {
    /** @var string Preference name for sections explicitly collapsed by the user. */
    public const PREFERENCE_COLLAPSED = 'scaccordioncollapsed';

    /** @var string Preference name for sections explicitly expanded by the user. */
    public const PREFERENCE_EXPANDED = 'scaccordionexpanded';

    /**
     * Parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
            'open' => new external_value(PARAM_BOOL, 'Whether the section was just expanded (true) or collapsed (false)'),
        ]);
    }

    /**
     * Records the section's new manual expand/collapse state for the current user.
     *
     * No capability is required beyond normal course access: this is a personal UI
     * preference, the same permission model core's own equivalent web service action
     * (core_courseformat_update_course, section_content_collapsed/_expanded) uses.
     *
     * @param int $sectionid Section id.
     * @param bool $open Whether the section was just expanded (true) or collapsed (false).
     * @return bool Always true on success.
     */
    public static function execute(int $sectionid, bool $open): bool {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'sectionid' => $sectionid,
            'open' => $open,
        ]);

        $courseid = $DB->get_field('course_sections', 'course', ['id' => $params['sectionid']], MUST_EXIST);
        $context  = context_course::instance($courseid);
        self::validate_context($context);

        $format = course_get_format($courseid);
        if ($params['open']) {
            $format->add_section_preference_ids(self::PREFERENCE_EXPANDED, [$params['sectionid']]);
            $format->remove_section_preference_ids(self::PREFERENCE_COLLAPSED, [$params['sectionid']]);
        } else {
            $format->add_section_preference_ids(self::PREFERENCE_COLLAPSED, [$params['sectionid']]);
            $format->remove_section_preference_ids(self::PREFERENCE_EXPANDED, [$params['sectionid']]);
        }

        return true;
    }

    /**
     * Return structure for execute().
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Always true on success');
    }
}
