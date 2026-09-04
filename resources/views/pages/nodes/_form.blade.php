@php($isUpdate = $target->exists)
@php($connectionReady = $target->exists)

<div data-node-form data-storage-url="{{ route('nodes.storage-options') }}" data-is-update="{{ $isUpdate ? 'true' : 'false' }}" data-target-id="{{ $target->id ?? '' }}">
	<div class="card">
		<div class="card-header"><h3 class="card-title">Connection</h3></div>
		<div class="card-body"><div class="row g-3">
			<div class="col-md-6"><label class="form-label required" for="name">Name</label><input class="form-control" id="name" name="name" required type="text" value="{{ old('name', $target->name) }}"></div>
			<label class="form-check form-switch mb-2 ms-3"><input class="form-check-input" name="drained" type="checkbox" value="1" @checked(old('drained', $target->drained_at !== null))><span class="form-check-label">Drain node</span></label>
			<div class="col-md-8"><label class="form-label required" for="proxmox_url">Proxmox API URL</label><input class="form-control" id="proxmox_url" name="proxmox_url" required type="url" value="{{ old('proxmox_url', $target->proxmox_url) }}"><small class="form-hint">The API endpoint for this Proxmox cluster, normally ending in <code>/api2/json</code>.</small></div>
			<div class="col-md-4"><label class="form-label required" for="proxmox_node">Node name</label><input class="form-control" id="proxmox_node" name="proxmox_node" required type="text" value="{{ old('proxmox_node', $target->proxmox_node) }}"><small class="form-hint">The exact Proxmox node name where runner VMs will be created.</small></div>
			@php($authRealm = old('proxmox_auth_realm', $target->proxmox_auth_realm ?? \App\Models\ProxmoxTarget::AUTH_REALM_API_TOKEN))
			<div class="col-md-12"><label class="form-label required" for="proxmox_auth_realm">Authentication method</label>
				<select class="form-select" data-auth-realm id="proxmox_auth_realm" name="proxmox_auth_realm" required>
					<option value="{{ \App\Models\ProxmoxTarget::AUTH_REALM_API_TOKEN }}" @selected($authRealm === \App\Models\ProxmoxTarget::AUTH_REALM_API_TOKEN)>API authentication</option>
					<option value="{{ \App\Models\ProxmoxTarget::AUTH_REALM_PASSWORD }}" @selected($authRealm === \App\Models\ProxmoxTarget::AUTH_REALM_PASSWORD)>Standard authentication</option>
				</select>
				<small class="form-hint">Some Proxmox endpoints (e.g. cloud image imports from arbitrary filesystem paths) reject API tokens and require a standard user login instead.</small>
			</div>
			<div data-auth-fields="{{ \App\Models\ProxmoxTarget::AUTH_REALM_API_TOKEN }}" class="col-md-6"><label class="form-label" for="proxmox_token_id">API token ID</label><input class="form-control" id="proxmox_token_id" name="proxmox_token_id" type="text" value="{{ old('proxmox_token_id', $target->proxmox_token_id) }}"></div>
			<div data-auth-fields="{{ \App\Models\ProxmoxTarget::AUTH_REALM_API_TOKEN }}" class="col-md-6"><label class="form-label" for="proxmox_token_secret">API token secret</label><input autocomplete="new-password" class="form-control" id="proxmox_token_secret" name="proxmox_token_secret" type="password" value="{{ old('proxmox_token_secret', $target->proxmox_token_secret) }}"></div>
			<div data-auth-fields="{{ \App\Models\ProxmoxTarget::AUTH_REALM_PASSWORD }}" class="col-md-6"><label class="form-label" for="proxmox_username">Username</label><input class="form-control" id="proxmox_username" name="proxmox_username" type="text" placeholder="root@pam" value="{{ old('proxmox_username', $target->proxmox_username) }}"></div>
			<div data-auth-fields="{{ \App\Models\ProxmoxTarget::AUTH_REALM_PASSWORD }}" class="col-md-6"><label class="form-label" for="proxmox_password">Password</label><input autocomplete="new-password" class="form-control" id="proxmox_password" name="proxmox_password" type="password" value="{{ old('proxmox_password', $target->proxmox_password) }}"></div>
			<div class="col-md-6"><label class="form-label" for="proxmox_ca_bundle">CA bundle path</label><input class="form-control" id="proxmox_ca_bundle" name="proxmox_ca_bundle" type="text" value="{{ old('proxmox_ca_bundle', $target->proxmox_ca_bundle) }}"></div>
			<div class="col-md-6 d-flex align-items-end"><label class="form-check form-switch mb-2"><input class="form-check-input" id="proxmox_verify_tls" name="proxmox_verify_tls" type="checkbox" value="1" @checked(old('proxmox_verify_tls', $target->proxmox_verify_tls ?? false))><span class="form-check-label">Verify TLS certificate</span></label></div>
		</div></div>
	</div>

	<fieldset class="mt-3" data-node-settings {{ $connectionReady ? '' : 'disabled' }}>
		<div class="card">
			<div class="card-header"><h3 class="card-title">Node settings</h3><div class="card-actions text-secondary small">Complete the connection details to enable these fields.</div></div>
			<div class="card-body"><div class="row g-3">
				<div class="col-md-3"><label class="form-label required" for="template_vmid_range_start">Template VMID start</label><input class="form-control" id="template_vmid_range_start" name="template_vmid_range_start" type="number" required value="{{ old('template_vmid_range_start', $target->template_vmid_range_start ?? 801) }}"></div>
				<div class="col-md-3"><label class="form-label required" for="template_vmid_range_end">Template VMID end</label><input class="form-control" id="template_vmid_range_end" name="template_vmid_range_end" type="number" required value="{{ old('template_vmid_range_end', $target->template_vmid_range_end ?? 899) }}"></div>
				<div class="col-md-3"><label class="form-label required" for="runner_vmid_range_start">Runner VMID start</label><input class="form-control" id="runner_vmid_range_start" name="runner_vmid_range_start" type="number" required value="{{ old('runner_vmid_range_start', $target->runner_vmid_range_start ?? 901) }}"></div>
				<div class="col-md-3"><label class="form-label required" for="runner_vmid_range_end">Runner VMID end</label><input class="form-control" id="runner_vmid_range_end" name="runner_vmid_range_end" type="number" required value="{{ old('runner_vmid_range_end', $target->runner_vmid_range_end ?? 999) }}"></div>
				<div class="col-md-6"><label class="form-label" for="build_vm_storage">Build VM storage</label><select class="form-select" data-current="{{ old('build_vm_storage', $target->build_vm_storage) }}" id="build_vm_storage" name="build_vm_storage"><option value="">Load storage options</option></select></div>
				<div class="col-md-6"><label class="form-label" for="build_iso_storage">ISO storage</label><select class="form-select" data-current="{{ old('build_iso_storage', $target->build_iso_storage) }}" id="build_iso_storage" name="build_iso_storage"><option value="">Load storage options</option></select></div>
				<div class="col-md-4"><label class="form-label" for="build_cpu_type">Build CPU type</label><input class="form-control" id="build_cpu_type" name="build_cpu_type" value="{{ old('build_cpu_type', $target->build_cpu_type ?? 'host') }}"></div>
				<div class="col-md-4"><label class="form-label" for="proxmox_resource_pool">Resource pool</label><input class="form-control" id="proxmox_resource_pool" name="proxmox_resource_pool" value="{{ old('proxmox_resource_pool', $target->proxmox_resource_pool) }}"></div>
				<div class="col-md-4"><label class="form-label required" for="max_total_vms">Maximum VMs</label><input class="form-control" id="max_total_vms" name="max_total_vms" type="number" required value="{{ old('max_total_vms', $target->max_total_vms ?? 12) }}"></div>
				<div class="col-md-4"><label class="form-label required" for="network_bridge">Network bridge</label><input class="form-control" id="network_bridge" name="network_bridge" required value="{{ old('network_bridge', $target->network_bridge ?? 'vmbr0') }}"><small class="form-hint">Used for template builds and runner VMs on this node.</small></div>
				<div class="col-md-4"><label class="form-label" for="vlan_tag">VLAN tag</label><input class="form-control" id="vlan_tag" max="4094" min="1" name="vlan_tag" type="number" value="{{ old('vlan_tag', $target->vlan_tag) }}"><small class="form-hint">Leave blank for an untagged bridge.</small></div>
				<div class="col-12"><button class="btn btn-sm" data-node-storage-load type="button"><x-action-content icon="fa-solid fa-plug-circle-check" label="Load storage options" /></button><span class="text-secondary small ms-2" data-node-storage-status></span></div>
			</div></div>
		</div>
	</fieldset>

	<div class="card mt-3"><div class="card-body d-flex justify-content-between align-items-center"><label class="form-check form-switch"><input class="form-check-input" name="enabled" type="checkbox" value="1" @checked(old('enabled', $target->enabled ?? true))><span class="form-check-label">Node enabled</span></label><div><a class="btn btn-link" href="{{ $environment ? route('environments.show', $environment) : route('nodes.index') }}">Cancel</a><button class="btn btn-primary" type="submit">{{ $isUpdate ? 'Save changes' : 'Create node' }}</button></div></div></div>
</div>
