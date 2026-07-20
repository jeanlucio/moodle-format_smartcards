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

use Closure;
use renderer_base;

/**
 * Derives the inline style declarations and icon selection for one card's icon circle
 * and title, from its custom appearance (if any) and the course's default title
 * colour/font (if the item does not set its own).
 *
 * Shared by {@see card_builder} (activities) and {@see section_card_builder} (sections)
 * so the two never drift into two slightly different renderings of the same
 * icon/emoji/image/colour rules — only the uploaded-image URL differs between them
 * (module context vs course context), which is why that one piece is a caller-supplied
 * resolver instead of being looked up here.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class appearance_style_resolver {
    /**
     * Resolves the icon/emoji/image and inline style declarations for one card.
     *
     * @param appearance|null $item The item's custom appearance, or null.
     * @param renderer_base $output Renderer used to resolve the curated icon's URL.
     * @param array $formatoptions The course's resolved format options, for the
     *                              defaultbgcolor/defaultlabelcolor/defaultlabelfont/defaulticoncolor fallback
     *                              (or the defaultsectionlabelcolor/defaultsectionlabelfont pair instead, when
     *                              $sectiondefaults is true).
     * @param Closure $imageurl Resolves the uploaded card image URL from its fileid
     *                          (appearance::$value cast to int), only called when
     *                          $item->type is TYPE_IMAGE. Lets each caller point at its
     *                          own appearance_image_store method (module vs course
     *                          context) without this class knowing which.
     * @param bool $sectiondefaults Whether the title style falls back to the course's
     *                               section-scoped defaults instead of its activity ones
     *                               — true only for the section card itself (see
     *                               {@see section_card_builder}), so a course can give
     *                               sections a different accent than activities.
     * @return array{0: bool, 1: string, 2: bool, 3: string, 4: string, 5: string, 6: bool, 7: string}
     *         isemoji, emoji, iscustomicon, customiconurl, iconstyle, titlestyle, isbsicon, iconcolorstyle.
     */
    public static function resolve(
        ?appearance $item,
        renderer_base $output,
        array $formatoptions,
        Closure $imageurl,
        bool $sectiondefaults = false
    ): array {
        $isemoji = $item !== null && $item->type === appearance_repository::TYPE_EMOJI;
        $emoji   = $isemoji ? $item->value : '';

        // The icon glyph's colour (via CSS mask, see cm_icon.mustache) can only ever
        // tint a bundled monochrome bsicon — never an uploaded photo or the default
        // per-module-type icon, both of which carry their own real colours.
        $isbsicon = $item !== null && $item->type === appearance_repository::TYPE_ICON;

        $iscustomicon = $item !== null
            && in_array($item->type, [appearance_repository::TYPE_ICON, appearance_repository::TYPE_IMAGE], true);
        $customiconurl = match ($item?->type) {
            appearance_repository::TYPE_ICON => $output->image_url('bsicons/' . $item->value, 'format_smartcards')->out(false),
            appearance_repository::TYPE_IMAGE => $imageurl((int)$item->value),
            default => '',
        };

        $defaultbgcolor = (string)($formatoptions['defaultbgcolor'] ?? '');
        $bgcolor        = $item?->bgcolor ?? ($defaultbgcolor !== '' ? $defaultbgcolor : null);
        $iconstyle      = $bgcolor !== null ? 'background-color: ' . $bgcolor : '';

        $titlestyle = $sectiondefaults
            ? self::resolve_section_titlestyle($item, $formatoptions)
            : self::resolve_titlestyle($item, $formatoptions);

        $defaulticoncolor = (string)($formatoptions['defaulticoncolor'] ?? '');
        $iconcolor      = $item?->iconcolor ?? ($defaulticoncolor !== '' ? $defaulticoncolor : null);
        $iconcolorstyle = ($isbsicon && $iconcolor !== null) ? 'background-color: ' . $iconcolor : '';

        return [$isemoji, $emoji, $iscustomicon, $customiconurl, $iconstyle, $titlestyle, $isbsicon, $iconcolorstyle];
    }

    /**
     * Resolves the inline `color`/`font-family` style declaration for one activity's
     * title, from its custom appearance (if any) and the course's default activity
     * title colour/font.
     *
     * Split out from {@see resolve()} so a caller that only needs the title style — the
     * content page's own section heading, which has no icon of its own to resolve —
     * does not need to supply a renderer or an image-url resolver it would never use.
     *
     * @param appearance|null $item The item's custom appearance, or null.
     * @param array $formatoptions The course's resolved format options, for the
     *                              defaultlabelcolor/defaultlabelfont fallback.
     * @return string Semicolon-separated inline style declaration, or '' when the item
     *                 and the course both leave title colour/font at their default.
     */
    public static function resolve_titlestyle(?appearance $item, array $formatoptions): string {
        return self::build_titlestyle(
            $item,
            (string)($formatoptions['defaultlabelcolor'] ?? ''),
            (string)($formatoptions['defaultlabelfont'] ?? '')
        );
    }

    /**
     * Resolves the inline `color`/`font-family` style declaration for one section's
     * title, from its custom appearance (if any) and the course's default section
     * title colour/font — a separate pair from {@see resolve_titlestyle()}'s activity
     * defaults, so a course can give sections a different accent than activities.
     *
     * @param appearance|null $item The section's custom appearance, or null.
     * @param array $formatoptions The course's resolved format options, for the
     *                              defaultsectionlabelcolor/defaultsectionlabelfont fallback.
     * @return string Semicolon-separated inline style declaration, or '' when the section
     *                 and the course both leave title colour/font at their default.
     */
    public static function resolve_section_titlestyle(?appearance $item, array $formatoptions): string {
        return self::build_titlestyle(
            $item,
            (string)($formatoptions['defaultsectionlabelcolor'] ?? ''),
            (string)($formatoptions['defaultsectionlabelfont'] ?? '')
        );
    }

    /**
     * Builds the inline `color`/`font-family` style declaration shared by
     * {@see resolve_titlestyle()} and {@see resolve_section_titlestyle()} — the two
     * only differ in which pair of course-default format options they fall back to.
     *
     * @param appearance|null $item The item's custom appearance, or null.
     * @param string $defaultlabelcolor The course's default title colour for this
     *                                   item's kind (activity or section), or ''.
     * @param string $defaultlabelfont The course's default title font slug for this
     *                                  item's kind (activity or section), or ''.
     * @return string Semicolon-separated inline style declaration, or '' when both the
     *                 item and the supplied defaults leave title colour/font unset.
     */
    private static function build_titlestyle(?appearance $item, string $defaultlabelcolor, string $defaultlabelfont): string {
        $labelcolor = $item?->labelcolor ?? ($defaultlabelcolor !== '' ? $defaultlabelcolor : null);
        $labelfont  = $item?->labelfont ?? ($defaultlabelfont !== '' ? $defaultlabelfont : null);

        $titlestyleparts = [];
        if ($labelcolor !== null) {
            $titlestyleparts[] = 'color: ' . $labelcolor;
        }
        if ($labelfont !== null && array_key_exists($labelfont, appearance_palette::LABEL_FONTS)) {
            $titlestyleparts[] = "font-family: '" . appearance_palette::LABEL_FONTS[$labelfont] . "', sans-serif";
        }
        return implode('; ', $titlestyleparts);
    }
}
