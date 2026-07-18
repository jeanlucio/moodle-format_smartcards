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

use stdClass;

/**
 * Immutable value object for one row of format_smartcards_appearance.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class appearance {
    /** @var int Record id. */
    public readonly int $id;

    /** @var string One of 'activity' or 'section'. */
    public readonly string $contextlevel;

    /** @var int cmid when contextlevel is 'activity', sectionid when 'section'. */
    public readonly int $itemid;

    /** @var string One of 'image', 'emoji' or 'icon'. */
    public readonly string $type;

    /** @var string Fileid, single emoji, or library icon name, depending on type. */
    public readonly string $value;

    /** @var string|null #RRGGBB background colour of the card circle, or null for the default. */
    public readonly ?string $bgcolor;

    /** @var string|null #RRGGBB title colour from the curated palette, or null for the default. */
    public readonly ?string $labelcolor;

    /** @var string|null Slug of the curated bundled font, or null for the system font. */
    public readonly ?string $labelfont;

    /**
     * @var string|null #RRGGBB icon glyph colour, or null for the default. Only
     *                  meaningful when type is TYPE_ICON.
     */
    public readonly ?string $iconcolor;

    /**
     * Constructor.
     *
     * @param int $id Record id.
     * @param string $contextlevel One of 'activity' or 'section'.
     * @param int $itemid cmid or sectionid.
     * @param string $type One of 'image', 'emoji' or 'icon'.
     * @param string $value Fileid, single emoji, or library icon name.
     * @param string|null $bgcolor #RRGGBB background colour, or null.
     * @param string|null $labelcolor #RRGGBB title colour, or null.
     * @param string|null $labelfont Curated font slug, or null.
     * @param string|null $iconcolor #RRGGBB icon glyph colour, or null.
     */
    public function __construct(
        int $id,
        string $contextlevel,
        int $itemid,
        string $type,
        string $value,
        ?string $bgcolor,
        ?string $labelcolor,
        ?string $labelfont,
        ?string $iconcolor,
    ) {
        $this->id           = $id;
        $this->contextlevel = $contextlevel;
        $this->itemid       = $itemid;
        $this->type         = $type;
        $this->value        = $value;
        $this->bgcolor      = $bgcolor;
        $this->labelcolor   = $labelcolor;
        $this->labelfont    = $labelfont;
        $this->iconcolor    = $iconcolor;
    }

    /**
     * Builds an appearance value object from a format_smartcards_appearance DB record.
     *
     * @param stdClass $record Raw database record.
     * @return self
     */
    public static function from_record(stdClass $record): self {
        return new self(
            id: (int)$record->id,
            contextlevel: $record->contextlevel,
            itemid: (int)$record->itemid,
            type: $record->type,
            value: $record->value,
            bgcolor: $record->bgcolor,
            labelcolor: $record->labelcolor,
            labelfont: $record->labelfont,
            iconcolor: $record->iconcolor,
        );
    }
}
