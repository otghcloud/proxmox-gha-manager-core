@extends('pages.settings.base')

@section('meta-page-title', 'Settings · Overview')
@section('page-title', 'Overview')

@section('page-sub-content')
	<div class="card-body">
		<h3 class="card-title">Installation</h3>
		<dl class="row mb-0">
			<dt class="col-5">Installed</dt>
			<dd class="col-7">{{ !empty($settings['installed_at']) ? \Illuminate\Support\Carbon::parse($settings['installed_at'])->forDisplay()->format('d/m/Y H:i:s') : '—' }}</dd>
			<dt class="col-5">Database</dt>
			<dd class="col-7">{{ ucfirst(config('database.default')) }}</dd>
			<dt class="col-5">Queue</dt>
			<dd class="col-7">{{ ucfirst(config('queue.default')) }}</dd>
		</dl>
	</div>
@endsection
