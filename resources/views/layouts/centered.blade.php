<!doctype html>
<html lang="en">

	<head>
		<meta charset="utf-8" />
		<meta content="width=device-width, initial-scale=1, viewport-fit=cover" name="viewport" />
		<title>@yield('meta-page-title', 'Sign in') :: Proxmox GHA Manager</title>
		<meta content="#662071" name="theme-color" />

		@vite(['resources/sass/backend.scss', 'resources/js/app.js'])
	</head>

	<body class="d-flex flex-column bg-light">
		<div class="page page-center">
			<div class="container container-tight py-4">

				<div class="text-center mb-4">
					<a href="{{ url('/') }}">
						<img src="{{ asset('default_logo_dark.png') }}" width="180" alt="OTGH Cloud" />
					</a>
				</div>

				@if (session('success'))
					<x-alert type="success">{{ session('success') }}</x-alert>
				@endif

				@if (session('error'))
					<x-alert type="danger">{{ session('error') }}</x-alert>
				@endif

				@if ($errors->any())
					<x-alert-list :items="$errors->all()" type="danger" />
				@endif

				@yield('content')
			</div>
		</div>
	</body>

</html>
