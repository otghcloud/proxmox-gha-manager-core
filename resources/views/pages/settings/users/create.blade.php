@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Add user')
@section('page-title', 'Add user')

@section('page-sub-content')
	<div class="card-body">
		<div class="card">
			<div class="card-header card-header-light">
				<h3 class="card-title mb-0">User details</h3>
			</div>
			<form action="{{ route('settings.users.store') }}" method="POST">
				@csrf
				@include('pages.settings.users._form')
			</form>
		</div>
	</div>
@endsection
