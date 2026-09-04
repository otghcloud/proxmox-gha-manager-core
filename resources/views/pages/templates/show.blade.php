@extends('layouts.admin-base')

@section('meta-page-title', $template->name)
@section('page-pretitle', 'Templates')
@section('page-title', $template->name)

@section('page-actions')
	<div class="col-auto ms-auto d-print-none">
		<div class="btn-list">
			@if ($buildableTargets->isNotEmpty())
				<form action="{{ route('templates.rebuild', $template) }}" class="d-flex align-items-center gap-2" method="POST">
					@csrf
					@foreach ($buildableTargets as $buildable)
						<input name="target_ids[]" type="hidden" value="{{ $buildable->id }}">
					@endforeach
					@if ($catalogEntry !== null)
						<select class="form-select w-auto" name="builder" title="Build method">
							@foreach ($catalogEntry->builders() as $builderKey => $builder)
								@php
									$builderType = $builder['type'] ?? $builderKey;
									$builderBuildable = (bool) ($builder['buildable'] ?? false);
								@endphp
								<option value="{{ $builderType }}" @selected($builderType === $catalogEntry->builderType()) @disabled(! $builderBuildable)>
									{{ $builder['display_name'] ?? $builder['label'] ?? ucfirst($builderType) }}{{ $builderBuildable ? '' : ' ('.($builder['disabled_reason'] ?? 'not supported').')' }}
								</option>
							@endforeach
						</select>
					@endif
					<select class="form-select w-auto" name="mode">
						<option value="sequential">Sequential</option>
						<option value="parallel">Parallel</option>
					</select>
					<button class="btn text-nowrap" @disabled($buildingTargetIds !== [])>
						<x-action-content icon="fa-solid fa-hammer" label="Rebuild all nodes" />
					</button>
				</form>
			@endif
			<a class="btn text-nowrap" href="{{ route('templates.edit', $template) }}">
				<x-action-content icon="fa-solid fa-pencil" label="Edit" />
			</a>
		</div>
	</div>
@endsection

