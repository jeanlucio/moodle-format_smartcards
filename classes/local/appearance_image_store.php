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

use Closure;
use context_course;
use context_module;
use invalid_parameter_exception;
use moodle_url;
use stored_file;

/**
 * Stores, replaces and serves the single uploaded image of one activity's or one
 * section's card appearance (appearance_repository::TYPE_IMAGE).
 *
 * Activity images are scoped to the course module's own context (not the course
 * context), one fixed-name file per module, with itemid always 0 — the simplest File
 * API pattern for "at most one file per owner". This is a deliberate choice over the
 * course-context alternative: deleting a course module (or the whole course, which
 * deletes every module in it) already makes core delete every file stored under that
 * module's context, for every component — so no extra cleanup wiring is needed here on
 * top of the appearance-row cleanup in classes/observer.php and
 * classes/hook_listener.php. Only the "replace" case (a new image uploaded, or the type
 * changed away from image) needs an explicit delete, handled by
 * save_appearance::execute() via delete()/store().
 *
 * A section has no context of its own, so section images are scoped to the *course*
 * context instead, disambiguated by a section-only file area (SECTION_FILEAREA) plus
 * itemid = sectionid. Unlike the activity case, deleting a section does NOT delete the
 * course context (only deleting the whole course does), so the section-deletion
 * observer (classes/observer.php) must explicitly call delete_for_section() here, next
 * to its existing appearance-row cleanup — this is the one part of the section-image
 * story that cannot simply piggyback on a context being torn down for free.
 *
 * The uploaded bytes are never stored as-is: they are decoded through GD and
 * re-encoded as PNG before being written to the File API. This rules out SVG/polyglot
 * payloads outright (GD does not decode them) and strips any embedded metadata,
 * trading a small amount of CPU for a much smaller attack surface than trusting the
 * uploaded bytes directly.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class appearance_image_store {
    /** @var string File area holding the one card image of a course module. */
    private const FILEAREA = 'cardimage';

    /** @var string File area holding the one card image of a course section. */
    private const SECTION_FILEAREA = 'sectioncardimage';

    /** @var string Fixed filename every stored card image is saved as. */
    private const FILENAME = 'card.png';

    /** @var int Maximum accepted size, in bytes, of the decoded upload before re-encoding. */
    public const MAX_UPLOAD_BYTES = 1048576;

    /** @var int Maximum width/height, in pixels, of the re-encoded PNG. Larger uploads are scaled down. */
    private const MAX_DIMENSION = 512;

    /**
     * Stores a base64-encoded image as the card image of one course module, replacing
     * any image already stored for it.
     *
     * @param int $cmid Course module id that owns the image.
     * @param string $base64 Base64-encoded image bytes (no "data:" prefix).
     * @return int The id of the newly stored file, to keep in the appearance row's value.
     * @throws invalid_parameter_exception If the data is not a valid, small-enough image.
     */
    public static function store(int $cmid, string $base64): int {
        $png = self::decode_and_reencode($base64);

        self::delete($cmid);

        $fs = get_file_storage();
        $file = $fs->create_file_from_string(self::file_record($cmid), $png);

        return (int)$file->get_id();
    }

    /**
     * Stores a base64-encoded image as the card image of one course section, replacing
     * any image already stored for it.
     *
     * @param int $sectionid Section id that owns the image.
     * @param int $courseid Id of the course the section belongs to.
     * @param string $base64 Base64-encoded image bytes (no "data:" prefix).
     * @return int The id of the newly stored file, to keep in the appearance row's value.
     * @throws invalid_parameter_exception If the data is not a valid, small-enough image.
     */
    public static function store_for_section(int $sectionid, int $courseid, string $base64): int {
        $png = self::decode_and_reencode($base64);

        self::delete_for_section($sectionid, $courseid);

        $fs = get_file_storage();
        $file = $fs->create_file_from_string(self::file_record_for_section($sectionid, $courseid), $png);

        return (int)$file->get_id();
    }

    /**
     * Deletes the card image of one course module, if any. Safe to call when no image
     * was ever stored for it.
     *
     * @param int $cmid Course module id.
     * @return void
     */
    public static function delete(int $cmid): void {
        $context = context_module::instance($cmid, IGNORE_MISSING);
        if ($context === false) {
            // The module is already gone; core's own context deletion already took its
            // files with it (see class docblock), nothing left to clean up here.
            return;
        }

        get_file_storage()->delete_area_files($context->id, 'format_smartcards', self::FILEAREA);
    }

    /**
     * Deletes the card image of one course section, if any. Safe to call when no image
     * was ever stored for it.
     *
     * Unlike delete(), the course context is never gone just because the section is —
     * only deleting the whole course removes it — so this must be called explicitly by
     * the section-deletion observer (classes/observer.php) rather than relying on a
     * context teardown to take the file with it for free.
     *
     * @param int $sectionid Section id.
     * @param int $courseid Id of the course the section belongs to.
     * @return void
     */
    public static function delete_for_section(int $sectionid, int $courseid): void {
        $context = context_course::instance($courseid, IGNORE_MISSING);
        if ($context === false) {
            // The course is already gone; core's own context deletion already took its
            // files with it, nothing left to clean up here.
            return;
        }

        get_file_storage()->delete_area_files(
            $context->id,
            'format_smartcards',
            self::SECTION_FILEAREA,
            $sectionid
        );
    }

    /**
     * Returns the deterministic pluginfile URL of one course module's card image.
     *
     * Deliberately does not check whether the file actually exists: this is a pure URL
     * builder with no File API/DB call, so it can be used from the grid renderer for
     * every card without an N+1 query — callers only call it when the appearance row's
     * own type/value already say a file was stored.
     *
     * The URL is otherwise identical before and after re-uploading a replacement image
     * (fixed itemid/filename, one file per module), and send_stored_file() sends a long
     * browser cache lifetime for it — without a cache-busting rev, a browser that already
     * fetched the old image keeps showing it after a real, successful replacement. $rev
     * should be the appearance row's own value (the image's fileid, already in memory at
     * every real call site, and guaranteed to change on every store() call — see
     * store()'s docblock), never a fresh File API lookup just for this.
     *
     * @param int $cmid Course module id.
     * @param int $rev Cache-busting revision, appended as a query param when > 0.
     *                  0 (the default) omits it entirely, for callers with no fileid to
     *                  hand (e.g. building the URL before any file has been stored).
     * @return moodle_url
     */
    public static function url(int $cmid, int $rev = 0): moodle_url {
        $context = context_module::instance($cmid);

        $url = moodle_url::make_pluginfile_url(
            $context->id,
            'format_smartcards',
            self::FILEAREA,
            0,
            '/',
            self::FILENAME
        );
        if ($rev > 0) {
            $url->param('rev', $rev);
        }

        return $url;
    }

    /**
     * Returns the deterministic pluginfile URL of one course section's card image.
     *
     * Same reasoning as url(): no existence check, no File API call, safe to call for
     * every section card without an N+1 query.
     *
     * @param int $sectionid Section id.
     * @param int $courseid Id of the course the section belongs to.
     * @param int $rev Cache-busting revision, appended as a query param when > 0.
     * @return moodle_url
     */
    public static function url_for_section(int $sectionid, int $courseid, int $rev = 0): moodle_url {
        $context = context_course::instance($courseid);

        $url = moodle_url::make_pluginfile_url(
            $context->id,
            'format_smartcards',
            self::SECTION_FILEAREA,
            $sectionid,
            '/',
            self::FILENAME
        );
        if ($rev > 0) {
            $url->param('rev', $rev);
        }

        return $url;
    }

    /**
     * Resolves the stored_file to serve for one course module's card image, for
     * format_smartcards_pluginfile() in lib.php.
     *
     * The filename is never taken from the request: there is always exactly one
     * possible file per module (FILENAME, itemid 0), so looking it up by the fixed
     * name closes off any path-traversal surface in the requested URL's segments.
     *
     * @param int $cmid Course module id.
     * @param string $filearea File area requested.
     * @return stored_file|null The matching file, or null when not found.
     */
    public static function resolve_for_serving(int $cmid, string $filearea): ?stored_file {
        if ($filearea !== self::FILEAREA) {
            return null;
        }

        $context = context_module::instance($cmid);
        $file = get_file_storage()->get_file($context->id, 'format_smartcards', self::FILEAREA, 0, '/', self::FILENAME);
        if (!$file || $file->is_directory()) {
            return null;
        }

        return $file;
    }

    /**
     * Resolves the stored_file to serve for one course section's card image, for
     * format_smartcards_pluginfile() in lib.php.
     *
     * @param int $sectionid Section id.
     * @param int $courseid Id of the course the section belongs to.
     * @param string $filearea File area requested.
     * @return stored_file|null The matching file, or null when not found.
     */
    public static function resolve_for_serving_section(int $sectionid, int $courseid, string $filearea): ?stored_file {
        if ($filearea !== self::SECTION_FILEAREA) {
            return null;
        }

        $context = context_course::instance($courseid);
        $file = get_file_storage()->get_file(
            $context->id,
            'format_smartcards',
            self::SECTION_FILEAREA,
            $sectionid,
            '/',
            self::FILENAME
        );
        if (!$file || $file->is_directory()) {
            return null;
        }

        return $file;
    }

    /**
     * Builds the File API file record for one module's card image.
     *
     * @param int $cmid Course module id.
     * @return array<string, mixed>
     */
    private static function file_record(int $cmid): array {
        $context = context_module::instance($cmid);

        return [
            'contextid' => $context->id,
            'component' => 'format_smartcards',
            'filearea'  => self::FILEAREA,
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => self::FILENAME,
        ];
    }

    /**
     * Builds the File API file record for one section's card image.
     *
     * @param int $sectionid Section id.
     * @param int $courseid Id of the course the section belongs to.
     * @return array<string, mixed>
     */
    private static function file_record_for_section(int $sectionid, int $courseid): array {
        $context = context_course::instance($courseid);

        return [
            'contextid' => $context->id,
            'component' => 'format_smartcards',
            'filearea'  => self::SECTION_FILEAREA,
            'itemid'    => $sectionid,
            'filepath'  => '/',
            'filename'  => self::FILENAME,
        ];
    }

    /**
     * Decodes and validates a base64-encoded upload, then re-encodes it as a
     * size-capped PNG. Shared by store() and store_for_section().
     *
     * @param string $base64 Base64-encoded image bytes (no "data:" prefix).
     * @return string PNG-encoded bytes.
     * @throws invalid_parameter_exception If the data is not a valid, small-enough image.
     */
    private static function decode_and_reencode(string $base64): string {
        $raw = base64_decode($base64, true);
        if ($raw === false || $raw === '') {
            throw new invalid_parameter_exception('Image data is not valid base64');
        }
        if (strlen($raw) > self::MAX_UPLOAD_BYTES) {
            throw new invalid_parameter_exception('Image exceeds the maximum upload size');
        }

        return self::reencode_as_png($raw);
    }

    /**
     * Decodes, validates and re-encodes raw image bytes as a size-capped PNG.
     *
     * @param string $raw Decoded (binary) image bytes.
     * @return string PNG-encoded bytes.
     * @throws invalid_parameter_exception If $raw is not a decodable image.
     */
    private static function reencode_as_png(string $raw): string {
        $info = @getimagesizefromstring($raw);
        if ($info === false) {
            throw new invalid_parameter_exception('File is not a valid image');
        }

        // Reject implausibly large declared dimensions before GD allocates memory for
        // them: getimagesizefromstring() only reads the header, so a small file can
        // still claim an enormous canvas.
        [$width, $height] = $info;
        if ($width <= 0 || $height <= 0 || $width > 8000 || $height > 8000) {
            throw new invalid_parameter_exception('Image dimensions are not supported');
        }

        $source = @imagecreatefromstring($raw);
        if ($source === false) {
            throw new invalid_parameter_exception('File is not a supported image format');
        }

        $scale = min(1.0, self::MAX_DIMENSION / max($width, $height));
        $targetwidth = max(1, (int)round($width * $scale));
        $targetheight = max(1, (int)round($height * $scale));

        $target = imagecreatetruecolor($targetwidth, $targetheight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefill($target, 0, 0, $transparent);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetwidth, $targetheight, $width, $height);
        imagedestroy($source);

        ob_start();
        imagepng($target);
        $png = ob_get_clean();
        imagedestroy($target);

        if ($png === false || $png === '') {
            throw new invalid_parameter_exception('Failed to re-encode the uploaded image');
        }

        return $png;
    }

    /**
     * Resolves the "value" to persist for a save_appearance/save_section_appearance
     * call, handling TYPE_IMAGE's file lifecycle: a freshly uploaded image is stored
     * (replacing any previous one), an empty upload keeps the previously stored image,
     * and switching away from TYPE_IMAGE deletes the now-orphaned file.
     *
     * Shared by the activity and section web services so the two lifecycles (which
     * differ only in which store()/delete() variant they call) cannot drift apart.
     *
     * @param string $type Validated appearance type.
     * @param string $value Validated value param (emoji/icon name; ignored for images).
     * @param string $imagedata Base64-encoded image bytes, or '' when not (re)uploading.
     * @param appearance|null $existing The item's current appearance, if any, used to
     *        detect a type change away from image.
     * @param Closure $store Stores $imagedata and returns the new fileid, e.g.
     *                        fn (string $imagedata) => self::store($cmid, $imagedata).
     * @param Closure $delete Deletes the previously stored image, e.g.
     *                         fn () => self::delete($cmid).
     * @return string The value to persist in format_smartcards_appearance.value.
     * @throws invalid_parameter_exception If type is TYPE_IMAGE with nothing to store.
     */
    public static function resolve_saved_value(
        string $type,
        string $value,
        string $imagedata,
        ?appearance $existing,
        Closure $store,
        Closure $delete
    ): string {
        $waskeepingimage = $existing !== null && $existing->type === appearance_repository::TYPE_IMAGE;

        if ($type !== appearance_repository::TYPE_IMAGE) {
            if ($waskeepingimage) {
                $delete();
            }
            return $value;
        }

        if ($imagedata !== '') {
            return (string)$store($imagedata);
        }

        if ($waskeepingimage && $existing->value !== '') {
            return $existing->value;
        }

        throw new invalid_parameter_exception('An image must be uploaded');
    }
}
