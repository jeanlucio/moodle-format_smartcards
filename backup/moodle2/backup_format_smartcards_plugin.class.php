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
 * Backs up format_smartcards_appearance rows, split by contextlevel: activity-level
 * rows are attached to the owning course module's own backup structure, section-level
 * rows to the owning section's. This is the standard course-format backup extension
 * point (backup_format_plugin), the same one format_tiles/format_ludilearn already use
 * for their own per-section/per-module custom tables.
 *
 * The one column that needs special handling is value when type=image: it holds the
 * File API fileid of the uploaded card image (appearance_image_store), so the image
 * itself is included via annotate_files() alongside the row data. Section images live
 * in the *course* context with itemid = sectionid (appearance_image_store::
 * SECTION_FILEAREA / file_record_for_section()) — itemid is declared as a backup
 * attribute here precisely so annotate_files() can key each file to its own row's
 * itemid, mirroring format_tiles's backup_format_tiles_plugin::
 * define_section_plugin_structure() (same "course context, itemid = sectionid" shape).
 *
 * Activity images are different: this format's own plugin structure attaches to
 * module.xml (backup_module_structure_step, via add_plugin_structure('format', ...)),
 * which is a *separate*, *earlier* backup/restore step than the module's own
 * <activity>/<modulename>.xml content — the step where core registers the module's
 * old->new context mapping (restore_activity_structure_step::process_activity()). On
 * restore, our data is already processed by the time that mapping exists, so
 * annotate_files()'s usual "resolve the new context via the mapping table" path (used
 * successfully for the section case above) fails with unknown_context_mapping. The
 * fix: capture the module's own *old* contextid explicitly here, at backup time —
 * cheap, since the module context already exists on the source course — so restore
 * never needs that mapping at all (see restore_format_smartcards_plugin's matching
 * comment for the read side of this).
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_format_smartcards_plugin extends backup_format_plugin {
    /** @var string[] format_smartcards_appearance columns backed up, besides id/itemid. */
    private const APPEARANCE_COLUMNS = [
        'type', 'value', 'bgcolor', 'labelcolor', 'labelfont', 'iconcolor', 'timecreated', 'timemodified',
    ];

    /**
     * Attaches the section-level appearance row (if any) of the section currently being
     * backed up, plus its card image file when type=image.
     *
     * @return backup_plugin_element
     */
    protected function define_section_plugin_structure(): backup_plugin_element {
        $plugin = $this->get_plugin_element(null, $this->get_format_condition(), 'smartcards');
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $appearance = new backup_nested_element('section_appearance', ['id', 'itemid'], self::APPEARANCE_COLUMNS);
        $pluginwrapper->add_child($appearance);

        $appearance->set_source_sql(
            "SELECT * FROM {format_smartcards_appearance} WHERE contextlevel = 'section' AND itemid = :sectionid",
            ['sectionid' => backup::VAR_SECTIONID]
        );

        // Itemid (declared above) equals the section's own id: the same value
        // appearance_image_store::file_record_for_section() uses as the File API itemid
        // for the card image stored in the *course* context.
        $appearance->annotate_files('format_smartcards', 'sectioncardimage', 'itemid');

        return $plugin;
    }

    /**
     * Attaches the activity-level appearance row (if any) of the course module
     * currently being backed up, plus its card image file when type=image.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure(): backup_plugin_element {
        $plugin = $this->get_plugin_element(null, $this->get_format_condition(), 'smartcards');
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $appearance = new backup_nested_element(
            'activity_appearance',
            ['id', 'oldcontextid'],
            self::APPEARANCE_COLUMNS
        );
        $pluginwrapper->add_child($appearance);

        // Oldcontextid: see this class's docblock — captured explicitly so restore
        // never needs the module's old->new context mapping, which does not exist yet
        // at the point our data is processed.
        $appearance->set_source_sql(
            "SELECT a.*, ctx.id AS oldcontextid
               FROM {format_smartcards_appearance} a
               JOIN {context} ctx ON ctx.contextlevel = " . CONTEXT_MODULE . " AND ctx.instanceid = a.itemid
              WHERE a.contextlevel = 'activity' AND a.itemid = :cmid",
            ['cmid' => backup::VAR_MODID]
        );

        // No itemid name here: appearance_image_store::file_record() always uses a
        // fixed itemid of 0 for activity card images, so only the context needs to
        // carry over — handled explicitly on restore via oldcontextid above, not by
        // annotate_files()'s usual per-row itemid remap.
        $appearance->annotate_files('format_smartcards', 'cardimage', null);

        return $plugin;
    }
}
