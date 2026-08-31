@if ($runners->isEmpty())
	<div class="card-body text-secondary">{{ $empty ?? 'No runners to show.' }}</div>
@else
	<div class="table-responsive">
		<table class="table card-table table-vcenter">
			<thead>
				<tr>
					<th>Runner</th>
					<th>Node</th>
					<th>VMID</th>
					<th>State</th>
					<th class="w-1">Age</th>
				</tr>
			</thead>
			<tbody>
				@foreach ($runners as $runner)
					<tr>
						<td style="max-width: 16rem;">
							<div class="text-truncate"><a href="{{ route('runners.show', $runner) }}">{{ $runner->runner_name }}</a></div>
							<div class="text-secondary small text-truncate">{{ $runner->pool?->name ?? '—' }}</div>
						</td>
						<td>{{ $runner->proxmoxTarget?->name ?? '—' }}</td>
						<td>{{ $runner->vmid }}</td>
						<td><span class="badge bg-{{ $runner->state->colour() }}-lt runner-state">{{ $runner->state->label() }}</span></td>
						<td class="text-nowrap text-secondary">{{ $runner->created_at->diffForHumans(short: true) }}</td>
					</tr>
				@endforeach
			</tbody>
		</table>
	</div>
@endif
