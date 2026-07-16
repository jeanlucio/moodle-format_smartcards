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
 * per-activity edit menu entry added by content/cm/controlmenu. Fetches the activity's
 * current appearance first (format_smartcards_get_appearance) so the form opens
 * pre-filled instead of always blank, keeps a live preview in sync with every field
 * change, then saves via format_smartcards_save_appearance.
 *
 * The colour and font palettes are curated and bundled with the plugin (never a free
 * colour picker or an external font), so they are declared here as plain constants —
 * they never change per course or user. The icon list, by contrast, comes from
 * get_appearance: bundled pix file URLs can only be resolved correctly server-side, so
 * this module never constructs one itself.
 *
 * @module     format_smartcards/appearance_picker
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import * as Str from 'core/str';
import * as Templates from 'core/templates';
import {add as addToast} from 'core/toast';

const SELECTORS = {
    TRIGGER: '[data-action="smartcardsEditAppearance"]',
    FORM: '[data-region="smartcards-appearance-form"]',
    FORM_ERROR: '[data-region="smartcards-form-error"]',
    TYPE_RADIO: '[data-region="smartcards-type-radio"]',
    EMOJI_FIELD: '[data-region="smartcards-emoji-field"]',
    EMOJI_INPUT: '[data-region="smartcards-emoji-input"]',
    EMOJI_FEEDBACK: '[data-region="smartcards-emoji-feedback"]',
    EMOJI_QUICKPICK: '[data-region="smartcards-emoji-quickpick"]',
    ICON_FIELD: '[data-region="smartcards-icon-field"]',
    ICON_BTN: '[data-region="smartcards-icon-btn"]',
    BGCOLOR_INPUT: '[data-region="smartcards-bgcolor-input"]',
    BGCOLOR_CLEAR: '[data-region="smartcards-bgcolor-clear"]',
    BGCOLOR_TRANSPARENT: '[data-region="smartcards-bgcolor-transparent"]',
    LABELCOLOR_SWATCH: '[data-region="smartcards-labelcolor-swatch"]',
    LABELFONT_SELECT: '[data-region="smartcards-labelfont-select"]',
    PREVIEW_ICONWRAP: '[data-region="smartcards-preview-iconwrap"]',
    PREVIEW_ICONIMG: '[data-region="smartcards-preview-iconimg"]',
    PREVIEW_EMOJI: '[data-region="smartcards-preview-emoji"]',
    PREVIEW_TITLE: '[data-region="smartcards-preview-title"]',
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

/** @type {string[]} Curated "quick pick" emoji, shown as buttons above the free-text emoji input. */
const EMOJIS = ['🎯', '🚀', '⭐', '🏆', '📚', '✏️', '🎨', '🎵', '🔬', '🧩', '🎮', '🌟'];

/**
 * Builds the template context for the editor form from the get_appearance response.
 *
 * @param {number} cmid Course module id being edited.
 * @param {string} name Activity name.
 * @param {object} bootstrap The format_smartcards_get_appearance response.
 * @returns {object} Context for the appearance_editor template.
 */
const buildEditorContext = (cmid, name, bootstrap) => {
    const bgcolorIsTransparent = bootstrap.bgcolor === 'transparent';
    const bgcolorIsDefault = bootstrap.bgcolor === '';

    return {
        cmid,
        name,
        iconurl: bootstrap.iconurl,
        isdefaulttype: bootstrap.type === 'default',
        isemojitype: bootstrap.type === 'emoji',
        isicontype: bootstrap.type === 'icon',
        emojivalue: bootstrap.type === 'emoji' ? bootstrap.value : '',
        isbgcolordefault: bgcolorIsDefault,
        isbgcolortransparent: bgcolorIsTransparent,
        bgcolorinputvalue: (!bgcolorIsTransparent && !bgcolorIsDefault) ? bootstrap.bgcolor : '#f8f9fa',
        labelcolor: bootstrap.labelcolor,
        colors: Object.entries(LABEL_COLORS).map(([slug, hex]) => ({
            slug,
            hex,
            selected: hex === bootstrap.labelcolor,
        })),
        fonts: Object.entries(LABEL_FONTS).map(([slug, fontname]) => ({
            slug,
            name: fontname,
            selected: slug === bootstrap.labelfont,
        })),
        icons: bootstrap.icons.map((icon) => ({
            ...icon,
            selected: bootstrap.type === 'icon' && icon.slug === bootstrap.value,
        })),
        emojis: EMOJIS,
    };
};

/**
 * Shows a validation error on the emoji field (Bootstrap is-invalid pattern) and moves
 * focus to it, so the teacher sees exactly what to fix instead of a generic dialog.
 *
 * @param {HTMLElement} form The editor form.
 * @param {string} message Localised error message.
 * @returns {void}
 */
const showEmojiError = (form, message) => {
    const input = form.querySelector(SELECTORS.EMOJI_INPUT);
    const feedback = form.querySelector(SELECTORS.EMOJI_FEEDBACK);
    input.classList.add('is-invalid');
    feedback.textContent = message;
    input.focus();
};

/**
 * Clears the emoji field's validation error, if any.
 *
 * @param {HTMLElement} form The editor form.
 * @returns {void}
 */
const clearEmojiError = (form) => {
    form.querySelector(SELECTORS.EMOJI_INPUT).classList.remove('is-invalid');
    form.querySelector(SELECTORS.EMOJI_FEEDBACK).textContent = '';
};

/**
 * Shows a form-level error banner (e.g. a save() failure the server rejected), instead
 * of Moodle's generic exception dialog — the teacher stays in the modal and sees the
 * actual reason inline.
 *
 * @param {HTMLElement} form The editor form.
 * @param {string} message Error message, from the caught exception.
 * @returns {void}
 */
const showFormError = (form, message) => {
    const banner = form.querySelector(SELECTORS.FORM_ERROR);
    banner.textContent = message;
    banner.hidden = false;
};

/**
 * Clears the form-level error banner, if any.
 *
 * @param {HTMLElement} form The editor form.
 * @returns {void}
 */
const clearFormError = (form) => {
    const banner = form.querySelector(SELECTORS.FORM_ERROR);
    banner.hidden = true;
    banner.textContent = '';
};

/**
 * Wires the type radio buttons to toggle the emoji/icon fields. The third option,
 * 'default', hides both — it means "keep the activity's default icon" so a teacher can
 * customise only the colours/font without being forced to also pick an emoji or icon.
 *
 * Switching to 'icon' with nothing pressed yet auto-selects the first icon, so "icon
 * type" can never end up with an empty selection — the same guarantee a <select> gives
 * for free, which the visual grid replaced.
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
        emojiField.hidden = event.target.value !== 'emoji';
        iconField.hidden = event.target.value !== 'icon';

        if (event.target.value === 'icon' && !form.querySelector(`${SELECTORS.ICON_BTN}[aria-pressed="true"]`)) {
            const firstIcon = form.querySelector(SELECTORS.ICON_BTN);
            if (firstIcon) {
                firstIcon.setAttribute('aria-pressed', 'true');
            }
        }
    });
};

/**
 * Wires the background colour input and its "Default"/"Transparent" buttons.
 *
 * A native colour input cannot represent "no value" or "transparent", so a
 * tri-state data-mode flag ('default'|'transparent'|'custom') tracks which of the
 * three the teacher actually wants; only 'custom' reads the colour input's value.
 *
 * @param {HTMLElement} form The editor form.
 * @param {string} initialBgcolor The activity's current bgcolor ('', 'transparent', or a hex value).
 * @returns {void}
 */
const wireBgcolor = (form, initialBgcolor) => {
    const input = form.querySelector(SELECTORS.BGCOLOR_INPUT);
    const defaultBtn = form.querySelector(SELECTORS.BGCOLOR_CLEAR);
    const transparentBtn = form.querySelector(SELECTORS.BGCOLOR_TRANSPARENT);

    const setMode = (mode) => {
        input.dataset.mode = mode;
        defaultBtn.setAttribute('aria-pressed', mode === 'default' ? 'true' : 'false');
        transparentBtn.setAttribute('aria-pressed', mode === 'transparent' ? 'true' : 'false');
    };

    if (initialBgcolor === '') {
        setMode('default');
    } else if (initialBgcolor === 'transparent') {
        setMode('transparent');
    } else {
        setMode('custom');
    }

    input.addEventListener('input', () => setMode('custom'));
    defaultBtn.addEventListener('click', () => setMode('default'));
    transparentBtn.addEventListener('click', () => setMode('transparent'));
};

/**
 * Wires a group of mutually-exclusive pressable buttons (title colour swatches or icon
 * grid buttons) so exactly one carries aria-pressed="true" at a time.
 *
 * @param {HTMLElement} form The editor form.
 * @param {string} selector Selector matching every button in the group.
 * @returns {void}
 */
const wireExclusivePressedGroup = (form, selector) => {
    const buttons = form.querySelectorAll(selector);
    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            buttons.forEach((other) => other.setAttribute('aria-pressed', 'false'));
            button.setAttribute('aria-pressed', 'true');
        });
    });
};

