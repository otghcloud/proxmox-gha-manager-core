@extends('layouts.admin-base')
@section('meta-page-title', 'GitHub accounts')
@section('page-pretitle', 'Configuration')
@section('page-title', 'GitHub accounts')
@section('page-actions')<div class="col-auto ms-auto"><a class="btn btn-primary" href="{{ route('github-accounts.create') }}"><x-action-content icon="fa-solid fa-plus" label="Add account" /></a></div>@endsection
@section('page-content')<div class="container-xl"><div class="card"><div class="table-responsive"><table class="table card-table table-vcenter"><thead><tr><th>Login</th><th>Type</th><th>Webhook ID</th><th>Environments</th><th></th></tr></thead><tbody>@forelse ($accounts as $account)<tr><td>{{ $account->login }}</td><td>{{ ucfirst($account->account_type) }}</td><td class="font-monospace">{{ $account->webhook_id }}</td><td>{{ $account->environments_count }}</td><td class="text-end"><a class="btn btn-sm" href="{{ route('github-accounts.edit', $account) }}"><x-action-content icon="fa-solid fa-pencil" label="Edit" /></a></td></tr>@empty<tr><td colspan="5" class="text-secondary">No GitHub accounts configured.</td></tr>@endforelse</tbody></table></div></div></div>@endsection
