@extends('layouts.centered')

@section('meta-page-title', 'Setup')

@section('content')
	<div class="card card-md mb-3">
		<div class="card-body">
			<h2 class="h2 text-center mb-1">Welcome to Proxmox GHA Manager</h2>
			<p class="text-secondary text-center mb-4">A few details and you will be ready to provision runners.</p>

			<h3 class="h4">Requirements</h3>
			<div class="list-group list-group-flush mb-0">
				@foreach ($requirements as $check)
					<div class="list-group-item px-0">
						<div class="row align-items-center">
							<div class="col-auto">
								@if ($check['passed'])
									<i class="fa-solid fa-circle-check text-success"></i>
								@else
									<i class="fa-solid fa-circle-xmark text-danger"></i>
								@endif
							</div>
							<div class="col text-truncate">
								<div>{{ $check['label'] }}</div>
								<div class="text-secondary text-truncate small">{{ $check['detail'] }}</div>
							</div>
						</div>
					</div>
				@endforeach
			</div>
		</div>
	</div>

	<form action="{{ route('setup.import') }}" class="card card-md mb-3" enctype="multipart/form-data" method="POST">
		@csrf

		<div class="card-body">
			<h3 class="h4">Or restore existing backup</h3>
			<p class="text-secondary small mb-3">
				Upload a configuration ZIP archive containing your <code>.env</code> file and SQLite database.
			</p>

			<div class="mb-3">
				<label class="form-label required" for="config_file">Configuration backup (.zip)</label>
				<input class="form-control" id="config_file" name="config_file" accept=".zip" required type="file">
			</div>

			<button class="btn btn-outline-primary w-100" type="submit">
				<x-action-content icon="fa-solid fa-file-import" label="Import configuration" />
			</button>
		</div>
	</form>

	<form action="{{ route('setup.store') }}" class="card card-md" method="POST">
		@csrf

		<div class="card-body">
			<h3 class="h4">Application</h3>

			<div class="mb-3">
				<label class="form-label" for="app_url">External URL</label>
				<input class="form-control" id="app_url" name="app_url" placeholder="https://runners.example.com" required type="url" value="{{ old('app_url', url('/')) }}">
				<small class="form-hint">GitHub webhooks are delivered to this address, so it must be reachable from the internet.</small>
			</div>

			<div class="mb-3">
				<label class="form-label" for="timezone">Timezone</label>
				<select class="form-select" id="timezone" name="timezone" required>
					@foreach (timezone_identifiers_list() as $timezone)
						<option value="{{ $timezone }}" @selected(old('timezone', 'Europe/London') === $timezone)>{{ $timezone }}</option>
					@endforeach
				</select>
			</div>

			<div class="mb-3">
				<label class="form-label">Database</label>
				<input class="form-control" disabled type="text" value="{{ ucfirst($databaseDriver) }}">
				<small class="form-hint">SQLite is bundled and needs no configuration. An external database can be configured later.</small>
			</div>

			<hr class="my-4">

			<h3 class="h4">Administrator account</h3>

			<div class="mb-3">
				<label class="form-label" for="name">Name</label>
				<input class="form-control" id="name" name="name" required type="text" value="{{ old('name') }}">
			</div>

			<div class="mb-3">
				<label class="form-label" for="email">Email address</label>
				<input class="form-control" id="email" name="email" required type="email" value="{{ old('email') }}">
			</div>

			<div class="mb-3">
				<label class="form-label" for="password">Password</label>
				<input autocomplete="new-password" class="form-control" id="password" name="password" required type="password">
				<small class="form-hint">At least 12 characters.</small>
			</div>

			<div class="mb-3">
				<label class="form-label" for="password_confirmation">Confirm password</label>
				<input autocomplete="new-password" class="form-control" id="password_confirmation" name="password_confirmation" required type="password">
			</div>

			<div class="form-footer">
				<button class="btn btn-primary w-100" type="submit">Complete setup</button>
			</div>
		</div>
	</form>
@endsection
