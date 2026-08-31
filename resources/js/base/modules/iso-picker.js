/**
 * Populates the installation ISO field from the selected environment's Proxmox node.
 *
 * Falls back to free text whenever Proxmox cannot be reached, or when the wanted ISO has not
 * been uploaded yet, so the field is never a dead end.
 */
export default function initIsoPicker() {
    const root = document.querySelector('[data-iso-picker]');

    if (!root) {
        return;
    }

    const select = root.querySelector('[data-iso-select]');
    const input = root.querySelector('[data-iso-input]');
    const status = root.querySelector('[data-iso-status]');
    const toggle = root.querySelector('[data-iso-toggle]');
    const environmentSelect = document.getElementById('environment_id');
    const urlTemplate = root.dataset.isoUrl;
    const current = root.dataset.current || '';

    const useManual = (manual) => {
        select.classList.toggle('d-none', manual);
        select.disabled = manual;
        input.classList.toggle('d-none', !manual);
        input.disabled = !manual;
        toggle.textContent = manual ? 'Choose from Proxmox' : 'Enter manually';
    };

    const load = async () => {
        const environmentId = environmentSelect?.value;

        if (!environmentId) {
            status.textContent = 'Select an environment to list its ISO images.';
            select.innerHTML = '<option value="">—</option>';
            return;
        }

        status.textContent = 'Loading ISO images from Proxmox…';
        select.innerHTML = '<option value="">Loading…</option>';

        try {
            const response = await fetch(urlTemplate.replace('__ID__', environmentId), {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || 'Proxmox did not respond.');
            }

            const images = payload.images || [];

            if (images.length === 0) {
                status.textContent = 'No ISO images found on this node. Upload one to Proxmox, or enter the volume ID manually.';
                select.innerHTML = '<option value="">No ISOs found</option>';
                return;
            }

            select.innerHTML = '<option value="">Select an ISO</option>'
                + images
                    .map((image) => {
                        const size = image.size ? ` (${(image.size / 1024 ** 3).toFixed(1)} GB)` : '';
                        const selected = image.volid === current ? ' selected' : '';
                        return `<option value="${image.volid}"${selected}>${image.volid}${size}</option>`;
                    })
                    .join('');

            status.textContent = `${images.length} ISO image${images.length === 1 ? '' : 's'} found.`;
        } catch (error) {
            status.textContent = `Could not list ISOs (${error.message}). Enter the volume ID manually.`;
            select.innerHTML = '<option value="">Unavailable</option>';
            useManual(true);
        }
    };

    // Keep one field named build_iso_file submitted at a time.
    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        useManual(select.disabled === false);
    });

    environmentSelect?.addEventListener('change', () => load());

    useManual(false);
    load();
}
