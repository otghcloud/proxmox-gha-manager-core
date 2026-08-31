@extends('layouts.admin-base')

@section('meta-page-title', 'Add pool')
@section('page-pretitle', 'Pools')
@section('page-title', 'Add pool')

@section('page-content')
	<div class="container-xl">
		<form action="{{ route('pools.store') }}" method="POST">
			@csrf
			@include('pages.pools._form')
		</form>
	</div>
@endsection
