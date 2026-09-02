@extends('layouts.admin-base')

@section('meta-page-title', $runner->runner_name)
@section('page-pretitle', 'Runners')
@section('page-title', $runner->runner_name)

@section('page-content')
	<div class="container-xl">
		<div class="row row-cards">
			<div class="col-lg-4">
				<div class="card">
					<div class="card-header"><h3 class="card-title">Details</h3></div>
					<div class="card-body">
						<dl class="row mb-0">
							<dt class="col-5">State</dt>
							<dd class="col-7">
								<span class="badge bg-{{ $runner->state->colour() }}-lt runner-state">{{ $runner->state->label() }}</span>
							</dd>
							<dt class="col-5">Spawned</dt>
							<dd class="col-7"><span class="badge bg-{{ $runner->spawn_reason->colour() }}-lt">{{ $runner->spawn_reason->label() }}</span></dd>
							<dt class="col-5">Environment</dt>
							<dd class="col-7"><a href="{{ route('environments.show', $runner->environment) }}">{{ $runner->environment->name }}</a></dd>
							<dt class="col-5">Pool</dt>
							<dd class="col-7">
								@if ($runner->pool)
									<a href="{{ route('pools.show', $runner->pool) }}">{{ $runner->pool->name }}</a>
								@else
									&mdash;
								@endif
							</dd>
							<dt class="col-5">VMID</dt>
							<dd class="col-7">{{ $runner->vmid }}</dd>
							<dt class="col-5">IP address</dt>
							<dd class="col-7">{{ $runner->ip_address ?? '—' }}</dd>
							<dt class="col-5">GitHub runner</dt>
							<dd class="col-7">{{ $runner->github_runner_id ?? '—' }}</dd>
						</dl>

						<hr class="card-hr">

						@if ($job)
							<dl class="row mb-0">
								<dt class="col-5">Job served</dt>
								<dd class="col-7">
									<a href="{{ route('jobs.show', $job) }}">{{ $job->job_name }}</a>
								</dd>
								<dt class="col-5">Repository</dt>
								<dd class="col-7"><a href="https://github.com/{{ $job->repository_full_name }}" rel="noopener" target="_blank">{{ $job->repository_full_name }}</a></dd>
							</dl>

							<hr class="card-hr">
						@endif

						<dl class="row mb-0">
							<dt class="col-5">Lifetime</dt>
							<dd class="col-7">{{ \App\Helpers\DataTableHelpers::duration($lifetimeSeconds) }}</dd>
							<dt class="col-5">Created</dt>
							<dd class="col-7">{{ $runner->created_at->forDisplay()->toDayDateTimeString() }}</dd>
							<dt class="col-5">Destroyed</dt>
							<dd class="col-7">{{ $runner->destroyed_at?->forDisplay()->toDayDateTimeString() ?? '—' }}</dd>
						</dl>

						@if ($runner->failure_reason)
							<hr class="card-hr">
							<x-alert type="danger">{{ $runner->failure_reason }}</x-alert>
						@endif
					</div>
				</div>
			</div>

			<div class="col-lg-8">
				<div class="card">
					<div class="card-header">
						<h3 class="card-title">Timeline</h3>
						<div class="card-actions text-secondary small">{{ \App\Helpers\DataTableHelpers::duration($lifetimeSeconds) }} total</div>
					</div>
					<div class="card-body">
						@if ($timeline->isEmpty())
							<p class="text-secondary mb-0">No transitions recorded.</p>
						@else
							<ul class="steps steps-vertical">
								@foreach ($timeline as $entry)
									<li class="step-item">
										<div class="h4 m-0">
											<span class="badge bg-{{ $entry['colour'] }}-lt me-1"><i class="{{ $entry['icon'] }} fa-fw"></i></span>
											{{ $entry['title'] }}
											@if ($entry['from'])
												<span class="text-secondary fw-normal">from {{ $entry['from'] }}</span>
											@endif
										</div>
										<div class="text-secondary">
											{{ $entry['at']->forDisplay()->toDayDateTimeString() }}
											@if ($entry['detail'])
												&middot; {{ $entry['detail'] }}
											@endif
										</div>
										@if ($entry['since_previous'] !== null)
											<div class="text-secondary small">
												+{{ \App\Helpers\DataTableHelpers::duration($entry['since_previous']) }} since the previous step
												&middot; {{ \App\Helpers\DataTableHelpers::duration($entry['since_start']) }} into its life
											</div>
										@endif
									</li>
								@endforeach
							</ul>
						@endif
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection
