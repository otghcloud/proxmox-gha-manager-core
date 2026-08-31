@extends('layouts.admin-base')

@section('page-content')
	<div class="container-xl">
		<div class="row">
			<div class="col-12">
				<div class="card">
					<div class="card-header">
						<div class="row w-100">
							<div class="col">
								<h3 class="card-title mb-0">@yield('page-title')</h3>
								<p class="text-secondary m-0">@yield('page-table-description')</p>
							</div>
							<div class="col-md-auto col-sm-12">
								<div class="ms-auto d-flex flex-wrap gap-2">
									@hasSection('page-table-filters')
										@yield('page-table-filters')
									@endif
									<div class="input-group input-group-flat w-auto">
										<span class="input-group-text">
											<i class="fa-solid fa-magnifying-glass"></i>
										</span>
										<input autocomplete="off" class="form-control" id="advanced-table-search" placeholder="Search" type="text">
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="table-responsive-datatable">
						{{ $dataTable->table() }}
					</div>
					<div class="card-footer datatable-card-footer"></div>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	@vite('resources/js/base/datatables.js')
	{{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
