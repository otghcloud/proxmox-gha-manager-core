export default function initNodeStoragePicker() {
    const root = document.querySelector('[data-node-form]');

    if (!root) {
        return;
    }

    const realmSelect = root.querySelector('[data-auth-realm]');
    const authFieldGroups = [...root.querySelectorAll('[data-auth-fields]')];
    const settings = root.querySelector('[data-node-settings]');
    const trigger = root.querySelector('[data-node-storage-load]');
    const status = root.querySelector('[data-node-storage-status]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const isUpdate = root.dataset.isUpdate === 'true';
    const targetId = root.dataset.targetId;

    const connectionFieldsFor = (realm) => realm === 'password'
        ? ['proxmox_url', 'proxmox_node', 'proxmox_username', 'proxmox_password']
        : ['proxmox_url', 'proxmox_node', 'proxmox_token_id', 'proxmox_token_secret'];

    const syncAuthFields = () => {
        const realm = realmSelect?.value || 'api_token';

        authFieldGroups.forEach((group) => {
            const matches = group.dataset.authFields === realm;
            group.hidden = !matches;
            group.querySelectorAll('input').forEach((input) => {
                input.disabled = !matches;
                if (input.type !== 'password') {
                    input.required = matches;
                }
            });
        });
    };

    const ready = () => {
        const realm = realmSelect?.value || 'api_token';
        const all = connectionFieldsFor(realm);
        const required = isUpdate ? all.filter((id) => id !== 'proxmox_token_secret' && id !== 'proxmox_password') : all;

        return required.every((id) => document.getElementById(id)?.value.trim() !== '');
    };

    const updateState = () => {
        settings.disabled = !ready();
        trigger.disabled = !ready();
    };

    const fill = (id, options) => {
        const select = document.getElementById(id);
        const current = select.dataset.current || select.value;
        select.innerHTML = '<option value="">Select storage</option>' + options.map((option) => `<option value="${option.name}">${option.name}${option.available ? ` — ${(option.available / 1024 ** 3).toFixed(0)} GB free` : ''}</option>`).join('');
        if (current) {
            select.value = current;
        }
    };

    syncAuthFields();
    realmSelect?.addEventListener('change', () => {
        syncAuthFields();
        updateState();
    });

    ['proxmox_url', 'proxmox_node', 'proxmox_token_id', 'proxmox_token_secret', 'proxmox_username', 'proxmox_password']
        .forEach((id) => document.getElementById(id)?.addEventListener('input', updateState));

    trigger.addEventListener('click', async () => {
        updateState();
        if (!ready()) return;
        status.textContent = 'Loading storage options...';
        const payload = Object.fromEntries([...document.querySelectorAll('[data-node-form] input, [data-node-form] select')].filter((field) => field.name && !field.disabled).map((field) => [field.name, field.type === 'checkbox' ? field.checked : field.value]));
        if (targetId) {
            payload.target_id = targetId;
        }

        try {
            const response = await fetch(root.dataset.storageUrl, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(payload) });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Proxmox did not respond.');
            fill('build_iso_storage', result.iso || []);
            fill('build_vm_storage', result.images || []);
            status.textContent = `${(result.iso || []).length} ISO and ${(result.images || []).length} VM storage(s) found.`;
        } catch (error) {
            status.textContent = `Could not list storages (${error.message}).`;
        }
    });

    updateState();

    const isoSelect = document.getElementById('build_iso_storage');
    const vmSelect = document.getElementById('build_vm_storage');
    if (isUpdate && ready() && (isoSelect?.options.length <= 1 || vmSelect?.options.length <= 1)) {
        trigger.click();
    }
}
