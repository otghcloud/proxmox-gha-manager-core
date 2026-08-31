@extends('layouts.admin-base')

@section('meta-page-title', 'Add environment')
@section('page-pretitle', 'Environments')
@section('page-title', 'Add environment')

@section('page-content')
	<div class="container-xl">
		<form action="{{ route('environments.store') }}" method="POST">
			@csrf
			@include('pages.environments._form')
		</form>
	</div>
@endsection
