@extends('layouts.admin-base')

@section('meta-page-title', 'Edit '.$environment->name)
@section('page-pretitle', 'Environments')
@section('page-title', 'Edit '.$environment->name)

@section('page-content')
	<div class="container-xl">
		<form action="{{ route('environments.update', $environment) }}" method="POST">
			@csrf
			@method('PUT')
			@include('pages.environments._form')
		</form>
	</div>
@endsection
