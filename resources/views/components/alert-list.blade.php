@props(['items' => [], 'heading' => null, 'type' => 'danger'])

@if (! empty($items))
	<div {{ $attributes->merge(['class' => 'alert alert-'.$type.' alert-dismissible', 'role' => 'alert']) }}>
		@if ($heading)
			<h4 class="alert-title">{{ $heading }}</h4>
		@endif
		<ul class="mb-0 ps-3">
			@foreach ($items as $item)
				<li>{{ $item }}</li>
			@endforeach
		</ul>
		<a aria-label="close" class="btn-close" data-bs-dismiss="alert"></a>
	</div>
@endif
