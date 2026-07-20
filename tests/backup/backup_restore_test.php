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

namespace format_smartcards\backup;

use backup;
use backup_controller;
use format_smartcards\local\appearance_image_store;
use format_smartcards\local\appearance_repository;
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * End-to-end backup/restore tests, exercising the real backup_controller/
 * restore_controller pipeline (never a hand-rolled call into the backup/restore plugin
 * classes directly) — the same approach core's own moodle2_course_format_test uses,
 * since only the real controllers exercise the full XML round trip (source SQL,
 * annotate_files(), restore_path_element dispatch, add_related_files()) that a more
 * direct test would silently skip past.
 *
 * Marked coversNothing below: this integration test spans backup_format_smartcards_plugin,
 * restore_format_smartcards_plugin, appearance_repository and appearance_image_store
 * together, so no single covers target would honestly describe what is exercised.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class backup_restore_test extends \advanced_testcase {
    /** @var string Base64-encoded 1x1 transparent PNG, small enough to always pass the size check. */
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    /**
     * Non-image appearance (emoji for the activity, icon for the section) must survive
     * a backup/restore round trip into a brand new course, remapped to the new cmid/
     * sectionid.
     */
    public function test_backup_and_restore_preserves_activity_and_section_appearance(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $cm = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);
        $sectionid = (int)get_fast_modinfo($course)->get_section_info(1)->id;

        $repository = new appearance_repository();
        $repository->save_for_activity(
            $cm->cmid,
            appearance_repository::TYPE_EMOJI,
            '🎉',
            '#ffffff',
            '#c62828',
            null,
            '#123abc',
            appearance_repository::DISPLAYMODE_TILE
        );
        $repository->save_for_section(
            $sectionid,
            appearance_repository::TYPE_ICON,
            'book',
            null,
            '#1565c0',
            null,
            '#abc123'
        );

        $newcourseid = $this->backup_and_restore($course);

        $newmodinfo = get_fast_modinfo($newcourseid);
        $newcms = array_values($newmodinfo->get_cms());
        $this->assertCount(1, $newcms);
        $newcmid = (int)$newcms[0]->id;
        $newsectionid = (int)$newmodinfo->get_section_info(1)->id;

        $this->assertNotSame($cm->cmid, $newcmid);
        $this->assertNotSame($sectionid, $newsectionid);

        $newrepository = new appearance_repository();

        $activityappearance = $newrepository->get_for_activity($newcmid);
        $this->assertNotNull($activityappearance);
        $this->assertSame(appearance_repository::TYPE_EMOJI, $activityappearance->type);
        $this->assertSame('🎉', $activityappearance->value);
        $this->assertSame('#ffffff', $activityappearance->bgcolor);
        $this->assertSame('#c62828', $activityappearance->labelcolor);
        $this->assertSame('#123abc', $activityappearance->iconcolor);
        $this->assertSame(appearance_repository::DISPLAYMODE_TILE, $activityappearance->displaymode);

        $sectionappearance = $newrepository->get_for_section($newsectionid);
        $this->assertNotNull($sectionappearance);
        $this->assertSame(appearance_repository::TYPE_ICON, $sectionappearance->type);
        $this->assertSame('book', $sectionappearance->value);
        $this->assertSame('#1565c0', $sectionappearance->labelcolor);
        $this->assertSame('#abc123', $sectionappearance->iconcolor);
    }

    /**
     * A type=image appearance must carry its actual card image file through the backup
     * archive and end up pointing value at the *restored* file's own (necessarily new)
     * id, for both the activity and section case.
     */
    public function test_backup_and_restore_preserves_activity_and_section_images(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'smartcards', 'numsections' => 1]);
        $cm = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);
        $sectionid = (int)get_fast_modinfo($course)->get_section_info(1)->id;

        $activityfileid = appearance_image_store::store($cm->cmid, self::TINY_PNG_BASE64);
        $sectionfileid = appearance_image_store::store_for_section($sectionid, $course->id, self::TINY_PNG_BASE64);

        $repository = new appearance_repository();
        $repository->save_for_activity(
            $cm->cmid,
            appearance_repository::TYPE_IMAGE,
            (string)$activityfileid,
            null,
            null,
            null
        );
        $repository->save_for_section(
            $sectionid,
            appearance_repository::TYPE_IMAGE,
            (string)$sectionfileid,
            null,
            null,
            null
        );

        $newcourseid = $this->backup_and_restore($course);

        $newmodinfo = get_fast_modinfo($newcourseid);
        $newcms = array_values($newmodinfo->get_cms());
        $newcmid = (int)$newcms[0]->id;
        $newsectionid = (int)$newmodinfo->get_section_info(1)->id;

        $newrepository = new appearance_repository();

        $activityappearance = $newrepository->get_for_activity($newcmid);
        $this->assertNotNull($activityappearance);
        $this->assertSame(appearance_repository::TYPE_IMAGE, $activityappearance->type);
        $this->assertNotSame((string)$activityfileid, $activityappearance->value);

        $activityfile = appearance_image_store::resolve_for_serving($newcmid, 'cardimage');
        $this->assertNotNull($activityfile);
        $this->assertSame((string)$activityfile->get_id(), $activityappearance->value);
        $this->assertGreaterThan(0, $activityfile->get_filesize());

        $sectionappearance = $newrepository->get_for_section($newsectionid);
        $this->assertNotNull($sectionappearance);
        $this->assertSame(appearance_repository::TYPE_IMAGE, $sectionappearance->type);
        $this->assertNotSame((string)$sectionfileid, $sectionappearance->value);

        $sectionfile = appearance_image_store::resolve_for_serving_section($newsectionid, $newcourseid, 'sectioncardimage');
        $this->assertNotNull($sectionfile);
        $this->assertSame((string)$sectionfile->get_id(), $sectionappearance->value);
        $this->assertGreaterThan(0, $sectionfile->get_filesize());
    }

    /**
     * Backs a course up and restores it, mirroring core's own
     * \core_backup\moodle2_course_format_test::backup_and_restore() helper.
     *
     * @param \stdClass $srccourse Course object to back up.
     * @param \stdClass|null $dstcourse Course object to restore into, or null for a new one.
     * @param int $target Target course mode (backup::TARGET_xx).
     * @return int Id of the restored course.
     */
    private function backup_and_restore(
        \stdClass $srccourse,
        ?\stdClass $dstcourse = null,
        int $target = backup::TARGET_NEW_COURSE
    ): int {
        global $USER, $CFG;

        // Turn off file logging, otherwise it can't delete the file (Windows) — same
        // reasoning as core's own helper.
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $srccourse->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        if ($dstcourse !== null) {
            $newcourseid = $dstcourse->id;
        } else {
            $newcourseid = restore_dbops::create_new_course(
                $srccourse->fullname,
                $srccourse->shortname . '_2',
                $srccourse->category
            );
        }

        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            $target
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
