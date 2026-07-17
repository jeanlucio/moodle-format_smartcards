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
 * Tests for the SmartCards appearance_image_store.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\local\appearance_image_store
 */
final class appearance_image_store_test extends \advanced_testcase {
    /** @var string Base64-encoded 1x1 transparent PNG, small enough to always pass the size check. */
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    /**
     * Creates a course with one page activity and returns its cmid.
     *
     * @return int
     */
    private function create_activity(): int {
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $page      = $generator->create_module('page', ['course' => $course->id]);
        return (int)$page->cmid;
    }

    /**
     * A valid small image must be stored and become servable straight after.
     *
     * @covers ::store
     * @covers ::resolve_for_serving
     */
    public function test_store_and_resolve_for_serving_roundtrip(): void {
        $this->resetAfterTest();
        $cmid = $this->create_activity();

        $fileid = appearance_image_store::store($cmid, self::TINY_PNG_BASE64);

        $this->assertGreaterThan(0, $fileid);
        $file = appearance_image_store::resolve_for_serving($cmid, 'cardimage');
        $this->assertNotNull($file);
        $this->assertSame('image/png', $file->get_mimetype());
    }

    /**
     * url() must build a deterministic pluginfile URL without needing a file to exist,
     * so the grid renderer never needs an extra File API call per card.
     *
     * @covers ::url
     */
    public function test_url_does_not_require_a_stored_file(): void {
        $this->resetAfterTest();
        $cmid = $this->create_activity();

        $url = appearance_image_store::url($cmid);

        $this->assertStringContainsString('format_smartcards', $url->out(false));
        $this->assertStringContainsString('cardimage', $url->out(false));
        $this->assertStringNotContainsString('rev=', $url->out(false));
    }

    /**
     * A $rev value must change the URL, so a replaced image never keeps serving a
     * browser-cached copy of the old one at an unchanged URL (the fixed filename/itemid
     * pluginfile URL is otherwise byte-identical before and after a re-upload).
     *
     * @covers ::url
     */
    public function test_url_with_rev_changes_when_rev_changes(): void {
        $this->resetAfterTest();
        $cmid = $this->create_activity();

        $firstid  = appearance_image_store::store($cmid, self::TINY_PNG_BASE64);
        $secondid = appearance_image_store::store($cmid, self::TINY_PNG_BASE64);

        $firsturl  = appearance_image_store::url($cmid, $firstid)->out(false);
        $secondurl = appearance_image_store::url($cmid, $secondid)->out(false);

        $this->assertStringContainsString('rev=' . $firstid, $firsturl);
        $this->assertStringContainsString('rev=' . $secondid, $secondurl);
        $this->assertNotSame($firsturl, $secondurl);
    }

    /**
     * Storing a new image for the same module must replace the old one, not create a
     * second file alongside it.
     *
     * @covers ::store
     */
    public function test_store_replaces_the_previous_image(): void {
        $this->resetAfterTest();
        $cmid = $this->create_activity();

        $firstid = appearance_image_store::store($cmid, self::TINY_PNG_BASE64);
        $secondid = appearance_image_store::store($cmid, self::TINY_PNG_BASE64);

        $this->assertNotSame($firstid, $secondid);
        $file = appearance_image_store::resolve_for_serving($cmid, 'cardimage');
        $this->assertSame($secondid, (int)$file->get_id());
    }

    /**
     * delete() must remove the stored image; calling it again (or when nothing was ever
     * stored) must be a harmless no-op.
     *
     * @covers ::delete
     */
    public function test_delete_removes_the_image_and_is_idempotent(): void {
        $this->resetAfterTest();
        $cmid = $this->create_activity();
        appearance_image_store::store($cmid, self::TINY_PNG_BASE64);

        appearance_image_store::delete($cmid);
        $this->assertNull(appearance_image_store::resolve_for_serving($cmid, 'cardimage'));

        // Deleting again, with nothing left to delete, must not throw.
        appearance_image_store::delete($cmid);
        $this->assertNull(appearance_image_store::resolve_for_serving($cmid, 'cardimage'));
    }

    /**
     * Malformed base64 must be rejected before any File API write is attempted.
     *
     * @covers ::store
     */
    public function test_store_rejects_invalid_base64(): void {
        $this->resetAfterTest();
        $cmid = $this->create_activity();

        $this->expectException(invalid_parameter_exception::class);
        appearance_image_store::store($cmid, 'not valid base64!!!');
    }

    /**
     * Well-formed base64 that does not decode to a real image must be rejected.
     *
     * @covers ::store
     */
    public function test_store_rejects_non_image_data(): void {
        $this->resetAfterTest();
        $cmid = $this->create_activity();

        $this->expectException(invalid_parameter_exception::class);
        appearance_image_store::store($cmid, base64_encode('this is definitely not an image'));
    }

    /**
     * An upload larger than the configured maximum must be rejected, even before the
     * bytes are checked for being a valid image.
     *
     * @covers ::store
     */
    public function test_store_rejects_oversized_upload(): void {
        $this->resetAfterTest();
        $cmid = $this->create_activity();

        $oversized = str_repeat('a', appearance_image_store::MAX_UPLOAD_BYTES + 1);

        $this->expectException(invalid_parameter_exception::class);
        appearance_image_store::store($cmid, base64_encode($oversized));
    }

    /**
     * resolve_for_serving() must reject any file area other than its own.
     *
     * @covers ::resolve_for_serving
     */
    public function test_resolve_for_serving_rejects_unknown_filearea(): void {
        $this->resetAfterTest();
        $cmid = $this->create_activity();
        appearance_image_store::store($cmid, self::TINY_PNG_BASE64);

        $this->assertNull(appearance_image_store::resolve_for_serving($cmid, 'somethingelse'));
    }
}
