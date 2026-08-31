@extends('layouts.admin-base-datatable')

@section('meta-page-title', 'Pools')
@section('page-pretitle', 'Configuration')
@section('page-title', 'Pools')
@section('page-table-description', 'A queued job matches a pool when every label it asks for appears here.')

@section('page-actions')
	<div class="col-auto ms-auto d-print-none">
		<a class="btn btn-primary" href="{{ route('pools.create') }}">
			<x-action-content icon="fa-solid fa-plus" label="Add pool" />
		</a>
	</div>
@endsection
