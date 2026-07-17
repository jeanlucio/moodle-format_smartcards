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
 * Main class for the SmartCards course format.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/lib.php');

use core\output\inplace_editable;
use format_smartcards\local\appearance_image_store;
use format_smartcards\local\appearance_palette;
use format_smartcards\local\appearance_repository;

/**
 * SmartCards course format class.
 *
 * Renders each section as a grid of activity cards/buttons, reusing
 * cm_info availability data instead of the stealth-activity workaround.
 *
 * @package    format_smartcards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_smartcards extends core_courseformat\base {
    /**
     * Returns true because this format uses sections.
     *
     * @return bool
     */
    public function uses_sections(): bool {
        return true;
    }

    /**
     * Returns true to show the course index sidebar.
     *
     * @return bool
     */
    public function uses_course_index(): bool {
        return true;
    }

    /**
     * Returns false — activity cards are laid out in a grid, not indented.
     *
     * @return bool
     */
    public function uses_indentation(): bool {
        return false;
    }

    /**
     * Supports the Moodle 4+ reactive component system (drag-and-drop editing).
     *
     * @return bool
     */
    public function supports_components(): bool {
        return true;
    }

    /**
     * Returns the page title for this format.
     *
     * @return string
     */
    public function page_title(): string {
        return get_string('sectionoutline');
    }

    /**
     * Returns the display name of the given section.
     *
     * Uses the section name set by the teacher, or falls back to the default.
     *
     * @param int|stdClass $section Section object or section number.
     * @return string
     */
    public function get_section_name($section): string {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string(
                $section->name,
                true,
                ['context' => context_course::instance($this->courseid)]
            );
        }
        return $this->get_default_section_name($section);
    }

    /**
     * Returns the default section name for the SmartCards format.
     *
     * Section 0 uses the key 'section0name'; all others use 'sectionname'.
     *
     * @param int|stdClass $section Section object or section number.
     * @return string
     */
    public function get_default_section_name($section): string {
        $section = $this->get_section($section);
        if ($section->sectionnum == 0) {
            return get_string('section0name', 'format_smartcards');
        }
        return get_string('sectionname', 'format_smartcards') . ' ' . $section->sectionnum;
    }

    /**
     * Returns the URL to view the specified section.
     *
     * @param int|stdClass $section Section object or number.
     * @param array $options Optional parameters (navigation, sr).
     * @return moodle_url
     */
    public function get_view_url($section, $options = []): moodle_url {
        $course = $this->get_course();

        if (array_key_exists('sr', $options) && !is_null($options['sr'])) {
            $sectionno = $options['sr'];
        } else if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }

        if (
            (!empty($options['navigation']) || array_key_exists('sr', $options))
            && $sectionno !== null
        ) {
            $sectioninfo = $this->get_section($sectionno);
            return new moodle_url('/course/section.php', ['id' => $sectioninfo->id]);
        }

        return new moodle_url('/course/view.php', ['id' => $course->id]);
    }

    /**
     * Returns AJAX support details.
     *
     * @return stdClass
     */
    public function supports_ajax(): stdClass {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Allows deletion of sections.
     *
     * @param int|stdClass|section_info $section Section to evaluate.
     * @return bool
     */
    public function can_delete_section($section): bool {
        return true;
    }

    /**
     * Returns whether this format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news(): bool {
        return true;
    }

    /**
     * Disallows stealth module visibility.
     *
     * SmartCards replaces the "available but not shown" (stealth) workaround
     * with native availability badges, so stealth activities are never needed.
     *
     * @param stdClass|cm_info $cm Course module.
     * @param stdClass|section_info $section Section where the module resides.
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section): bool {
        return false;
    }

    /**
     * Loads course sections into the navigation tree.
     *
     * @param global_navigation $navigation Navigation object.
     * @param navigation_node $node The course navigation node.
     * @return void
     */
    public function extend_course_navigation($navigation, navigation_node $node): void {
        global $PAGE;
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if (
                $selectedsection !== null
                && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0')
                && $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
            ) {
                $navigation->includesectionnum = $selectedsection;
            }
        }
        parent::extend_course_navigation($navigation, $node);
    }

    /**
     * Loads the appearance picker AMD module whenever the user can manage card
     * appearance, so its "Card appearance" entry in the per-activity edit menu
     * (added by content\cm\controlmenu, only rendered while editing) is functional.
     *
     * Called on every course page for this format (moodle_page::set_course()), unlike
     * content::export_for_template(), which only runs its custom logic outside edit
     * mode — this is the one hook shared by both modes.
     *
     * @param moodle_page $page Instance of page calling set_course.
     * @return void
     */
    public function page_set_course($page): void {
        parent::page_set_course($page);

        if (has_capability('format/smartcards:manageappearance', $this->get_context())) {
            $page->requires->js_call_amd('format_smartcards/appearance_picker', 'init');
        }
    }

    /**
     * Returns the format options for this course.
     *
     * Six options, each with a site-wide default (settings.php) overridable per
     * course: cardsize and showcardframe control the grid's visual density; the three
     * "default" colour/font options give the whole course a fallback that an
     * individual activity's own appearance still takes priority over (see
     * card_builder::build()); navstyle controls the section navigation layout (view
     * mode only, see classes/output/courseformat/content.php).
     *
     * @param bool $foreditform Whether this is being called to populate the course edit form.
     * @return array
     */
    public function course_format_options($foreditform = false): array {
        static $courseformatoptions = false;

        if ($courseformatoptions === false) {
            $courseformatoptions = [
                'cardsize' => [
                    'default' => get_config('format_smartcards', 'cardsize'),
                    'type' => PARAM_ALPHA,
                ],
                'showcardframe' => [
                    'default' => get_config('format_smartcards', 'showcardframe'),
                    'type' => PARAM_INT,
                ],
                'defaultbgcolor' => [
                    'default' => get_config('format_smartcards', 'defaultbgcolor'),
                    'type' => PARAM_TEXT,
                ],
                'defaultlabelcolor' => [
                    'default' => get_config('format_smartcards', 'defaultlabelcolor'),
                    'type' => PARAM_TEXT,
                ],
                'defaultlabelfont' => [
                    'default' => get_config('format_smartcards', 'defaultlabelfont'),
                    'type' => PARAM_ALPHANUM,
                ],
                'navstyle' => [
                    'default' => get_config('format_smartcards', 'navstyle'),
                    'type' => PARAM_ALPHA,
                ],
                'progressdisplay' => [
                    'default' => get_config('format_smartcards', 'progressdisplay'),
                    'type' => PARAM_ALPHA,
                ],
            ];
        }

        if ($foreditform && !isset($courseformatoptions['cardsize']['label'])) {
            $bgcoloroptions = [
                '' => get_string('appearance_defaultcolor', 'format_smartcards'),
                appearance_repository::BGCOLOR_TRANSPARENT => get_string('appearance_transparent', 'format_smartcards'),
            ];
            foreach (appearance_palette::LABEL_COLORS as $slug => $hex) {
                $bgcoloroptions[$hex] = ucfirst($slug);
            }

            $labelcoloroptions = ['' => get_string('appearance_defaultcolor', 'format_smartcards')];
            foreach (appearance_palette::LABEL_COLORS as $slug => $hex) {
                $labelcoloroptions[$hex] = ucfirst($slug);
            }

            $labelfontoptions = ['' => get_string('appearance_labelfont_system', 'format_smartcards')];
            foreach (appearance_palette::LABEL_FONTS as $slug => $fontname) {
                $labelfontoptions[$slug] = $fontname;
            }

            $courseformatoptionsedit = [
                'cardsize' => [
                    'label' => new lang_string('cardsize', 'format_smartcards'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            'small' => new lang_string('cardsize_small', 'format_smartcards'),
                            'medium' => new lang_string('cardsize_medium', 'format_smartcards'),
                            'large' => new lang_string('cardsize_large', 'format_smartcards'),
                        ],
                    ],
                ],
                'showcardframe' => [
                    'label' => new lang_string('showcardframe', 'format_smartcards'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => new lang_string('no'),
                            1 => new lang_string('yes'),
                        ],
                    ],
                ],
                'defaultbgcolor' => [
                    'label' => new lang_string('defaultbgcolor', 'format_smartcards'),
                    'element_type' => 'select',
                    'element_attributes' => [$bgcoloroptions],
                ],
                'defaultlabelcolor' => [
                    'label' => new lang_string('defaultlabelcolor', 'format_smartcards'),
                    'element_type' => 'select',
                    'element_attributes' => [$labelcoloroptions],
                ],
                'defaultlabelfont' => [
                    'label' => new lang_string('defaultlabelfont', 'format_smartcards'),
                    'element_type' => 'select',
                    'element_attributes' => [$labelfontoptions],
                ],
                'navstyle' => [
                    'label' => new lang_string('navstyle', 'format_smartcards'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            'default' => new lang_string('navstyle_default', 'format_smartcards'),
                            'accordion' => new lang_string('navstyle_accordion', 'format_smartcards'),
                            'tabs' => new lang_string('navstyle_tabs', 'format_smartcards'),
                            'sticky' => new lang_string('navstyle_sticky', 'format_smartcards'),
                            'sectioncards' => new lang_string('navstyle_sectioncards', 'format_smartcards'),
                        ],
                    ],
                ],
                'progressdisplay' => [
                    'label' => new lang_string('progressdisplay', 'format_smartcards'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            'none' => new lang_string('progressdisplay_none', 'format_smartcards'),
                            'count' => new lang_string('progressdisplay_count', 'format_smartcards'),
                            'percent' => new lang_string('progressdisplay_percent', 'format_smartcards'),
                        ],
                    ],
                ],
            ];
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }

        return $courseformatoptions;
    }
}

