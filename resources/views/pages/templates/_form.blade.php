@php($isUpdate = $template->exists)
@php($existingMappings = $template->targetMappings->keyBy('id'))
@php($targetCatalog = $targets->map(fn ($target) => ['id' => $target->id, 'name' => $target->name, 'node' => $target->proxmox_node, 'isoUrl' => route('nodes.isos', $target)])->values()->all())
@php($mappingCatalog = $existingMappings->map(fn ($target) => ['id' => $target->id, 'templateVmid' => $target->pivot->template_vmid, 'buildIsoFile' => $target->pivot->build_iso_file, 'buildCores' => $target->pivot->build_cores, 'buildMemoryMb' => $target->pivot->build_memory_mb, 'buildDiskGb' => $target->pivot->build_disk_gb])->values()->all())

<div data-template-form data-target-catalog="{{ base64_encode(json_encode($targetCatalog, JSON_THROW_ON_ERROR)) }}" data-existing-mappings="{{ base64_encode(json_encode($mappingCatalog, JSON_THROW_ON_ERROR)) }}">
	<div class="card">
	<div class="card-body">
		<div class="row g-3">
			<div class="col-md-6">
				<label class="form-label required" for="environment_id">Environment</label>
				<select class="form-select" id="environment_id" name="environment_id" required>
					<option value="">Select an environment</option>
					@foreach ($environments as $environment)
						<option value="{{ $environment->id }}" @selected(old('environment_id', $template->environment_id) == $environment->id)>{{ $environment->name }}</option>
					@endforeach
				</select>
			</div>
			<div class="col-md-6">
				<label class="form-label required" for="name">Name</label>
				<input class="form-control" id="name" name="name" placeholder="ubuntu2404" required type="text" value="{{ old('name', $template->name) }}">
			</div>
			<div class="col-md-6">
				<label class="form-label required" for="os">Operating system</label>
				<select class="form-select" id="os" name="os" required>
					@foreach (\App\Enums\PoolOs::cases() as $os)
						<option value="{{ $os->value }}" @selected(old('os', $template->os?->value) === $os->value)>{{ $os->label() }}</option>
					@endforeach
				</select>
				@if (\App\Enums\PoolOs::tryFrom(old('os', $template->os?->value ?? '')) === \App\Enums\PoolOs::Windows)
					<small class="form-hint text-warning">Windows provisioning is not implemented yet.</small>
				@endif
			</div>
			<div class="col-12">
				<label class="form-label" for="target_ids">Proxmox nodes</label>
				<div class="row g-2" data-template-targets>
					@foreach ($targets as $target)
						<div class="col-md-6"><label class="form-check"><input class="form-check-input" data-target-toggle type="checkbox" name="target_ids[]" value="{{ $target->id }}" @checked(in_array($target->id, old('target_ids', $existingMappings->pluck('id')->all())))><span class="form-check-label">{{ $target->name }} ({{ $target->proxmox_node }})</span></label></div>
					@endforeach
				</div>
				<small class="form-hint">Select the nodes where this logical template should be available.</small>
			</div>
			<div class="col-12">
				<label class="form-label" for="description">Description</label>
				<textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $template->description) }}</textarea>
			</div>
		</div>
	</div>
</div>

		<div class="card mt-3" data-template-mappings>
	<div class="card-header">
		<h3 class="card-title">Node configuration</h3>
		<div class="card-actions text-secondary small">Each node has its own physical template and build settings.</div>
	</div>
	<div class="card-body">
		<div class="row g-3">
			<div class="col-md-6">
				<label class="form-label" for="build_target">Target template</label>
				<select class="form-select" id="build_target" name="build_target">
					<option value="">Not built from here</option>
					@foreach (\App\Enums\BuildTarget::cases() as $target)
						<option value="{{ $target->value }}" @selected(old('build_target', $template->build_target?->value) === $target->value) @disabled(! $target->isSupported())>
							{{ $target->label() }}{{ $target->isSupported() ? '' : ' (not supported yet)' }}
						</option>
					@endforeach
				</select>
			</div>
			<div class="col-12"><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Node</th><th>Current VMID</th><th>Installation ISO</th><th>Build cores</th><th>Build memory</th><th>Build disk</th></tr></thead><tbody data-template-mapping-rows></tbody></table></div><div class="text-secondary small" data-template-mapping-empty>Select one or more Proxmox nodes above to configure their physical template.</div></div>
		</div>
	</div>
	<div class="card-footer text-end">
		<a class="btn btn-link" href="{{ route('templates.index') }}">Cancel</a>
		<button class="btn btn-primary" type="submit">{{ $isUpdate ? 'Save changes' : 'Create template' }}</button>
	</div>
</div>

</div>
