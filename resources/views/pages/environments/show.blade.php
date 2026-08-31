@extends('layouts.admin-base')

@section('meta-page-title', $environment->name)
@section('page-pretitle', 'Environments')
@section('page-title', $environment->name)

@section('page-actions')
	<div class="col-auto ms-auto d-print-none">
		<div class="btn-list">
			<a class="btn" href="{{ route('environments.edit', $environment) }}">
				<x-action-content icon="fa-solid fa-pencil" label="Edit" />
			</a>
		</div>
	</div>
@endsection

@section('page-content')
	<div class="container-xl">
		<div class="row row-cards">

			<div class="col-lg-8">
				<div class="card mb-3">
					<div class="card-header">
						<h3 class="card-title">Proxmox Nodes</h3>
						<div class="card-actions">
							<a class="btn btn-sm" href="{{ route('environments.targets.create', $environment) }}">
								<x-action-content icon="fa-solid fa-plus" label="Add node" />
							</a>
						</div>
					</div>
					@if ($targets->isEmpty())
						<div class="card-body text-secondary">No Proxmox nodes configured. Provisioning cannot start until an enabled node is added.</div>
					@else
						<div class="table-responsive">
							<table class="table card-table table-vcenter">
								<thead><tr><th>Target</th><th>Node</th><th>Status</th><th>Capacity</th><th>Templates</th><th></th></tr></thead>
								<tbody>
									@foreach ($targets as $target)
										<tr>
											<td>{{ $target->name }}</td>
											<td>{{ $target->proxmox_node }}</td>
											<td><span class="badge bg-{{ $target->enabled && $target->health_status === 'healthy' ? 'green' : 'secondary' }}-lt">{{ $target->enabled ? ucfirst($target->health_status) : 'Disabled' }}</span></td>
											<td>{{ $target->current_vm_count }} / {{ $target->max_total_vms }}</td>
											<td>{{ $target->runner_templates_count }}</td>
											<td class="text-end"><a class="btn btn-sm" href="{{ route('environments.targets.edit', [$environment, $target]) }}"><x-action-content icon="fa-solid fa-pencil" label="Edit" /></a></td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					@endif
				</div>

				<div class="card mt-3">
					<div class="card-header">
						<h3 class="card-title">Pools</h3>
						<div class="card-actions">
							<a class="btn btn-sm" href="{{ route('pools.create') }}">
								<x-action-content icon="fa-solid fa-plus" label="Add pool" />
							</a>
						</div>
					</div>
					@if ($environment->pools->isEmpty())
						<div class="card-body text-secondary">
							No pools yet. Without a pool, queued jobs have nothing to match against.
						</div>
					@else
						<div class="table-responsive">
							<table class="table card-table table-vcenter">
								<thead>
									<tr>
										<th>Pool</th>
										<th>Template</th>
										<th>Labels</th>
										<th>Concurrency</th>
									</tr>
								</thead>
								<tbody>
									@foreach ($environment->pools as $pool)
										<tr>
											<td><a href="{{ route('pools.show', $pool) }}">{{ $pool->name }}</a></td>
											<td>{{ $pool->runnerTemplate?->name ?? '—' }}</td>
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

				<div class="card">
					<div class="card-header"><h3 class="card-title">Webhook</h3></div>
					<div class="card-body">
						<p class="text-secondary">
							Add this as an organisation webhook in GitHub, content type <code>application/json</code>,
							subscribed to <strong>Workflow jobs</strong> only.
						</p>
						<div class="input-group">
							<input class="form-control font-monospace" readonly type="text" value="{{ $environment->webhook_url }}">
						</div>
					</div>
				</div>

				<div class="card mt-3">
					<div class="card-header">
						<h3 class="card-title">Templates</h3>
						<div class="card-actions">
							<a class="btn btn-sm" href="{{ route('templates.create') }}">
								<x-action-content icon="fa-solid fa-plus" label="Add template" />
							</a>
						</div>
					</div>
					@if ($environment->runnerTemplates->isEmpty())
						<div class="card-body text-secondary">No templates registered.</div>
					@else
						<div class="table-responsive">
							<table class="table card-table table-vcenter">
								<thead>
									<tr>
										<th>Name</th>
										<th>Nodes</th>
										<th>OS</th>
										<th>Status</th>
									</tr>
								</thead>
								<tbody>
									@foreach ($environment->runnerTemplates as $template)
										@php($isBuilding = $template->imageBuilds->contains(fn ($build): bool => $build->status->isFinished() === false))
										@php($isAvailable = $template->targetMappings->contains(fn ($target): bool => $target->pivot->availability_status === 'available'))
										<tr>
											<td><a href="{{ route('templates.show', $template) }}">{{ $template->name }}</a></td>
											<td>{{ $template->targetMappings->count() }}</td>
											<td>{{ $template->os->label() }}</td>
											<td><span class="badge bg-{{ $isBuilding ? 'blue' : ($isAvailable ? 'green' : 'secondary') }}-lt">{{ $isBuilding ? 'Building' : ($isAvailable ? 'Available' : 'Unavailable') }}</span></td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					@endif
				</div>
			</div>

			<div class="col-lg-4">
				<div class="card">
					<div class="card-header"><h3 class="card-title">Details</h3></div>
					<div class="card-body">
						<dl class="row mb-0">
							<dt class="col-5">Status</dt>
							<dd class="col-7">
								<span class="badge bg-{{ $environment->enabled ? 'green' : 'secondary' }}-lt">
									{{ $environment->enabled ? 'Enabled' : 'Disabled' }}
								</span>
							</dd>
							<dt class="col-5">GitHub org</dt>
							<dd class="col-7">{{ $environment->githubAccount->login }}</dd>
							<dt class="col-5">Max lifetime</dt>
							<dd class="col-7">{{ $environment->max_lifetime_seconds }}s</dd>
							<dt class="col-5">Idle timeout</dt>
							<dd class="col-7">{{ $environment->idle_timeout_seconds }}s</dd>
						</dl>
					</div>
				</div>
			</div>

		</div>
	</div>
@endsection
