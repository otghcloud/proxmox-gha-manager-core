@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Templates')
@section('page-title', 'Templates')

@section('page-sub-content')
	<form action="{{ route('settings.templates.update') }}" method="POST">
		@csrf
		@method('PUT')

		<div class="card-body">
			<h3 class="card-title">Retention</h3>
			<p class="text-secondary small">
				A rebuild always builds into a new VMID and switches pools over once it succeeds. This
				controls what happens to the template it replaced.
			</p>
			<div class="mb-3" data-template-retention>
				<label class="form-check">
					<input class="form-check-input" name="template_retention_mode" type="radio" value="auto" @checked(old('template_retention_mode', $settings['template_retention_mode'] ?? 'auto') !== 'keep_last_n')>
					<span class="form-check-label">Delete as soon as no runner is cloned from it</span>
				</label>
				<label class="form-check">
					<input class="form-check-input" name="template_retention_mode" type="radio" value="keep_last_n" @checked(old('template_retention_mode', $settings['template_retention_mode'] ?? 'auto') === 'keep_last_n')>
					<span class="form-check-label">Keep the last few generations for rollback</span>
				</label>
			</div>
			<div class="mb-0">
				<label class="form-label" for="template_retention_generations">Generations to keep</label>
				<input class="form-control" id="template_retention_generations" max="20" min="1" name="template_retention_generations" type="number" value="{{ old('template_retention_generations', $settings['template_retention_generations'] ?? 1) }}">
				<small class="form-hint">Only used when keeping generations. Older ones are pruned hourly.</small>
			</div>
		</div>
		<hr class="my-0">
		<div class="card-body">
			<div class="d-flex align-items-center justify-content-between">
				<h3 class="card-title mb-0">Automatic updates</h3>
				<button class="btn btn-sm btn-outline-secondary" form="check-template-updates-form" type="submit">
					<x-action-content icon="fa-solid fa-arrows-rotate" label="Check now" />
				</button>
			</div>
			<p class="text-secondary small">
				Automatically check GitHub for template index updates and notify when new versions are published.
			</p>
			<div class="mb-3">
				<label class="form-check form-switch">
					<input class="form-check-input" name="template_auto_check_enabled" type="checkbox" value="1" @checked(old('template_auto_check_enabled', $settings['template_auto_check_enabled'] ?? '0') == '1')>
					<span class="form-check-label">Automatically check for template updates</span>
				</label>
			</div>
			<div class="mb-3">
				<label class="form-label" for="template_check_interval_hours">Check interval (hours)</label>
				<input class="form-control" id="template_check_interval_hours" max="168" min="1" name="template_check_interval_hours" type="number" value="{{ old('template_check_interval_hours', $settings['template_check_interval_hours'] ?? 24) }}">
				<small class="form-hint">Queries GitHub template index every X hours for version updates.</small>
			</div>

			@if (! empty($settings[\App\Services\SettingsRepository::TEMPLATE_UPDATES_AVAILABLE]))
				@php($updateData = json_decode($settings[\App\Services\SettingsRepository::TEMPLATE_UPDATES_AVAILABLE], true))
				@if (is_array($updateData))
					<div class="alert alert-info mb-0">
						<div class="d-flex align-items-center gap-2">
							<i class="fa-solid fa-clock-rotate-left"></i>
							<div>
								<strong>Last checked:</strong> {{ ! empty($updateData['checked_at']) ? \Carbon\Carbon::parse($updateData['checked_at'])->diffForHumans() : 'Recently' }}
								&middot;
								@if (! empty($updateData['available']))
									<span class="text-warning font-weight-bold">{{ count($updateData['updates'] ?? []) }} update(s) available.</span>
								@else
									<span class="text-success">All templates up to date.</span>
								@endif
							</div>
						</div>
					</div>
				@endif
			@endif
		</div>
		<div class="card-footer text-end">
			<button class="btn btn-primary" type="submit">Save changes</button>
		</div>
	</form>

	<form action="{{ route('settings.templates.check-updates') }}" id="check-template-updates-form" method="POST">
		@csrf
	</form>
@endsection
