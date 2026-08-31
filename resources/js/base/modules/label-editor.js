import $ from 'jquery';

/**
 * Tag-style editor for JIT runner labels.
 *
 * JIT runners receive only the labels set here, so the field offers the known-good
 * preset sets for the selected template's OS to reduce label-mismatch mistakes.
 */
export default function initLabelEditor() {
    const root = document.querySelector('[data-label-editor]');

    if (!root) {
        return;
    }

    const list = root.querySelector('[data-label-list]');
    const input = root.querySelector('[data-label-input]');
    const hidden = root.querySelector('[data-label-value]');
    const presetContainer = root.querySelector('[data-label-presets]');
    const presets = JSON.parse(root.dataset.presets || '{}');
    const templateOs = JSON.parse(root.dataset.templateOs || '{}');

    let labels = JSON.parse(root.dataset.initial || '[]');

    function sync() {
        hidden.value = labels.join(',');

        list.innerHTML = labels
            .map(
                (label) =>
                    `<span class="badge bg-blue-lt me-1 mb-1">${label}`
                    + `<a class="ms-2 text-decoration-none" data-remove-label="${label}" href="#" aria-label="Remove ${label}">&times;</a>`
                    + '</span>'
            )
            .join('');
    }

    function add(value) {
        const label = value.trim();

        if (label && !labels.includes(label)) {
            labels.push(label);
            sync();
        }
    }

    function renderPresets() {
        const select = document.getElementById('runner_template_id');
        const os = templateOs[select?.value] || null;
        const available = os ? presets[os] || {} : {};

        presetContainer.innerHTML = Object.keys(available).length === 0
            ? '<span class="text-secondary small">Select a template to see suggested label sets.</span>'
            : Object.entries(available)
                .map(
                    ([name, set]) =>
                        `<button class="btn btn-sm me-1 mb-1" type="button" data-preset='${JSON.stringify(set)}'>${name}</button>`
                )
                .join('');
    }

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            add(input.value);
            input.value = '';
        }
    });

    input.addEventListener('blur', () => {
        add(input.value);
        input.value = '';
    });

    $(root).on('click', '[data-remove-label]', function (event) {
        event.preventDefault();
        labels = labels.filter((label) => label !== this.dataset.removeLabel);
        sync();
    });

    $(presetContainer).on('click', '[data-preset]', function () {
        labels = JSON.parse(this.dataset.preset);
        sync();
    });

    document.getElementById('runner_template_id')?.addEventListener('change', renderPresets);

    sync();
    renderPresets();
}
