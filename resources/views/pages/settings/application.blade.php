@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Application')
@section('page-title', 'Application')

@section('page-sub-content')
	<div class="card-body">
		<div class="card">
			<div class="card-header card-header-light">
				<h3 class="card-title mb-0">Application</h3>
			</div>
			<form action="{{ route('settings.application.update') }}" method="POST">
				@csrf
				@method('PUT')

				<div class="card-body">
					<div class="mb-3">
						<label class="form-label required" for="app_url">External URL</label>
						<input class="form-control" id="app_url" name="app_url" required type="url" value="{{ old('app_url', $settings['app_url'] ?? url('/')) }}">
						<small class="form-hint">Used to build the webhook URLs shown on each environment.</small>
					</div>
					<div class="mb-0">
						<label class="form-label required" for="timezone">Display timezone</label>
						<select class="form-select" id="timezone" name="timezone" required>
							@foreach (timezone_identifiers_list() as $timezone)
								<option value="{{ $timezone }}" @selected(old('timezone', $settings['timezone'] ?? 'Europe/London') === $timezone)>{{ $timezone }}</option>
							@endforeach
						</select>
						<small class="form-hint">Only affects how timestamps are shown. They are always stored in {{ config('app.timezone') }}.</small>
					</div>
				</div>
				<div class="card-footer text-end">
					<button class="btn btn-primary" type="submit">Save changes</button>
				</div>
			</form>
		</div>
	</div>
@endsection
