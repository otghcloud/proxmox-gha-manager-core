@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Add user')
@section('page-title', 'Add user')

@section('page-sub-content')
	<form action="{{ route('settings.users.store') }}" method="POST">
		@csrf
		@include('pages.settings.users._form')
	</form>
@endsection
