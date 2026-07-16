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
 * Immutable value object describing one section's activity-completion progress, for the
 * "Acordeão com progresso" navigation style (SCOPE.md §16 Fase 3).
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class section_progress {
    /** @var int Number of visible, completion-tracked activities the user has completed. */
    public readonly int $complete;

    /** @var int Number of visible activities in the section with completion tracking enabled. */
    public readonly int $total;

    /**
     * Constructor.
     *
     * @param int $complete Completed, completion-tracked activity count.
     * @param int $total Total completion-tracked activity count.
     */
    public function __construct(int $complete, int $total) {
        $this->complete = $complete;
        $this->total    = $total;
    }

    /**
     * Whether the section has any completion-tracked activities at all.
     *
     * @return bool
     */
    public function has_tracking(): bool {
        return $this->total > 0;
    }

    /**
     * Whether the section has at least one completion-tracked activity the user has not
     * completed yet — used to pick which section the accordion opens by default.
     *
     * @return bool
     */
    public function has_pending(): bool {
        return $this->complete < $this->total;
    }
}
