<?php

namespace App\Services\Builds;

use App\Enums\BuildStatus;
use App\Models\ImageBuild;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Force kills a hung build by signalling the Packer process the build worker started.
 */
class BuildCanceller
{
    /** Time allowed for Packer to exit on SIGTERM before it is killed outright. */
    private const GRACE_SECONDS = 5;

    public function cancel(ImageBuild $build, ?string $reason = null): bool
    {
        if ($build->status->isFinished()) {
            return false;
        }

        $pid = $build->process_pid;

        if ($pid !== null) {
            $this->terminate($pid);
        }

        $build->forceFill([
            'status' => BuildStatus::Cancelled,
            'process_pid' => null,
            'finished_at' => now(),
        ])->save();

        Log::warning('Image build force killed', [
            'build' => $build->id,
            'pid' => $pid,
            'reason' => $reason,
        ]);

        return true;
    }

    /**
     * The worker holds the Symfony Process object, so the OS pid is signalled directly.
     */
    private function terminate(int $pid): void
    {
        if (! $this->running($pid)) {
            return;
        }

        $this->signal($pid, 'TERM');

        $deadline = microtime(true) + self::GRACE_SECONDS;

        while (microtime(true) < $deadline) {
            if (! $this->running($pid)) {
                return;
            }

            usleep(200000);
        }

        $this->signal($pid, 'KILL');
    }

    private function running(int $pid): bool
    {
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        return (new Process(['kill', '-0', (string) $pid]))->run() === 0;
    }

    private function signal(int $pid, string $signal): void
    {
        if (function_exists('posix_kill')) {
            posix_kill($pid, $signal === 'KILL' ? 9 : 15);

            return;
        }

        (new Process(['kill', '-'.$signal, (string) $pid]))->run();
    }
}
