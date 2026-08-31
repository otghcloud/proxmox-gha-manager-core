@extends('layouts.admin-base')

@section('meta-page-title', 'Dashboard')
@section('page-pretitle', 'Overview')
@section('page-title', 'Dashboard')

@section('page-content')
	<div class="container-xl">

		<livewire:dashboard-cards />

		@if ($environments->isEmpty())
			<div class="empty">
				<div class="empty-icon"><i class="fa-solid fa-server fa-3x text-secondary"></i></div>
				<p class="empty-title">No environments yet</p>
				<p class="empty-subtitle text-secondary">
					Add a Proxmox environment to start provisioning ephemeral runners.
				</p>
				<div class="empty-action">
					<a class="btn btn-primary" href="{{ route('environments.create') }}">
						<x-action-content icon="fa-solid fa-plus" label="Add environment" />
					</a>
				</div>
			</div>
		@else
			<div class="row row-cards mb-3">
				@foreach ($environments as $environment)
					<div class="col-md-6 col-lg-4">
						<div class="card">
							<div class="card-body">
								<div class="d-flex align-items-center mb-2">
									<h3 class="card-title mb-0">
										<a href="{{ route('environments.show', $environment) }}">{{ $environment->name }}</a>
									</h3>
									<span class="ms-auto badge bg-{{ $environment->enabled ? 'green' : 'secondary' }}-lt">
										{{ $environment->enabled ? 'Enabled' : 'Disabled' }}
									</span>
								</div>
								<div class="text-secondary small mb-3">{{ $environment->githubAccount->login }} &middot; reusable Proxmox nodes</div>

								@php($usage = $targetCapacity > 0 ? min(100, ($environment->active_runners_count / $targetCapacity) * 100) : 0)
								<div class="d-flex justify-content-between small mb-1">
									<span>Capacity</span>
									<span>{{ $environment->active_runners_count }} / {{ $targetCapacity }}</span>
								</div>
								<div class="progress progress-sm mb-3">
									<div class="progress-bar bg-primary" style="width: {{ $usage }}%"></div>
								</div>

								<div class="row text-center">
									<div class="col">
										<div class="h4 mb-0">{{ $environment->runner_templates_count }}</div>
										<div class="text-secondary small">Templates</div>
									</div>
									<div class="col">
										<div class="h4 mb-0">{{ $environment->pools_count }}</div>
										<div class="text-secondary small">Pools</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				@endforeach
			</div>

			<div class="row row-cards">
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title">Active runners</h3></div>
						<div class="table-responsive">
							{!! $activeRunnersTable->table(['class' => 'table table-vcenter card-table']) !!}
						</div>
					</div>
				</div>
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header"><h3 class="card-title">Recently finished</h3></div>
						<div class="table-responsive">
							{!! $recentRunnersTable->table(['class' => 'table table-vcenter card-table']) !!}
						</div>
					</div>
				</div>
			</div>
		@endif
	</div>
@endsection

@push('scripts')
	@vite('resources/js/base/datatables.js')
	{!! $activeRunnersTable->scripts(attributes: ['type' => 'module']) !!}
	{!! $recentRunnersTable->scripts(attributes: ['type' => 'module']) !!}
@endpush
