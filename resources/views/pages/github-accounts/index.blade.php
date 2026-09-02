@extends('layouts.admin-base-datatable')

@section('meta-page-title', 'GitHub accounts')
@section('page-pretitle', 'Configuration')
@section('page-title', 'GitHub accounts')
@section('page-table-description', 'GitHub organizations and users this installation provisions runners for.')

@section('page-actions')
	<div class="col-auto ms-auto d-print-none">
		<a class="btn btn-primary" href="{{ route('github-accounts.create') }}">
			<x-action-content icon="fa-solid fa-plus" label="Add account" />
		</a>
	</div>
@endsection
