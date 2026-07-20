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
 * Tests for the SmartCards appearance_style_resolver, focused on
 * resolve_titlestyle()/resolve_section_titlestyle() — in particular that the two fall
 * back to two genuinely independent pairs of course format options (activity vs
 * section defaults), never to each other's.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \format_smartcards\local\appearance_style_resolver
 */
final class appearance_style_resolver_test extends \advanced_testcase {
    /**
     * An item's own labelcolor/labelfont must win over the course's activity default.
     *
     * @covers ::resolve_titlestyle
     */
    public function test_resolve_titlestyle_prefers_the_items_own_colour_and_font(): void {
        $this->resetAfterTest();

        $item = new appearance(
            1,
            'activity',
            1,
            appearance_repository::TYPE_DEFAULT,
            '',
            null,
            appearance_palette::LABEL_COLORS['red'],
            'baloo2',
            null,
            null
        );

        $titlestyle = appearance_style_resolver::resolve_titlestyle($item, [
            'defaultlabelcolor' => appearance_palette::LABEL_COLORS['blue'],
            'defaultlabelfont' => 'nunito',
        ]);

        $this->assertStringContainsString('color: ' . appearance_palette::LABEL_COLORS['red'], $titlestyle);
        $this->assertStringContainsString(appearance_palette::LABEL_FONTS['baloo2'], $titlestyle);
        $this->assertStringNotContainsString(appearance_palette::LABEL_COLORS['blue'], $titlestyle);
    }

    /**
     * With no item (or an item that sets neither colour nor font), the course's
     * defaultlabelcolor/defaultlabelfont must apply.
     *
     * @covers ::resolve_titlestyle
     */
    public function test_resolve_titlestyle_falls_back_to_the_activity_default(): void {
        $this->resetAfterTest();

        $titlestyle = appearance_style_resolver::resolve_titlestyle(null, [
            'defaultlabelcolor' => appearance_palette::LABEL_COLORS['green'],
            'defaultlabelfont' => 'fredoka',
        ]);

        $this->assertStringContainsString('color: ' . appearance_palette::LABEL_COLORS['green'], $titlestyle);
        $this->assertStringContainsString(appearance_palette::LABEL_FONTS['fredoka'], $titlestyle);
    }

    /**
     * With no item and no course default set at all, the style must be empty — never a
     * stray "color: ; font-family: ;" declaration.
     *
     * @covers ::resolve_titlestyle
     */
    public function test_resolve_titlestyle_is_empty_when_nothing_is_set(): void {
        $this->resetAfterTest();

        $this->assertSame('', appearance_style_resolver::resolve_titlestyle(null, []));
    }

    /**
     * resolve_section_titlestyle() must fall back to defaultsectionlabelcolor/font, the
     * section-scoped pair — never to the activity-scoped defaultlabelcolor/font, even
     * when those are the only ones set. This is the core guarantee behind letting a
     * course give sections a different accent than activities.
     *
     * @covers ::resolve_section_titlestyle
     */
    public function test_resolve_section_titlestyle_never_falls_back_to_the_activity_default(): void {
        $this->resetAfterTest();

        $titlestyle = appearance_style_resolver::resolve_section_titlestyle(null, [
            'defaultlabelcolor' => appearance_palette::LABEL_COLORS['blue'],
            'defaultlabelfont' => 'nunito',
        ]);

        $this->assertSame('', $titlestyle);
    }

    /**
     * With defaultsectionlabelcolor/font set, resolve_section_titlestyle() must apply
     * them — independent of whatever defaultlabelcolor/font the course also has.
     *
     * @covers ::resolve_section_titlestyle
     */
    public function test_resolve_section_titlestyle_uses_the_section_default(): void {
        $this->resetAfterTest();

        $titlestyle = appearance_style_resolver::resolve_section_titlestyle(null, [
            'defaultlabelcolor' => appearance_palette::LABEL_COLORS['blue'],
            'defaultlabelfont' => 'nunito',
            'defaultsectionlabelcolor' => appearance_palette::LABEL_COLORS['green'],
            'defaultsectionlabelfont' => 'comicneue',
        ]);

        $this->assertStringContainsString('color: ' . appearance_palette::LABEL_COLORS['green'], $titlestyle);
        $this->assertStringContainsString(appearance_palette::LABEL_FONTS['comicneue'], $titlestyle);
        $this->assertStringNotContainsString(appearance_palette::LABEL_COLORS['blue'], $titlestyle);
    }

    /**
     * A section's own appearance must still win over the section default, mirroring
     * resolve_titlestyle()'s same precedence for activities.
     *
     * @covers ::resolve_section_titlestyle
     */
    public function test_resolve_section_titlestyle_prefers_the_sections_own_colour(): void {
        $this->resetAfterTest();

        $item = new appearance(
            1,
            'section',
            1,
            appearance_repository::TYPE_DEFAULT,
            '',
            null,
            appearance_palette::LABEL_COLORS['orange'],
            null,
            null,
            null
        );

        $titlestyle = appearance_style_resolver::resolve_section_titlestyle($item, [
            'defaultsectionlabelcolor' => appearance_palette::LABEL_COLORS['green'],
        ]);

        $this->assertStringContainsString('color: ' . appearance_palette::LABEL_COLORS['orange'], $titlestyle);
    }

    /**
     * The $sectiondefaults flag on resolve() must switch which pair of format options
     * the returned titlestyle (array index 5) falls back to — section_card_builder
     * passes true (it builds the section's own card), card_builder never passes it
     * (activities always use the activity default).
     *
     * @covers ::resolve
     */
    public function test_resolve_sectiondefaults_flag_picks_the_right_titlestyle_pair(): void {
        global $PAGE;

        $this->resetAfterTest();
        $renderer = $PAGE->get_renderer('core');
        $formatoptions = [
            'defaultlabelcolor' => appearance_palette::LABEL_COLORS['blue'],
            'defaultsectionlabelcolor' => appearance_palette::LABEL_COLORS['green'],
        ];
        $imageurl = fn (int $fileid): string => '';

        $activityresult = appearance_style_resolver::resolve(null, $renderer, $formatoptions, $imageurl);
        $sectionresult = appearance_style_resolver::resolve(null, $renderer, $formatoptions, $imageurl, sectiondefaults: true);

        $this->assertStringContainsString('color: ' . appearance_palette::LABEL_COLORS['blue'], $activityresult[5]);
        $this->assertStringContainsString('color: ' . appearance_palette::LABEL_COLORS['green'], $sectionresult[5]);
    }
}
