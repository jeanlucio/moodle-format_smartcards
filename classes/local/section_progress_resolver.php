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

use completion_info;
use course_modinfo;
use section_info;

/**
 * Derives per-section activity-completion progress from core's native completion API,
 * mirroring the same aggregation core_courseformat\output\local\content\section\cmsummary
 * already does for every other course format's section summary — never a bespoke
 * recalculation of completion state.
 *
 * Callers must construct a single {@see completion_info} instance and reuse it across
 * every section of the course: {@see completion_info::get_data()} runs its bulk
 * per-course completion query only on the first call (when $wholecourse is true) and
 * serves every later call from cache, so looping resolve() with a shared instance is one
 * query for the whole course, not one per section or per activity.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class section_progress_resolver {
    /**
     * Resolves the completion progress of one section.
     *
     * @param completion_info $completioninfo Shared instance for the whole course render.
     * @param course_modinfo $modinfo Course module info.
     * @param section_info $section Section to evaluate.
     * @return section_progress
     */
    public static function resolve(
        completion_info $completioninfo,
        course_modinfo $modinfo,
        section_info $section
    ): section_progress {
        if (!isloggedin() || isguestuser()) {
            return new section_progress(0, 0);
        }

        $cmids = $modinfo->sections[$section->section] ?? [];

        $total    = 0;
        $complete = 0;
        foreach ($cmids as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible || (int)$completioninfo->is_enabled($cm) === COMPLETION_TRACKING_NONE) {
                continue;
            }

            $total++;
            $data = $completioninfo->get_data($cm, true);
            if (in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
                $complete++;
            }
        }

        return new section_progress($complete, $total);
    }
}
