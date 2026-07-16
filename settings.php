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
}
