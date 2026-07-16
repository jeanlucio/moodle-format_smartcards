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
use core_availability\info;
use renderer_base;
use stdClass;

/**
 * Builds the format_smartcards/local/cm_button template context for one course module.
 *
 * Shared by the grid ({@see \format_smartcards\output\courseformat\content}) and the
 * appearance-save external function ({@see \format_smartcards\external\save_appearance}),
 * so both always render a card the exact same way — a card returned by the web service
 * after saving is never allowed to visually drift from what a full page load would have
 * produced for the same data.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class card_builder {
    /**
     * Builds the cm_button template context for one course module, or null when the
     * module must not be rendered at all (fully hidden from this user).
     *
     * @param cm_info $cm Course module to render.
     * @param stdClass $course Course record the module belongs to.
     * @param renderer_base $output Renderer used to resolve the default module icon URL.
     * @param appearance|null $item The module's custom appearance, or null for the default.
     * @return array<string, mixed>|null Template context, or null when not visible at all.
     */
    public static function build(cm_info $cm, stdClass $course, renderer_base $output, ?appearance $item): ?array {
        $status = status_resolver::resolve($cm);
        if (!$status->isvisible) {
            return null;
        }

        $reason = $status->reason !== ''
            ? info::format_info($status->reason, $course)
            : '';

        $duedate          = $status->duedate;
        $hasduedate       = $duedate > 0;
        $duedateformatted = $hasduedate
            ? userdate($duedate, get_string('strftimedatefullshort', 'langconfig'))
            : '';

        $hasurl = !empty($cm->url) && $status->canaccess;

        $badgelabel = match ($status->badge) {
            status_resolver::BADGE_LOCKED => get_string('status_locked', 'format_smartcards'),
            status_resolver::BADGE_TIMED  => get_string('status_timed', 'format_smartcards'),
            default => '',
        };

        [$isemoji, $emoji, $iscustomicon, $customiconurl, $iconstyle, $titlestyle]
            = self::build_appearance_styles($item, $output);

        return [
            'cmid'             => $cm->id,
            'name'             => format_string($cm->name, true, ['context' => $cm->context]),
            'iconurl'          => $output->image_url('icon', $cm->modname)->out(false),
            'modtypelabel'     => get_string('modulename', $cm->modname),
            'url'              => $hasurl ? $cm->url->out(false) : '',
            'hasurl'           => $hasurl,
            'badge'            => $status->badge,
            'badgelabel'       => $badgelabel,
            'islocked'         => ($status->badge === status_resolver::BADGE_LOCKED),
            'istimed'          => ($status->badge === status_resolver::BADGE_TIMED),
            'hasbadge'         => ($status->badge !== null),
            'reason'           => $reason,
            'hasreason'        => ($reason !== ''),
            'duedate'          => $duedate,
            'hasduedate'       => $hasduedate,
            'duedateformatted' => $duedateformatted,
            'dimmed'           => $status->dimmed,
            'hiddenlabel'      => $status->dimmed ? get_string('hiddenactivity', 'format_smartcards') : '',
            'isemoji'          => $isemoji,
            'emoji'            => $emoji,
            'iscustomicon'     => $iscustomicon,
            'customiconurl'    => $customiconurl,
            'hasiconstyle'     => ($iconstyle !== ''),
            'iconstyle'        => $iconstyle,
            'hastitlestyle'    => ($titlestyle !== ''),
            'titlestyle'       => $titlestyle,
        ];
    }

    /**
     * Derives the inline style declarations and icon selection for one card's icon
     * circle and title, from its custom appearance, if any.
     *
     * The image appearance type is not rendered yet (uploaded images need the File API
     * wiring, not yet built), so only emoji, library icon, and the two colour/font
     * fields are wired up so far.
     *
     * @param appearance|null $item The activity's custom appearance, or null.
     * @param renderer_base $output Renderer used to resolve the custom icon's URL.
     * @return array{0: bool, 1: string, 2: bool, 3: string, 4: string, 5: string}
     *         isemoji, emoji, iscustomicon, customiconurl, iconstyle, titlestyle.
     */
    private static function build_appearance_styles(?appearance $item, renderer_base $output): array {
        if ($item === null) {
            return [false, '', false, '', '', ''];
        }

        $isemoji = ($item->type === appearance_repository::TYPE_EMOJI);
        $emoji   = $isemoji ? $item->value : '';

        $iscustomicon = ($item->type === appearance_repository::TYPE_ICON);
        $customiconurl = $iscustomicon
            ? $output->image_url('bsicons/' . $item->value, 'format_smartcards')->out(false)
            : '';

        $iconstyle = $item->bgcolor !== null ? 'background-color: ' . $item->bgcolor : '';

        $titlestyleparts = [];
        if ($item->labelcolor !== null) {
            $titlestyleparts[] = 'color: ' . $item->labelcolor;
        }
        if ($item->labelfont !== null && array_key_exists($item->labelfont, appearance_palette::LABEL_FONTS)) {
            $titlestyleparts[] = "font-family: '" . appearance_palette::LABEL_FONTS[$item->labelfont] . "', sans-serif";
        }
        $titlestyle = implode('; ', $titlestyleparts);

        return [$isemoji, $emoji, $iscustomicon, $customiconurl, $iconstyle, $titlestyle];
    }
}
