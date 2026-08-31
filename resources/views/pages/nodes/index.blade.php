@extends('layouts.admin-base-datatable')
@section('meta-page-title', 'Nodes')
@section('page-pretitle', 'Infrastructure')
@section('page-title', 'Proxmox nodes')
@section('page-actions')<div class="col-auto ms-auto"><a class="btn btn-primary" href="{{ route('nodes.create') }}"><x-action-content icon="fa-solid fa-plus" label="Add node" /></a></div>@endsection
@section('page-table-description', 'Reusable Proxmox nodes with independent capacity, health, and template coverage.')
