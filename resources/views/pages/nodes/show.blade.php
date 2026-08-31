@extends('layouts.admin-base')

@section('meta-page-title', $target->name)
@section('page-pretitle', 'Proxmox nodes')
@section('page-title', $target->name)

@section('page-actions')
	<div class="col-auto ms-auto d-print-none"><div class="btn-list">
		<form action="{{ route('nodes.test', $target) }}" method="POST">@csrf<button class="btn" type="submit"><x-action-content icon="fa-solid fa-plug-circle-check" label="Test connection" /></button></form>
		<a class="btn" href="{{ route('nodes.edit', $target) }}"><x-action-content icon="fa-solid fa-pencil" label="Edit" /></a>
	</div></div>
@endsection

@section('page-content')
	<div class="container-xl"><div class="row row-cards">
		<div class="col-lg-5"><div class="card"><div class="card-header"><h3 class="card-title">Connection</h3></div><div class="card-body"><dl class="row mb-0">
			<dt class="col-5">API URL</dt><dd class="col-7 text-break">{{ $target->proxmox_url }}</dd>
			<dt class="col-5">Node</dt><dd class="col-7">{{ $target->proxmox_node }}</dd>
			<dt class="col-5">TLS verification</dt><dd class="col-7">{{ $target->proxmox_verify_tls ? 'Enabled' : 'Disabled' }}</dd>
			<dt class="col-5">Resource pool</dt><dd class="col-7">{{ $target->proxmox_resource_pool ?: '—' }}</dd>
			<dt class="col-5">Capacity</dt><dd class="col-7">{{ $target->current_vm_count }} / {{ $target->max_total_vms }}</dd>
			<dt class="col-5">Health</dt><dd class="col-7">{{ ucfirst($target->health_status) }}</dd>
		</dl></div></div></div>
		<div class="col-lg-7"><div class="card"><div class="card-header"><h3 class="card-title">Template coverage</h3></div><div class="table-responsive"><table class="table card-table table-vcenter"><thead><tr><th>Template</th><th>Physical VMID</th><th>Status</th></tr></thead><tbody>@forelse ($target->runnerTemplates as $template)<tr><td><a href="{{ route('templates.show', $template) }}">{{ $template->name }}</a></td><td>{{ $template->pivot->template_vmid ?? '—' }}</td><td>{{ ucfirst($template->pivot->availability_status ?? 'unavailable') }}</td></tr>@empty<tr><td colspan="3" class="text-secondary">No templates assigned to this node.</td></tr>@endforelse</tbody></table></div></div></div>
	</div></div>
@endsection
