@extends('layouts.admin-base')

@section('meta-page-title', 'Edit '.$template->name)
@section('page-pretitle', 'Templates')
@section('page-title', 'Edit '.$template->name)

@section('page-content')
	<div class="container-xl">
		<form action="{{ route('templates.update', $template) }}" method="POST">
			@csrf
			@method('PUT')
			@include('pages.templates._form')
		</form>
	</div>
@endsection
