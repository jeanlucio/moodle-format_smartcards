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

/**
 * Immutable value object describing one activity's completion-tracking state for the
 * current user, for the card's completion badge and the status sheet's completion
 * section.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cm_completion {
    /** @var string No completion tracking configured for this activity. */
    public const TRACKING_NONE = 'none';

    /** @var string Completion is toggled manually by the student. */
    public const TRACKING_MANUAL = 'manual';

    /** @var string Completion is derived automatically from activity criteria (view, grade, ...). */
    public const TRACKING_AUTOMATIC = 'automatic';

    /** @var string One of TRACKING_NONE, TRACKING_MANUAL or TRACKING_AUTOMATIC. */
    public readonly string $tracking;

    /** @var bool Whether the activity is currently complete for this user. */
    public readonly bool $iscomplete;

    /**
     * @var array Automatic completion criteria, one entry per condition (e.g. "View",
     *            "Receive a grade"), each shaped exactly like core's own
     *            core_course/completion_automatic template context — {description:
     *            string, statuscomplete: bool, statuscompletefail: bool, statusincomplete:
     *            bool} — so the status sheet can render the same badge core itself shows
     *            on the course page, reused via that same template, never redrawn.
     *            Always empty for TRACKING_NONE/TRACKING_MANUAL.
     */
    public readonly array $criteria;

    /**
     * Constructor.
     *
     * @param string $tracking One of TRACKING_NONE, TRACKING_MANUAL or TRACKING_AUTOMATIC.
     * @param bool $iscomplete Whether the activity is currently complete.
     * @param array $criteria Automatic completion criteria, core_course/completion_automatic-shaped.
     */
    public function __construct(string $tracking, bool $iscomplete, array $criteria) {
        $this->tracking   = $tracking;
        $this->iscomplete = $iscomplete;
        $this->criteria   = $criteria;
    }

    /**
     * Whether this activity has any completion tracking at all.
     *
     * @return bool
     */
    public function is_tracked(): bool {
        return $this->tracking !== self::TRACKING_NONE;
    }
}
