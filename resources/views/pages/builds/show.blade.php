@extends('layouts.admin-base')

@section('meta-page-title', 'Build '.$build->id)
@section('page-pretitle', 'Builds')
@section('page-title', $catalogEntry?->name() ?? $build->template_catalog_id)

@section('page-actions')
	@unless ($build->status->isFinished())
		<div class="col-auto ms-auto d-print-none">
			<form action="{{ route('builds.cancel', $build) }}" method="POST" onsubmit="return confirm('Force kill this build? The Packer process is terminated immediately.');">
				@csrf
				<button class="btn btn-danger"><x-action-content icon="fa-solid fa-skull" label="Force kill" /></button>
			</form>
		</div>
	@endunless
@endsection

@section('page-content')
	<div class="container-xl">
		<div class="card-group mb-3">
			<div class="card">
				<div class="card-body">
					<div class="subheader">Environment</div>
					<div class="h3 mb-0 text-truncate"><a href="{{ route('environments.show', $build->environment) }}">{{ $build->environment->name }}</a></div>
				</div>
			</div>
			<div class="card">
				<div class="card-body">
					<div class="subheader">Template</div>
					<div class="h3 mb-0 text-truncate" title="{{ $build->runnerTemplate?->name }}{{ $build->version ? ' ('.$build->version.')' : '' }}">
						@if ($build->runnerTemplate)
							<a href="{{ route('templates.show', $build->runnerTemplate) }}">{{ $build->runnerTemplate->name }}</a>
							@if ($build->version)
								<span class="text-muted small">({{ $build->version }})</span>
							@endif
						@else
							&mdash;
						@endif
					</div>
				</div>
			</div>
			<div class="card">
				<div class="card-body">
					<div class="subheader">Proxmox node</div>
					<div class="h3 mb-0 text-truncate">{{ $build->proxmoxTarget?->name ?? '—' }}</div>
				</div>
			</div>
			<div class="card">
				<div class="card-body">
					<div class="subheader">Build method</div>
					<div class="h3 mb-0 text-truncate">{{ $catalogEntry?->builder()['display_name'] ?? $catalogEntry?->builder()['label'] ?? $build->builder_type ?? '—' }}</div>
				</div>
			</div>
			<div class="card">
				<div class="card-body">
					<div class="subheader">Started</div>
					<div class="h3 mb-0">{{ $build->started_at?->forDisplay()->format('M j, g:i A') ?? '—' }}</div>
				</div>
			</div>
			<div class="card">
				<div class="card-body">
					<div class="subheader">Finished</div>
					<div class="h3 mb-0">{{ $build->finished_at?->forDisplay()->format('M j, g:i A') ?? '—' }}</div>
				</div>
			</div>
		</div>

		<div class="row row-cards">
			<div class="col-12">
				<div class="card build-observer-card">
					<div class="card-header">
						<h3 class="card-title">
							Build output
							<span class="badge bg-{{ $build->status->colour() }}-lt ms-2" id="build-status">{{ $build->status->label() }}</span>
						</h3>
						@unless ($build->status->isFinished())
							<div class="card-actions">
								<span class="spinner-border spinner-border-sm text-blue" role="status"></span>
							</div>
						@endunless
					</div>
					<div class="card-body p-0">
						<div class="row g-0">
							<div class="col-lg-7 build-progress-pane">
								@if ($progress['available'] ?? false)
									<div id="build-progress" data-build-progress>
										<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
											<div>
												<div class="subheader">Progress</div>
												<div class="h2 mb-1" data-build-progress-current>{{ $progress['status_label'] ?? $progress['current_stage']['name'] ?? 'Waiting for first stage' }}</div>
												@if (! $build->status->isFinished())
													<div class="text-secondary">
														@if ($progress['estimated_duration'] ?? null)
															Estimated build time: {{ $progress['estimated_duration'] }}.
														@endif
														This page follows the log as it is written.
													</div>
												@endif
											</div>
											<div class="text-secondary align-self-end">
												<span data-build-progress-completed>{{ $progress['completed_count'] }}</span> / <span data-build-progress-total>{{ $progress['stage_count'] }}</span> stages
											</div>
										</div>
										<div class="progress progress-sm mb-3">
											<div class="progress-bar" data-build-progress-bar style="width: {{ $progress['percent'] }}%"></div>
										</div>
										<div class="build-stage-list">
											@foreach ($progress['groups'] as $group)
												<div class="build-stage-group build-stage-group-{{ $group['state'] }}" data-build-stage-group="{{ $group['id'] }}">
													<button class="build-stage-group-header" type="button" data-build-stage-toggle aria-expanded="{{ $group['state'] === 'complete' ? 'false' : 'true' }}">
														<i class="fa-solid fa-chevron-down build-stage-group-caret"></i>
														<span class="build-stage-group-label">{{ $group['label'] }}</span>
														<span class="build-stage-group-count text-secondary">{{ $group['completed_count'] }} / {{ $group['stage_count'] }}</span>
													</button>
													<div class="build-stage-group-stages" @if ($group['state'] === 'complete') hidden @endif>
														@foreach ($group['stages'] as $stage)
															<div class="build-stage build-stage-{{ $stage['state'] }}" data-build-stage-id="{{ $stage['id'] }}">
																<span class="build-stage-dot"></span>
																<span>{{ $stage['name'] }}</span>
															</div>
														@endforeach
													</div>
												</div>
											@endforeach
										</div>
									</div>
								@else
									<div class="text-secondary">No progress metadata is available for this build target.</div>
								@endif
							</div>
							<div class="col-lg-5 build-log-pane">
								@if ($log === null && $build->status->isFinished())
									<div class="card-body text-secondary">No log output is available for this build.</div>
								@else
									<div class="log-viewer" id="build-log" data-log-url="{{ route('builds.log', $build) }}" data-finished="{{ $build->status->isFinished() ? 'true' : 'false' }}">{{ $log }}</div>
								@endif
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection
