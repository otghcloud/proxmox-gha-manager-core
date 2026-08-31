<!doctype html>
<html lang="en">

	<head>
		<meta charset="utf-8" />
		<meta content="width=device-width, initial-scale=1, viewport-fit=cover" name="viewport" />
		<meta content="{{ csrf_token() }}" name="csrf-token" />
		<title>@yield('meta-page-title', 'Proxmox Actions Manager') :: Proxmox Actions Manager</title>
		<meta content="#662071" name="theme-color" />
		<meta content="Ephemeral GitHub Actions runners backed by Proxmox VE" name="description" />

		@vite(['resources/sass/backend.scss', 'resources/js/app.js'])
		@livewireStyles
	</head>

	<body>
		<div class="page">
			@include('layouts.global.header')

			<div class="page-wrapper">

				<div aria-label="Page header" class="page-header d-print-none">
					<div class="container-xl">
						<div class="row g-2 align-items-center">
							<div class="col">
								@hasSection('page-pretitle')
									<div class="page-pretitle">@yield('page-pretitle')</div>
								@endif
								@hasSection('page-title')
									<h2 class="page-title">@yield('page-title')</h2>
								@endif
							</div>

							@hasSection('page-actions')
								@yield('page-actions')
							@endif
						</div>
					</div>
				</div>

				@hasSection('page-breadcrumbs')
					@yield('page-breadcrumbs')
				@else
					@php($breadcrumbs = \App\Helpers\BreadcrumbHelpers::forRequest())
					@if (! empty($breadcrumbs))
						<div class="container-xl mt-2 mb-1">
							<ol class="breadcrumb breadcrumb-arrows mb-0">
								@foreach ($breadcrumbs as $breadcrumb)
									<li class="breadcrumb-item{{ $breadcrumb['active'] ? ' active' : '' }}">
										@if (! empty($breadcrumb['href']))
											<a href="{{ $breadcrumb['href'] }}">{{ $breadcrumb['label'] }}</a>
										@else
											<span>{{ $breadcrumb['label'] }}</span>
										@endif
									</li>
								@endforeach
							</ol>
						</div>
					@endif
				@endif

				<div class="page-body">

					@if (session('success'))
						<div class="container-xl">
							<x-alert type="success">{{ session('success') }}</x-alert>
						</div>
					@endif

					@if (session('error'))
						<div class="container-xl">
							<x-alert type="danger">{{ session('error') }}</x-alert>
						</div>
					@endif

					@if ($errors->any())
						<div class="container-xl">
							<x-alert-list :items="$errors->all()" heading="Validation Errors:" type="danger" />
						</div>
					@endif

					@hasSection('page-alerts')
						<div class="container-xl">
							@yield('page-alerts')
						</div>
					@endif

					@yield('page-content')
				</div>

				@include('layouts.global.footer')
			</div>
		</div>

		@include('components.delete-modal')

		@livewireScripts
		@stack('scripts')
	</body>

</html>
