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
     *                              defaultbgcolor/defaultlabelcolor/defaultlabelfont fallback.
     * @param Closure $imageurl Resolves the uploaded card image URL from its fileid
     *                          (appearance::$value cast to int), only called when
     *                          $item->type is TYPE_IMAGE. Lets each caller point at its
     *                          own appearance_image_store method (module vs course
     *                          context) without this class knowing which.
     * @return array{0: bool, 1: string, 2: bool, 3: string, 4: string, 5: string}
     *         isemoji, emoji, iscustomicon, customiconurl, iconstyle, titlestyle.
     */
    public static function resolve(
        ?appearance $item,
        renderer_base $output,
        array $formatoptions,
        Closure $imageurl
    ): array {
        $isemoji = $item !== null && $item->type === appearance_repository::TYPE_EMOJI;
        $emoji   = $isemoji ? $item->value : '';

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

        $defaultlabelcolor = (string)($formatoptions['defaultlabelcolor'] ?? '');
        $defaultlabelfont  = (string)($formatoptions['defaultlabelfont'] ?? '');
        $labelcolor        = $item?->labelcolor ?? ($defaultlabelcolor !== '' ? $defaultlabelcolor : null);
        $labelfont         = $item?->labelfont ?? ($defaultlabelfont !== '' ? $defaultlabelfont : null);

        $titlestyleparts = [];
        if ($labelcolor !== null) {
            $titlestyleparts[] = 'color: ' . $labelcolor;
        }
        if ($labelfont !== null && array_key_exists($labelfont, appearance_palette::LABEL_FONTS)) {
            $titlestyleparts[] = "font-family: '" . appearance_palette::LABEL_FONTS[$labelfont] . "', sans-serif";
        }
        $titlestyle = implode('; ', $titlestyleparts);

        return [$isemoji, $emoji, $iscustomicon, $customiconurl, $iconstyle, $titlestyle];
    }
}
