@extends('layouts.admin-base')

@section('meta-page-title', 'Add template')
@section('page-pretitle', 'Templates')
@section('page-title', 'Add template')

@section('page-content')
	<div class="container-xl">
		<form action="{{ route('templates.store') }}" method="POST">
			@csrf
			@include('pages.templates._form')
		</form>
	</div>
@endsection
