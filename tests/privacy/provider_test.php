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

/**
 * Tests for the format_smartcards privacy provider.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_smartcards\tests\privacy;

use core_privacy\tests\provider_testcase;
use format_smartcards\privacy\provider;

/**
 * Tests for format_smartcards\privacy\provider.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_smartcards\privacy\provider
 */
final class provider_test extends provider_testcase {
    /**
     * The plugin must declare the correct language string identifier explaining why it
     * stores no personal data.
     *
     * @covers \format_smartcards\privacy\provider::get_reason
     */
    public function test_get_reason_returns_privacy_metadata(): void {
        $this->assertSame('privacy:metadata', provider::get_reason());
    }

    /**
     * The provider must implement null_provider — see SCOPE.md §12 for why this holds
     * even after appearance images were added (§18 v2.6): the uploaded card image is
     * decorative teacher-set configuration for how an activity/section looks to every
     * viewer, not personal data about any user, and format_smartcards_appearance
     * itself has no userid column at all.
     *
     * @covers \format_smartcards\privacy\provider
     */
    public function test_implements_null_provider(): void {
        $this->assertInstanceOf(
            \core_privacy\local\metadata\null_provider::class,
            new provider()
        );
    }
}
