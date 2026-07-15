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
use context_course;
use core_availability\info;
use core_courseformat\output\local\content as content_base;
use format_smartcards\local\appearance;
use format_smartcards\local\appearance_palette;
use format_smartcards\local\appearance_repository;
use format_smartcards\local\status_resolver;
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

        $appearances = (new appearance_repository())->get_many_for_activities(array_keys($modinfo->get_cms()));

        $sectionsdata = [];
        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            if (!$sectioninfo->uservisible && $sectioninfo->section > 0) {
                continue;
            }

            $cards = $this->build_cards_data($modinfo, $sectioninfo, $course, $output, $appearances);
            if (empty($cards) && $sectioninfo->section > 0 && (string)$sectioninfo->summary === '') {
                continue;
            }

            $sectionsdata[] = [
                'id'          => $sectioninfo->id,
                'num'         => $sectioninfo->section,
                'name'        => $format->get_section_name($sectioninfo),
                'issection0'  => ($sectioninfo->section === 0),
                'cards'       => $cards,
                'hascards'    => !empty($cards),
            ];
        }

        return (object)[
            'uniqid'         => 'sc' . uniqid(),
            'sections'       => $sectionsdata,
            'hassections'    => !empty($sectionsdata),
            'canedit'        => $canedit,
            'editmodeactive' => $PAGE->user_is_editing(),
        ];
    }

    /**
     * Builds the card data for every visible module in one section.
     *
     * @param \course_modinfo $modinfo Course module info.
     * @param section_info $sectioninfo Section to render.
     * @param stdClass $course Course record.
     * @param renderer_base $output Renderer used to resolve module icon URLs.
     * @param appearance[] $appearances Custom appearance keyed by cmid, from get_many_for_activities().
     * @return array<int, array<string, mixed>> Card data, one entry per visible module.
     */
    private function build_cards_data(
        \course_modinfo $modinfo,
        section_info $sectioninfo,
        stdClass $course,
        renderer_base $output,
        array $appearances
    ): array {
        $cards = [];

        foreach ($this->get_section_cms($modinfo, $sectioninfo) as $cm) {
            $status = status_resolver::resolve($cm);
            if (!$status->isvisible) {
                continue;
            }

            $reason = $status->reason !== ''
                ? info::format_info($status->reason, $course)
                : '';

            $duedate          = $status->duedate;
            $hasduedate       = $duedate > 0;
            $duedateformatted = $hasduedate
                ? userdate($duedate, get_string('strftimedatefullshort', 'langconfig'))
                : '';

            $hasurl = !empty($cm->url) && $status->badge !== status_resolver::BADGE_LOCKED;

            $badgelabel = match ($status->badge) {
                status_resolver::BADGE_LOCKED => get_string('status_locked', 'format_smartcards'),
                status_resolver::BADGE_TIMED  => get_string('status_timed', 'format_smartcards'),
                default => '',
            };

            [$isemoji, $emoji, $iconstyle, $titlestyle] = $this->build_appearance_styles($appearances[$cm->id] ?? null);

            $cards[] = [
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
                'hasiconstyle'     => ($iconstyle !== ''),
                'iconstyle'        => $iconstyle,
                'hastitlestyle'    => ($titlestyle !== ''),
                'titlestyle'       => $titlestyle,
            ];
        }

        return $cards;
    }

    /**
     * Derives the inline style declarations for one card's icon circle and title from
     * its custom appearance, if any.
     *
     * Image and icon appearance types are not rendered yet (uploaded images need the
     * File API wiring added together with the appearance editor UI; icon names need the
     * bundled icon library), so only the emoji type and the two colour/font fields are
     * wired up so far.
     *
     * @param appearance|null $item The activity's custom appearance, or null.
     * @return array{0: bool, 1: string, 2: string, 3: string} isemoji, emoji, iconstyle, titlestyle.
     */
    private function build_appearance_styles(?appearance $item): array {
        if ($item === null) {
            return [false, '', '', ''];
        }

        $isemoji = ($item->type === appearance_repository::TYPE_EMOJI);
        $emoji   = $isemoji ? $item->value : '';

        $iconstyle = $item->bgcolor !== null ? 'background-color: ' . $item->bgcolor : '';

        $titlestyleparts = [];
        if ($item->labelcolor !== null) {
            $titlestyleparts[] = 'color: ' . $item->labelcolor;
        }
        if ($item->labelfont !== null && array_key_exists($item->labelfont, appearance_palette::LABEL_FONTS)) {
            $titlestyleparts[] = "font-family: '" . appearance_palette::LABEL_FONTS[$item->labelfont] . "', sans-serif";
        }
        $titlestyle = implode('; ', $titlestyleparts);

        return [$isemoji, $emoji, $iconstyle, $titlestyle];
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
