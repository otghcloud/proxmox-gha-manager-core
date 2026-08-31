@extends('layouts.admin-base')

@section('meta-page-title', 'Add Proxmox target')
@section('page-pretitle', $environment?->name ?? 'Infrastructure')
@section('page-title', 'Add Proxmox target')

@section('page-content')
	<div class="container-xl">
		<form action="{{ $environment ? route('environments.targets.store', $environment) : route('nodes.store') }}" method="POST">
			@csrf
			@include('pages.nodes._form')
		</form>
	</div>
@endsection
