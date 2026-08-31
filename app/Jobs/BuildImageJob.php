<?php

namespace App\Jobs;

use App\Enums\BuildStatus;
use App\Models\ImageBuild;
use App\Services\Builds\ImageBuilder;
use App\Services\Provisioning\EnvironmentServices;
use App\Services\Proxmox\ProxmoxClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BuildImageJob implements ShouldQueue
{
    use Queueable;

    /** A failed build is never retried automatically; it wastes hours and usually needs a fix. */
    public int $tries = 1;

    /** Ubuntu takes about an hour, Windows about four (or longer). */
    public int $timeout = 43200;

    public function __construct(public readonly int $imageBuildId)
    {
        $this->onQueue('builds');
    }

    public function handle(EnvironmentServices $services): void
    {
        $build = ImageBuild::with(['environment.githubAccount', 'runnerTemplate', 'proxmoxTarget'])->find($this->imageBuildId);

        if ($build === null || $build->status !== BuildStatus::Queued) {
            return;
        }

        if ($build->proxmoxTarget === null) {
            throw new \RuntimeException('The image build has no Proxmox target.');
        }

        $builder = new ImageBuilder(new ProxmoxClient($build->proxmoxTarget));

        try {
            $builder->run($build);
        } catch (Throwable $e) {
            if (in_array($build->fresh()?->status, [BuildStatus::Queued, BuildStatus::Running], true)) {
                $build->forceFill([
                    'status' => BuildStatus::Failed,
                    'finished_at' => now(),
                ])->save();
            }

            Log::error('Image build threw', ['build' => $build->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        ImageBuild::where('id', $this->imageBuildId)
            ->whereIn('status', [BuildStatus::Queued->value, BuildStatus::Running->value])
            ->update([
                'status' => BuildStatus::Failed->value,
                'finished_at' => now(),
            ]);

        if ($e !== null) {
            Log::error('Build job failed', [
                'build' => $this->imageBuildId,
                'error' => Str::limit($e->getMessage(), 500),
            ]);
        }
    }
}
