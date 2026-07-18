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

use coding_exception;
use invalid_parameter_exception;

/**
 * CRUD access to format_smartcards_appearance, plus the bulk deletes used by the
 * course/module/section deletion observers (see classes/observer.php and
 * classes/hook_listener.php).
 *
 * Generic over both context levels (activity and section) — the same table serves both
 * from day one, and both have a full UI (section appearance since SCOPE.md §16 Fase 4).
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appearance_repository {
    /** @var string Appearance row describes a course module. */
    public const CONTEXTLEVEL_ACTIVITY = 'activity';

    /** @var string Appearance row describes a course section. */
    public const CONTEXTLEVEL_SECTION = 'section';

    /** @var string Custom appearance is an uploaded image. */
    public const TYPE_IMAGE = 'image';

    /** @var string Custom appearance is a single emoji character. */
    public const TYPE_EMOJI = 'emoji';

    /** @var string Custom appearance is a bundled library icon name. */
    public const TYPE_ICON = 'icon';

    /**
     * @var string No icon/emoji override — the item keeps the default per-module-type
     *             icon. Lets a teacher customise only bgcolor/labelcolor/labelfont
     *             without being forced to also pick an emoji or icon.
     */
    public const TYPE_DEFAULT = 'default';

    /** @var string[] All valid values of the "type" column. */
    private const VALID_TYPES = [self::TYPE_IMAGE, self::TYPE_EMOJI, self::TYPE_ICON, self::TYPE_DEFAULT];

    /**
     * @var string Special bgcolor value meaning "no background colour at all" (the
     *             circle blends into the card). Not a #RRGGBB value, so it is checked
     *             for explicitly in validate_bgcolor() rather than by the hex regex.
     */
    public const BGCOLOR_TRANSPARENT = 'transparent';

    /**
     * Returns the appearance configured for one course module, if any.
     *
     * @param int $cmid Course module id.
     * @return appearance|null
     */
    public function get_for_activity(int $cmid): ?appearance {
        return $this->get_for_item(self::CONTEXTLEVEL_ACTIVITY, $cmid);
    }

    /**
     * Returns the appearance configured for one course section, if any.
     *
     * @param int $sectionid Section id.
     * @return appearance|null
     */
    public function get_for_section(int $sectionid): ?appearance {
        return $this->get_for_item(self::CONTEXTLEVEL_SECTION, $sectionid);
    }

    /**
     * Bulk-loads the appearance configured for several course modules at once.
     *
     * Callers rendering a whole section/course grid must use this instead of calling
     * get_for_activity() inside a loop, to avoid an N+1 query pattern.
     *
     * @param int[] $cmids Course module ids.
     * @return array<int, appearance> Appearance keyed by cmid; cmids without a custom
     *                                appearance are simply absent from the array.
     */
    public function get_many_for_activities(array $cmids): array {
        return $this->get_many_for_items(self::CONTEXTLEVEL_ACTIVITY, $cmids);
    }

    /**
     * Bulk-loads the appearance configured for several course sections at once.
     *
     * @param int[] $sectionids Section ids.
     * @return array<int, appearance> Appearance keyed by sectionid; sectionids without a
     *                                custom appearance are simply absent from the array.
     */
    public function get_many_for_sections(array $sectionids): array {
        return $this->get_many_for_items(self::CONTEXTLEVEL_SECTION, $sectionids);
    }

    /**
     * Creates or updates the appearance of one course module.
     *
     * @param int $cmid Course module id.
     * @param string $type One of TYPE_IMAGE, TYPE_EMOJI, TYPE_ICON.
     * @param string $value Fileid, single emoji, or library icon name, depending on $type.
     * @param string|null $bgcolor #RRGGBB background colour of the card circle, or null.
     * @param string|null $labelcolor #RRGGBB title colour, must belong to the curated palette, or null.
     * @param string|null $labelfont Curated font slug, or null for the system font.
     * @param string|null $iconcolor #RRGGBB icon glyph colour, or null for the default.
     * @return appearance The saved row.
     * @throws invalid_parameter_exception If any value fails validation.
     */
    public function save_for_activity(
        int $cmid,
        string $type,
        string $value,
        ?string $bgcolor,
        ?string $labelcolor,
        ?string $labelfont,
        ?string $iconcolor = null,
    ): appearance {
        return $this->save_for_item(
            self::CONTEXTLEVEL_ACTIVITY,
            $cmid,
            $type,
            $value,
            $bgcolor,
            $labelcolor,
            $labelfont,
            $iconcolor
        );
    }

    /**
     * Creates or updates the appearance of one course section.
     *
     * @param int $sectionid Section id.
     * @param string $type One of TYPE_IMAGE, TYPE_EMOJI, TYPE_ICON.
     * @param string $value Fileid, single emoji, or library icon name, depending on $type.
     * @param string|null $bgcolor #RRGGBB background colour of the card circle, or null.
     * @param string|null $labelcolor #RRGGBB title colour, must belong to the curated palette, or null.
     * @param string|null $labelfont Curated font slug, or null for the system font.
     * @param string|null $iconcolor #RRGGBB icon glyph colour, or null for the default.
     * @return appearance The saved row.
     * @throws invalid_parameter_exception If any value fails validation.
     */
    public function save_for_section(
        int $sectionid,
        string $type,
        string $value,
        ?string $bgcolor,
        ?string $labelcolor,
        ?string $labelfont,
        ?string $iconcolor = null,
    ): appearance {
        return $this->save_for_item(
            self::CONTEXTLEVEL_SECTION,
            $sectionid,
            $type,
            $value,
            $bgcolor,
            $labelcolor,
            $labelfont,
            $iconcolor
        );
    }

    /**
     * Deletes the appearance of one course module, if any.
     *
     * @param int $cmid Course module id.
     * @return void
     */
    public function delete_for_activity(int $cmid): void {
        $this->delete_for_items(self::CONTEXTLEVEL_ACTIVITY, [$cmid]);
    }

    /**
     * Deletes the appearance of one course section, if any.
     *
     * @param int $sectionid Section id.
     * @return void
     */
    public function delete_for_section(int $sectionid): void {
        $this->delete_for_items(self::CONTEXTLEVEL_SECTION, [$sectionid]);
    }

    /**
     * Bulk-deletes the appearance of several course modules at once.
     *
     * Used by the course-deletion cleanup, which resolves every cmid that belonged to
     * the course before it was removed (see classes/hook_listener.php).
     *
     * @param int[] $cmids Course module ids.
     * @return void
     */
    public function delete_for_activities(array $cmids): void {
        $this->delete_for_items(self::CONTEXTLEVEL_ACTIVITY, $cmids);
    }

    /**
     * Bulk-deletes the appearance of several course sections at once.
     *
     * @param int[] $sectionids Section ids.
     * @return void
     */
    public function delete_for_sections(array $sectionids): void {
        $this->delete_for_items(self::CONTEXTLEVEL_SECTION, $sectionids);
    }

    /**
     * Returns the appearance configured for one item, if any.
     *
     * @param string $contextlevel One of CONTEXTLEVEL_ACTIVITY or CONTEXTLEVEL_SECTION.
     * @param int $itemid cmid or sectionid.
     * @return appearance|null
     */
    private function get_for_item(string $contextlevel, int $itemid): ?appearance {
        global $DB;

        $record = $DB->get_record('format_smartcards_appearance', [
            'contextlevel' => $contextlevel,
            'itemid'       => $itemid,
        ]);

        return $record ? appearance::from_record($record) : null;
    }

    /**
     * Bulk-loads the appearance configured for several items at once.
     *
     * @param string $contextlevel One of CONTEXTLEVEL_ACTIVITY or CONTEXTLEVEL_SECTION.
     * @param int[] $itemids cmids or sectionids.
     * @return array<int, appearance> Appearance keyed by itemid.
     */
    private function get_many_for_items(string $contextlevel, array $itemids): array {
        global $DB;

        if (empty($itemids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $params = array_merge(['contextlevel' => $contextlevel], $inparams);

        $records = $DB->get_records_select(
            'format_smartcards_appearance',
            "contextlevel = :contextlevel AND itemid $insql",
            $params
        );

        $result = [];
        foreach ($records as $record) {
            $result[(int)$record->itemid] = appearance::from_record($record);
        }
        return $result;
    }

    /**
     * Creates or updates the appearance of one item, validating every field.
     *
     * @param string $contextlevel One of CONTEXTLEVEL_ACTIVITY or CONTEXTLEVEL_SECTION.
     * @param int $itemid cmid or sectionid.
     * @param string $type One of TYPE_IMAGE, TYPE_EMOJI, TYPE_ICON.
     * @param string $value Fileid, single emoji, or library icon name, depending on $type.
     * @param string|null $bgcolor #RRGGBB background colour of the card circle, or null.
     * @param string|null $labelcolor #RRGGBB title colour, must belong to the curated palette, or null.
     * @param string|null $labelfont Curated font slug, or null for the system font.
     * @param string|null $iconcolor #RRGGBB icon glyph colour, or null for the default.
     * @return appearance The saved row.
     * @throws invalid_parameter_exception If any value fails validation.
     */
    private function save_for_item(
        string $contextlevel,
        int $itemid,
        string $type,
        string $value,
        ?string $bgcolor,
        ?string $labelcolor,
        ?string $labelfont,
        ?string $iconcolor = null,
    ): appearance {
        global $DB;

        $this->validate_type_and_value($type, $value);
        $this->validate_bgcolor($bgcolor);
        $this->validate_labelcolor($labelcolor);
        $this->validate_labelfont($labelfont);
        $this->validate_iconcolor($iconcolor);

        $now = time();
        $existing = $DB->get_record('format_smartcards_appearance', [
            'contextlevel' => $contextlevel,
            'itemid'       => $itemid,
        ]);

        $record = (object)[
            'contextlevel' => $contextlevel,
            'itemid'       => $itemid,
            'type'         => $type,
            'value'        => $value,
            'bgcolor'      => $bgcolor,
            'labelcolor'   => $labelcolor,
            'labelfont'    => $labelfont,
            'iconcolor'    => $iconcolor,
            'timemodified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('format_smartcards_appearance', $record);
            $id = $existing->id;
        } else {
            $record->timecreated = $now;
            $id = $DB->insert_record('format_smartcards_appearance', $record);
        }

        return $this->get_for_item($contextlevel, $itemid) ?? throw new coding_exception(
            'format_smartcards_appearance row disappeared right after being saved, id ' . $id
        );
    }

    /**
     * Bulk-deletes appearance rows for the given items.
     *
     * @param string $contextlevel One of CONTEXTLEVEL_ACTIVITY or CONTEXTLEVEL_SECTION.
     * @param int[] $itemids cmids or sectionids.
     * @return void
     */
    private function delete_for_items(string $contextlevel, array $itemids): void {
        global $DB;

        if (empty($itemids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $params = array_merge(['contextlevel' => $contextlevel], $inparams);

        $DB->delete_records_select(
            'format_smartcards_appearance',
            "contextlevel = :contextlevel AND itemid $insql",
            $params
        );
    }

    /**
     * Validates that $type is known and $value matches what that type expects.
     *
     * @param string $type Appearance type to validate.
     * @param string $value Value to validate against $type.
     * @return void
     * @throws invalid_parameter_exception If $type is unknown or $value is malformed.
     */
    private function validate_type_and_value(string $type, string $value): void {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new invalid_parameter_exception('Invalid appearance type: ' . $type);
        }

        if ($type === self::TYPE_EMOJI && !$this->is_single_emoji($value)) {
            throw new invalid_parameter_exception('Value is not a single emoji character');
        }

        if ($type === self::TYPE_ICON && !preg_match('/^[a-z0-9-]+$/', $value)) {
            throw new invalid_parameter_exception('Invalid icon name: ' . $value);
        }

        if ($type === self::TYPE_IMAGE && !ctype_digit($value)) {
            throw new invalid_parameter_exception('Invalid image file id: ' . $value);
        }
    }

    /**
     * Validates the free-form circle background colour, if provided.
     *
     * Unlike labelcolor, bgcolor is not restricted to a curated palette: the circle
     * only ever holds a decorative icon, never text, so WCAG text-contrast rules do not
     * apply to it. It is still validated as either a well-formed #RRGGBB value (defense
     * in depth against malformed CSS injection) or the literal BGCOLOR_TRANSPARENT.
     *
     * @param string|null $bgcolor Colour to validate.
     * @return void
     * @throws invalid_parameter_exception If $bgcolor is set but malformed.
     */
    private function validate_bgcolor(?string $bgcolor): void {
        if ($bgcolor === null || $bgcolor === self::BGCOLOR_TRANSPARENT) {
            return;
        }
        if (!appearance_palette::is_valid_hex_color($bgcolor)) {
            throw new invalid_parameter_exception('Invalid bgcolor: ' . $bgcolor);
        }
    }

    /**
     * Validates the title colour against the curated palette, if provided.
     *
     * @param string|null $labelcolor Colour to validate.
     * @return void
     * @throws invalid_parameter_exception If $labelcolor is set but not in the palette.
     */
    private function validate_labelcolor(?string $labelcolor): void {
        if ($labelcolor !== null && !appearance_palette::is_valid_labelcolor($labelcolor)) {
            throw new invalid_parameter_exception('labelcolor is not part of the curated palette: ' . $labelcolor);
        }
    }

    /**
     * Validates the title font against the curated palette, if provided.
     *
     * @param string|null $labelfont Font slug to validate.
     * @return void
     * @throws invalid_parameter_exception If $labelfont is set but not in the palette.
     */
    private function validate_labelfont(?string $labelfont): void {
        if ($labelfont !== null && !appearance_palette::is_valid_labelfont($labelfont)) {
            throw new invalid_parameter_exception('labelfont is not part of the curated palette: ' . $labelfont);
        }
    }

    /**
     * Validates the free-form icon glyph colour, if provided.
     *
     * Like bgcolor (and unlike labelcolor), iconcolor is not restricted to a curated
     * palette: the icon it colours is always aria-hidden and purely decorative, so WCAG
     * text-contrast rules do not apply to it. Only meaningful when type is TYPE_ICON,
     * but stored independently of type, same as bgcolor/labelcolor/labelfont.
     *
     * @param string|null $iconcolor Colour to validate.
     * @return void
     * @throws invalid_parameter_exception If $iconcolor is set but malformed.
     */
    private function validate_iconcolor(?string $iconcolor): void {
        if ($iconcolor !== null && !appearance_palette::is_valid_hex_color($iconcolor)) {
            throw new invalid_parameter_exception('Invalid iconcolor: ' . $iconcolor);
        }
    }

    /**
     * Returns whether $value is exactly one emoji grapheme.
     *
     * The browser's native emoji picker is not a guarantee: a hand-crafted POST can
     * send arbitrary text, so this is validated server-side rather than trusted from
     * the client (see SCOPE.md §8/§13).
     *
     * @param string $value Value to validate.
     * @return bool
     */
    private function is_single_emoji(string $value): bool {
        if ($value === '') {
            return false;
        }

        if (function_exists('grapheme_strlen')) {
            if (grapheme_strlen($value) !== 1) {
                return false;
            }
        } else if (mb_strlen($value) > 8) {
            // Fallback when intl is unavailable: a single emoji grapheme (including
            // ZWJ sequences and variation selectors) rarely exceeds a handful of
            // codepoints, so anything longer than this is rejected as not a single emoji.
            return false;
        }

        // Requires at least one codepoint from the common emoji blocks, so plain text
        // (e.g. a single ASCII letter, which also passes the grapheme-count check) is
        // rejected.
        return (bool)preg_match(
            '/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{2B00}-\x{2BFF}\x{FE0F}]/u',
            $value
        );
    }
}
