@extends('layouts.admin-base')
@section('meta-page-title', 'Add GitHub account')
@section('page-pretitle', 'GitHub accounts')
@section('page-title', 'Add GitHub account')
@section('page-content')<div class="container-xl"><form action="{{ route('github-accounts.store') }}" method="POST">@csrf @include('pages.github-accounts._form')</form></div>@endsection
