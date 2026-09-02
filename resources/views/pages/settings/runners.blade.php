@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Runners')
@section('page-title', 'Runners')

@section('page-sub-content')
	<div class="card-body">
		<div class="card">
			<div class="card-header card-header-light">
				<h3 class="card-title mb-0">Runner naming</h3>
			</div>
			<form action="{{ route('settings.runners.update') }}" method="POST">
				@csrf
				@method('PUT')

				<div class="card-body">
					<label class="form-label required" for="runner_name_prefix">Runner name prefix</label>
					<input class="form-control" id="runner_name_prefix" maxlength="32" name="runner_name_prefix" required value="{{ old('runner_name_prefix', $settings['runner_name_prefix'] ?? 'gha') }}">
					<small class="form-hint">Provisioned runner VMs are named "{prefix}-XXXXXXXXXXXXXXXX".</small>
				</div>
				<div class="card-footer text-end">
					<button class="btn btn-primary" type="submit">Save changes</button>
				</div>
			</form>
		</div>
	</div>
@endsection
