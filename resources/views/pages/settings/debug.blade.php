@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Debug')
@section('page-title', 'Debug')

@section('page-sub-content')
	<div class="card-body">
		<div class="card mb-4">
			<div class="card-header card-header-light">
				<h3 class="card-title mb-0">Toggles</h3>
			</div>
			<div class="card-body">
				<div class="d-flex align-items-center justify-content-between mb-4">
					<div class="me-3">
						<strong>Reaping</strong>
						<div class="text-secondary small">
							When off, the scheduled pass stops reconciling and destroying spent VMs.
						</div>
					</div>
					<form action="{{ route('settings.debug.toggle') }}" method="POST">
						@csrf
						@method('PUT')
						<input name="key" type="hidden" value="reaping_enabled">
						<input name="enabled" type="hidden" value="{{ $reapingEnabled ? 0 : 1 }}">
						<button class="btn btn-{{ $reapingEnabled ? 'outline-danger' : 'success' }}" type="submit">
							{{ $reapingEnabled ? 'Disable' : 'Enable' }}
						</button>
					</form>
				</div>

				<div class="d-flex align-items-center justify-content-between">
					<div class="me-3">
						<strong>Auto spawning</strong>
						<div class="text-secondary small">
							When off, warm pools stop topping up and webhook-triggered provisioning jobs are dropped.
						</div>
					</div>
					<form action="{{ route('settings.debug.toggle') }}" method="POST">
						@csrf
						@method('PUT')
						<input name="key" type="hidden" value="auto_spawn_enabled">
						<input name="enabled" type="hidden" value="{{ $autoSpawnEnabled ? 0 : 1 }}">
						<button class="btn btn-{{ $autoSpawnEnabled ? 'outline-danger' : 'success' }}" type="submit">
							{{ $autoSpawnEnabled ? 'Disable' : 'Enable' }}
						</button>
					</form>
				</div>
			</div>
			<div class="card-footer">
				<span class="badge bg-{{ $reapingEnabled ? 'green' : 'red' }}-lt me-2">
					Reaping {{ $reapingEnabled ? 'on' : 'off' }}
				</span>
				<span class="badge bg-{{ $autoSpawnEnabled ? 'green' : 'red' }}-lt">
					Auto spawning {{ $autoSpawnEnabled ? 'on' : 'off' }}
				</span>
			</div>
		</div>

		<div class="card mb-4">
			<div class="card-header card-header-light">
				<h3 class="card-title mb-0">Configuration export</h3>
			</div>
			<div class="card-body">
				<p class="text-secondary small mb-3">
					Download a compressed ZIP archive containing your active <code>.env</code> configuration file and SQLite database.
				</p>
				<a class="btn btn-primary" href="{{ route('settings.debug.export-config') }}">
					<x-action-content icon="fa-solid fa-file-export" label="Export configuration" />
				</a>
			</div>
		</div>

		<div class="card mb-4">
			<div class="card-header card-header-light">
				<h3 class="card-title mb-0">Destructive actions</h3>
			</div>
			<div class="list-group list-group-flush">
				<div class="list-group-item d-flex align-items-center justify-content-between">
					<div class="me-3">
						<strong>Reap all managed VMs</strong>
						<div class="text-secondary small">
							The next scheduled reaper pass destroys all {{ $liveRunnerCount }} tracked runner VM(s), whatever state they are in.
						</div>
					</div>
					<form action="{{ route('settings.debug.reap-all') }}" method="POST" onsubmit="return confirm('Force-reap every managed runner VM on the next scheduled pass?');">
						@csrf
						<button class="btn btn-danger" type="submit">Reap all</button>
					</form>
				</div>

				<div class="list-group-item d-flex align-items-center justify-content-between">
					<div class="me-3">
						<strong>Clear runner history</strong>
						<div class="text-secondary small">
														{{ $runnerHistoryCount }} runner history record(s); {{ $historicRunnerCount }} destroyed and failed record(s) can be cleared.
						</div>
					</div>
					<form action="{{ route('settings.debug.runner-history') }}" method="POST" onsubmit="return confirm('Delete all historic runner records?');">
						@csrf
						@method('DELETE')
						<button class="btn btn-danger" type="submit">Clear</button>
					</form>
				</div>

				<div class="list-group-item d-flex align-items-center justify-content-between">
					<div class="me-3">
						<strong>Clear build history</strong>
						<div class="text-secondary small">
							Deletes all {{ $buildCount }} build record(s) and their logs, including builds stuck as running.
						</div>
					</div>
					<form action="{{ route('settings.debug.build-history') }}" method="POST" onsubmit="return confirm('Delete all build records, including running builds?');">
						@csrf
						@method('DELETE')
						<button class="btn btn-danger" type="submit">Clear</button>
					</form>
				</div>

				<div class="list-group-item d-flex align-items-center justify-content-between">
					<div class="me-3">
						<strong>Purge webhook delivery logs</strong>
						<div class="text-secondary small">
							Deletes all {{ $webhookDeliveryCount }} recorded webhook delivery log(s).
						</div>
					</div>
					<form action="{{ route('settings.debug.webhook-logs') }}" method="POST" onsubmit="return confirm('Delete all webhook delivery logs?');">
						@csrf
						@method('DELETE')
						<button class="btn btn-danger" type="submit">Purge</button>
					</form>
				</div>

				<div class="list-group-item d-flex align-items-center justify-content-between">
					<div class="me-3">
						<strong>Purge GitHub job history</strong>
						<div class="text-secondary small">
							Deletes all {{ $workflowJobCount }} recorded GitHub job(s) and their stored logs.
						</div>
					</div>
					<form action="{{ route('settings.debug.workflow-jobs') }}" method="POST" onsubmit="return confirm('Delete all GitHub job records and their logs?');">
						@csrf
						@method('DELETE')
						<button class="btn btn-danger" type="submit">Purge</button>
					</form>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header card-header-light">
				<h3 class="card-title mb-0">Effective pool limits</h3>
			</div>
			<div class="table-responsive">
				<table class="table table-vcenter card-table">
					<thead>
						<tr>
							<th>Pool</th>
							<th>Environment</th>
							<th>Host</th>
							<th>Min idle</th>
							<th>Active / max</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($pools as $pool)
							@if ($pool->proxmoxTargets->isEmpty())
								<tr>
									<td><a href="{{ route('pools.show', $pool) }}">{{ $pool->name }}</a></td>
									<td>{{ $pool->environment->name }}</td>
									<td class="text-secondary">No nodes assigned</td>
									<td>0</td>
									<td>{{ $pool->activeRunnerCount() }} / 0</td>
								</tr>
							@else
								@foreach ($pool->proxmoxTargets as $target)
									<tr>
										<td><a href="{{ route('pools.show', $pool) }}">{{ $pool->name }}</a></td>
										<td>{{ $pool->environment->name }}</td>
										<td>{{ $target->name }}</td>
										<td>{{ $pool->minIdleRunnersOn($target) }}</td>
										<td>{{ $pool->activeRunnerCountOn($target) }} / {{ $pool->maxConcurrentOn($target) }}</td>
									</tr>
								@endforeach
							@endif
						@empty
							<tr><td class="text-secondary" colspan="5">No pools are configured.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>
@endsection
