<?php

namespace App\Console\Commands;

use App\Models\ImageBuild;
use App\Models\LogEntry;
use App\Models\WorkflowJob;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes on-disk log files whose contents are already stored in the database.
 *
 * Not scheduled yet - run manually until the retention behaviour has been agreed.
 */
class LogsPruneFilesCommand extends Command
{
    protected $signature = 'logs:prune-files {--dry-run : List the files that would be deleted}';

    protected $description = 'Delete build and job log files that have already been copied into the database';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deleted = 0;

        $deleted += $this->prune(ImageBuild::query()->whereNotNull('log_path'), LogEntry::CHANNEL_BUILD, $dryRun);
        $deleted += $this->prune(WorkflowJob::query()->whereNotNull('log_path'), LogEntry::CHANNEL_JOB, $dryRun);

        $this->components->info($dryRun
            ? "{$deleted} log file(s) would be deleted."
            : "Deleted {$deleted} log file(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Builder<ImageBuild|WorkflowJob>  $query
     */
    private function prune(Builder $query, string $channel, bool $dryRun): int
    {
        $deleted = 0;

        $query->with(['logEntries' => fn ($relation) => $relation->where('channel', $channel)])
            ->chunkById(100, function ($records) use (&$deleted, $dryRun): void {
                foreach ($records as $record) {
                    // Only ever removes a file whose contents are safely in the database.
                    if ($record->logEntries->isEmpty()) {
                        continue;
                    }

                    $path = $record->log_path;

                    if ($path === null || ! is_file($path)) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line($path);
                        $deleted++;

                        continue;
                    }

                    if (@unlink($path)) {
                        $record->forceFill(['log_path' => null])->save();
                        $deleted++;
                    }
                }
            });

        return $deleted;
    }
}
