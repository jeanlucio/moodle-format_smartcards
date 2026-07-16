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
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Opens the "Card appearance" editor (a core/modal_save_cancel) from the native
 * per-activity edit menu entry added by content/cm/controlmenu, and saves the chosen
 * emoji/icon, colours and font via the format_smartcards_save_appearance web service.
 *
 * The colour and font palettes are curated and bundled with the plugin (never a free
 * colour picker or an external font), so they are declared here as plain constants
 * instead of being fetched from the server — they never change per course or user.
 * Icon glyph rendering is not wired into the card yet (bundled in a later step), so
 * picking an icon here only saves the slug; it will start rendering once that step
 * lands, with no further change needed to this module.
 *
 * @module     format_smartcards/appearance_picker
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import * as Str from 'core/str';
import * as Templates from 'core/templates';
import {add as addToast} from 'core/toast';

const SELECTORS = {
    TRIGGER: '[data-action="smartcardsEditAppearance"]',
    FORM: '[data-region="smartcards-appearance-form"]',
    TYPE_RADIO: '[data-region="smartcards-type-radio"]',
    EMOJI_FIELD: '[data-region="smartcards-emoji-field"]',
    EMOJI_INPUT: '[data-region="smartcards-emoji-input"]',
    ICON_FIELD: '[data-region="smartcards-icon-field"]',
    ICON_SELECT: '[data-region="smartcards-icon-select"]',
    BGCOLOR_INPUT: '[data-region="smartcards-bgcolor-input"]',
    BGCOLOR_CLEAR: '[data-region="smartcards-bgcolor-clear"]',
    LABELCOLOR_SWATCH: '[data-region="smartcards-labelcolor-swatch"]',
    LABELFONT_SELECT: '[data-region="smartcards-labelfont-select"]',
};

/** @type {Object<string, string>} Curated labelcolor palette slug => #RRGGBB, mirrors appearance_palette.php. */
const LABEL_COLORS = {
    charcoal: '#212529',
    red: '#c62828',
    orange: '#bf360c',
    green: '#2e7d32',
    teal: '#00695c',
    blue: '#1565c0',
    purple: '#6a1b9a',
    pink: '#ad1457',
};

/** @type {Object<string, string>} Curated labelfont palette slug => display name, mirrors appearance_palette.php. */
const LABEL_FONTS = {
    fredoka: 'Fredoka',
    baloo2: 'Baloo 2',
    varelaround: 'Varela Round',
    nunito: 'Nunito',
    comicneue: 'Comic Neue',
};

/** @type {string[]} Curated "quick pick" icon names (real Bootstrap Icons slugs, bundled in a later step). */
const ICONS = [
    'book', 'pencil', 'camera-video', 'mic', 'chat-dots', 'trophy', 'star', 'flag',
    'puzzle', 'gear', 'calendar-event', 'clipboard-check', 'lightbulb', 'map',
    'music-note', 'palette', 'rocket', 'bullseye', 'award', 'journal-text',
    'mortarboard', 'people',
];

/**
 * Builds the template context for the editor form.
 *
 * @param {number} cmid Course module id being edited.
 * @returns {object} Context for the appearance_editor template.
 */
const buildEditorContext = (cmid) => ({
    cmid,
    colors: Object.entries(LABEL_COLORS).map(([slug, hex]) => ({slug, hex})),
    fonts: Object.entries(LABEL_FONTS).map(([slug, name]) => ({slug, name})),
    icons: ICONS.map((slug) => ({slug})),
});

/**
 * Wires the type radio buttons to toggle the emoji/icon fields.
 *
 * @param {HTMLElement} form The editor form.
 * @returns {void}
 */
const wireTypeToggle = (form) => {
    const emojiField = form.querySelector(SELECTORS.EMOJI_FIELD);
    const iconField = form.querySelector(SELECTORS.ICON_FIELD);
    form.addEventListener('change', (event) => {
        if (!event.target.matches(SELECTORS.TYPE_RADIO)) {
            return;
        }
        const isEmoji = event.target.value === 'emoji';
        emojiField.hidden = !isEmoji;
        iconField.hidden = isEmoji;
    });
};

