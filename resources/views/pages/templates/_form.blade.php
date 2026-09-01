@php($isUpdate = $template->exists)
@php($existingMappings = $template->targetMappings->keyBy('id'))
@php($targetCatalog = $targets->map(fn ($target) => ['id' => $target->id, 'name' => $target->name, 'node' => $target->proxmox_node, 'isoUrl' => route('nodes.isos', $target)])->values()->all())
@php($mappingCatalog = $existingMappings->map(fn ($target) => ['id' => $target->id, 'templateVmid' => $target->pivot->template_vmid, 'buildIsoFile' => $target->pivot->build_iso_file, 'buildIsoUrl' => $target->pivot->build_iso_url, 'buildCores' => $target->pivot->build_cores, 'buildMemoryMb' => $target->pivot->build_memory_mb, 'buildDiskGb' => $target->pivot->build_disk_gb])->values()->all())
@php($selectedBuildTarget = old('build_target', $template->build_target?->value))

<div data-template-form data-target-catalog="{{ base64_encode(json_encode($targetCatalog, JSON_THROW_ON_ERROR)) }}" data-template-catalog="{{ base64_encode(json_encode($catalogTemplates, JSON_THROW_ON_ERROR)) }}" data-existing-mappings="{{ base64_encode(json_encode($mappingCatalog, JSON_THROW_ON_ERROR)) }}">
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
				<label class="form-label required" for="build_target">Target template</label>
				<select class="form-select" data-template-select id="build_target" name="build_target" required>
					<option value="">Select a target template</option>
					@foreach ($catalogTemplates as $catalogTemplate)
						<option value="{{ $catalogTemplate['target'] }}" @selected($selectedBuildTarget === $catalogTemplate['target']) @disabled(($catalogTemplate['platform'] ?? null) === 'windows')>
							{{ $catalogTemplate['name'] }}{{ ($catalogTemplate['platform'] ?? null) === 'windows' ? ' (not supported yet)' : '' }}
						</option>
					@endforeach
				</select>
			</div>
			<div class="col-12" data-template-details hidden></div>
			<div class="col-12">
				<label class="form-label" for="description">Description</label>
				<textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $template->description) }}</textarea>
			</div>
			</div>
		</div>

		<div class="card mt-3" data-template-mappings>
			<div class="card-header"><h3 class="card-title">Node configuration</h3><div class="card-actions text-secondary small">Each node has its own physical template and build settings.</div></div>
			<div class="card-body">
				<label class="form-label" for="target_ids">Proxmox nodes</label>
				<div class="row g-2 mb-3" data-template-targets>
					@foreach ($targets as $target)
						<div class="col-md-6"><label class="form-check"><input class="form-check-input" data-target-toggle type="checkbox" name="target_ids[]" value="{{ $target->id }}" @checked(in_array($target->id, old('target_ids', $existingMappings->pluck('id')->all())))><span class="form-check-label">{{ $target->name }} ({{ $target->proxmox_node }})</span></label></div>
					@endforeach
				</div>
				<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Node</th><th>Installation ISO</th><th>ISO URL</th><th>Build cores</th><th>Build memory</th><th>Build disk</th></tr></thead><tbody data-template-mapping-rows></tbody></table></div><div class="text-secondary small" data-template-mapping-empty>Select one or more Proxmox nodes to configure their physical template.</div>
			</div>
			<div class="card-footer text-end"><a class="btn btn-link" href="{{ route('templates.index') }}">Cancel</a><button class="btn btn-primary" type="submit">{{ $isUpdate ? 'Save changes' : 'Create template' }}</button></div>
		</div>

</div>
