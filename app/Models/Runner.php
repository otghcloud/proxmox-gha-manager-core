<?php

namespace App\Models;

use App\Enums\RunnerState;
use App\Enums\SpawnReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Runner extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'state' => RunnerState::class,
            'spawn_reason' => SpawnReason::class,
            'vmid' => 'integer',
            'source_template_vmid' => 'integer',
            'github_runner_id' => 'integer',
            'workflow_job_id' => 'integer',
            'proxmox_target_id' => 'integer',
            'state_changed_at' => 'datetime',
            'destroyed_at' => 'datetime',
        ];
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function proxmoxTarget(): BelongsTo
    {
        return $this->belongsTo(ProxmoxTarget::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RunnerEvent::class);
    }

    public function workflowJobs(): HasMany
    {
        return $this->hasMany(WorkflowJob::class);
    }

    /**
     * The job this runner served. Warm runners gain one when GitHub hands them work.
     */
    public function servedJob(): HasOne
    {
        return $this->hasOne(WorkflowJob::class)->latestOfMany();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('state', RunnerState::activeValues());
    }

    public function scopeForEnvironment(Builder $query, Environment|int $environment): Builder
    {
        return $query->where('environment_id', $environment instanceof Environment ? $environment->id : $environment);
    }

    public function ageSeconds(): int
    {
        return (int) $this->created_at->diffInSeconds(now());
    }

    public function secondsInState(): int
    {
        return (int) $this->state_changed_at->diffInSeconds(now());
    }

    /**
     * Move to a new state, recording the transition for the runner's history timeline.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function transitionTo(RunnerState $state, ?string $reason = null, array $metadata = []): void
    {
        $from = $this->state;

        $this->forceFill([
            'state' => $state,
            'state_changed_at' => now(),
            'destroyed_at' => $state === RunnerState::Destroyed ? now() : $this->destroyed_at,
        ])->save();

        // Re-entering the same state is a no-op that only adds noise to the timeline.
        if ($from === $state) {
            return;
        }

        $this->events()->create([
            'from_state' => $from?->value,
            'to_state' => $state->value,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
