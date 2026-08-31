@php($isUpdate = $environment->exists)
<div class="card">
	<div class="card-header"><h3 class="card-title">Environment policy</h3></div>
	<div class="card-body"><div class="row g-3">
		<div class="col-md-6"><label class="form-label required" for="name">Name</label><input class="form-control" id="name" name="name" required value="{{ old('name', $environment->name) }}"></div>
		<div class="col-md-6"><label class="form-label" for="slug">Slug</label><input class="form-control" id="slug" name="slug" value="{{ old('slug', $environment->slug) }}"></div>
		<div class="col-md-6"><label class="form-label required" for="github_account_id">GitHub account</label><select class="form-select" id="github_account_id" name="github_account_id" required><option value="">Select an account</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected(old('github_account_id', $environment->github_account_id) == $account->id)>{{ $account->login }} ({{ $account->account_type }})</option>@endforeach</select></div>
		<div class="col-12"><label class="form-check form-switch"><input class="form-check-input" name="enabled" type="checkbox" value="1" @checked(old('enabled', $environment->enabled ?? true))><span class="form-check-label">Enabled</span></label></div>
		<div class="col-md-3"><label class="form-label required" for="max_lifetime_seconds">Max lifetime (s)</label><input class="form-control" id="max_lifetime_seconds" name="max_lifetime_seconds" type="number" required value="{{ old('max_lifetime_seconds', $environment->max_lifetime_seconds ?? 43200) }}"></div>
		<div class="col-md-3"><label class="form-label required" for="idle_timeout_seconds">Idle timeout (s)</label><input class="form-control" id="idle_timeout_seconds" name="idle_timeout_seconds" type="number" required value="{{ old('idle_timeout_seconds', $environment->idle_timeout_seconds ?? 900) }}"></div>
		<div class="col-md-3"><label class="form-label required" for="job_claim_timeout_seconds">Job claim timeout (s)</label><input class="form-control" id="job_claim_timeout_seconds" name="job_claim_timeout_seconds" type="number" required value="{{ old('job_claim_timeout_seconds', $environment->job_claim_timeout_seconds ?? 45) }}"></div>
		<div class="col-md-3 d-flex align-items-end"><label class="form-check form-switch mb-2"><input class="form-check-input" name="keep_failed_vms" type="checkbox" value="1" @checked(old('keep_failed_vms', $environment->keep_failed_vms ?? false))><span class="form-check-label">Keep failed VMs</span></label></div>
	</div></div>
	<div class="card-footer text-end"><a class="btn btn-link" href="{{ route('environments.index') }}">Cancel</a><button class="btn btn-primary" type="submit">{{ $isUpdate ? 'Save changes' : 'Create environment' }}</button></div>
</div>
