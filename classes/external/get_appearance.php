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

/**
 * Returns the current appearance of one activity, so the editor modal can pre-fill its
 * form instead of always opening blank, plus everything else needed to render the
 * editor and its live preview without further round trips: the default per-module-type
 * icon URL and the curated icon list with resolved URLs (bundled SVGs are plugin pix
 * files, so only server-side code can construct their URLs correctly — see
 * card_builder::build() for the same lookup used when actually rendering a card).
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_appearance extends external_api {
    /**
     * Parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Returns the current appearance of one activity, plus editor bootstrap data.
     *
     * @param int $cmid Course module id.
     * @return array<string, mixed>
     */
    public static function execute(int $cmid): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $cm      = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $course  = get_course($cm->course);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('format/smartcards:manageappearance', $context);

        $item     = (new appearance_repository())->get_for_activity($params['cmid']);
        $modinfo  = get_fast_modinfo($course);
        $cminfo   = $modinfo->get_cm($params['cmid']);
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
            'cmid'       => $params['cmid'],
            'type'       => $item?->type ?? appearance_repository::TYPE_DEFAULT,
            'value'      => $item?->value ?? '',
            'bgcolor'    => $item?->bgcolor ?? '',
            'labelcolor' => $item?->labelcolor ?? '',
            'labelfont'  => $item?->labelfont ?? '',
            'iconurl'    => $renderer->image_url('icon', $cminfo->modname)->out(false),
            'imageurl'   => $hasimage ? appearance_image_store::url($params['cmid'], (int)$item->value)->out(false) : '',
            'icons'      => $icons,
        ];
    }

    /**
     * Return structure for execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'       => new external_value(PARAM_INT, 'Course module id'),
            'type'       => new external_value(PARAM_ALPHA, "'default', 'emoji', 'icon' or 'image'"),
            'value'      => new external_value(PARAM_RAW, 'Emoji character or icon name, or empty'),
            'bgcolor'    => new external_value(PARAM_RAW, 'Circle background #RRGGBB, or empty'),
            'labelcolor' => new external_value(PARAM_RAW, 'Title colour #RRGGBB, or empty'),
            'labelfont'  => new external_value(PARAM_RAW, 'Curated font slug, or empty'),
            'iconurl'    => new external_value(PARAM_RAW, 'Default per-module-type icon URL'),
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
