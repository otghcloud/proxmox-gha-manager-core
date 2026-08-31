@extends('layouts.admin-base-datatable')

@section('meta-page-title', 'Environments')
@section('page-pretitle', 'Configuration')
@section('page-title', 'Environments')
@section('page-table-description', 'Environments define runner pools and policies for a GitHub account.')

@section('page-actions')
	<div class="col-auto ms-auto d-print-none">
		<a class="btn btn-primary" href="{{ route('environments.create') }}">
			<x-action-content icon="fa-solid fa-plus" label="Add environment" />
		</a>
	</div>
@endsection
