@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Users')
@section('page-title', 'Users')

@section('page-sub-content')
	<div class="card-body">
		<div class="card">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h3 class="card-title mb-0">Users</h3>
				<a class="btn btn-primary btn-sm" href="{{ route('settings.users.create') }}">
					<x-action-content icon="fa-solid fa-plus" label="Add user" />
				</a>
			</div>
			<div class="table-responsive">
				<table class="table card-table table-vcenter">
					<thead>
						<tr>
							<th>Name</th>
							<th>Email</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						@forelse ($users as $user)
							<tr>
								<td>{{ $user->name }}</td>
								<td class="text-secondary">{{ $user->email }}</td>
								<td class="text-end">
									<a class="btn btn-sm" href="{{ route('settings.users.edit', $user) }}">
										<x-action-content icon="fa-solid fa-pencil" label="Edit" />
									</a>
									<form action="{{ route('settings.users.destroy', $user) }}" class="d-inline" method="POST" onsubmit="return confirm('Delete this user? This cannot be undone.');">
										@csrf
										@method('DELETE')
										<button class="btn btn-sm text-danger" type="submit">
											<x-action-content icon="fa-solid fa-trash" label="Delete" />
										</button>
									</form>
								</td>
							</tr>
						@empty
							<tr><td class="text-secondary" colspan="3">No users configured.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>
@endsection
