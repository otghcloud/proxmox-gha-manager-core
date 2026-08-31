@props(['icon' => null, 'label' => ''])

@if ($icon)
	<i class="{{ $icon }}"></i>
@endif
<span class="d-none d-md-inline ms-1">{{ $label }}</span>
