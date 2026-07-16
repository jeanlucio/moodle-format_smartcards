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

/**
 * Tests for the SmartCards cm_description_resolver.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\local\cm_description_resolver
 */
final class cm_description_resolver_test extends \advanced_testcase {
    /**
     * An activity with showdescription enabled and a non-empty intro must resolve to its
     * rendered description.
     *
     * @covers ::resolve_many
     * @covers ::resolve_one
     */
    public function test_resolves_description_when_showdescription_is_enabled(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $page      = $generator->create_module('page', [
            'course' => $course->id,
            'showdescription' => 1,
            'intro' => '<p>Read this first.</p>',
            'introformat' => FORMAT_HTML,
        ]);

        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        $descriptions = cm_description_resolver::resolve_many([$cm]);
        $this->assertArrayHasKey($cm->id, $descriptions);
        $this->assertStringContainsString('Read this first.', $descriptions[$cm->id]);

        $this->assertStringContainsString('Read this first.', cm_description_resolver::resolve_one($cm));
    }

    /**
     * An activity with showdescription disabled must be entirely absent from the result,
     * regardless of whether it has an intro.
     *
     * @covers ::resolve_many
     * @covers ::resolve_one
     */
    public function test_skips_activities_with_showdescription_disabled(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $page      = $generator->create_module('page', [
            'course' => $course->id,
            'showdescription' => 0,
            'intro' => '<p>Hidden intro.</p>',
            'introformat' => FORMAT_HTML,
        ]);

        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        $this->assertArrayNotHasKey($cm->id, cm_description_resolver::resolve_many([$cm]));
        $this->assertSame('', cm_description_resolver::resolve_one($cm));
    }

    /**
     * An activity with showdescription enabled but an empty intro must be absent from the
     * result — an empty description section would be pointless clutter in the sheet.
     *
     * @covers ::resolve_many
     */
    public function test_skips_activities_with_an_empty_intro(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $page      = $generator->create_module('page', [
            'course' => $course->id,
            'showdescription' => 1,
            'introformat' => FORMAT_HTML,
        ]);
        // The base module generator fills empty($record->intro) with a default "Test page N"
        // text, so an actually-empty intro can only be set directly on the instance table.
        $DB->set_field('page', 'intro', '', ['id' => $page->id]);
        rebuild_course_cache($course->id, true);

        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        $this->assertArrayNotHasKey($cm->id, cm_description_resolver::resolve_many([$cm]));
    }

    /**
     * Activities from different module types must each be resolved against their own
     * instance table, grouped into one bulk query per distinct modname.
     *
     * @covers ::resolve_many
     */
    public function test_resolves_across_multiple_module_types(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $page      = $generator->create_module('page', [
            'course' => $course->id,
            'showdescription' => 1,
            'intro' => '<p>Page intro.</p>',
            'introformat' => FORMAT_HTML,
        ]);
        $forum = $generator->create_module('forum', [
            'course' => $course->id,
            'showdescription' => 1,
            'intro' => '<p>Forum intro.</p>',
            'introformat' => FORMAT_HTML,
        ]);

        $modinfo = get_fast_modinfo($course);
        $cms     = [$modinfo->get_cm($page->cmid), $modinfo->get_cm($forum->cmid)];

        $descriptions = cm_description_resolver::resolve_many($cms);

        $this->assertStringContainsString('Page intro.', $descriptions[$page->cmid]);
        $this->assertStringContainsString('Forum intro.', $descriptions[$forum->cmid]);
    }
}
