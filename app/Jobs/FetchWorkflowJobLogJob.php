<?php

namespace App\Jobs;

use App\Models\WorkflowJob;
use App\Services\GitHub\GitHubClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchWorkflowJobLogJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $workflowJobId)
    {
        $this->onQueue('provision');

        // GitHub takes a moment to finalise the log after the completed webhook fires.
        $this->delay(now()->addSeconds(20));
    }

    public function handle(): void
    {
        $job = WorkflowJob::with('environment.githubAccount')->find($this->workflowJobId);
        $account = $job?->environment?->githubAccount;

        if ($job === null || $account === null || $job->log_fetched_at !== null) {
            return;
        }

        $log = (new GitHubClient($account))->jobLog($job->repository_full_name, $job->github_job_id);

        if ($log === null) {
            $job->forceFill(['log_fetched_at' => now()])->save();

            return;
        }

        $path = $this->path($job);
        file_put_contents($path, $log);

        $job->forceFill(['log_path' => $path, 'log_fetched_at' => now()])->save();
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function failed(?Throwable $e): void
    {
        Log::warning('Could not store the log for a workflow job', [
            'workflow_job' => $this->workflowJobId,
            'error' => $e?->getMessage(),
        ]);
    }

    private function path(WorkflowJob $job): string
    {
        $directory = config('jobs.log_directory');

        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        return $directory.'/job-'.$job->id.'.log';
    }
}
