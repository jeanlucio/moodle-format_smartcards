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
 * Immutable value object describing the SmartCards visual status of one course module.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cm_status {
    /** @var bool False when the card must not be rendered at all (fully hidden). */
    public readonly bool $isvisible;

    /** @var string|null One of 'locked', 'timed', or null when the item is freely available. */
    public readonly ?string $badge;

    /**
     * @var string Raw availability info text (may contain trusted core-generated markup).
     *             Only ever non-empty for the 'locked' badge, since core only populates
     *             cm_info::$availableinfo while the item is unavailable.
     */
    public readonly string $reason;

    /** @var int Unix timestamp from cm_info::$completionexpected, or 0 when unset. */
    public readonly int $duedate;

    /** @var bool True when the item should be rendered dimmed ("Hidden") for editing users. */
    public readonly bool $dimmed;

    /**
     * @var bool Whether the current user can actually follow the card's link right now.
     *           A teacher who can bypass a restriction (moodle/course:
     *           ignoreavailabilityrestrictions) still sees the 'locked' badge as a
     *           transparency indicator, but this stays true for them; for a student who
     *           cannot bypass it, this is false whenever the badge is 'locked'.
     */
    public readonly bool $canaccess;

    /**
     * Constructor.
     *
     * @param bool $isvisible Whether the card should be rendered at all.
     * @param string|null $badge Badge key, or null when freely available.
     * @param string $reason Availability info text.
     * @param int $duedate Unix timestamp of the expected completion date, or 0.
     * @param bool $dimmed Whether the item should be rendered dimmed for editing users.
     * @param bool $canaccess Whether the current user can actually follow the card's link.
     */
    public function __construct(
        bool $isvisible,
        ?string $badge,
        string $reason,
        int $duedate,
        bool $dimmed,
        bool $canaccess,
    ) {
        $this->isvisible = $isvisible;
        $this->badge     = $badge;
        $this->reason    = $reason;
        $this->duedate   = $duedate;
        $this->dimmed    = $dimmed;
        $this->canaccess = $canaccess;
    }
}
