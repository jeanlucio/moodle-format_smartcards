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
 * Upgrade steps for format_smartcards.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Performs plugin database upgrades.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool
 */
function xmldb_format_smartcards_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026071500) {
        $table = new xmldb_table('format_smartcards_appearance');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('contextlevel', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'activity');
            $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'icon');
            $table->add_field('value', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('bgcolor', XMLDB_TYPE_CHAR, '7', null, null);
            $table->add_field('labelcolor', XMLDB_TYPE_CHAR, '7', null, null);
            $table->add_field('labelfont', XMLDB_TYPE_CHAR, '20', null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('uq_contextlevel_itemid', XMLDB_INDEX_UNIQUE, ['contextlevel', 'itemid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071500, 'format', 'smartcards');
    }

    if ($oldversion < 2026071700) {
        $table = new xmldb_table('format_smartcards_appearance');
        $field = new xmldb_field('bgcolor', XMLDB_TYPE_CHAR, '11', null, null, null, null, 'value');

        if ($dbman->field_exists($table, $field)) {
            // Widened from 7 to 11 chars so bgcolor can also store the literal
            // 'transparent', alongside #RRGGBB.
            $dbman->change_field_precision($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026071700, 'format', 'smartcards');
    }

    if ($oldversion < 2026071800) {
        $table = new xmldb_table('format_smartcards_appearance');
        $field = new xmldb_field('iconcolor', XMLDB_TYPE_CHAR, '7', null, null, null, null, 'labelfont');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026071800, 'format', 'smartcards');
    }

    if ($oldversion < 2026072011) {
        $table = new xmldb_table('format_smartcards_appearance');
        $field = new xmldb_field('displaymode', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'iconcolor');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026072011, 'format', 'smartcards');
    }

    return true;
}
