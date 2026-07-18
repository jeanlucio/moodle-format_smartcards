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

namespace format_smartcards\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use format_smartcards\local\appearance_image_store;
use format_smartcards\local\appearance_palette;
use format_smartcards\local\appearance_repository;
use moodle_exception;
use section_info;

/**
 * Returns the current appearance of one section, so the editor modal can pre-fill its
 * form instead of always opening blank — the section-level counterpart of
 * {@see get_appearance}, sharing its editor template and icon-list shape. The only
 * meaningful difference is 'iconurl': an activity has a default per-module-type icon to
 * fall back to, a section does not, so this returns the generic section icon instead
 * (see section_card_builder::build()'s own use of the same icon).
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_section_appearance extends external_api {
    /**
     * Parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
        ]);
    }

    /**
     * Returns the current appearance of one section, plus editor bootstrap data.
     *
     * @param int $sectionid Section id.
     * @return array<string, mixed>
     */
    public static function execute(int $sectionid): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['sectionid' => $sectionid]);

        $sectioninfo = self::get_section_or_fail($params['sectionid']);
        $course      = get_course($sectioninfo->course);
        $context     = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('format/smartcards:manageappearance', $context);

        $item     = (new appearance_repository())->get_for_section($params['sectionid']);
        $renderer = $PAGE->get_renderer('format_smartcards');

        $icons = [];
        foreach (appearance_palette::ICONS as $slug) {
            $icons[] = [
                'slug' => $slug,
                'url' => $renderer->image_url('bsicons/' . $slug, 'format_smartcards')->out(false),
            ];
        }

        $hasimage = $item?->type === appearance_repository::TYPE_IMAGE && $item->value !== '';

        return [
            'sectionid'  => $params['sectionid'],
            'type'       => $item?->type ?? appearance_repository::TYPE_DEFAULT,
            'value'      => $item?->value ?? '',
            'bgcolor'    => $item?->bgcolor ?? '',
            'labelcolor' => $item?->labelcolor ?? '',
            'labelfont'  => $item?->labelfont ?? '',
            'iconcolor'  => $item?->iconcolor ?? '',
            'iconurl'    => $renderer->image_url('i/section')->out(false),
            'imageurl'   => $hasimage
                ? appearance_image_store::url_for_section($params['sectionid'], $course->id, (int)$item->value)->out(false)
                : '',
            'icons'      => $icons,
        ];
    }

    /**
     * Resolves a sectionid to its section_info, or throws when it does not exist.
     *
     * @param int $sectionid Section id.
     * @return section_info
     * @throws moodle_exception If no section with this id exists.
     */
    public static function get_section_or_fail(int $sectionid): section_info {
        global $DB;

        $section = $DB->get_record('course_sections', ['id' => $sectionid], 'course', MUST_EXIST);
        $modinfo = get_fast_modinfo((int)$section->course);

        return $modinfo->get_section_info_by_id($sectionid, MUST_EXIST);
    }

    /**
     * Return structure for execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sectionid'  => new external_value(PARAM_INT, 'Section id'),
            'type'       => new external_value(PARAM_ALPHA, "'default', 'emoji', 'icon' or 'image'"),
            'value'      => new external_value(PARAM_RAW, 'Emoji character or icon name, or empty'),
            'bgcolor'    => new external_value(PARAM_RAW, 'Circle background #RRGGBB, or empty'),
            'labelcolor' => new external_value(PARAM_RAW, 'Title colour #RRGGBB, or empty'),
            'labelfont'  => new external_value(PARAM_RAW, 'Curated font slug, or empty'),
            'iconcolor'  => new external_value(PARAM_RAW, 'Icon glyph #RRGGBB (type=icon only), or empty'),
            'iconurl'    => new external_value(PARAM_RAW, 'Generic section icon URL'),
            'imageurl'   => new external_value(PARAM_RAW, "Uploaded card image URL (type='image' only), or empty"),
            'icons'      => new external_multiple_structure(
                new external_single_structure([
                    'slug' => new external_value(PARAM_RAW, 'Icon slug'),
                    'url'  => new external_value(PARAM_RAW, 'Resolved icon URL'),
                ]),
                'Curated icon list with resolved URLs'
            ),
        ]);
    }
}
