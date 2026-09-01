@php($isUpdate = $pool->exists)
@php($templateOs = $templates->pluck('os.value', 'id'))

<div class="card">
	<div class="card-body">
		<div class="row g-3">
			<div class="col-md-6">
				<label class="form-label required" for="environment_id">Environment</label>
				<select class="form-select" id="environment_id" name="environment_id" required>
					<option value="">Select an environment</option>
					@foreach ($environments as $environment)
						<option value="{{ $environment->id }}" @selected(old('environment_id', $pool->environment_id) == $environment->id)>{{ $environment->name }}</option>
					@endforeach
				</select>
			</div>
			<div class="col-md-6">
				<label class="form-label required" for="runner_template_id">Template</label>
				<select class="form-select" id="runner_template_id" name="runner_template_id" required>
					<option value="">Select a template</option>
					@foreach ($templates as $template)
						<option value="{{ $template->id }}" @selected(old('runner_template_id', $pool->runner_template_id) == $template->id)>
							{{ $template->name }} ({{ $template->environment->name }})
						</option>
					@endforeach
				</select>
			</div>
			<div class="col-md-6">
				<label class="form-label required" for="name">Name</label>
				<input class="form-control" id="name" name="name" placeholder="ubuntu2404" required type="text" value="{{ old('name', $pool->name) }}">
			</div>
			<div class="col-md-6 d-flex align-items-end">
				<label class="form-check form-switch mb-2">
					<input class="form-check-input" name="enabled" type="checkbox" value="1" @checked(old('enabled', $pool->enabled ?? true))>
					<span class="form-check-label">Enabled</span>
				</label>
			</div>

			<div class="col-12">
				<label class="form-label required">Labels</label>
				<div
					data-label-editor
					data-initial='@json(old('labels', $pool->labels ?? []))'
					data-presets='@json($presets)'
					data-template-os='@json($templateOs)'
				>
					<div class="mb-2" data-label-list></div>
					<input class="form-control" data-label-input placeholder="Type a label and press Enter" type="text">
					<input data-label-value name="labels" type="hidden" value="{{ collect(old('labels', $pool->labels ?? []))->implode(',') }}">
					<div class="mt-2" data-label-presets></div>
				</div>
				<small class="form-hint">
					JIT runners receive <strong>only</strong> these labels &mdash; nothing is added automatically.
					Include <code>self-hosted</code>, the OS label and the architecture label explicitly.
				</small>
			</div>

			<div class="col-md-2">
				<label class="form-label required" for="cores">vCPU cores</label>
				<input class="form-control" id="cores" name="cores" required type="number" value="{{ old('cores', $pool->cores ?? 4) }}">
			</div>
			<div class="col-md-3">
				<label class="form-label required" for="memory">Memory (MB)</label>
				<input class="form-control" id="memory" name="memory" required type="number" value="{{ old('memory', $pool->memory ?? 8192) }}">
			</div>
			<div class="col-md-3">
				<label class="form-label required" for="boot_timeout_seconds">Boot timeout (s)</label>
				<input class="form-control" id="boot_timeout_seconds" name="boot_timeout_seconds" required type="number" value="{{ old('boot_timeout_seconds', $pool->boot_timeout_seconds ?? 300) }}">
			</div>
			<div class="col-12">
				<label class="form-label" for="runner_dir">Runner directory</label>
				<input class="form-control" id="runner_dir" name="runner_dir" placeholder="/opt/actions-runner" type="text" value="{{ old('runner_dir', $pool->runner_dir) }}">
				<small class="form-hint">Left blank, this defaults per OS: <code>/opt/actions-runner</code> or <code>C:\actions-runner</code>.</small>
			</div>
		</div>
	</div>
	<div class="card-footer text-end">
		<a class="btn btn-link" href="{{ route('pools.index') }}">Cancel</a>
		<button class="btn btn-primary" type="submit">{{ $isUpdate ? 'Save changes' : 'Create pool' }}</button>
	</div>
</div>

<div class="card mt-3">
	<div class="card-header"><h3 class="card-title">Per-node limits</h3></div>
	<div class="card-body">
		@if ($targets->isEmpty())
			<p class="text-secondary mb-0">No Proxmox nodes are configured yet.</p>
		@else
			<p class="text-secondary small">
				Every limit is per node. Tick a node to let this pool run there, then set how many idle runners
				are kept warm on it and how many may run on it at once. Lower preference values are selected first;
				equal preferences favour the node with more available capacity. The pool totals are the sum of the rows below.
			</p>
			@error('nodes')
				<div class="alert alert-danger">{{ $message }}</div>
			@enderror
			<div class="table-responsive" data-pool-nodes data-built-targets='@json($builtTargets)'>
				<table class="table table-vcenter card-table">
					<thead>
						<tr>
							<th style="width: 4rem;">Use</th>
							<th>Node</th>
							<th style="width: 10rem;">Preference</th>
							<th style="width: 12rem;">Min idle (warm pool)</th>
							<th style="width: 12rem;">Max concurrent</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($targets as $target)
							@php($enabled = (bool) old('nodes.'.$target->id.'.enabled', array_key_exists($target->id, $nodeLimits)))
							<tr data-target-id="{{ $target->id }}">
								<td>
									<label class="form-check mb-0">
										<input class="form-check-input" name="nodes[{{ $target->id }}][enabled]" type="checkbox" value="1" @checked($enabled)>
									</label>
								</td>
								<td>
									{{ $target->name }} <span class="text-secondary">({{ $target->proxmox_node }})</span>
									<span class="badge bg-warning-lt ms-2 d-none" data-unbuilt-warning title="Build this template on the node before runners can spawn there.">No template built here</span>
								</td>
								<td>
									<input class="form-control @error('nodes.'.$target->id.'.preference') is-invalid @enderror" min="0" name="nodes[{{ $target->id }}][preference]" type="number" value="{{ old('nodes.'.$target->id.'.preference', $nodeLimits[$target->id]['preference'] ?? 0) }}">
								</td>
								<td>
									<input
										class="form-control @error('nodes.'.$target->id.'.min_idle_runners') is-invalid @enderror"
										min="0"
										name="nodes[{{ $target->id }}][min_idle_runners]"
										type="number"
										value="{{ old('nodes.'.$target->id.'.min_idle_runners', $nodeLimits[$target->id]['min_idle_runners'] ?? 0) }}"
									>
								</td>
								<td>
									<input
										class="form-control @error('nodes.'.$target->id.'.max_concurrent') is-invalid @enderror"
										min="1"
										name="nodes[{{ $target->id }}][max_concurrent]"
										type="number"
										value="{{ old('nodes.'.$target->id.'.max_concurrent', $nodeLimits[$target->id]['max_concurrent'] ?? 4) }}"
									>
								</td>
							</tr>
						@endforeach
					</tbody>
					<tfoot>
						<tr>
							<th class="text-end" colspan="3">Pool total</th>
							<th data-total-min-idle>0</th>
							<th data-total-max-concurrent>0</th>
						</tr>
					</tfoot>
				</table>
			</div>
		@endif
	</div>
</div>
