<?php

namespace App\Models;

use App\Enums\JobConclusion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A GitHub Actions job this installation served, built from the workflow_job webhook payloads.
 */
class WorkflowJob extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'github_job_id' => 'integer',
            'github_run_id' => 'integer',
            'run_attempt' => 'integer',
            'labels' => 'array',
            'steps' => 'array',
            'conclusion' => JobConclusion::class,
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'log_fetched_at' => 'datetime',
        ];
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(Runner::class);
    }

    public function scopeForEnvironment(Builder $query, Environment $environment): Builder
    {
        return $query->where('environment_id', $environment->getKey());
    }

    public function repositoryName(): string
    {
        return str_contains($this->repository_full_name, '/')
            ? explode('/', $this->repository_full_name, 2)[1]
            : $this->repository_full_name;
    }

    /**
     * How long the job occupied a runner, or null while it is still going.
     */
    public function durationSeconds(): ?int
    {
        if ($this->started_at === null || $this->completed_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->completed_at);
    }

    /**
     * How long the job sat in GitHub's queue before a runner picked it up.
     */
    public function queueWaitSeconds(): ?int
    {
        if ($this->queued_at === null || $this->started_at === null) {
            return null;
        }

        return max(0, (int) $this->queued_at->diffInSeconds($this->started_at));
    }

    public function hasLog(): bool
    {
        return $this->log_path !== null && is_readable($this->log_path);
    }
}
