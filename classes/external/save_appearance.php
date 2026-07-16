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

use coding_exception;
use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use format_smartcards\local\appearance;
use format_smartcards\local\appearance_image_store;
use format_smartcards\local\appearance_repository;
use format_smartcards\local\card_builder;
use format_smartcards\local\cm_description_resolver;
use invalid_parameter_exception;

/**
 * Saves the custom appearance of one activity card and returns it fully re-rendered.
 *
 * The returned structure matches the format_smartcards/local/cm_button template context
 * exactly (built by the same card_builder the grid itself uses), so the caller can feed
 * it straight into core/templates and swap the card's DOM node — no separate rendering
 * logic to keep in sync between the initial page load and this save round trip.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_appearance extends external_api {
    /**
     * Parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
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
     * Saves the given appearance for one activity and returns its re-rendered card.
     *
     * @param int $cmid Course module id.
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
        int $cmid,
        string $type,
        string $value,
        string $bgcolor = '',
        string $labelcolor = '',
        string $labelfont = '',
        string $imagedata = ''
    ): array {
        global $PAGE, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'type' => $type,
            'value' => $value,
            'bgcolor' => $bgcolor,
            'labelcolor' => $labelcolor,
            'labelfont' => $labelfont,
            'imagedata' => $imagedata,
        ]);

        $cm      = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $course  = get_course($cm->course);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('format/smartcards:manageappearance', $context);

        $repository = new appearance_repository();
        $existing   = $repository->get_for_activity($params['cmid']);
        $resolvedvalue = self::resolve_image_value(
            $params['cmid'],
            $params['type'],
            $params['value'],
            $params['imagedata'],
            $existing
        );

        $repository->save_for_activity(
            $params['cmid'],
            $params['type'],
            $resolvedvalue,
            $params['bgcolor'] !== '' ? $params['bgcolor'] : null,
            $params['labelcolor'] !== '' ? $params['labelcolor'] : null,
            $params['labelfont'] !== '' ? $params['labelfont'] : null,
        );

        $modinfo       = get_fast_modinfo($course);
        $cminfo        = $modinfo->get_cm($params['cmid']);
        $renderer      = $PAGE->get_renderer('format_smartcards');
        $formatoptions = course_get_format($course)->get_format_options();

        $card = card_builder::build(
            $cminfo,
            $course,
            $renderer,
            $repository->get_for_activity($params['cmid']),
            $formatoptions,
            (int)$USER->id,
            cm_description_resolver::resolve_one($cminfo)
        );
        if ($card === null) {
            throw new coding_exception('Card became invisible right after saving its own appearance');
        }
        $card['badge'] = $card['badge'] ?? '';

        return $card;
    }

    /**
     * Resolves the "value" to persist for the given type, handling TYPE_IMAGE's file
     * lifecycle: a freshly uploaded image is stored (replacing any previous one), an
     * empty upload keeps the previously stored image, and switching away from
     * TYPE_IMAGE deletes the now-orphaned file.
     *
     * @param int $cmid Course module id.
     * @param string $type Validated appearance type.
     * @param string $value Validated value param (emoji/icon name; ignored for images).
     * @param string $imagedata Base64-encoded image bytes, or '' when not (re)uploading.
     * @param appearance|null $existing The activity's current appearance, if any, used
     *        to detect a type change away from image.
     * @return string The value to persist in format_smartcards_appearance.value.
     * @throws invalid_parameter_exception If type is TYPE_IMAGE with nothing to store.
     */
    private static function resolve_image_value(
        int $cmid,
        string $type,
        string $value,
        string $imagedata,
        ?appearance $existing
    ): string {
        $waskeepingimage = $existing !== null && $existing->type === appearance_repository::TYPE_IMAGE;

        if ($type !== appearance_repository::TYPE_IMAGE) {
            if ($waskeepingimage) {
                appearance_image_store::delete($cmid);
            }
            return $value;
        }

        if ($imagedata !== '') {
            return (string)appearance_image_store::store($cmid, $imagedata);
        }

        if ($waskeepingimage && $existing->value !== '') {
            return $existing->value;
        }

        throw new invalid_parameter_exception('An image must be uploaded');
    }

    /**
     * Return structure for execute(), matching the cm_button template context.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'                 => new external_value(PARAM_INT, 'Course module id'),
            'name'                 => new external_value(PARAM_RAW, 'Activity name'),
            'iconurl'              => new external_value(PARAM_RAW, 'Default per-module-type icon URL'),
            'modtypelabel'         => new external_value(PARAM_RAW, 'Localised module type name'),
            'url'                  => new external_value(
                PARAM_RAW,
                'Activity URL, or empty when not directly accessible'
            ),
            'hasurl'               => new external_value(PARAM_BOOL, 'Whether the activity is directly accessible'),
            'badge'                => new external_value(PARAM_RAW, "'locked', 'timed', or empty"),
            'badgelabel'           => new external_value(PARAM_RAW, 'Localised badge label'),
            'islocked'             => new external_value(PARAM_BOOL, 'Whether the locked badge applies'),
            'istimed'              => new external_value(PARAM_BOOL, 'Whether the timed badge applies'),
            'hasbadge'             => new external_value(PARAM_BOOL, 'Whether any badge applies'),
            'reason'               => new external_value(
                PARAM_RAW,
                'Availability reason (may contain safe core-generated markup)'
            ),
            'hasreason'            => new external_value(PARAM_BOOL, 'Whether a reason is present'),
            'duedate'              => new external_value(PARAM_INT, 'Expected completion timestamp, or 0'),
            'hasduedate'           => new external_value(PARAM_BOOL, 'Whether a due date is present'),
            'duedateformatted'     => new external_value(PARAM_RAW, 'Localised due date, or empty'),
            'dimmed'               => new external_value(PARAM_BOOL, 'Whether the card should render dimmed'),
            'hiddenlabel'          => new external_value(PARAM_RAW, "Localised 'Hidden' label, or empty"),
            'isemoji'              => new external_value(PARAM_BOOL, 'Whether the appearance type is emoji'),
            'emoji'                => new external_value(PARAM_RAW, 'Emoji character, or empty'),
            'iscustomicon'         => new external_value(
                PARAM_BOOL,
                'Whether a custom icon/image replaces the default one'
            ),
            'customiconurl'        => new external_value(PARAM_RAW, 'Custom icon/image URL, or empty'),
            'hasiconstyle'         => new external_value(PARAM_BOOL, 'Whether the icon circle has a custom style'),
            'iconstyle'            => new external_value(PARAM_RAW, 'Inline CSS for the icon circle, or empty'),
            'hastitlestyle'        => new external_value(PARAM_BOOL, 'Whether the title has a custom style'),
            'titlestyle'           => new external_value(PARAM_RAW, 'Inline CSS for the title, or empty'),
            'opensheet'            => new external_value(PARAM_BOOL, 'Whether tapping the card opens the status sheet'),
            'statuslabel'          => new external_value(
                PARAM_RAW,
                'Combined availability + completion label for aria-label'
            ),
            'hasstatuslabel'       => new external_value(PARAM_BOOL, 'Whether statuslabel is non-empty'),
            'hascompletionbadge'   => new external_value(PARAM_BOOL, 'Whether completion is tracked for this activity'),
            'iscompletionpending'  => new external_value(PARAM_BOOL, 'Whether completion is tracked but not yet met'),
            'iscompletioncomplete' => new external_value(PARAM_BOOL, 'Whether completion is tracked and already met'),
            'completionbadgelabel' => new external_value(PARAM_RAW, 'Localised completion badge label, or empty'),
            'completiontype'       => new external_value(PARAM_RAW, "'none', 'manual' or 'automatic'"),
            'completioncriteria'   => new external_value(
                PARAM_RAW,
                'JSON array of localised automatic criteria descriptions'
            ),
            'cantoggle'            => new external_value(PARAM_BOOL, 'Whether the manual completion toggle applies'),
            'hasdescription'       => new external_value(PARAM_BOOL, 'Whether a "Display description" intro is present'),
            'description'          => new external_value(PARAM_RAW, 'Rendered description HTML, or empty'),
        ]);
    }
}