/**
 * Implements callback inplace_editable() allowing section names to be edited in-place.
 *
 * @param string $itemtype The item type being edited.
 * @param int $itemid The item ID.
 * @param mixed $newvalue The new value.
 * @return inplace_editable
 */
function format_smartcards_inplace_editable(string $itemtype, int $itemid, mixed $newvalue): inplace_editable {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id
              WHERE s.id = ? AND c.format = ?',
            [$itemid, 'smartcards'],
            MUST_EXIST
        );
        return course_get_format($section->course)->inplace_editable_update_section_name(
            $section,
            $itemtype,
            $newvalue
        );
    }
    throw new coding_exception('Unknown inplace editable itemtype: ' . $itemtype);
}

/**
 * Serves the uploaded card image of one activity's or one section's custom appearance
 * (appearance_repository::TYPE_IMAGE), stored by appearance_image_store.
 *
 * The card image is purely decorative — the same icon a locked/restricted activity or
 * section still shows on its card, alongside the 'locked' badge, so a student can see
 * what the card looks like without being able to open the activity/section itself. It
 * must therefore stay servable whenever the card itself is visible
 * ({@see cm_info::is_visible_on_course_page()} for an activity;
 * {@see \core_courseformat\base::is_section_visible()} for a section — the same two
 * gates status_resolver::resolve() and content.php's export_for_template() already use
 * to decide whether a card renders at all), never gated behind the stricter
 * {@see cm_info::$uservisible} / {@see section_info::$uservisible} that governs the
 * activity's or section's own content. require_course_login() with a $cm argument
 * enforces exactly that stricter rule for an activity (it redirects when
 * $cm->uservisible is false), so the course-level login check and the card-visibility
 * check are done separately here.
 *
 * @param stdClass $course Course the file's module or section belongs to.
 * @param stdClass|null $cm Course module owning the requested context, null for a section image.
 * @param context $context The module or course context resolved from the request URL.
 * @param string $filearea File area requested.
 * @param array $args Remaining pluginfile path segments.
 * @param bool $forcedownload Whether the browser should force-download the file.
 * @param array $options Additional options affecting file serving.
 * @return bool false when the file cannot be served (core then renders a 404).
 */
function format_smartcards_pluginfile(
    stdClass $course,
    ?stdClass $cm,
    context $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    if ($context->contextlevel === CONTEXT_MODULE && $cm !== null) {
        require_course_login($course);

        $cminfo = get_fast_modinfo($course)->get_cm($cm->id);
        if (!$cminfo->is_visible_on_course_page()) {
            return false;
        }

        $file = appearance_image_store::resolve_for_serving($cm->id, $filearea);
        if ($file === null) {
            return false;
        }

        send_stored_file($file, null, 0, $forcedownload, $options);
        return true;
    }

    if ($context->contextlevel === CONTEXT_COURSE) {
        require_course_login($course);

        $sectionid = (int)array_shift($args);
        $sectioninfo = get_fast_modinfo($course)->get_section_info_by_id($sectionid);
        if ($sectioninfo === null || !course_get_format($course)->is_section_visible($sectioninfo)) {
            return false;
        }

        $file = appearance_image_store::resolve_for_serving_section($sectionid, $course->id, $filearea);
        if ($file === null) {
            return false;
        }

        send_stored_file($file, null, 0, $forcedownload, $options);
        return true;
    }

    return false;
}
