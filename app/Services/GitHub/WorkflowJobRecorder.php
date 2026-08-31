<?php

namespace App\Services\GitHub;

use App\Enums\RunnerState;
use App\Jobs\FetchWorkflowJobLogJob;
use App\Models\Environment;
use App\Models\Runner;
use App\Models\WorkflowJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns `workflow_job` webhook payloads into durable job records.
 *
 * The payload carries everything we display, including the per-step timings on completion, so no
 * GitHub API call is needed to build the history. Only the raw log is fetched separately.
 */
class WorkflowJobRecorder
{
    /**
     * @param  array<string, mixed>  $job
     * @param  array<string, mixed>  $body
     */
    public function record(Environment $environment, array $job, array $body, ?Runner $runner = null): ?WorkflowJob
    {
        $jobId = $job['id'] ?? null;

        if (! is_numeric($jobId)) {
            return null;
        }

        $status = (string) ($job['status'] ?? 'queued');

        $attributes = array_filter([
            'github_run_id' => isset($job['run_id']) ? (int) $job['run_id'] : null,
            'run_attempt' => isset($job['run_attempt']) ? (int) $job['run_attempt'] : null,
            'repository_full_name' => $this->string($body['repository']['full_name'] ?? null),
            'workflow_name' => $this->string($job['workflow_name'] ?? null),
            'job_name' => $this->string($job['name'] ?? null),
            'runner_name' => $this->string($job['runner_name'] ?? null),
            'head_branch' => $this->string($job['head_branch'] ?? null),
            'head_sha' => $this->string($job['head_sha'] ?? null),
            'html_url' => $this->string($job['html_url'] ?? null),
            'labels' => array_values(array_filter((array) ($job['labels'] ?? []), 'is_string')) ?: null,
            'status' => $status,
            'conclusion' => $this->string($job['conclusion'] ?? null),
            'steps' => $this->steps($job),
            'queued_at' => $this->timestamp($job['created_at'] ?? null),
            'started_at' => $this->timestamp($job['started_at'] ?? null),
            'completed_at' => $this->timestamp($job['completed_at'] ?? null),
            'runner_id' => $runner?->getKey(),
        ], fn ($value): bool => $value !== null);

        // A job record is built up across three deliveries, so never overwrite a known value with a
        // blank one from an earlier or out-of-order payload.
        $record = WorkflowJob::firstOrNew([
            'environment_id' => $environment->id,
            'github_job_id' => (int) $jobId,
        ]);

        $record->fill($attributes);
        $record->repository_full_name ??= 'unknown';
        $record->job_name ??= 'unknown';
        $record->save();

        if ($status === 'completed' && $record->log_fetched_at === null) {
            FetchWorkflowJobLogJob::dispatch($record->id);
        }

        return $record;
    }

    /**
     * Bind a job to the runner that actually served it, releasing any other live runner still
     * holding the association. A warm runner can claim a job that an on-demand spawn was booting
     * for, and the database only allows one live runner per job.
     */
    public function assignRunner(WorkflowJob $job, Runner $runner): void
    {
        DB::transaction(function () use ($job, $runner): void {
            Runner::where('environment_id', $runner->environment_id)
                ->where('workflow_job_id', $job->github_job_id)
                ->whereKeyNot($runner->getKey())
                ->whereNot('state', RunnerState::Destroyed->value)
                ->each(function (Runner $other): void {
                    $other->forceFill(['workflow_job_id' => null])->save();
                    $other->events()->create([
                        'from_state' => $other->state->value,
                        'to_state' => $other->state->value,
                        'reason' => 'another runner claimed the job first',
                        'created_at' => now(),
                    ]);
                });

            $runner->forceFill([
                'workflow_job_id' => $job->github_job_id,
                'repository_full_name' => $job->repository_full_name,
            ])->save();

            $job->forceFill(['runner_id' => $runner->getKey()])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $job
     * @return array<int, array<string, mixed>>|null
     */
    private function steps(array $job): ?array
    {
        $steps = $job['steps'] ?? null;

        return is_array($steps) && $steps !== [] ? array_values($steps) : null;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