/**
 * Wires the emoji quick-pick buttons to fill the free-text input.
 *
 * @param {HTMLElement} form The editor form.
 * @returns {void}
 */
const wireEmojiQuickpicks = (form) => {
    const input = form.querySelector(SELECTORS.EMOJI_INPUT);
    form.querySelectorAll(SELECTORS.EMOJI_QUICKPICK).forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.value;
            clearEmojiError(form);
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
    const type = checkedType ? checkedType.value : 'default';

    let value = '';
    if (type === 'emoji') {
        value = form.querySelector(SELECTORS.EMOJI_INPUT).value.trim();
    } else if (type === 'icon') {
        const selectedIcon = form.querySelector(`${SELECTORS.ICON_BTN}[aria-pressed="true"]`);
        value = selectedIcon ? selectedIcon.dataset.value : '';
    }

    const bgcolorInput = form.querySelector(SELECTORS.BGCOLOR_INPUT);
    let bgcolor = '';
    if (bgcolorInput.dataset.mode === 'transparent') {
        bgcolor = 'transparent';
    } else if (bgcolorInput.dataset.mode === 'custom') {
        bgcolor = bgcolorInput.value;
    }

    const selectedSwatch = form.querySelector(`${SELECTORS.LABELCOLOR_SWATCH}[aria-pressed="true"]`);
    const labelcolor = selectedSwatch ? selectedSwatch.dataset.value : '';

    const labelfont = form.querySelector(SELECTORS.LABELFONT_SELECT).value;

    return {type, value, bgcolor, labelcolor, labelfont};
};

