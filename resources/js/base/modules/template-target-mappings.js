export default function initTemplateTargetMappings() {
    const root = document.querySelector('[data-template-form]');

    if (!root) {
        return;
    }

    const decodeJson = (encoded) => {
        if (!encoded) {
            return [];
        }

        const bytes = Uint8Array.from(atob(encoded), (character) => character.charCodeAt(0));
        return JSON.parse(new TextDecoder().decode(bytes));
    };

    const catalog = decodeJson(root.dataset.targetCatalog);
    const templates = decodeJson(root.dataset.templateCatalog);
    const existing = decodeJson(root.dataset.existingMappings).reduce((items, mapping) => {
        items[mapping.id] = mapping;
        return items;
    }, {});
    const rows = root.querySelector('[data-template-mapping-rows]');
    const empty = root.querySelector('[data-template-mapping-empty]');
    const templateSelect = root.querySelector('[data-template-select]');
    const details = root.querySelector('[data-template-details]');

    const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
    }[character]));

    const selectedTemplate = () => templates.find((template) => template.id === templateSelect?.value);

    const renderDetails = () => {
        const template = selectedTemplate();

        if (!template) {
            details.hidden = true;
            details.innerHTML = '';
            return;
        }

        const requirements = template.build_requirements || {};
        details.hidden = false;
        details.innerHTML = `<div class="alert alert-info mb-0"><strong>${esc(template.name)}</strong>${template.description ? ` <span class="text-secondary">${esc(template.description)}</span>` : ''}<div class="small mt-1">Version ${esc(template.version || 'unknown')} &middot; ${esc(requirements.cpu_cores || '—')} cores &middot; ${esc(requirements.memory_mb || '—')} MB memory &middot; ${esc(requirements.disk_gb || '—')} GB disk${requirements.estimated_minutes ? ` &middot; approximately ${esc(requirements.estimated_minutes)} minutes` : ''}</div></div>`;
    };

    const render = () => {
        const template = selectedTemplate();
        const requirements = template?.build_requirements || {};
        const selected = [...root.querySelectorAll('[data-target-toggle]:checked')].map((input) => Number(input.value));
        rows.innerHTML = selected.map((id) => {
            const target = catalog.find((item) => item.id === id);
            const mapping = existing[id] || {};
            return `<tr data-target-row="${id}">
                <td>${esc(target?.name)} <span class="text-secondary">(${esc(target?.node)})</span></td>
                <td><select class="form-select form-select-sm" data-iso-select data-target-id="${id}" data-iso-url="${esc(target?.isoUrl)}" name="mappings[${id}][build_iso_file]"><option value="${esc(mapping.buildIsoFile || '')}">${mapping.buildIsoFile ? esc(mapping.buildIsoFile) : 'Load ISO options'}</option></select><small class="text-secondary" data-iso-status></small></td>
                <td><input class="form-control form-control-sm" name="mappings[${id}][build_iso_url]" type="url" placeholder="https://..." value="${esc(mapping.buildIsoUrl || template?.iso_url || '')}"></td>
                <td><input class="form-control form-control-sm" name="mappings[${id}][build_cores]" type="number" min="1" value="${esc(mapping.buildCores || requirements.cpu_cores || '')}"></td>
                <td><input class="form-control form-control-sm" name="mappings[${id}][build_memory_mb]" type="number" min="1024" value="${esc(mapping.buildMemoryMb || requirements.memory_mb || '')}"></td>
                <td><input class="form-control form-control-sm" name="mappings[${id}][build_disk_gb]" type="number" min="20" value="${esc(mapping.buildDiskGb || requirements.disk_gb || '')}"></td>
            </tr>`;
        }).join('');
        empty.hidden = selected.length > 0;
        selected.forEach((id) => loadIsos(id));
    };

    const loadIsos = async (id) => {
        const select = rows.querySelector(`[data-iso-select][data-target-id="${id}"]`);
        if (!select || select.dataset.loaded === 'true' || !select.dataset.isoUrl) return;
        const status = select.parentElement.querySelector('[data-iso-status]');
        try {
            const response = await fetch(select.dataset.isoUrl, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.error || 'Proxmox did not respond.');
            const current = select.value;
            select.innerHTML = '<option value="">Select installation ISO</option>' + (payload.images || []).map((image) => `<option value="${esc(image.volid)}">${esc(image.volid)}</option>`).join('');
            const isoFilename = selectedTemplate()?.iso_url?.split('/').pop();
            const matchingIso = (payload.images || []).find((image) => image.volid.endsWith(`/${isoFilename}`));
            select.value = current || matchingIso?.volid || '';
            select.dataset.loaded = 'true';
            status.textContent = `${(payload.images || []).length} ISO(s) found.`;
        } catch (error) {
            status.textContent = `Could not load ISOs (${error.message}).`;
        }
    };

    root.querySelectorAll('[data-target-toggle]').forEach((input) => input.addEventListener('change', render));
    templateSelect?.addEventListener('change', () => {
        renderDetails();
        render();
    });
    renderDetails();
    render();
}
