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

use format_smartcards\local\appearance_image_store;
use format_smartcards\local\appearance_repository;

/**
 * Restores format_smartcards_appearance rows backed up by
 * backup_format_smartcards_plugin, one section/module at a time.
 *
 * $this->task->get_sectionid()/get_moduleid() already return the *new* id by the time
 * process_section_appearance()/process_activity_appearance() fire — unlike a standalone
 * cross-referencing table, there is no course_module-not-restored-yet ordering problem
 * to defer around here (see CLAUDE.md's Backup and restore section on that general
 * hazard; it does not apply to this format-plugin extension point).
 *
 * The section and activity cases differ in how their card image file gets restored:
 * - Section images live in the *course* context, itemid = sectionid. add_related_files()
 *   resolves the new course context via the standard 'context' backup_ids mapping
 *   without any trouble, because the course-level restore step that registers it always
 *   runs before any section is processed — the same reasoning
 *   format_tiles::backup_format_tiles_plugin uses for its own (proven, shipped) section
 *   photo restore.
 * - Activity images live in the module's own context, but this format's plugin
 *   structure attaches to module.xml (a separate, *earlier* step than the module's own
 *   <activity>/<modulename>.xml content, where core registers the module's old->new
 *   context mapping via restore_activity_structure_step::process_activity()). By the
 *   time our data here is processed, that mapping does not exist yet, so
 *   add_related_files()'s usual resolution throws restore_dbops_exception
 *   ('unknown_context_mapping') — confirmed by actually hitting it, not assumed.
 *   Deferring to after_execute_module() does not help either: that hook still fires
 *   within the same (too-early) module.xml step. The fix is to sidestep the mapping
 *   table entirely: backup_format_smartcards_plugin captures the module's own *old*
 *   contextid explicitly (cheap — the module context already exists on the source
 *   course), and restore_dbops::send_files_to_pool() is called directly (bypassing
 *   add_related_files()'s wrapper, which has no way to pass a forced context) with the
 *   *new* context computed directly from the already-resolved new cmid — exactly the
 *   sanctioned "forced new context" escape hatch send_files_to_pool() documents for
 *   components whose context mapping cannot be relied on.
 *
 * For type=image rows, the card image file is restored *before* the appearance row is
 * saved, then the row's value is set to the freshly restored file's own real id — never
 * the pre-restore id carried in the backup XML, which is stale the moment the file is
 * re-created with a new id in the target site's File API. This sidesteps the
 * "read the id back out afterwards" dance format_tiles's own restore class needs
 * (restore_format_tiles_plugin::update_file_records_sections()) by simply resolving the
 * real id before the row is ever written.
 *
 * Both rows are saved via appearance_repository's own save_for_section()/
 * save_for_activity() (not a raw insert_record()) so a restore into an *existing*
 * course whose target section/module already has an appearance (a "merge" restore, not
 * a fresh duplicate) updates that row instead of violating the
 * contextlevel+itemid unique key.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_format_smartcards_plugin extends restore_format_plugin {
    /**
     * Declares the restore path for one section's appearance row.
     *
     * @return array<restore_path_element>
     */
    protected function define_section_plugin_structure(): array {
        return [new restore_path_element('section_appearance', $this->get_pathfor('/section_appearance'))];
    }

    /**
     * Declares the restore path for one module's appearance row.
     *
     * @return array<restore_path_element>
     */
    protected function define_module_plugin_structure(): array {
        return [new restore_path_element('activity_appearance', $this->get_pathfor('/activity_appearance'))];
    }

    /**
     * Restores one section's appearance row, remapping its card image (if any) from
     * the old section id to the new one.
     *
     * @param array $data Backed-up row, including the pre-restore section id as 'itemid'.
     * @return void
     */
    public function process_section_appearance(array $data): void {
        $data = (object)$data;
        $oldsectionid = (int)$data->itemid;
        $newsectionid = (int)$this->task->get_sectionid();
        $courseid = (int)$this->task->get_courseid();

        // Registered under a plugin-specific name (not the generic 'itemid' some
        // third-party formats reuse) to rule out any accidental cross-component
        // collision in the shared backup_ids mapping table.
        $this->set_mapping('format_smartcards_sectionitemid', $oldsectionid, $newsectionid, true);
        $this->add_related_files('format_smartcards', 'sectioncardimage', 'format_smartcards_sectionitemid');

        $value = $data->value;
        if ($data->type === appearance_repository::TYPE_IMAGE) {
            $file = appearance_image_store::resolve_for_serving_section($newsectionid, $courseid, 'sectioncardimage');
            if ($file === null) {
                // The row said type=image but no file actually came through the backup
                // archive (e.g. an old/foreign backup) — nothing sane to point value at.
                return;
            }
            $value = (string)$file->get_id();
        }

        (new appearance_repository())->save_for_section(
            $newsectionid,
            $data->type,
            $value,
            $data->bgcolor,
            $data->labelcolor,
            $data->labelfont,
            $data->iconcolor ?? null,
        );
    }

    /**
     * Restores one module's appearance row, remapping its card image (if any) to the
     * module's own (already new) context — see this class's docblock for why this
     * cannot go through add_related_files()'s usual mapping-based resolution.
     *
     * @param array $data Backed-up row, including the pre-restore context id as 'oldcontextid'.
     * @return void
     */
    public function process_activity_appearance(array $data): void {
        $data = (object)$data;
        $newcmid = (int)$this->task->get_moduleid();

        $newcontextid = \context_module::instance($newcmid)->id;
        \restore_dbops::send_files_to_pool(
            $this->task->get_basepath(),
            $this->get_restoreid(),
            'format_smartcards',
            'cardimage',
            (int)$data->oldcontextid,
            $this->task->get_userid(),
            null,
            null,
            $newcontextid
        );

        $value = $data->value;
        if ($data->type === appearance_repository::TYPE_IMAGE) {
            $file = appearance_image_store::resolve_for_serving($newcmid, 'cardimage');
            if ($file === null) {
                return;
            }
            $value = (string)$file->get_id();
        }

        (new appearance_repository())->save_for_activity(
            $newcmid,
            $data->type,
            $value,
            $data->bgcolor,
            $data->labelcolor,
            $data->labelfont,
            $data->iconcolor ?? null,
        );
    }
}
