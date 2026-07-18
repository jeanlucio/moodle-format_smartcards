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

use invalid_parameter_exception;

/**
 * Tests for the SmartCards appearance_repository.
 *
 * Coverage is declared once at class level (not per test method) so that private helpers
 * reached only via delegation from these public methods (e.g. save_for_item()) are correctly
 * attributed to this test suite instead of being silently excluded by php-code-coverage's
 * per-method coverage-annotation line filtering.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \format_smartcards\local\appearance_repository
 */
final class appearance_repository_test extends \advanced_testcase {
    /**
     * Saving a new activity appearance, then reading it back, must return the same values.
     */
    public function test_save_and_get_roundtrip(): void {
        $this->resetAfterTest();
        $repository = new appearance_repository();

        $saved = $repository->save_for_activity(
            cmid: 42,
            type: appearance_repository::TYPE_ICON,
            value: 'book',
            bgcolor: '#ff00aa',
            labelcolor: appearance_palette::LABEL_COLORS['blue'],
            labelfont: 'nunito',
        );

        $this->assertSame('activity', $saved->contextlevel);
        $this->assertSame(42, $saved->itemid);
        $this->assertSame(appearance_repository::TYPE_ICON, $saved->type);
        $this->assertSame('book', $saved->value);
        $this->assertSame('#ff00aa', $saved->bgcolor);
        $this->assertSame(appearance_palette::LABEL_COLORS['blue'], $saved->labelcolor);
        $this->assertSame('nunito', $saved->labelfont);

        $fetched = $repository->get_for_activity(42);
        $this->assertNotNull($fetched);
        $this->assertSame($saved->id, $fetched->id);
    }

    /**
     * Saving a second time for the same cmid must update the existing row (upsert),
     * never insert a duplicate — the table's unique key on (contextlevel, itemid)
     * would otherwise be violated.
     */
    public function test_save_twice_upserts_same_row(): void {
        $this->resetAfterTest();
        $repository = new appearance_repository();

        $first = $repository->save_for_activity(7, appearance_repository::TYPE_EMOJI, '🎉', null, null, null);
        $second = $repository->save_for_activity(7, appearance_repository::TYPE_ICON, 'star', null, null, null);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(appearance_repository::TYPE_ICON, $repository->get_for_activity(7)->type);
    }

