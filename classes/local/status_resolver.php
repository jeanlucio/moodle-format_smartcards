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

/**
 * Derives the SmartCards badge/visibility status of a course module from cm_info alone.
 *
 * This class never recomputes availability rules. It only reads properties already
 * calculated by the core availability system ({@see cm_info::$uservisible},
 * {@see cm_info::$available}, {@see cm_info::is_visible_on_course_page()},
 * {@see cm_info::$visible}, {@see cm_info::$availableinfo},
 * {@see cm_info::$completionexpected}), so behaviour always stays consistent with core.
 *
 * The badge is derived from {@see cm_info::$available}, not {@see cm_info::$uservisible}.
 * Core computes $available purely from the restriction condition, with no regard for
 * whether the viewer can bypass it; $uservisible additionally folds in
 * moodle/course:ignoreavailabilityrestrictions (true for a teacher even when the
 * condition is unmet — see cm_info::update_user_visible()). Using $uservisible for the
 * badge would make a teacher who can bypass a restriction never see the 'locked' badge
 * at all, defeating the plugin's whole point of surfacing restriction status instead of
 * hiding it like the stealth-activity workaround does. $uservisible is still what
 * decides {@see cm_status::$canaccess} — whether the card's link actually works.
 *
 * Note: core only ever populates {@see cm_info::$availableinfo} while the module is
 * unavailable ({@see \core_availability\info::is_available()} clears it to an empty
 * string whenever the item is available). So a 'has a deadline' badge cannot be derived
 * from availability conditions once the item is accessible; it uses
 * {@see cm_info::$completionexpected} instead, a generic per-activity date that is
 * equally available on every module type without any module-specific logic.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_resolver {
    /** @var string Badge shown when the activity is restricted/unavailable. */
    public const BADGE_LOCKED = 'locked';

    /** @var string Badge shown when the activity is available but has an expected completion date. */
    public const BADGE_TIMED = 'timed';

    /**
     * Resolves the visual status of a single course module.
     *
     * @param cm_info $cm Course module to evaluate.
     * @return cm_status Resolved status.
     */
    public static function resolve(cm_info $cm): cm_status {
        if (!$cm->is_visible_on_course_page()) {
            return new cm_status(isvisible: false, badge: null, reason: '', duedate: 0, dimmed: false, canaccess: false);
        }

        $dimmed  = !$cm->visible;
        $duedate = (int)$cm->completionexpected;

        if (!$cm->available) {
            return new cm_status(
                isvisible: true,
                badge: self::BADGE_LOCKED,
                reason: (string)$cm->availableinfo,
                duedate: $duedate,
                dimmed: $dimmed,
                canaccess: $cm->uservisible,
            );
        }

        if ($duedate > 0) {
            return new cm_status(
                isvisible: true,
                badge: self::BADGE_TIMED,
                reason: '',
                duedate: $duedate,
                dimmed: $dimmed,
                canaccess: true,
            );
        }

        return new cm_status(isvisible: true, badge: null, reason: '', duedate: 0, dimmed: $dimmed, canaccess: true);
    }
}
