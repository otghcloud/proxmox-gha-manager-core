@extends('layouts.admin-base-datatable')

@section('meta-page-title', 'Jobs')
@section('page-pretitle', 'Workflows')
@section('page-title', 'Jobs')
@section('page-table-description', 'Every GitHub Actions job this installation has served.')

@section('page-table-filters')
	<select class="form-select w-auto" id="filter-environment">
		<option value="">All environments</option>
		@foreach ($environments as $environment)
			<option value="{{ $environment->id }}">{{ $environment->name }}</option>
		@endforeach
	</select>
	<select class="form-select w-auto" id="filter-conclusion">
		<option value="">All results</option>
		@foreach (\App\Enums\JobConclusion::cases() as $conclusion)
			<option value="{{ $conclusion->value }}">{{ $conclusion->label() }}</option>
		@endforeach
	</select>
@endsection
