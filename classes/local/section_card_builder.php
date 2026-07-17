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

use course_modinfo;
use renderer_base;
use section_info;

/**
 * Builds the format_smartcards/local/section_card_button template context for one
 * course section, for navstyle = 'sectioncards' (SCOPE.md §16 Fase 4).
 *
 * Mirrors {@see card_builder} for activities: the badge is derived only from
 * section_info::$available (never recomputed — the same principle status_resolver
 * uses for activities), and the icon/colour styling is resolved through the same
 * {@see appearance_style_resolver} an activity card uses, so the two visual languages
 * never drift apart.
 *
 * Progress and card-visibility inputs (hasprogress/progresslabel/completion state,
 * hascards) are accepted pre-resolved rather than recomputed here: the caller
 * (content.php::export_for_template()) already computes them once per section for the
 * flat-grid rendering shared by every other navstyle, and a second computation here
 * would risk drifting from that single source of truth. Only an ordered list of visible
 * cmids is needed here (not the fully-built cm_button contexts), to keep the icon
 * fallback chain (SCOPE.md §4) cheap for callers — such as save_section_appearance —
 * that have no reason to build full activity cards just to answer "does this section
 * have visible activities, and does the first one have a custom appearance?".
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class section_card_builder {
    /**
     * Builds the section_card_button template context for one course section.
     *
     * @param section_info $sectioninfo Section to render.
     * @param string $name Resolved section name (course_format::get_section_name()).
     * @param renderer_base $output Renderer used to resolve icon URLs.
     * @param appearance|null $item The section's own custom appearance, or null.
     * @param appearance[] $activityappearances Appearance of the section's
     *                                 activities, keyed by cmid (already bulk-loaded by
     *                                 the caller), used for the icon fallback chain
     *                                 (SCOPE.md §4) when the section has none of its own.
     * @param int[] $sectioncmids Ids of this section's visible activities, in course
     *                             order — the fallback-chain search order.
     * @param array $formatoptions The course's resolved format options, for the
     *                              defaultbgcolor/defaultlabelcolor/defaultlabelfont fallback.
     * @param bool $sectionavailable Whether the section itself is available to this user
     *                                (false only for a restricted-but-visible section —
     *                                a fully hidden one never reaches this method).
     * @param bool $hascards Whether the section has at least one visible activity —
     *                        determines whether the card opens a nested grid at all.
     * @param bool $hasprogress Whether a progress label should be shown, pre-resolved
     *                           by the caller from the progressdisplay format option.
     * @param string $progresslabel Pre-formatted progress label, or '' when $hasprogress
     *                                is false.
     * @param bool $iscompletionpending Whether the section has at least one
     *                                   completion-tracked activity still pending.
     * @param bool $iscompletioncomplete Whether the section is fully tracked and complete.
     * @return array<string, mixed>
     */
    public static function build(
        section_info $sectioninfo,
        string $name,
        renderer_base $output,
        ?appearance $item,
        array $activityappearances,
        array $sectioncmids,
        array $formatoptions,
        bool $sectionavailable,
        bool $hascards,
        bool $hasprogress,
        string $progresslabel,
        bool $iscompletionpending,
        bool $iscompletioncomplete
    ): array {
        [$fallbackitem, $fallbackcmid] = self::resolve_fallback_appearance($item, $activityappearances, $sectioncmids);

        $imageurl = $fallbackcmid !== null
            ? fn (int $fileid): string => appearance_image_store::url($fallbackcmid, $fileid)->out(false)
            : fn (int $fileid): string => appearance_image_store::url_for_section(
                $sectioninfo->id,
                $sectioninfo->course,
                $fileid
            )->out(false);

        [$isemoji, $emoji, $iscustomicon, $customiconurl, $iconstyle, $titlestyle]
            = appearance_style_resolver::resolve($fallbackitem, $output, $formatoptions, $imageurl);

        $islocked = !$sectionavailable;
        $badgelabel = $islocked ? get_string('status_locked', 'format_smartcards') : '';

        $completionbadgelabel = match (true) {
            $iscompletionpending  => get_string('status_completion_pending', 'format_smartcards'),
            $iscompletioncomplete => get_string('status_completion_complete', 'format_smartcards'),
            default => '',
        };

        return [
            'id'                    => $sectioninfo->id,
            'name'                  => $name,
            'islocked'              => $islocked,
            'hasbadge'              => $islocked,
            'badgelabel'            => $badgelabel,
            // Generic per-section icon (core's own pix/i/section.svg) — a section has no
            // "type" to derive a default icon from the way an activity's modname does,
            // so this is the fixed system fallback when neither the section nor any of
            // its activities has a custom appearance configured (SCOPE.md §4 chain).
            'iconurl'               => $output->image_url('i/section')->out(false),
            'isemoji'               => $isemoji,
            'emoji'                 => $emoji,
            'iscustomicon'          => $iscustomicon,
            'customiconurl'         => $customiconurl,
            'hasiconstyle'          => ($iconstyle !== ''),
            'iconstyle'             => $iconstyle,
            'hastitlestyle'         => ($titlestyle !== ''),
            'titlestyle'            => $titlestyle,
            'hasprogress'           => $hasprogress,
            'progresslabel'         => $progresslabel,
            'hascompletionbadge'    => ($iscompletionpending || $iscompletioncomplete),
            'iscompletionpending'   => $iscompletionpending,
            'iscompletioncomplete'  => $iscompletioncomplete,
            'completionbadgelabel'  => $completionbadgelabel,
            'hascards'              => $hascards,
        ];
    }

    /**
     * Returns the ids of one section's visible activities, in course order.
     *
     * A thin, cheap alternative to building full cm_button contexts (card_builder::
     * build() for every module) when the caller only needs to know which activities
     * exist, not how each one renders — used both by content.php (which already has
     * the full cards built for other reasons and could derive this from them, but
     * shares this helper for a single source of truth) and by save_section_appearance,
     * which has no other reason to touch the section's activities at all.
     *
     * @param course_modinfo $modinfo Course module info.
     * @param section_info $sectioninfo Section to inspect.
     * @return int[] Visible course module ids, in course order.
     */
    public static function get_visible_section_cmids(course_modinfo $modinfo, section_info $sectioninfo): array {
        $sequence = $sectioninfo->sequence ?? '';
        if ($sequence === '') {
            return [];
        }

        $cmids = [];
        foreach (explode(',', $sequence) as $cmid) {
            $cmid = (int)$cmid;
            if ($cmid <= 0 || !isset($modinfo->cms[$cmid])) {
                continue;
            }
            if ($modinfo->cms[$cmid]->is_visible_on_course_page()) {
                $cmids[] = $cmid;
            }
        }

        return $cmids;
    }

    /**
     * Resolves which appearance to render on the section card: the section's own, or —
     * when it has none — the first of its visible activities (in course order) that has
     * a custom appearance configured, or neither (SCOPE.md §4's fallback chain).
     *
     * @param appearance|null $item The section's own custom appearance, or null.
     * @param appearance[] $activityappearances Appearance of the section's
     *                                 activities, keyed by cmid.
     * @param int[] $sectioncmids Ids of this section's visible activities, in course order.
     * @return array{0: appearance|null, 1: int|null} The appearance to render (or null
     *         for the generic fallback icon) and the cmid it belongs to (null when the
     *         appearance is the section's own, needed to pick the right image URL).
     */
    private static function resolve_fallback_appearance(?appearance $item, array $activityappearances, array $sectioncmids): array {
        if ($item !== null) {
            return [$item, null];
        }

        foreach ($sectioncmids as $cmid) {
            if (isset($activityappearances[$cmid])) {
                return [$activityappearances[$cmid], $cmid];
            }
        }

        return [null, null];
    }
}
