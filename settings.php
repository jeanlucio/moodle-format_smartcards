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
 * Site-wide default settings for the SmartCards course format.
 *
 * Every setting here is also a per-course format option (see lib.php
 * course_format_options()); teachers can override the site default in their own
 * course's settings, same mechanism already used by every core course format.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use format_smartcards\local\appearance_palette;
use format_smartcards\local\appearance_repository;

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configselect(
        'format_smartcards/cardsize',
        new lang_string('cardsize', 'format_smartcards'),
        new lang_string('cardsize_desc', 'format_smartcards'),
        'small',
        [
            'small'  => new lang_string('cardsize_small', 'format_smartcards'),
            'medium' => new lang_string('cardsize_medium', 'format_smartcards'),
            'large'  => new lang_string('cardsize_large', 'format_smartcards'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_smartcards/showcardframe',
        new lang_string('showcardframe', 'format_smartcards'),
        new lang_string('showcardframe_desc', 'format_smartcards'),
        1
    ));

    $bgcoloroptions = [
        '' => new lang_string('appearance_defaultcolor', 'format_smartcards'),
        appearance_repository::BGCOLOR_TRANSPARENT => new lang_string('appearance_transparent', 'format_smartcards'),
    ];
    foreach (appearance_palette::LABEL_COLORS as $slug => $hex) {
        $bgcoloroptions[$hex] = ucfirst($slug) . " ($hex)";
    }
    $settings->add(new admin_setting_configselect(
        'format_smartcards/defaultbgcolor',
        new lang_string('defaultbgcolor', 'format_smartcards'),
        new lang_string('defaultbgcolor_desc', 'format_smartcards'),
        '',
        $bgcoloroptions
    ));

    $labelcoloroptions = ['' => new lang_string('appearance_defaultcolor', 'format_smartcards')];
    foreach (appearance_palette::LABEL_COLORS as $slug => $hex) {
        $labelcoloroptions[$hex] = ucfirst($slug) . " ($hex)";
    }
    $settings->add(new admin_setting_configselect(
        'format_smartcards/defaultlabelcolor',
        new lang_string('defaultlabelcolor', 'format_smartcards'),
        new lang_string('defaultlabelcolor_desc', 'format_smartcards'),
        '',
        $labelcoloroptions
    ));

    $labelfontoptions = ['' => new lang_string('appearance_labelfont_system', 'format_smartcards')];
    foreach (appearance_palette::LABEL_FONTS as $slug => $fontname) {
        $labelfontoptions[$slug] = $fontname;
    }
    $settings->add(new admin_setting_configselect(
        'format_smartcards/defaultlabelfont',
        new lang_string('defaultlabelfont', 'format_smartcards'),
        new lang_string('defaultlabelfont_desc', 'format_smartcards'),
        '',
        $labelfontoptions
    ));

    $settings->add(new admin_setting_configselect(
        'format_smartcards/navstyle',
        new lang_string('navstyle', 'format_smartcards'),
        new lang_string('navstyle_desc', 'format_smartcards'),
        'default',
        [
            'default'      => new lang_string('navstyle_default', 'format_smartcards'),
            'accordion'    => new lang_string('navstyle_accordion', 'format_smartcards'),
            'tabs'         => new lang_string('navstyle_tabs', 'format_smartcards'),
            'sticky'       => new lang_string('navstyle_sticky', 'format_smartcards'),
            'sectioncards' => new lang_string('navstyle_sectioncards', 'format_smartcards'),
            'trail'        => new lang_string('navstyle_trail', 'format_smartcards'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_smartcards/generalinstyle',
        new lang_string('generalinstyle', 'format_smartcards'),
        new lang_string('generalinstyle_desc', 'format_smartcards'),
        0
    ));

    $settings->add(new admin_setting_configselect(
        'format_smartcards/progressdisplay',
        new lang_string('progressdisplay', 'format_smartcards'),
        new lang_string('progressdisplay_desc', 'format_smartcards'),
        'none',
        [
            'none'    => new lang_string('progressdisplay_none', 'format_smartcards'),
            'count'   => new lang_string('progressdisplay_count', 'format_smartcards'),
            'percent' => new lang_string('progressdisplay_percent', 'format_smartcards'),
        ]
    ));
}
