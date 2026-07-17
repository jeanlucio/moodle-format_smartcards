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

use completion_info;
use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use format_smartcards\local\appearance_image_store;
use format_smartcards\local\appearance_repository;
use format_smartcards\local\section_card_builder;
use format_smartcards\local\section_progress_resolver;

/**
 * Saves the custom appearance of one section card and returns it fully re-rendered.
 *
 * The section-level counterpart of {@see save_appearance}: same field lifecycle (type/
 * value/colours/font/imagedata), same returned-and-rerendered-card round trip, but
 * built by section_card_builder instead of card_builder, since a section card's context
 * shape (no url/completion-toggle/description, an icon fallback chain, a progress
 * badge) differs enough from an activity's that sharing one builder would mean
 * branching throughout it instead of reusing the two pieces (appearance_style_resolver,
 * appearance_image_store::resolve_saved_value()) that actually are shared.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_section_appearance extends external_api {
    /**
     * Parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
            'type' => new external_value(PARAM_ALPHA, "Appearance type: 'default', 'emoji', 'icon' or 'image'"),
            'value' => new external_value(PARAM_RAW_TRIMMED, 'Emoji character or icon name; ignored for image'),
            'bgcolor' => new external_value(
                PARAM_RAW_TRIMMED,
                'Circle background #RRGGBB, or empty for the default',
                VALUE_DEFAULT,
                ''
            ),
            'labelcolor' => new external_value(
                PARAM_RAW_TRIMMED,
                'Title colour #RRGGBB from the curated palette, or empty for the default',
                VALUE_DEFAULT,
                ''
            ),
            'labelfont' => new external_value(
                PARAM_ALPHANUM,
                'Curated font slug, or empty for the system font',
                VALUE_DEFAULT,
                ''
            ),
            'imagedata' => new external_value(
                PARAM_RAW_TRIMMED,
                'Base64-encoded image bytes (type=image only); empty keeps the existing image',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Saves the given appearance for one section and returns its re-rendered card.
     *
     * @param int $sectionid Section id.
     * @param string $type One of appearance_repository::TYPE_EMOJI, TYPE_ICON, TYPE_IMAGE or TYPE_DEFAULT.
     * @param string $value Emoji character or icon name; ignored for TYPE_IMAGE (see $imagedata).
     * @param string $bgcolor Circle background #RRGGBB, or '' for the default.
     * @param string $labelcolor Title colour #RRGGBB, or '' for the default.
     * @param string $labelfont Curated font slug, or '' for the system font.
     * @param string $imagedata Base64-encoded image bytes (TYPE_IMAGE only), or '' to
     *                          keep the previously uploaded image.
     * @return array<string, mixed>
     */
    public static function execute(
        int $sectionid,
        string $type,
        string $value,
        string $bgcolor = '',
        string $labelcolor = '',
        string $labelfont = '',
        string $imagedata = ''
    ): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'sectionid' => $sectionid,
            'type' => $type,
            'value' => $value,
            'bgcolor' => $bgcolor,
            'labelcolor' => $labelcolor,
            'labelfont' => $labelfont,
            'imagedata' => $imagedata,
        ]);

        $sectioninfo = get_section_appearance::get_section_or_fail($params['sectionid']);
        $course      = get_course($sectioninfo->course);
        $context     = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('format/smartcards:manageappearance', $context);

        $repository = new appearance_repository();
        $existing   = $repository->get_for_section($params['sectionid']);
        $resolvedvalue = appearance_image_store::resolve_saved_value(
            $params['type'],
            $params['value'],
            $params['imagedata'],
            $existing,
            fn (string $imagedata): string => (string)appearance_image_store::store_for_section(
                $params['sectionid'],
                $course->id,
                $imagedata
            ),
            fn () => appearance_image_store::delete_for_section($params['sectionid'], $course->id)
        );

        $repository->save_for_section(
            $params['sectionid'],
            $params['type'],
            $resolvedvalue,
            $params['bgcolor'] !== '' ? $params['bgcolor'] : null,
            $params['labelcolor'] !== '' ? $params['labelcolor'] : null,
            $params['labelfont'] !== '' ? $params['labelfont'] : null,
        );

        $format        = course_get_format($course);
        $modinfo       = get_fast_modinfo($course);
        $sectioninfo   = $modinfo->get_section_info_by_id($params['sectionid'], MUST_EXIST);
        $renderer      = $PAGE->get_renderer('format_smartcards');
        $formatoptions = $format->get_format_options();

        $sectioncmids = section_card_builder::get_visible_section_cmids($modinfo, $sectioninfo);
        $activityappearances = $repository->get_many_for_activities($sectioncmids);

        $progressdisplay = $formatoptions['progressdisplay'] ?? '';
        $showprogress     = in_array($progressdisplay, ['count', 'percent'], true);

        $progress = $sectioninfo->section > 0
            ? section_progress_resolver::resolve(new completion_info($course), $modinfo, $sectioninfo)
            : null;
        $hastracking = $progress !== null && $progress->has_tracking();
        $hasprogress = $hastracking && $showprogress;
        $ispending   = $hastracking && $progress->has_pending();
        $iscomplete  = $hastracking && !$progress->has_pending();

        return section_card_builder::build(
            $sectioninfo,
            $format->get_section_name($sectioninfo),
            $renderer,
            $repository->get_for_section($params['sectionid']),
            $activityappearances,
            $sectioncmids,
            $formatoptions,
            $sectioninfo->section === 0 || $sectioninfo->uservisible,
            !empty($sectioncmids),
            $hasprogress,
            $hasprogress ? $progress->format_label($progressdisplay) : '',
            $ispending,
            $iscomplete
        );
    }

    /**
     * Return structure for execute(), matching the section_card_button template context.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'                    => new external_value(PARAM_INT, 'Section id'),
            'name'                  => new external_value(PARAM_RAW, 'Section name'),
            'islocked'              => new external_value(PARAM_BOOL, 'Whether the locked badge applies'),
            'hasbadge'              => new external_value(PARAM_BOOL, 'Whether any badge applies'),
            'badgelabel'            => new external_value(PARAM_RAW, 'Localised badge label, or empty'),
            'iconurl'               => new external_value(PARAM_RAW, 'Generic section icon URL'),
            'isemoji'               => new external_value(PARAM_BOOL, 'Whether the appearance type is emoji'),
            'emoji'                 => new external_value(PARAM_RAW, 'Emoji character, or empty'),
            'iscustomicon'          => new external_value(
                PARAM_BOOL,
                'Whether a custom icon/image replaces the default one'
            ),
            'customiconurl'         => new external_value(PARAM_RAW, 'Custom icon/image URL, or empty'),
            'hasiconstyle'          => new external_value(PARAM_BOOL, 'Whether the icon circle has a custom style'),
            'iconstyle'             => new external_value(PARAM_RAW, 'Inline CSS for the icon circle, or empty'),
            'hastitlestyle'         => new external_value(PARAM_BOOL, 'Whether the title has a custom style'),
            'titlestyle'            => new external_value(PARAM_RAW, 'Inline CSS for the title, or empty'),
            'hasprogress'           => new external_value(PARAM_BOOL, 'Whether progresslabel should be shown'),
            'progresslabel'         => new external_value(PARAM_RAW, 'Pre-formatted progress label, or empty'),
            'hascompletionbadge'    => new external_value(PARAM_BOOL, 'Whether completion is tracked for this section'),
            'iscompletionpending'   => new external_value(PARAM_BOOL, 'Whether completion is tracked but not yet met'),
            'iscompletioncomplete'  => new external_value(PARAM_BOOL, 'Whether completion is tracked and already met'),
            'completionbadgelabel'  => new external_value(PARAM_RAW, 'Localised completion badge label, or empty'),
            'hascards'              => new external_value(PARAM_BOOL, 'Whether the section has any visible activity'),
        ]);
    }
}
