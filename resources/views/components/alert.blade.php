@props(['type' => 'info'])

@php
	$icons = [
		'success' => 'fa-solid fa-circle-check',
		'danger' => 'fa-solid fa-circle-exclamation',
		'warning' => 'fa-solid fa-triangle-exclamation',
		'info' => 'fa-solid fa-circle-info',
	];
@endphp

<div {{ $attributes->merge(['class' => 'alert alert-'.$type.' alert-dismissible', 'role' => 'alert']) }}>
	<div class="d-flex">
		<div class="me-2">
			<i class="{{ $icons[$type] ?? $icons['info'] }}"></i>
		</div>
		<div>{{ $slot }}</div>
	</div>
	<a aria-label="close" class="btn-close" data-bs-dismiss="alert"></a>
</div>