@section('page-content')
	<div class="container-xl">
		<div class="row row-cards">
			<div class="col-lg-4">
				<div class="card mb-3">
					<div class="card-header"><h3 class="card-title">Details</h3></div>
					<div class="card-body">
						<dl class="row mb-0">
							<dt class="col-5">Environment</dt>
							<dd class="col-7"><a href="{{ route('environments.show', $template->environment) }}">{{ $template->environment->name }}</a></dd>
							<dt class="col-5">OS</dt>
							<dd class="col-7">{{ $catalogEntry?->platform() ?? 'Unknown' }}</dd>
							<dt class="col-5">Build target</dt>
							<dd class="col-7">{{ $catalogEntry?->name() ?? 'Catalog entry unavailable' }}</dd>
						</dl>
						@if ($template->description)
							<hr>
							<p class="text-secondary mb-0">{{ $template->description }}</p>
						@endif
					</div>
				</div>

			</div>

			<div class="col-lg-8">
				<div class="card mb-3">
					<div class="card-header"><h3 class="card-title">Node availability</h3></div>
					<div class="table-responsive">
						<table class="table card-table table-vcenter">
							<thead><tr><th>Node</th><th>Template VMID</th><th>Version</th><th>Status</th><th>Last built</th><th></th></tr></thead>
							<tbody>
								@forelse ($targets as $target)
									@php
										$isBuilding = isset($buildingTargetIds[$target->id]);
										$updateService = app(\App\Services\Templates\TemplateUpdateService::class);
										$version = $target->pivot->version ?? \App\Services\Templates\TemplateUpdateService::getLocalVersionForId($template->template_catalog_id);
										$updateVersion = $updateService->getAvailableUpdateVersion($template->template_catalog_id, $target->pivot->version);
									@endphp
									<tr>
										<td>{{ $target->name }}</td>
										<td>{{ $target->pivot->template_vmid ?? 'Allocated on build' }}</td>
										<td>
											{{ $version ?: '—' }}
											@if ($updateVersion)
												<span class="badge bg-warning-lt ms-1" title="Update available: {{ $updateVersion }}">
													<i class="fa-solid fa-arrow-up me-1"></i>{{ $updateVersion }} available
												</span>
											@endif
										</td>
										<td><span class="badge bg-{{ $isBuilding ? 'blue' : ($target->pivot->availability_status === 'available' ? 'green' : 'secondary') }}-lt">{{ $isBuilding ? 'Building' : ucfirst($target->pivot->availability_status) }}</span></td>
										<td>{{ $target->pivot->last_built_at?->diffForHumans() ?? 'Never' }}</td>
										<td class="text-end">@if ($isBuilding)<button class="btn btn-sm" disabled><x-action-content icon="fa-solid fa-spinner" label="Building" /></button>@elseif ($catalogEntry?->isBuildable() && $target->pivot->build_iso_file && $target->build_iso_storage && $target->build_vm_storage)<form action="{{ route('templates.build', [$template, $target]) }}" method="POST">@csrf<button class="btn btn-sm"><x-action-content icon="fa-solid fa-hammer" label="{{ $target->pivot->template_vmid ? 'Rebuild' : 'Build now' }}" /></button></form>@endif</td>
									</tr>
								@empty
									<tr><td colspan="6" class="text-secondary">No Proxmox nodes selected.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>

				<div class="card">
					<div class="card-header"><h3 class="card-title">Pools using this template</h3></div>					@if ($template->pools->isEmpty())
						<div class="card-body text-secondary">No pools reference this template.</div>
					@else
						<div class="table-responsive">
							<table class="table card-table table-vcenter">
								<thead>
									<tr><th>Pool</th><th>Labels</th><th>Concurrency</th></tr>
								</thead>
								<tbody>
									@foreach ($template->pools as $pool)
										<tr>
											<td><a href="{{ route('pools.show', $pool) }}">{{ $pool->name }}</a></td>
											<td>
												@foreach ($pool->labels as $label)
													<span class="badge bg-blue-lt">{{ $label }}</span>
												@endforeach
											</td>
											<td>{{ $pool->totalMaxConcurrent() }}</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					@endif
				</div>

				@if ($retiredVmids->isNotEmpty())
					<div class="card mt-3">
						<div class="card-header">
							<h3 class="card-title">Superseded templates</h3>
							<div class="card-actions">
								<form action="{{ route('templates.superseded.purge-all', $template) }}" method="POST" onsubmit="return confirm('Destroy every superseded template VM that nothing is cloned from?');">
									@csrf
									<button class="btn btn-sm"><x-action-content icon="fa-solid fa-broom" label="Purge all" /></button>
								</form>
							</div>
						</div>
						<div class="table-responsive">
							<table class="table card-table table-vcenter">
								<thead><tr><th>Node</th><th>VMID</th><th>Generation</th><th>Retired</th><th>Runners still using it</th><th></th></tr></thead>
								<tbody>
									@foreach ($retiredVmids as $retired)
										@php($inUse = ($retiredUsage[$retired->id] ?? 0) > 0)
										<tr>
											<td>{{ $retired->proxmoxTarget->name }}</td>
											<td>{{ $retired->vmid }}</td>
											<td>{{ $retired->generation }}</td>
											<td>{{ $retired->retired_at->diffForHumans() }}</td>
											<td>{{ $retiredUsage[$retired->id] ?? 0 }}</td>
											<td class="text-end">
												@unless ($inUse)
													<form action="{{ route('templates.superseded.purge', [$template, $retired]) }}" method="POST" onsubmit="return confirm('Destroy template VMID {{ $retired->vmid }}?');">
														@csrf
														<button class="btn btn-sm"><x-action-content icon="fa-solid fa-trash" label="Purge now" /></button>
													</form>
												@endunless
											</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
						<div class="card-footer text-secondary small">
							These are removed automatically once nothing is cloned from them, subject to the retention
							setting on the <a href="{{ route('settings.templates.index') }}">settings page</a>.
						</div>
					</div>
				@endif

				@if ($template->imageBuilds->isNotEmpty())
					<div class="card mt-3">
						<div class="card-header"><h3 class="card-title">Build history</h3></div>
						<div class="table-responsive">
							<table class="table card-table table-vcenter">
								<thead>
									<tr><th>Target</th><th>Node</th><th>Status</th><th>Started</th><th>Finished</th></tr>
								</thead>
								<tbody>
									@foreach ($template->imageBuilds as $build)
										<tr>
											<td>
												<a href="{{ route('builds.show', $build) }}">{{ $catalogEntry?->name() ?? $build->template_catalog_id }}</a>
												@if ($build->version)
													<span class="text-secondary small">({{ $build->version }})</span>
												@endif
											</td>
											<td>{{ $build->proxmoxTarget?->name ?? '—' }}</td>
											<td><span class="badge bg-{{ $build->status->colour() }}-lt">{{ $build->status->label() }}</span></td>
											<td class="text-secondary">{{ $build->started_at?->diffForHumans() ?? '—' }}</td>
											<td class="text-secondary">{{ $build->finished_at?->diffForHumans() ?? '—' }}</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					</div>
				@endif
			</div>
		</div>
	</div>
@endsection
