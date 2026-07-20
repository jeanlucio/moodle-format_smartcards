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
     * @param array $formatoptions The course's resolved format options
     *                              (course_format::get_format_options()), used for the
     *                              defaultbgcolor/defaultlabelcolor/defaultlabelfont/
     *                              defaulticoncolor fallback below the activity's own appearance. Pass [] to skip the
     *                              course-level fallback (falls straight through to the
     *                              system default).
     * @param int $userid User id to resolve completion state for. 0 (not logged in) or a
     *                     guest always resolves to no completion tracking.
     * @param string $description Rendered "Display description on course page" HTML for
     *                             this activity (see cm_description_resolver), or '' when
     *                             not applicable. Resolving this is the caller's
     *                             responsibility so a whole-grid render can bulk-load it
     *                             once instead of once per card.
     * @return array<string, mixed>|null Template context, or null when not visible at all.
     */
    public static function build(
        cm_info $cm,
        stdClass $course,
        renderer_base $output,
        ?appearance $item,
        array $formatoptions,
        int $userid,
        string $description
    ): ?array {
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

        [$isemoji, $emoji, $iscustomicon, $customiconurl, $iconstyle, $titlestyle, $isbsicon, $iconcolorstyle]
            = appearance_style_resolver::resolve(
                $item,
                $output,
                $formatoptions,
                fn (int $fileid): string => appearance_image_store::url($cm->id, $fileid)->out(false)
            );

        $completion  = cm_completion_resolver::resolve($cm, $userid);
        $ispending   = $completion->is_tracked() && !$completion->iscomplete;
        $iscomplete  = $completion->is_tracked() && $completion->iscomplete;
        $hasdescription = ($description !== '');

        // Mirrors the visibility check core itself applies to its own manual completion
        // checkbox — showing a toggle button that would just 403 on submit is worse than
        // not showing one at all.
        $cantoggle = ($completion->tracking === cm_completion::TRACKING_MANUAL)
            && has_capability('moodle/course:togglecompletion', $cm->context, $userid);

        // A custom-cmlist-item activity (e.g. Label) has its own content to show and no
        // view link of its own (see mod_label::label_cm_info_view()) — by default it
        // renders inline, full-width, directly in the flow, matching how core itself
        // shows it, instead of behind a tap. A teacher can opt one back into a normal
        // clickable tile via the "Card appearance" editor (appearance::$displaymode).
        $isinline = $cm->has_custom_cmlist_item()
            && $item?->displaymode !== appearance_repository::DISPLAYMODE_TILE;

        // The sheet only ever needs to interrupt the tap when it has something the
        // badge alone cannot show: an availability reason/date, a pending completion
        // (either an explanation of the automatic criteria, or the manual toggle
        // button), or a description the teacher opted into showing. A plain "complete"
        // state needs no explanation — the badge alone already says it all — so it
        // never opens the sheet on its own (see SCOPE.md §4 for the full reasoning).
        // An inline card never opens the sheet at all — everything it would have shown
        // renders directly inline instead (see the sc-card-inline-* fields below).
        $opensheet = !$isinline && (($status->badge !== null) || $ispending || $hasdescription);

        $completionbadgelabel = match (true) {
            $ispending  => get_string('status_completion_pending', 'format_smartcards'),
            $iscomplete => get_string('status_completion_complete', 'format_smartcards'),
            default => '',
        };

        $statuslabelparts = array_filter([$badgelabel, $completionbadgelabel]);

        return [
            'cmid'                 => $cm->id,
            'name'                 => format_string($cm->name, true, ['context' => $cm->context]),
            'iconurl'              => $output->image_url('icon', $cm->modname)->out(false),
            'modtypelabel'         => get_string('modulename', $cm->modname),
            'url'                  => $hasurl ? $cm->url->out(false) : '',
            'hasurl'               => $hasurl,
            'badge'                => $status->badge,
            'badgelabel'           => $badgelabel,
            'islocked'             => ($status->badge === status_resolver::BADGE_LOCKED),
            'istimed'              => ($status->badge === status_resolver::BADGE_TIMED),
            'hasbadge'             => ($status->badge !== null),
            'reason'               => $reason,
            'hasreason'            => ($reason !== ''),
            'duedate'              => $duedate,
            'hasduedate'           => $hasduedate,
            'duedateformatted'     => $duedateformatted,
            'dimmed'               => $status->dimmed,
            'hiddenlabel'          => $status->dimmed ? get_string('hiddenactivity', 'format_smartcards') : '',
            'isemoji'              => $isemoji,
            'emoji'                => $emoji,
            'iscustomicon'         => $iscustomicon,
            'customiconurl'        => $customiconurl,
            'hasiconstyle'         => ($iconstyle !== ''),
            'iconstyle'            => $iconstyle,
            'isbsicon'             => $isbsicon,
            'hasiconcolorstyle'    => ($iconcolorstyle !== ''),
            'iconcolorstyle'       => $iconcolorstyle,
            'hastitlestyle'        => ($titlestyle !== ''),
            'titlestyle'           => $titlestyle,
            'opensheet'            => $opensheet,
            'statuslabel'          => implode(', ', $statuslabelparts),
            'hasstatuslabel'       => !empty($statuslabelparts),
            'hascompletionbadge'   => $completion->is_tracked(),
            'iscompletionpending'  => $ispending,
            'iscompletioncomplete' => $iscomplete,
            'completionbadgelabel' => $completionbadgelabel,
            'completiontype'       => $completion->tracking,
            'completioncriteria'   => json_encode($completion->criteria),
            'cantoggle'            => $cantoggle,
            'hasdescription'       => $hasdescription,
            'description'          => $description,
            'isinline'             => $isinline,
            'showinlinebadge'      => $isinline && ($status->badge !== null),
            'showinlinemanualcompletion' => $isinline
                && ($completion->tracking === cm_completion::TRACKING_MANUAL),
            'showinlineautomaticcriteria' => $isinline
                && ($completion->tracking === cm_completion::TRACKING_AUTOMATIC),
            'criteria'             => $completion->criteria,
            'markcompletelabel'    => get_string('completion_markdone', 'format_smartcards'),
            'markincompletelabel'  => get_string('completion_markundone', 'format_smartcards'),
        ];
    }
}
