@extends('layouts.admin-base')
@section('meta-page-title', 'Edit '.$account->login)
@section('page-pretitle', 'GitHub accounts')
@section('page-title', 'Edit '.$account->login)
@section('page-content')<div class="container-xl"><form action="{{ route('github-accounts.update', $account) }}" method="POST">@csrf @method('PUT') @include('pages.github-accounts._form')</form></div>@endsection
