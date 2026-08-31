/**
 * Populates the ISO and VM storage fields from the Proxmox node described by the form.
 *
 * The environment being edited may not be saved yet, so the lookup is driven by an explicit
 * "Load from Proxmox" action using whatever connection details are currently on screen.
 */
export default function initStoragePicker() {
    const root = document.querySelector('[data-storage-picker]');

    if (!root) {
        return;
    }

    const trigger = root.querySelector('[data-storage-load]');
    const status = root.querySelector('[data-storage-status]');
    const url = root.dataset.storageUrl;

    if (!url) {
        // A new environment has no id to query against until it has been saved once.
        status.textContent = 'Save the environment first to list its storages.';
        trigger.classList.add('disabled');
        return;
    }

    const fill = (fieldId, options, current) => {
        const input = document.getElementById(fieldId);

        if (!input) {
            return;
        }

        const list = document.getElementById(`${fieldId}-options`);
        list.innerHTML = options
            .map((option) => {
                const size = option.available
                    ? ` — ${(option.available / 1024 ** 3).toFixed(0)} GB free`
                    : '';
                return `<option value="${option.name}">${option.type || ''}${size}</option>`;
            })
            .join('');

        if (!input.value && current) {
            input.value = current;
        }
    };

    trigger.addEventListener('click', async (event) => {
        event.preventDefault();
        status.textContent = 'Loading storages from Proxmox…';

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || 'Proxmox did not respond.');
            }

            fill('build_iso_storage', payload.iso || []);
            fill('build_vm_storage', payload.images || []);

            status.textContent = `${(payload.iso || []).length} ISO and ${(payload.images || []).length} VM storage(s) found. Click a field to choose.`;
        } catch (error) {
            status.textContent = `Could not list storages (${error.message}).`;
        }
    });
}
