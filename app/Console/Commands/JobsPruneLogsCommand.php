<?php

namespace App\Console\Commands;

use App\Models\WorkflowJob;
use App\Services\SettingsRepository;
use Illuminate\Console\Command;

class JobsPruneLogsCommand extends Command
{
    protected $signature = 'jobs:prune-logs';

    protected $description = 'Delete stored workflow job logs older than the configured retention';

    public function handle(SettingsRepository $settings): int
    {
        $cutoff = now()->subDays($settings->jobLogRetentionDays());
        $pruned = 0;

        WorkflowJob::whereNotNull('log_path')
            ->where('created_at', '<', $cutoff)
            ->each(function (WorkflowJob $job) use (&$pruned): void {
                if (is_file($job->log_path)) {
                    @unlink($job->log_path);
                }

                // The job row itself is kept; only the log leaves.
                $job->forceFill(['log_path' => null])->save();
                $pruned++;
            });

        $this->components->info($pruned > 0
            ? "Pruned {$pruned} workflow job log(s)."
            : 'No workflow job logs were old enough to prune.');

        return self::SUCCESS;
    }
}
