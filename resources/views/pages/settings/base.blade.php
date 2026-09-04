@extends('layouts.admin-base')

@section('page-pretitle', 'Settings')

@section('page-content')
	<div class="container-xl">
		<div class="card">
			<div class="row g-0">
				<div class="col-12 col-md-3 border-end">
					<div class="card-body">
						<div class="d-flex d-md-none align-items-center justify-content-between mb-1">
							<span class="text-secondary small fw-medium text-uppercase">Settings menu</span>
							<button aria-controls="settings-sidebar-menu" aria-expanded="false" class="navbar-toggler" data-bs-target="#settings-sidebar-menu" data-bs-toggle="collapse" type="button">
								<span class="navbar-toggler-icon"></span>
							</button>
						</div>

						<div class="collapse d-md-block" id="settings-sidebar-menu">
							<h4 class="subheader">General</h4>
							<div class="list-group list-group-transparent mb-3">
								<a class="list-group-item list-group-item-action{{ request()->routeIs('settings.overview') ? ' active' : '' }}" href="{{ route('settings.overview') }}">Overview</a>
								<a class="list-group-item list-group-item-action{{ request()->routeIs('settings.application*') ? ' active' : '' }}" href="{{ route('settings.application') }}">Application</a>
							</div>

							<h4 class="subheader">Templates</h4>
							<div class="list-group list-group-transparent mb-3">
								<a class="list-group-item list-group-item-action{{ request()->routeIs('settings.templates.index') ? ' active' : '' }}" href="{{ route('settings.templates.index') }}">General</a>
								<a class="list-group-item list-group-item-action{{ request()->routeIs('settings.templates.retention*') ? ' active' : '' }}" href="{{ route('settings.templates.retention') }}">Retention</a>
								<a class="list-group-item list-group-item-action{{ request()->routeIs('settings.templates.credentials*') ? ' active' : '' }}" href="{{ route('settings.templates.credentials.index') }}">Credentials</a>
							</div>

							<h4 class="subheader">Workflows</h4>
							<div class="list-group list-group-transparent mb-3">
								<a class="list-group-item list-group-item-action{{ request()->routeIs('settings.jobs*') ? ' active' : '' }}" href="{{ route('settings.jobs.index') }}">Jobs</a>
								<a class="list-group-item list-group-item-action{{ request()->routeIs('settings.runners*') ? ' active' : '' }}" href="{{ route('settings.runners.index') }}">Runners</a>
							</div>

							<h4 class="subheader">Administration</h4>
							<div class="list-group list-group-transparent mb-3">
								<a class="list-group-item list-group-item-action{{ request()->routeIs('settings.users*') ? ' active' : '' }}" href="{{ route('settings.users.index') }}">Users</a>
							</div>

							<h4 class="subheader">Debug</h4>
							<div class="list-group list-group-transparent">
								<a class="list-group-item list-group-item-action{{ request()->routeIs('settings.debug*') ? ' active' : '' }}" href="{{ route('settings.debug.index') }}">Debug</a>
							</div>
						</div>
					</div>
				</div>

				<div class="col-12 col-md-9 d-flex flex-column">
					@yield('page-sub-content')
				</div>
			</div>
		</div>
	</div>
@endsection
