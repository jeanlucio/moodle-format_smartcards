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
use format_smartcards\local\appearance;
use format_smartcards\local\appearance_repository;
use format_smartcards\local\card_builder;
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
        global $PAGE;

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

        $isaccordion = ($formatoptions['navstyle'] ?? 'default') === 'accordion';
        $completioninfo = null;
        if ($isaccordion) {
            // Loading this module is what activates Bootstrap's [data-bs-toggle="collapse"]
            // click handling for the whole page (a side effect of its own import chain) —
            // no bespoke AMD module needed for the accordion toggle itself.
            $PAGE->requires->js_call_amd('core/local/collapsable_section/controls', 'init');
            $completioninfo = new completion_info($course);
        }

        $sectionsdata = [];
        $progressbyindex = [];
        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            if (!$sectioninfo->uservisible && $sectioninfo->section > 0) {
                continue;
            }

            $cards = $this->build_cards_data($modinfo, $sectioninfo, $course, $output, $appearances, $formatoptions);
            if (empty($cards) && $sectioninfo->section > 0 && (string)$sectioninfo->summary === '') {
                continue;
            }

            $iscollapsible = $isaccordion && $sectioninfo->section > 0;
            $progress = $iscollapsible ? section_progress_resolver::resolve($completioninfo, $modinfo, $sectioninfo) : null;

            $sectionsdata[] = [
                'id'            => $sectioninfo->id,
                'num'           => $sectioninfo->section,
                'name'          => $format->get_section_name($sectioninfo),
                'issection0'    => ($sectioninfo->section === 0),
                'cards'         => $cards,
                'hascards'      => !empty($cards),
                'iscollapsible' => $iscollapsible,
                'isopen'        => false,
                'hasprogress'   => $progress !== null && $progress->has_tracking(),
                'progresslabel' => ($progress !== null && $progress->has_tracking())
                    ? get_string('progresstotal', 'completion', (object)[
                        'complete' => $progress->complete,
                        'total' => $progress->total,
                    ])
                    : '',
            ];

            if ($iscollapsible) {
                $progressbyindex[array_key_last($sectionsdata)] = $progress;
            }
        }

        if ($isaccordion) {
            $openindex = $this->find_default_open_section_index($progressbyindex);
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
     * Picks which collapsible section the accordion opens by default: the first one
     * (in course order) with at least one completion-tracked activity the user has not
     * finished yet, so a returning student lands where they left off. Falls back to the
     * first collapsible section when nothing is pending (completion disabled entirely,
     * or everything already complete) rather than leaving the whole accordion collapsed.
     *
     * @param section_progress[] $progressbyindex Progress keyed by the section's index
     *                                             in $sectionsdata, in course order.
     * @return int|null Index into $sectionsdata to mark open, or null when there are no
     *                   collapsible sections at all.
     */
    private function find_default_open_section_index(array $progressbyindex): ?int {
        $firstindex = null;
        foreach ($progressbyindex as $index => $progress) {
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
     * @return array<int, array<string, mixed>> Card data, one entry per visible module.
     */
    private function build_cards_data(
        \course_modinfo $modinfo,
        section_info $sectioninfo,
        stdClass $course,
        renderer_base $output,
        array $appearances,
        array $formatoptions
    ): array {
        $cards = [];

        foreach ($this->get_section_cms($modinfo, $sectioninfo) as $cm) {
            $card = card_builder::build($cm, $course, $output, $appearances[$cm->id] ?? null, $formatoptions);
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
