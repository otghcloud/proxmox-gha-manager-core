<div wire:poll.15s class="card-group mb-3">
	@foreach (['spawning' => 'Spawning', 'idle' => 'Idle', 'busy' => 'Busy', 'failed' => 'Failed'] as $state => $label)
		<div class="card">
			<div class="card-body">
				<div class="subheader">{{ $label }}</div>
				<div class="h1 mb-0">{{ $stateCounts[$state] ?? 0 }}</div>
			</div>
		</div>
	@endforeach
	<a class="card card-link" href="{{ route('builds.index') }}">
		<div class="card-body">
			<div class="subheader">Active builds</div>
			<div class="h1 mb-0">{{ $activeBuildsCount }}</div>
		</div>
	</a>
</div>
