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

namespace format_smartcards\output\courseformat;

use cm_info;
use completion_info;
use context_course;
use core_courseformat\output\local\content as content_base;
use format_smartcards\external\toggle_section;
use format_smartcards\local\appearance;
use format_smartcards\local\appearance_repository;
use format_smartcards\local\card_builder;
use format_smartcards\local\cm_description_resolver;
use format_smartcards\local\section_progress;
use format_smartcards\local\section_progress_resolver;
use renderer_base;
use section_info;
use stdClass;

/**
 * Main content output class for the SmartCards course format.
 *
 * Builds the grid data structure consumed by the content Mustache template.
 * Every course module becomes a card whose badge is derived only from
 * cm_info availability data (never recomputed).
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends content_base {
    /**
     * Returns the Mustache template used to render the grid.
     *
     * In edit mode the standard core template is used so Moodle's full
     * editing interface (add section, add activity, drag-drop) is available.
     *
     * @param renderer_base $renderer The renderer requesting the template name.
     * @return string Template name.
     */
    public function get_template_name(renderer_base $renderer): string {
        global $PAGE;
        if ($PAGE->user_is_editing()) {
            return parent::get_template_name($renderer);
        }
        return 'format_smartcards/local/content';
    }

    /**
     * Exports all data required by the grid Mustache template.
     *
     * In edit mode delegates entirely to the parent class so the standard
     * Moodle editing controls are rendered correctly.
     *
     * @param renderer_base $output The renderer calling this method.
     * @return stdClass|array Data context for the template.
     */
    public function export_for_template(renderer_base $output): stdClass|array {
        global $PAGE, $USER;

        if ($PAGE->user_is_editing()) {
            return parent::export_for_template($output);
        }

        $format  = $this->format;
        $course  = $format->get_course();
        $modinfo = $format->get_modinfo();
        $context = context_course::instance($course->id);

        $PAGE->requires->js_call_amd('format_smartcards/mutations', 'init');
        $PAGE->requires->js_call_amd('format_smartcards/status_sheet', 'init');

        $canedit = has_capability('moodle/course:manageactivities', $context);

        $formatoptions = $format->get_format_options();
        $appearances   = (new appearance_repository())->get_many_for_activities(array_keys($modinfo->get_cms()));
        $descriptions  = cm_description_resolver::resolve_many($modinfo->get_cms());
        $userid        = (int)$USER->id;

        $isaccordion = ($formatoptions['navstyle'] ?? 'default') === 'accordion';
        $completioninfo = null;
        $collapsedids = [];
        $expandedids = [];
        if ($isaccordion) {
            // Loads theme_boost/bootstrap/collapse (as a side effect of its own import
            // chain) to activate Bootstrap's [data-bs-toggle="collapse"] click handling,
            // and persists each manual toggle via format_smartcards_toggle_section, so a
            // student's choice survives reloads.
            $PAGE->requires->js_call_amd('format_smartcards/accordion', 'init');
            $completioninfo = new completion_info($course);
            $preferences    = $format->get_sections_preferences_by_preference();
            // Cast to int: section_info::$id comes back as a string from the DB, but
            // in_array() below is intentionally strict, so an uncast list here would
            // never match any section id (the exact bug a real course hit).
            $collapsedids   = array_map('intval', $preferences[toggle_section::PREFERENCE_COLLAPSED] ?? []);
            $expandedids    = array_map('intval', $preferences[toggle_section::PREFERENCE_EXPANDED] ?? []);
        }

        $sectionsdata = [];
        $untouchedbyindex = [];
        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            if (!$sectioninfo->uservisible && $sectioninfo->section > 0) {
                continue;
            }

            $cards = $this->build_cards_data(
                $modinfo,
                $sectioninfo,
                $course,
                $output,
                $appearances,
                $formatoptions,
                $userid,
                $descriptions
            );
            if (empty($cards) && $sectioninfo->section > 0 && (string)$sectioninfo->summary === '') {
                continue;
            }

            $iscollapsible = $isaccordion && $sectioninfo->section > 0;
            $progress = $iscollapsible ? section_progress_resolver::resolve($completioninfo, $modinfo, $sectioninfo) : null;

            // A section is either explicitly collapsed, explicitly expanded, or genuinely
            // untouched (null) — tracking both directions (see toggle_section's docblock)
            // means an explicit expand of a section the accordion had closed by its own
            // default is never confused with a section the student never touched.
            if (in_array((int)$sectioninfo->id, $collapsedids, true)) {
                $explicitopen = false;
            } else if (in_array((int)$sectioninfo->id, $expandedids, true)) {
                $explicitopen = true;
            } else {
                $explicitopen = null;
            }

            $sectionsdata[] = [
                'id'            => $sectioninfo->id,
                'num'           => $sectioninfo->section,
                'name'          => $format->get_section_name($sectioninfo),
                'issection0'    => ($sectioninfo->section === 0),
                'cards'         => $cards,
                'hascards'      => !empty($cards),
                'iscollapsible' => $iscollapsible,
                'isopen'        => $iscollapsible && ($explicitopen ?? false),
                'hasprogress'   => $progress !== null && $progress->has_tracking(),
                'progresslabel' => ($progress !== null && $progress->has_tracking())
                    ? get_string('progresstotal', 'completion', (object)[
                        'complete' => $progress->complete,
                        'total' => $progress->total,
                    ])
                    : '',
            ];

            if ($iscollapsible && $explicitopen === null) {
                $untouchedbyindex[array_key_last($sectionsdata)] = $progress;
            }
        }

        if ($isaccordion) {
            // Among the sections the student has never touched, let the pending
            // activity pick the one that opens by default — a section already
            // explicitly collapsed or expanded above always keeps that choice.
            $openindex = $this->find_default_open_section_index($untouchedbyindex);
            if ($openindex !== null) {
                $sectionsdata[$openindex]['isopen'] = true;
            }
        }

        $cardsize = $formatoptions['cardsize'] ?? 'small';
        if (!in_array($cardsize, ['small', 'medium', 'large'], true)) {
            $cardsize = 'small';
        }

        return (object)[
            'uniqid'         => 'sc' . uniqid(),
            'sections'       => $sectionsdata,
            'hassections'    => !empty($sectionsdata),
            'canedit'        => $canedit,
            'editmodeactive' => $PAGE->user_is_editing(),
            'cardsizeclass'  => 'sc-size-' . $cardsize,
            'noframeclass'   => empty($formatoptions['showcardframe']) ? 'sc-noframe' : '',
            'isaccordion'    => $isaccordion,
        ];
    }

    /**
     * Picks which never-manually-toggled section the accordion opens by default: the
     * first one (in course order) with at least one completion-tracked activity the user
     * has not finished yet, so a returning student lands where they left off. Falls back
     * to the first untouched section when nothing is pending (completion disabled
     * entirely, or everything already complete) rather than leaving it collapsed.
     *
     * @param section_progress[] $untouchedbyindex Progress of sections with no explicit
     *                                              collapse/expand preference yet, keyed
     *                                              by the section's index in
     *                                              $sectionsdata, in course order.
     * @return int|null Index into $sectionsdata to mark open, or null when every
     *                   collapsible section already has an explicit preference.
     */
    private function find_default_open_section_index(array $untouchedbyindex): ?int {
        $firstindex = null;
        foreach ($untouchedbyindex as $index => $progress) {
            $firstindex ??= $index;
            if ($progress->has_pending()) {
                return $index;
            }
        }
        return $firstindex;
    }

    /**
     * Builds the card data for every visible module in one section.
     *
     * @param \course_modinfo $modinfo Course module info.
     * @param section_info $sectioninfo Section to render.
     * @param stdClass $course Course record.
     * @param renderer_base $output Renderer used to resolve module icon URLs.
     * @param appearance[] $appearances Custom appearance keyed by cmid, from get_many_for_activities().
     * @param array $formatoptions The course's resolved format options.
     * @param int $userid User id to resolve completion state for.
     * @param array<int, string> $descriptions Rendered description HTML keyed by cmid,
     *                                          from cm_description_resolver::resolve_many().
     * @return array<int, array<string, mixed>> Card data, one entry per visible module.
     */
    private function build_cards_data(
        \course_modinfo $modinfo,
        section_info $sectioninfo,
        stdClass $course,
        renderer_base $output,
        array $appearances,
        array $formatoptions,
        int $userid,
        array $descriptions
    ): array {
        $cards = [];

        foreach ($this->get_section_cms($modinfo, $sectioninfo) as $cm) {
            $card = card_builder::build(
                $cm,
                $course,
                $output,
                $appearances[$cm->id] ?? null,
                $formatoptions,
                $userid,
                $descriptions[$cm->id] ?? ''
            );
            if ($card !== null) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * Returns the modules (cm_info objects) belonging to a section in sequence order.
     *
     * @param \course_modinfo $modinfo Course module info.
     * @param section_info $sectioninfo Section info object.
     * @return cm_info[] Ordered array of course modules.
     */
    private function get_section_cms(\course_modinfo $modinfo, section_info $sectioninfo): array {
        $sequence = $sectioninfo->sequence ?? '';
        if (empty($sequence)) {
            return [];
        }

        $cmids  = explode(',', $sequence);
        $result = [];
        foreach ($cmids as $cmid) {
            $cmid = (int)$cmid;
            if ($cmid > 0 && isset($modinfo->cms[$cmid])) {
                $result[] = $modinfo->cms[$cmid];
            }
        }
        return $result;
    }
}
