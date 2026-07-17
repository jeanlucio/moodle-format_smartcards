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

use cm_info;
use core_completion\cm_completion_details;

/**
 * Derives one activity's completion state via core_completion\cm_completion_details —
 * never a bespoke recalculation of completion state or of the automatic completion
 * criteria descriptions (those already come fully localised from core's own
 * get_string('detail_desc:...', 'completion') calls).
 *
 * cm_completion_details::get_instance() builds its own completion_info internally, but
 * that is not an N+1 concern: completion_info's bulk ($wholecourse=true) cache is keyed
 * by user+course in a shared MUC cache store, not by PHP object instance, so the first
 * call from any instance in the request primes it and every later call (from this
 * resolver or from section_progress_resolver) reads from cache.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cm_completion_resolver {
    /**
     * Resolves the completion state of one activity for one user.
     *
     * @param cm_info $cm Course module to evaluate.
     * @param int $userid User id. 0 (not logged in) or a guest always resolves to
     *                     TRACKING_NONE, since neither has meaningful completion state.
     * @return cm_completion
     */
    public static function resolve(cm_info $cm, int $userid): cm_completion {
        if ($userid <= 0 || isguestuser($userid)) {
            return new cm_completion(cm_completion::TRACKING_NONE, false, []);
        }

        $details = cm_completion_details::get_instance($cm, $userid);
        if (!$details->has_completion()) {
            return new cm_completion(cm_completion::TRACKING_NONE, false, []);
        }

        $tracking = $details->is_manual() ? cm_completion::TRACKING_MANUAL : cm_completion::TRACKING_AUTOMATIC;

        $criteria = [];
        if ($tracking === cm_completion::TRACKING_AUTOMATIC) {
            foreach ($details->get_details() as $detail) {
                // A manually overridden criterion carries $completiondata->completionstate as
                // its status, which comes back as a string when sourced from a real DB row
                // (the same class of bug already fixed in section_progress_resolver.php) — cast
                // before the strict comparisons below, or an overridden-complete criterion would
                // wrongly render as incomplete.
                $status = (int)$detail->status;

                // Same status → badge-shape mapping as core_course\output\activity_completion,
                // so core's own core_course/completion_automatic template (reused as-is by
                // status_sheet.mustache) renders identically to the course page.
                $criteria[] = [
                    'description' => $detail->description,
                    'statuscomplete' => in_array($status, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true),
                    'statuscompletefail' => $status === COMPLETION_COMPLETE_FAIL,
                    'statusincomplete' => $status === COMPLETION_INCOMPLETE,
                    'istrackeduser' => true,
                ];
            }
        }

        return new cm_completion($tracking, $details->is_overall_complete(), $criteria);
    }
}