/**
 * Re-renders the live preview card from the form's current state. This is a
 * client-side approximation of card_builder.php for preview purposes only — the actual
 * saved card is always rendered server-side by card_builder, the single source of truth
 * once a save succeeds.
 *
 * @param {HTMLElement} form The editor form.
 * @param {string} defaultIconUrl The activity's default per-module-type icon URL.
 * @returns {void}
 */
const updatePreview = (form, defaultIconUrl) => {
    const values = gatherFormValues(form);

    const iconWrap = form.querySelector(SELECTORS.PREVIEW_ICONWRAP);
    const iconImg = form.querySelector(SELECTORS.PREVIEW_ICONIMG);
    const emojiSpan = form.querySelector(SELECTORS.PREVIEW_EMOJI);
    const titleSpan = form.querySelector(SELECTORS.PREVIEW_TITLE);

    const isEmoji = values.type === 'emoji';
    emojiSpan.hidden = !isEmoji;
    iconImg.hidden = isEmoji;
    if (isEmoji) {
        emojiSpan.textContent = values.value;
    } else if (values.type === 'icon') {
        const selectedIcon = form.querySelector(`${SELECTORS.ICON_BTN}[aria-pressed="true"]`);
        iconImg.src = selectedIcon ? selectedIcon.dataset.url : defaultIconUrl;
    } else {
        iconImg.src = defaultIconUrl;
    }

    iconWrap.style.backgroundColor = values.bgcolor || '';
    titleSpan.style.color = values.labelcolor || '';
    titleSpan.style.fontFamily = values.labelfont ? `'${LABEL_FONTS[values.labelfont]}', sans-serif` : '';
};

/**
 * Opens the appearance editor modal for one activity and saves the result.
 *
 * @param {string} cmid Course module id, from the trigger's dataset.
 * @param {string} name Activity name, from the trigger's dataset.
 * @returns {Promise<void>}
 */
const openEditor = async(cmid, name) => {
    const [title, savedMessage, emojiRequiredMessage, bootstrap] = await Promise.all([
        Str.get_string('editappearance', 'format_smartcards'),
        Str.get_string('changessaved', 'moodle'),
        Str.get_string('appearance_emoji_required', 'format_smartcards'),
        Ajax.call([{methodname: 'format_smartcards_get_appearance', args: {cmid: Number(cmid)}}])[0],
    ]);
    const {html, js} = await Templates.renderForPromise(
        'format_smartcards/local/appearance_editor',
        buildEditorContext(Number(cmid), name, bootstrap)
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
    wireBgcolor(form, bootstrap.bgcolor);
    wireExclusivePressedGroup(form, SELECTORS.LABELCOLOR_SWATCH);
    wireExclusivePressedGroup(form, SELECTORS.ICON_BTN);
    wireEmojiQuickpicks(form);
    form.querySelector(SELECTORS.EMOJI_INPUT).addEventListener('input', () => clearEmojiError(form));

    const refreshPreview = () => updatePreview(form, bootstrap.iconurl);
    form.addEventListener('input', refreshPreview);
    form.addEventListener('change', refreshPreview);
    form.addEventListener('click', (event) => {
        if (event.target.closest('button')) {
            refreshPreview();
        }
    });
    refreshPreview();

    modal.getRoot().on(ModalEvents.save, async(event) => {
        event.preventDefault();
        clearFormError(form);

        const values = gatherFormValues(form);
        if (values.type === 'emoji' && values.value === '') {
            showEmojiError(form, emojiRequiredMessage);
            return;
        }

        try {
            await Ajax.call([{
                methodname: 'format_smartcards_save_appearance',
                args: {cmid: Number(cmid), ...values},
            }])[0];
            await addToast(savedMessage, {type: 'success'});
            modal.hide();
        } catch (error) {
            // The debuginfo field carries the specific reason (e.g. "Value is not a
            // single emoji character") but is only present when debugdisplay is on;
            // message is the generic localised string always sent, so it is the
            // production fallback.
            showFormError(form, error.debuginfo ?? error.message ?? String(error));
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
