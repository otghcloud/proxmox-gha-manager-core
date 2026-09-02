@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Templates · Retention')
@section('page-title', 'Templates · Retention')

@section('page-sub-content')
	<div class="card-body">
		<form action="{{ route('settings.templates.retention.update') }}" method="POST">
			@csrf
			@method('PUT')

			<div class="card mb-4">
				<div class="card-header card-header-light">
					<h3 class="card-title mb-0">Built templates retention</h3>
				</div>
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
			</div>

			<div class="card">
				<div class="card-header card-header-light">
					<h3 class="card-title mb-0">Template bundles retention</h3>
				</div>
				<div class="card-body">
					<p class="text-secondary small">
						Controls how many downloaded template bundles are kept on disk for rollback. The active
						bundle is never deleted.
					</p>
					<div class="mb-3" data-template-bundle-retention>
						<label class="form-check">
							<input class="form-check-input" name="template_bundle_retention_mode" type="radio" value="auto" @checked(old('template_bundle_retention_mode', $settings['template_bundle_retention_mode'] ?? 'auto') !== 'keep_last_n')>
							<span class="form-check-label">Keep only the active bundle</span>
						</label>
						<label class="form-check">
							<input class="form-check-input" name="template_bundle_retention_mode" type="radio" value="keep_last_n" @checked(old('template_bundle_retention_mode', $settings['template_bundle_retention_mode'] ?? 'auto') === 'keep_last_n')>
							<span class="form-check-label">Keep the last few bundles for rollback</span>
						</label>
					</div>
					<div class="mb-0">
						<label class="form-label" for="template_bundle_retention_generations">Bundles to keep</label>
						<input class="form-control" id="template_bundle_retention_generations" max="20" min="1" name="template_bundle_retention_generations" type="number" value="{{ old('template_bundle_retention_generations', $settings['template_bundle_retention_generations'] ?? 1) }}">
						<small class="form-hint">Only used when keeping bundles. Older ones are pruned hourly.</small>
					</div>
				</div>
				<div class="card-footer text-end">
					<button class="btn btn-primary" type="submit">Save changes</button>
				</div>
			</div>
		</form>
	</div>
@endsection