/**
 * Wires the background colour input and its "Default" clear button.
 *
 * A native colour input cannot represent "no value", so a data-cleared flag tracks
 * whether the teacher wants the default background instead of a chosen colour.
 *
 * @param {HTMLElement} form The editor form.
 * @returns {void}
 */
const wireBgcolor = (form) => {
    const input = form.querySelector(SELECTORS.BGCOLOR_INPUT);
    const clearBtn = form.querySelector(SELECTORS.BGCOLOR_CLEAR);
    input.dataset.cleared = '1';
    input.addEventListener('input', () => {
        input.dataset.cleared = '0';
    });
    clearBtn.addEventListener('click', () => {
        input.dataset.cleared = '1';
    });
};

/**
 * Wires the title colour swatches so exactly one is pressed at a time.
 *
 * @param {HTMLElement} form The editor form.
 * @returns {void}
 */
const wireLabelColorSwatches = (form) => {
    const swatches = form.querySelectorAll(SELECTORS.LABELCOLOR_SWATCH);
    swatches.forEach((swatch) => {
        swatch.addEventListener('click', () => {
            swatches.forEach((other) => other.setAttribute('aria-pressed', 'false'));
            swatch.setAttribute('aria-pressed', 'true');
        });
    });
};

/**
 * Reads the current form state into the shape the web service expects.
 *
 * @param {HTMLElement} form The editor form.
 * @returns {{type: string, value: string, bgcolor: string, labelcolor: string, labelfont: string}}
 */
const gatherFormValues = (form) => {
    const checkedType = form.querySelector(`${SELECTORS.TYPE_RADIO}:checked`);
    const type = checkedType ? checkedType.value : 'emoji';
    const value = type === 'emoji'
        ? form.querySelector(SELECTORS.EMOJI_INPUT).value.trim()
        : form.querySelector(SELECTORS.ICON_SELECT).value;

    const bgcolorInput = form.querySelector(SELECTORS.BGCOLOR_INPUT);
    const bgcolor = bgcolorInput.dataset.cleared === '1' ? '' : bgcolorInput.value;

    const selectedSwatch = form.querySelector(`${SELECTORS.LABELCOLOR_SWATCH}[aria-pressed="true"]`);
    const labelcolor = selectedSwatch ? selectedSwatch.dataset.value : '';

    const labelfont = form.querySelector(SELECTORS.LABELFONT_SELECT).value;

    return {type, value, bgcolor, labelcolor, labelfont};
};

/**
 * Opens the appearance editor modal for one activity and saves the result.
 *
 * @param {string} cmid Course module id, from the trigger's dataset.
 * @param {string} name Activity name, from the trigger's dataset.
 * @returns {Promise<void>}
 */
const openEditor = async(cmid, name) => {
    const [title, savedMessage] = await Promise.all([
        Str.get_string('editappearance', 'format_smartcards'),
        Str.get_string('changessaved', 'moodle'),
    ]);
    const {html, js} = await Templates.renderForPromise(
        'format_smartcards/local/appearance_editor',
        buildEditorContext(Number(cmid))
    );

    const modal = await ModalSaveCancel.create({
        title: name ? `${title} — ${name}` : title,
        body: html,
        show: true,
        removeOnClose: true,
    });
    Templates.runTemplateJS(js);

    const form = modal.getBody()[0].querySelector(SELECTORS.FORM);
    wireTypeToggle(form);
    wireBgcolor(form);
    wireLabelColorSwatches(form);

    modal.getRoot().on(ModalEvents.save, async(event) => {
        event.preventDefault();
        try {
            await Ajax.call([{
                methodname: 'format_smartcards_save_appearance',
                args: {cmid: Number(cmid), ...gatherFormValues(form)},
            }])[0];
            await addToast(savedMessage, {type: 'success'});
            modal.hide();
        } catch (error) {
            Notification.exception(error);
        }
    });
};

/**
 * Initialises the delegated click handler for every "Card appearance" menu entry.
 *
 * @returns {void}
 */
export const init = () => {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest(SELECTORS.TRIGGER);
        if (!trigger) {
            return;
        }
        event.preventDefault();
        openEditor(trigger.dataset.cmid, trigger.dataset.name ?? '');
    });
};
