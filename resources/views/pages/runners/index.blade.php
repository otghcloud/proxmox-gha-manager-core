@extends('layouts.admin-base-datatable')

@section('meta-page-title', 'Runners')
@section('page-pretitle', 'Workflows')
@section('page-title', 'Runners')
@section('page-table-description', 'Every runner VM this installation has provisioned.')

@section('page-table-filters')
	<select class="form-select w-auto" id="filter-environment">
		<option value="">All environments</option>
		@foreach ($environments as $environment)
			<option value="{{ $environment->id }}">{{ $environment->name }}</option>
		@endforeach
	</select>
	<select class="form-select w-auto" id="filter-state">
		<option value="">All states</option>
		@foreach (\App\Enums\RunnerState::cases() as $state)
			<option value="{{ $state->value }}">{{ $state->label() }}</option>
		@endforeach
	</select>
@endsection