    /**
     * An unknown appearance type must be rejected before anything is written.
     */
    public function test_invalid_type_is_rejected(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new appearance_repository())->save_for_activity(1, 'video', 'x', null, null, null);
    }

    /**
     * A value that is not a single emoji grapheme must be rejected for type=emoji —
     * the browser's native picker is not trusted as the only validation (SCOPE.md §8).
     */
    public function test_emoji_type_rejects_plain_text(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new appearance_repository())->save_for_activity(1, appearance_repository::TYPE_EMOJI, 'hello', null, null, null);
    }

    /**
     * A single emoji grapheme, including a multi-codepoint ZWJ sequence, must be accepted.
     */
    public function test_emoji_type_accepts_single_emoji(): void {
        $this->resetAfterTest();
        $saved = (new appearance_repository())->save_for_activity(
            1,
            appearance_repository::TYPE_EMOJI,
            '👩‍🚀',
            null,
            null,
            null
        );
        $this->assertSame('👩‍🚀', $saved->value);
    }

    /**
     * TYPE_DEFAULT must be accepted with an empty value, so a teacher can customise
     * only bgcolor/labelcolor/labelfont while keeping the activity's default
     * per-module-type icon, without being forced to also pick an emoji or icon.
     */
    public function test_default_type_accepts_empty_value_with_colours(): void {
        $this->resetAfterTest();
        $saved = (new appearance_repository())->save_for_activity(
            1,
            appearance_repository::TYPE_DEFAULT,
            '',
            null,
            appearance_palette::LABEL_COLORS['blue'],
            'nunito'
        );

        $this->assertSame(appearance_repository::TYPE_DEFAULT, $saved->type);
        $this->assertSame('', $saved->value);
        $this->assertSame(appearance_palette::LABEL_COLORS['blue'], $saved->labelcolor);
        $this->assertSame('nunito', $saved->labelfont);
    }

    /**
     * An icon name containing anything other than lowercase letters, digits and
     * hyphens must be rejected.
     */
    public function test_icon_type_rejects_unsafe_value(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new appearance_repository())->save_for_activity(
            1,
            appearance_repository::TYPE_ICON,
            '<script>alert(1)</script>',
            null,
            null,
            null
        );
    }

    /**
     * An image value that is not a plain numeric fileid must be rejected.
     */
    public function test_image_type_rejects_non_numeric_value(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new appearance_repository())->save_for_activity(
            1,
            appearance_repository::TYPE_IMAGE,
            'not-a-fileid',
            null,
            null,
            null
        );
    }

    /**
     * A malformed bgcolor must be rejected, even though bgcolor is not restricted to
     * the curated palette (unlike labelcolor).
     */
    public function test_malformed_bgcolor_is_rejected(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new appearance_repository())->save_for_activity(1, appearance_repository::TYPE_ICON, 'book', 'red', null, null);
    }

    /**
     * The literal 'transparent' is not a #RRGGBB value but must still be accepted, so a
     * teacher can make the icon circle blend into the card instead of picking a colour.
     */
    public function test_transparent_bgcolor_is_accepted(): void {
        $this->resetAfterTest();
        $saved = (new appearance_repository())->save_for_activity(
            1,
            appearance_repository::TYPE_ICON,
            'book',
            appearance_repository::BGCOLOR_TRANSPARENT,
            null,
            null
        );

        $this->assertSame(appearance_repository::BGCOLOR_TRANSPARENT, $saved->bgcolor);
    }

    /**
     * A labelcolor outside the curated palette must be rejected, even if it is a
     * well-formed #RRGGBB value — the palette is the whole point (pre-validated
     * contrast, never a free colour picker).
     */
    public function test_labelcolor_outside_palette_is_rejected(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new appearance_repository())->save_for_activity(1, appearance_repository::TYPE_ICON, 'book', null, '#123456', null);
    }

    /**
     * A labelfont slug outside the curated palette must be rejected.
     */
    public function test_labelfont_outside_palette_is_rejected(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new appearance_repository())->save_for_activity(1, appearance_repository::TYPE_ICON, 'book', null, null, 'comicsans');
    }

    /**
     * A well-formed iconcolor must round-trip, and — like bgcolor, unlike labelcolor —
     * is not restricted to the curated palette, since the icon glyph it colours is
     * always aria-hidden and decorative.
     */
    public function test_iconcolor_roundtrips_and_is_not_restricted_to_the_palette(): void {
        $this->resetAfterTest();
        $saved = (new appearance_repository())->save_for_activity(
            1,
            appearance_repository::TYPE_ICON,
            'book',
            null,
            null,
            null,
            '#ff00aa'
        );

        $this->assertSame('#ff00aa', $saved->iconcolor);
    }

    /**
     * A malformed iconcolor must be rejected.
     */
    public function test_malformed_iconcolor_is_rejected(): void {
        $this->resetAfterTest();
        $this->expectException(invalid_parameter_exception::class);
        (new appearance_repository())->save_for_activity(1, appearance_repository::TYPE_ICON, 'book', null, null, null, 'red');
    }

    /**
     * Omitting iconcolor entirely must default to null, so every existing call site
     * that predates this field keeps working unchanged.
     */
    public function test_iconcolor_defaults_to_null_when_omitted(): void {
        $this->resetAfterTest();
        $saved = (new appearance_repository())->save_for_activity(1, appearance_repository::TYPE_ICON, 'book', null, null, null);

        $this->assertNull($saved->iconcolor);
    }

    /**
     * Deleting an activity's appearance must remove the row and leave get_for_activity()
     * returning null.
     */
    public function test_delete_for_activity_removes_row(): void {
        $this->resetAfterTest();
        $repository = new appearance_repository();

        $repository->save_for_activity(5, appearance_repository::TYPE_ICON, 'book', null, null, null);
        $repository->delete_for_activity(5);

        $this->assertNull($repository->get_for_activity(5));
    }

    /**
     * Bulk-loading several activities at once must return only the ones that have a
     * custom appearance, keyed by cmid, without issuing one query per cmid.
     */
    public function test_get_many_for_activities_returns_only_configured_items(): void {
        $this->resetAfterTest();
        $repository = new appearance_repository();

        $repository->save_for_activity(1, appearance_repository::TYPE_ICON, 'book', null, null, null);
        $repository->save_for_activity(2, appearance_repository::TYPE_EMOJI, '🎉', null, null, null);
        // Cmid 3 intentionally left unconfigured.

        $many = $repository->get_many_for_activities([1, 2, 3]);

        $this->assertCount(2, $many);
        $this->assertSame('book', $many[1]->value);
        $this->assertSame('🎉', $many[2]->value);
        $this->assertArrayNotHasKey(3, $many);
    }

    /**
     * Bulk-loading several sections at once must return only the ones that have a
     * custom appearance, keyed by sectionid, mirroring get_many_for_activities().
     */
    public function test_get_many_for_sections_returns_only_configured_items(): void {
        $this->resetAfterTest();
        $repository = new appearance_repository();

        $repository->save_for_section(1, appearance_repository::TYPE_ICON, 'book', null, null, null);
        $repository->save_for_section(2, appearance_repository::TYPE_EMOJI, '🎉', null, null, null);
        // Sectionid 3 intentionally left unconfigured.

        $many = $repository->get_many_for_sections([1, 2, 3]);

        $this->assertCount(2, $many);
        $this->assertSame('book', $many[1]->value);
        $this->assertSame('🎉', $many[2]->value);
        $this->assertArrayNotHasKey(3, $many);
    }

    /**
     * Bulk-deleting several activities at once must remove exactly those rows and
     * leave unrelated ones untouched.
     */
    public function test_delete_for_activities_removes_only_given_items(): void {
        $this->resetAfterTest();
        $repository = new appearance_repository();

        $repository->save_for_activity(1, appearance_repository::TYPE_ICON, 'book', null, null, null);
        $repository->save_for_activity(2, appearance_repository::TYPE_ICON, 'star', null, null, null);
        $repository->save_for_activity(3, appearance_repository::TYPE_ICON, 'flag', null, null, null);

        $repository->delete_for_activities([1, 2]);

        $this->assertNull($repository->get_for_activity(1));
        $this->assertNull($repository->get_for_activity(2));
        $this->assertNotNull($repository->get_for_activity(3));
    }

    /**
     * Activity and section appearance rows must be isolated by contextlevel, even
     * when they share the same itemid value.
     */
    public function test_activity_and_section_contexts_are_isolated(): void {
        $this->resetAfterTest();
        $repository = new appearance_repository();

        $repository->save_for_activity(9, appearance_repository::TYPE_ICON, 'book', null, null, null);

        $this->assertNull($repository->get_for_section(9));
        $this->assertNotNull($repository->get_for_activity(9));
    }
}
