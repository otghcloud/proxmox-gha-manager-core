@extends('layouts.admin-base')

@section('meta-page-title', 'Edit '.$pool->name)
@section('page-pretitle', 'Pools')
@section('page-title', 'Edit '.$pool->name)

@section('page-content')
	<div class="container-xl">
		<form action="{{ route('pools.update', $pool) }}" method="POST">
			@csrf
			@method('PUT')
			@include('pages.pools._form')
		</form>
	</div>
@endsection
