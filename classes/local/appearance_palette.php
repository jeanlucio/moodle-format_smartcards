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
 * Curated palettes for the title colour (labelcolor) and title font (labelfont).
 *
 * Both fields are restricted to a fixed list instead of a free colour picker or a text
 * field: every labelcolor entry already has a contrast ratio of at least 4.5:1 against
 * the card background (#fff, see styles.css --card-bg fallback) so no contrast maths is
 * ever needed at render time, and every labelfont entry is a font bundled with the
 * plugin (never loaded from a CDN). See SCOPE.md §4/§8/§13 and CLAUDE.md's badge
 * bg-secondary example for the same pre-approved-pair reasoning.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appearance_palette {
    /**
     * @var array<string, string> Curated labelcolor palette, slug => #RRGGBB.
     *      Every value's contrast ratio against #fff is >= 4.5:1 (WCAG AA for normal
     *      text), computed at design time; see the git history of this file for the
     *      calculation.
     */
    public const LABEL_COLORS = [
        'charcoal' => '#212529',
        'red'      => '#c62828',
        'orange'   => '#bf360c',
        'green'    => '#2e7d32',
        'teal'     => '#00695c',
        'blue'     => '#1565c0',
        'purple'   => '#6a1b9a',
        'pink'     => '#ad1457',
    ];

    /**
     * @var array<string, string> Curated labelfont palette, slug => display font family
     *      name. Backed by WOFF2 files bundled in fonts/ and declared in
     *      thirdpartylibs.xml; never loaded from an external CDN.
     */
    public const LABEL_FONTS = [
        'fredoka'     => 'Fredoka',
        'baloo2'      => 'Baloo 2',
        'varelaround' => 'Varela Round',
        'nunito'      => 'Nunito',
        'comicneue'   => 'Comic Neue',
    ];

    /**
     * @var string[] Curated "quick pick" icon slugs offered in the appearance picker.
     *      Each name is a real Bootstrap Icons slug with a matching SVG bundled in
     *      pix/bsicons/ (see readme_moodle.txt for the update procedure). This is the
     *      single source of truth for the list — the appearance editor's icon grid
     *      fetches it (with resolved URLs) from get_appearance rather than keeping its
     *      own copy in JavaScript.
     */
    public const ICONS = [
        'book', 'pencil', 'camera-video', 'mic', 'chat-dots', 'trophy', 'star', 'flag',
        'puzzle', 'gear', 'calendar-event', 'clipboard-check', 'lightbulb', 'map',
        'music-note', 'palette', 'rocket', 'bullseye', 'award', 'journal-text',
        'mortarboard', 'people',
    ];

    /**
     * Returns whether the given #RRGGBB value belongs to the curated labelcolor palette.
     *
     * labelcolor is stored as the hex value itself (not the slug), so validation checks
     * membership against the palette's values, not its keys.
     *
     * @param string $hex Colour value to validate.
     * @return bool
     */
    public static function is_valid_labelcolor(string $hex): bool {
        return in_array($hex, self::LABEL_COLORS, true);
    }

    /**
     * Returns whether the given value is a valid labelfont slug.
     *
     * @param string $slug Slug to validate.
     * @return bool
     */
    public static function is_valid_labelfont(string $slug): bool {
        return array_key_exists($slug, self::LABEL_FONTS);
    }

    /**
     * Returns whether the given value is a well-formed #RRGGBB colour.
     *
     * Used for bgcolor (the card circle background), which is not restricted to a
     * curated list — the circle only ever contains a decorative icon, never text, so
     * WCAG text-contrast validation does not apply to it.
     *
     * @param string $value Colour value to validate.
     * @return bool
     */
    public static function is_valid_hex_color(string $value): bool {
        return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $value);
    }
}
