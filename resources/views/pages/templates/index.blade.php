@extends('layouts.admin-base-datatable')

@section('meta-page-title', 'Templates')
@section('page-pretitle', 'Images')
@section('page-title', 'Templates')
@section('page-table-description', 'Proxmox VM templates that runners are cloned from.')

@section('page-actions')
	<div class="col-auto ms-auto d-print-none">
		<a class="btn btn-primary" href="{{ route('templates.create') }}">
			<x-action-content icon="fa-solid fa-plus" label="Add template" />
		</a>
	</div>
@endsection
