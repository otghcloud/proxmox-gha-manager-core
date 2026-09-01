@php($isUpdate = $user->exists)
<div class="card-body">
	<div class="row g-3">
		<div class="col-12">
			<label class="form-label required" for="name">Name</label>
			<input class="form-control" id="name" name="name" required value="{{ old('name', $user->name) }}">
		</div>
		<div class="col-12">
			<label class="form-label required" for="email">Email</label>
			<input class="form-control" id="email" name="email" required type="email" value="{{ old('email', $user->email) }}">
		</div>
		<div class="col-md-6">
			<label class="form-label {{ $isUpdate ? '' : 'required' }}" for="password">{{ $isUpdate ? 'New password' : 'Password' }}</label>
			<input class="form-control" id="password" name="password" type="password" {{ $isUpdate ? '' : 'required' }}>
			@if ($isUpdate)
				<small class="form-hint">Leave blank to keep the current password.</small>
			@endif
		</div>
		<div class="col-md-6">
			<label class="form-label {{ $isUpdate ? '' : 'required' }}" for="password_confirmation">Confirm password</label>
			<input class="form-control" id="password_confirmation" name="password_confirmation" type="password" {{ $isUpdate ? '' : 'required' }}>
		</div>
	</div>
</div>
<div class="card-footer text-end">
	<a class="btn btn-link" href="{{ route('settings.users.index') }}">Cancel</a>
	<button class="btn btn-primary" type="submit">{{ $isUpdate ? 'Save changes' : 'Create user' }}</button>
</div>
