@extends('layouts.admin-base')


@section('meta-page-title', $job->job_name)
@section('page-pretitle', 'Jobs')
@section('page-title', $job->job_name)

@section('page-actions')
	<div class="col-auto ms-auto d-print-none">
		<div class="btn-list">
			@if ($job->hasLog())
				<a class="btn" href="{{ route('jobs.log', $job) }}" target="_blank">
					<x-action-content icon="fa-solid fa-file-lines" label="Raw log" />
				</a>
			@endif
			@if ($job->html_url)
				<a class="btn" href="{{ $job->html_url }}" rel="noopener" target="_blank">
					<x-action-content icon="fa-brands fa-github" label="View on GitHub" />
				</a>
			@endif
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
							<dd class="col-7"><a href="{{ route('environments.show', $job->environment) }}">{{ $job->environment->name }}</a></dd>
							<dt class="col-5">Runner</dt>
							<dd class="col-7">
								@if ($job->runner)
									<a href="{{ route('runners.show', $job->runner) }}">{{ $job->runner->runner_name }}</a>
								@else
									{{ $job->runner_name ?? '—' }}
								@endif
							</dd>
							<dt class="col-5">Node</dt>
							<dd class="col-7">
								@if ($job->runner?->proxmoxTarget)
									<a href="{{ route('nodes.show', $job->runner->proxmoxTarget) }}">{{ $job->runner->proxmoxTarget->name }}</a>
								@else
									—
								@endif
							</dd>
						</dl>

						<hr class="card-hr">

						<dl class="row mb-0">
							<dt class="col-5">Result</dt>
							<dd class="col-7">
								@if ($job->conclusion)
									<span class="badge bg-{{ $job->conclusion->colour() }}-lt">{{ $job->conclusion->label() }}</span>
								@else
									<span class="badge bg-secondary-lt">{{ ucfirst(str_replace('_', ' ', $job->status)) }}</span>
								@endif
							</dd>
							<dt class="col-5">Workflow</dt>
							<dd class="col-7">{{ $job->workflow_name ?? '—' }}</dd>
							<dt class="col-5">Repository</dt>
							<dd class="col-7"><a href="https://github.com/{{ $job->repository_full_name }}" rel="noopener" target="_blank">{{ $job->repository_full_name }}</a></dd>
							<dt class="col-5">Branch</dt>
							<dd class="col-7">
								@if ($job->head_branch)
									<a href="https://github.com/{{ $job->repository_full_name }}/tree/{{ $job->head_branch }}" rel="noopener" target="_blank">{{ $job->head_branch }}</a>
								@else
									—
								@endif
							</dd>
							<dt class="col-5">Commit</dt>
							<dd class="col-7">
								@if ($job->head_sha)
									<a href="https://github.com/{{ $job->repository_full_name }}/commit/{{ $job->head_sha }}" rel="noopener" target="_blank">{{ substr($job->head_sha, 0, 7) }}</a>
								@else
									—
								@endif
							</dd>
							<dt class="col-5">Run ID</dt>
							<dd class="col-7">
								@if ($job->github_run_id)
									<a href="https://github.com/{{ $job->repository_full_name }}/actions/runs/{{ $job->github_run_id }}" rel="noopener" target="_blank">{{ $job->github_run_id }}</a>
								@else
									—
								@endif
								@if ($job->run_attempt) <span class="text-secondary">attempt {{ $job->run_attempt }}</span>@endif
							</dd>
							<dt class="col-5">Job ID</dt>
							<dd class="col-7">
								@if ($job->github_job_id && $job->github_run_id)
									<a href="https://github.com/{{ $job->repository_full_name }}/actions/runs/{{ $job->github_run_id }}/job/{{ $job->github_job_id }}" rel="noopener" target="_blank">{{ $job->github_job_id }}</a>
								@else
									{{ $job->github_job_id }}
								@endif
							</dd>
							<dt class="col-5">Queue wait</dt>
							<dd class="col-7">{{ \App\Helpers\DataTableHelpers::duration($job->queueWaitSeconds()) }}</dd>
							<dt class="col-5">Duration</dt>
							<dd class="col-7">{{ \App\Helpers\DataTableHelpers::duration($job->durationSeconds()) }}</dd>
						</dl>

						<hr class="card-hr">

						<dl class="row mb-0">
							<dt class="col-5">Started</dt>
							<dd class="col-7">{{ $job->started_at?->forDisplay()->toDayDateTimeString() ?? '—' }}</dd>
							<dt class="col-5">Completed</dt>
							<dd class="col-7">{{ $job->completed_at?->forDisplay()->toDayDateTimeString() ?? '—' }}</dd>
						</dl>
					</div>
				</div>

				@if ($job->labels)
					<div class="card">
						<div class="card-header"><h3 class="card-title">Labels</h3></div>
						<div class="card-body">
							@foreach ($job->labels as $label)
								<span class="badge bg-blue-lt me-1 mb-1">{{ $label }}</span>
							@endforeach
						</div>
					</div>
				@endif
			</div>

			<div class="col-lg-8">
				<div class="card mb-3">
					<div class="card-header"><h3 class="card-title">Steps</h3></div>
					@if (empty($job->steps))
						<div class="card-body text-secondary">GitHub has not reported any steps for this job.</div>
					@else
						<div class="table-responsive">
							<table class="table card-table table-vcenter">
								<thead><tr><th style="width: 3rem;">#</th><th>Step</th><th style="width: 8rem;">Result</th><th style="width: 7rem;">Duration</th></tr></thead>
								<tbody>
									@foreach ($job->steps as $step)
										@php($conclusion = \App\Enums\JobConclusion::tryFrom($step['conclusion'] ?? ''))
										@php($started = !empty($step['started_at']) ? \Illuminate\Support\Carbon::parse($step['started_at']) : null)
										@php($finished = !empty($step['completed_at']) ? \Illuminate\Support\Carbon::parse($step['completed_at']) : null)
										<tr>
											<td class="text-secondary">{{ $step['number'] ?? '—' }}</td>
											<td>{{ $step['name'] ?? 'Unnamed step' }}</td>
											<td>
												@if ($conclusion)
													<span class="badge bg-{{ $conclusion->colour() }}-lt">{{ $conclusion->label() }}</span>
												@else
													<span class="badge bg-secondary-lt">{{ ucfirst(str_replace('_', ' ', $step['status'] ?? 'unknown')) }}</span>
												@endif
											</td>
											<td class="text-secondary">{{ \App\Helpers\DataTableHelpers::duration($started && $finished ? (int) $started->diffInSeconds($finished) : null) }}</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					@endif
				</div>

				<div class="card">
					<div class="card-header"><h3 class="card-title">Log output</h3></div>
					@if ($job->hasLog())
						<div class="card-body p-0">
							<div class="log-viewer" id="job-log">{{ file_get_contents($job->log_path) }}</div>
						</div>
					@elseif ($job->log_fetched_at)
						<div class="card-body text-secondary">GitHub no longer has a log for this job.</div>
					@else
						<div class="card-body text-secondary">The log has not been fetched yet.</div>
					@endif
				</div>
			</div>
		</div>
	</div>
@endsection
