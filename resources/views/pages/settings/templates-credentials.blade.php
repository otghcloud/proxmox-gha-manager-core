@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Templates · Credentials')
@section('page-title', 'Templates · Credentials')

@section('page-sub-content')
	<div class="card-header d-flex justify-content-between align-items-center"><h3 class="card-title mb-0">Runner credentials</h3><div><form class="d-inline" action="{{ route('settings.templates.credentials.default') }}" method="POST">@csrf<button class="btn btn-outline-secondary me-2" type="submit">Ensure default SSH key</button></form><a class="btn btn-primary" href="{{ route('settings.templates.credentials.create') }}">Add credential</a></div></div>
	<div class="table-responsive"><table class="table card-table table-vcenter"><thead><tr><th>Name</th><th>Platform</th><th>Username</th><th>Authentication</th><th></th></tr></thead><tbody>
	@forelse ($credentials as $credential)
		<tr><td>{{ $credential->name }}</td><td>{{ $credential->os->label() }}</td><td>{{ $credential->username ?: 'Default setting' }}</td><td>{{ $credential->private_key ? 'SSH key' : 'Password' }}</td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('settings.templates.credentials.edit', $credential) }}">Edit</a><form class="d-inline" action="{{ route('settings.templates.credentials.destroy', $credential) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form></td></tr>
	@empty
		<tr><td class="text-secondary" colspan="5">No credentials configured.</td></tr>
	@endforelse
	</tbody></table></div>
@endsection
