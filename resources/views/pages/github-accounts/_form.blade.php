@php($isUpdate = $account->exists)
<div class="card">
	<div class="card-header"><h3 class="card-title">GitHub account</h3></div>
	<div class="card-body"><div class="row g-3">
		<div class="col-md-4"><label class="form-label required" for="account_type">Type</label><select class="form-select" id="account_type" name="account_type" required><option value="organization" @selected(old('account_type', $account->account_type) === 'organization')>Organisation</option><option value="user" @selected(old('account_type', $account->account_type) === 'user')>Personal user</option></select></div>
		<div class="col-md-8"><label class="form-label required" for="login">GitHub login</label><input class="form-control" id="login" name="login" required value="{{ old('login', $account->login) }}"></div>
		@if ($isUpdate)
		<div class="col-12"><label class="form-label required" for="webhook_id">Webhook UUID</label><input class="form-control font-monospace" id="webhook_id" name="webhook_id" required value="{{ old('webhook_id', $account->webhook_id) }}"><small class="form-hint">Changing this changes the webhook URL. Update the webhook in GitHub after saving.</small></div>
		@endif
		<div class="col-md-6"><label class="form-label {{ $isUpdate ? '' : 'required' }}" for="github_token">GitHub token</label><input class="form-control" id="github_token" name="github_token" type="password" {{ $isUpdate ? '' : 'required' }}></div>
		<div class="col-md-6"><label class="form-label {{ $isUpdate ? '' : 'required' }}" for="github_webhook_secret">Webhook secret</label><input class="form-control" id="github_webhook_secret" name="github_webhook_secret" type="password" {{ $isUpdate ? '' : 'required' }}></div>
		<div class="col-md-6"><label class="form-label required" for="github_api_url">API URL</label><input class="form-control" id="github_api_url" name="github_api_url" type="url" required value="{{ old('github_api_url', $account->github_api_url) }}"></div>
		<div class="col-md-3"><label class="form-label required" for="github_runner_group_id">Runner group ID</label><input class="form-control" id="github_runner_group_id" name="github_runner_group_id" type="number" required value="{{ old('github_runner_group_id', $account->github_runner_group_id) }}"></div>
		<div class="col-md-3"><label class="form-label required" for="github_work_folder">Work folder</label><input class="form-control" id="github_work_folder" name="github_work_folder" required value="{{ old('github_work_folder', $account->github_work_folder) }}"></div>
		<div class="col-md-6"><label class="form-label required" for="linux_ssh_username">Linux SSH username</label><input class="form-control" id="linux_ssh_username" name="linux_ssh_username" required value="{{ old('linux_ssh_username', $account->linux_ssh_username) }}"></div>
		<div class="col-md-6"><label class="form-label" for="linux_ssh_password">Linux SSH password</label><input class="form-control" id="linux_ssh_password" name="linux_ssh_password" type="password"></div>
		<div class="col-md-6"><label class="form-label" for="windows_username">Windows username</label><input class="form-control" id="windows_username" name="windows_username" value="{{ old('windows_username', $account->windows_username) }}"></div>
		<div class="col-md-6"><label class="form-label" for="windows_password">Windows password</label><input class="form-control" id="windows_password" name="windows_password" type="password"></div>
	</div></div>
	<div class="card-footer text-end"><a class="btn btn-link" href="{{ route('github-accounts.index') }}">Cancel</a><button class="btn btn-primary" type="submit">{{ $isUpdate ? 'Save changes' : 'Create account' }}</button></div>
</div>
