@extends('layouts.centered')

@section('meta-page-title', 'Sign in')

@section('content')
	<form action="{{ route('login.store') }}" class="card card-md" method="POST">
		@csrf

		<div class="card-body">
			<h2 class="h2 text-center mb-4">Sign in to Proxmox GHA Manager</h2>

			<div class="mb-3">
				<label class="form-label" for="email">Email address</label>
				<input autocomplete="email" autofocus class="form-control" id="email" name="email" required type="email" value="{{ old('email') }}">
			</div>

			<div class="mb-2">
				<label class="form-label" for="password">Password</label>
				<input autocomplete="current-password" class="form-control" id="password" name="password" required type="password">
			</div>

			<div class="mb-2">
				<label class="form-check">
					<input class="form-check-input" name="remember" type="checkbox" value="1">
					<span class="form-check-label">Remember me on this device</span>
				</label>
			</div>

			<div class="form-footer">
				<button class="btn btn-primary w-100" type="submit">Sign in</button>
			</div>
		</div>
	</form>
@endsection
