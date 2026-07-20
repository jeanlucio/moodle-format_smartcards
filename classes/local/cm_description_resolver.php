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

use cm_info;

/**
 * Resolves the rendered "Display description on course page" text for activities that
 * have it enabled (cm_info::$showdescription), for the status sheet's description
 * section — the card grid itself has no room for inline description text, unlike a
 * standard Moodle course page.
 *
 * Also covers modules that opt out of the standard link/card entirely via
 * cm_info::set_custom_cmlist_item() (e.g. mod_label, whose whole purpose is to show its
 * content inline instead of behind a link). Those have no showdescription setting and no
 * URL to open, so without this they would render as a dead card with nothing to tap.
 * Their content is already resolved and cached on the cm itself
 * (cm_info::get_formatted_content()), so no extra query is needed for them.
 *
 * cm_info::$showdescription is already a cached field (no query needed to read it), but
 * the actual description text lives on each module's own instance table, so
 * resolve_many() groups the activities that need it by modname and issues one bulk
 * query per distinct module type instead of one query per activity.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cm_description_resolver {
    /**
     * Resolves rendered descriptions for every activity with showdescription enabled, plus
     * every activity with a custom cmlist item (see class docblock).
     *
     * @param cm_info[] $cms Course modules to check.
     * @return array<int, string> Rendered description HTML keyed by cmid; activities with
     *                            neither showdescription nor a custom cmlist item, or with
     *                            empty content either way, are simply absent from the array.
     */
    public static function resolve_many(array $cms): array {
        global $DB;

        $descriptions = [];
        $instanceidsbymodname = [];
        foreach ($cms as $cm) {
            if ($cm->has_custom_cmlist_item()) {
                $content = $cm->get_formatted_content(['overflowdiv' => true]);
                if ((string) $content !== '') {
                    $descriptions[$cm->id] = $content;
                }
                continue;
            }
            if (!empty($cm->showdescription)) {
                $instanceidsbymodname[$cm->modname][(int)$cm->instance] = $cm->id;
            }
        }

        foreach ($instanceidsbymodname as $modname => $instancetocmid) {
            $records = $DB->get_records_list($modname, 'id', array_keys($instancetocmid), '', 'id, intro, introformat');
            foreach ($records as $instanceid => $record) {
                if ((string)$record->intro === '') {
                    continue;
                }
                $cmid = $instancetocmid[$instanceid];
                $descriptions[$cmid] = format_module_intro($modname, $record, $cmid);
            }
        }

        return $descriptions;
    }

    /**
     * Resolves the rendered description of a single activity, for callers that only
     * ever handle one cm (e.g. the appearance-save web service re-rendering one card).
     *
     * @param cm_info $cm Course module to check.
     * @return string Rendered description HTML, or '' when not applicable.
     */
    public static function resolve_one(cm_info $cm): string {
        return self::resolve_many([$cm])[$cm->id] ?? '';
    }
}
