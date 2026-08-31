@extends('layouts.admin-base')

@section('meta-page-title', 'Settings')
@section('page-pretitle', 'Configuration')
@section('page-title', 'Settings')

@section('page-content')
	<div class="container-xl">
		<div class="row row-cards">
			<div class="col-lg-7">
				<form action="{{ route('settings.update') }}" method="POST">
					@csrf
					@method('PUT')

					<div class="card">
						<div class="card-header"><h3 class="card-title">Application</h3></div>
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
					</div>

					<div class="card mt-3">
						<div class="card-header"><h3 class="card-title">Template retention</h3></div>
						<div class="card-body">
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
						<div class="card-footer text-end">
							<button class="btn btn-primary" type="submit">Save changes</button>
						</div>
					</div>

					<div class="card mt-3">
						<div class="card-header"><h3 class="card-title">Job logs</h3></div>
						<div class="card-body">
							<label class="form-label" for="job_log_retention_days">Keep job logs for (days)</label>
							<input class="form-control" id="job_log_retention_days" max="365" min="1" name="job_log_retention_days" type="number" value="{{ old('job_log_retention_days', $settings['job_log_retention_days'] ?? 14) }}">
							<small class="form-hint">Job records are kept indefinitely; only the stored log output is pruned.</small>
						</div>
						<div class="card-footer text-end">
							<button class="btn btn-primary" type="submit">Save changes</button>
						</div>
					</div>

					<div class="card mt-3">
						<div class="card-header d-flex align-items-center justify-content-between">
							<h3 class="card-title mb-0">Template updates</h3>
							<button class="btn btn-sm btn-outline-secondary" form="check-template-updates-form" type="submit">
								<x-action-content icon="fa-solid fa-arrows-rotate" label="Check now" />
							</button>
						</div>
						<div class="card-body">
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
					</div>
				</form>

				<form action="{{ route('settings.check-template-updates') }}" id="check-template-updates-form" method="POST">
					@csrf
				</form>
			</div>

			<div class="col-lg-5">
				<div class="card">
					<div class="card-header"><h3 class="card-title">Users</h3></div>
					<div class="table-responsive">
						<table class="table card-table table-vcenter">
							<thead><tr><th>Name</th><th>Email</th></tr></thead>
							<tbody>
								@foreach ($users as $user)
									<tr>
										<td>{{ $user->name }}</td>
										<td class="text-secondary">{{ $user->email }}</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>

				<div class="card mt-3">
					<div class="card-header"><h3 class="card-title">Installation</h3></div>
					<div class="card-body">
						<dl class="row mb-0">
							<dt class="col-5">Installed</dt>
							<dd class="col-7">{{ !empty($settings['installed_at']) ? \Illuminate\Support\Carbon::parse($settings['installed_at'])->forDisplay()->format('d/m/Y H:i:s') : '—' }}</dd>
							<dt class="col-5">Database</dt>
							<dd class="col-7">{{ ucfirst(config('database.default')) }}</dd>
							<dt class="col-5">Queue</dt>
							<dd class="col-7">{{ ucfirst(config('queue.default')) }}</dd>
						</dl>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection
