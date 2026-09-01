@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Edit user')
@section('page-title', 'Edit user')

@section('page-sub-content')
	<form action="{{ route('settings.users.update', $user) }}" method="POST">
		@csrf
		@method('PUT')
		@include('pages.settings.users._form')
	</form>
@endsection
