@extends('layouts.admin-base')

@section('meta-page-title', $pool->name)
@section('page-pretitle', 'Pools')
@section('page-title', $pool->name)

@section('page-actions')
	<div class="col-auto ms-auto d-print-none">
		<a class="btn" href="{{ route('pools.edit', $pool) }}">
			<x-action-content icon="fa-solid fa-pencil" label="Edit" />
		</a>
	</div>
@endsection

@section('page-content')
	<div class="container-xl">
		<div class="row row-cards">
			<div class="col-lg-4">
				<div class="card">
					<div class="card-header"><h3 class="card-title">Details</h3></div>
					<div class="card-body">
						<dl class="row mb-0">
							<dt class="col-5">Environment</dt>
							<dd class="col-7"><a href="{{ route('environments.show', $pool->environment) }}">{{ $pool->environment->name }}</a></dd>
							<dt class="col-5">Template</dt>
							<dd class="col-7">
								@if ($pool->runnerTemplate)
									<a href="{{ route('templates.show', $pool->runnerTemplate) }}">{{ $pool->runnerTemplate->name }}</a>
								@else
									&mdash;
								@endif
							</dd>
							<dt class="col-5">Status</dt>
							<dd class="col-7">
								<span class="badge bg-{{ $pool->enabled ? 'green' : 'secondary' }}-lt">
									{{ $pool->enabled ? 'Enabled' : 'Disabled' }}
								</span>
							</dd>
							<dt class="col-5">Resources</dt>
							<dd class="col-7">{{ $pool->cores }} vCPU / {{ $pool->memory }} MB</dd>
							<dt class="col-5">Capacity</dt>
							<dd class="col-7">{{ $pool->activeRunnerCount() }} / {{ $pool->totalMaxConcurrent() }}</dd>
							<dt class="col-5">Min idle</dt>
							<dd class="col-7">{{ $pool->totalMinIdleRunners() }}</dd>
							<dt class="col-5">Boot timeout</dt>
							<dd class="col-7">{{ $pool->boot_timeout_seconds }}s</dd>
						</dl>
					</div>
				</div>

				<div class="card mt-3">
					<div class="card-header"><h3 class="card-title">Labels</h3></div>
					<div class="card-body">
						@foreach ($pool->labels as $label)
							<span class="badge bg-blue-lt me-1 mb-1">{{ $label }}</span>
						@endforeach
						<p class="text-secondary small mb-0 mt-3">
							A queued job matches this pool when every label it requests appears above.
						</p>
					</div>
				</div>
			</div>

			<div class="col-lg-8">
				<div class="card">
					<div class="card-header"><h3 class="card-title">Per-node limits</h3></div>
					@if ($pool->proxmoxTargets->isEmpty())
						<div class="card-body text-secondary">This pool has no nodes assigned, so it can never spawn a runner.</div>
					@else
						<div class="table-responsive">
							<table class="table table-vcenter card-table">
								<thead><tr><th>Node</th><th>Min idle</th><th>Active / max</th></tr></thead>
								<tbody>
									@foreach ($pool->proxmoxTargets as $target)
										<tr>
											<td>
												<a href="{{ route('nodes.show', $target) }}">{{ $target->name }}</a>
												@unless (in_array($target->id, $buildableTargetIds, true))
													<span class="badge bg-warning-lt ms-2" title="Build this template on the node before runners can spawn there.">No template built here</span>
												@endunless
											</td>
											<td>{{ $pool->minIdleRunnersOn($target) }}</td>
											<td>{{ $pool->activeRunnerCountOn($target) }} / {{ $pool->maxConcurrentOn($target) }}</td>
										</tr>
									@endforeach
								</tbody>
								<tfoot>
									<tr>
										<th>Pool total</th>
										<th>{{ $pool->totalMinIdleRunners() }}</th>
										<th>{{ $pool->activeRunnerCount() }} / {{ $pool->totalMaxConcurrent() }}</th>
									</tr>
								</tfoot>
							</table>
						</div>
					@endif
				</div>

				<div class="card mt-3">
					<div class="card-header"><h3 class="card-title">Active runners</h3></div>
					@include('pages.runners._table', ['runners' => $activeRunners, 'empty' => 'No runners are active in this pool.'])
				</div>
			</div>
		</div>
	</div>
@endsection
