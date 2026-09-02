@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Edit user')
@section('page-title', 'Edit user')

@section('page-sub-content')
	<div class="card-body">
		<div class="card">
			<div class="card-header card-header-light">
				<h3 class="card-title mb-0">User details</h3>
			</div>
			<form action="{{ route('settings.users.update', $user) }}" method="POST">
				@csrf
				@method('PUT')
				@include('pages.settings.users._form')
			</form>
		</div>
	</div>
@endsection
