@extends('layouts.admin-base')

@section('meta-page-title', 'Edit '.$target->name)
@section('page-pretitle', $environment?->name ?? 'Infrastructure')
@section('page-title', 'Edit '.$target->name)

@section('page-content')
	<div class="container-xl">
		<form action="{{ $environment ? route('environments.targets.update', [$environment, $target]) : route('nodes.update', $target) }}" method="POST">
			@csrf
			@method('PUT')
			@include('pages.nodes._form')
		</form>
	</div>
@endsection
