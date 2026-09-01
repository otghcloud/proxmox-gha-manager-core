@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Jobs')
@section('page-title', 'Jobs')

@section('page-sub-content')
	<form action="{{ route('settings.jobs.update') }}" method="POST">
		@csrf
		@method('PUT')

		<div class="card-body">
			<h3 class="card-title">Log retention</h3>
			<label class="form-label" for="job_log_retention_days">Keep job logs for (days)</label>
			<input class="form-control" id="job_log_retention_days" max="365" min="1" name="job_log_retention_days" type="number" value="{{ old('job_log_retention_days', $settings['job_log_retention_days'] ?? 14) }}">
			<small class="form-hint">Job records are kept indefinitely; only the stored log output is pruned.</small>
		</div>
		<div class="card-footer text-end">
			<button class="btn btn-primary" type="submit">Save changes</button>
		</div>
	</form>
@endsection
