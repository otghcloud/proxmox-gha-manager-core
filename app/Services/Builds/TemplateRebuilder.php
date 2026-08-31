<?php

namespace App\Services\Builds;

use App\Enums\BuildStatus;
use App\Jobs\BuildImageJob;
use App\Models\ImageBuild;
use App\Models\ProxmoxTarget;
use App\Models\RetiredTemplateVmid;
use App\Models\RunnerTemplate;
use App\Services\Provisioning\VmidAllocator;
use App\Services\Proxmox\ProxmoxClient;
use App\Services\Templates\TemplateUpdateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Builds templates into a freshly allocated VMID and swaps the pool references once they succeed,
 * so a rebuild never has to destroy the image that runners are still being cloned from.
 */
class TemplateRebuilder
{
    public const MODE_SEQUENTIAL = 'sequential';

    public const MODE_PARALLEL = 'parallel';

    /**
     * Queue a build per node. Sequential batches only dispatch the first; the rest follow as each
     * one succeeds.
     *
     * @param  Collection<int, ProxmoxTarget>  $targets
     * @return Collection<int, ImageBuild>
     */
    public function queue(RunnerTemplate $template, Collection $targets, string $mode, ?int $userId = null): Collection
    {
        $batchId = $targets->count() > 1 ? (string) Str::uuid() : null;

        $builds = $targets->values()->map(fn (ProxmoxTarget $target, int $index): ImageBuild => $this->reserve($template, $target, $userId, $batchId, $index));

        if ($mode === self::MODE_SEQUENTIAL && $batchId !== null) {
            BuildImageJob::dispatch($builds->first()->id);

            return $builds;
        }

        $builds->each(fn (ImageBuild $build) => BuildImageJob::dispatch($build->id));

        return $builds;
    }

    /**
     * Create the build record inside the VMID lock so the reservation is visible before it lifts.
     */
    private function reserve(RunnerTemplate $template, ProxmoxTarget $target, ?int $userId, ?string $batchId, int $index): ImageBuild
    {
        $version = TemplateUpdateService::getLocalVersionForTarget($template->build_target?->value);

        return (new VmidAllocator(new ProxmoxClient($target)))->allocate(
            $target,
            'template',
            fn (int $vmid): ImageBuild => ImageBuild::create([
                'environment_id' => $template->environment_id,
                'runner_template_id' => $template->id,
                'proxmox_target_id' => $target->id,
                'triggered_by' => $userId,
                'target' => $template->build_target->value,
                'status' => BuildStatus::Queued,
                'template_vmid' => $vmid,
                'version' => $version,
                'rebuild_batch_id' => $batchId,
                'sequence' => $index,
            ]),
        );
    }

    /**
     * Point the template's node mapping at the VMID this build produced and retire the old one.
     */
    public function promote(ImageBuild $build, int $vmid): void
    {
        DB::transaction(function () use ($build, $vmid): void {
            $template = $build->runnerTemplate;
            $mapping = $template->targetMappings()->whereKey($build->proxmox_target_id)->firstOrFail();
            $previous = $mapping->pivot->template_vmid;
            $generation = (int) $mapping->pivot->generation + 1;
            $version = $build->version ?? TemplateUpdateService::getLocalVersionForTarget($build->target);

            if ($previous !== null && (int) $previous !== $vmid) {
                RetiredTemplateVmid::create([
                    'runner_template_id' => $template->id,
                    'proxmox_target_id' => $build->proxmox_target_id,
                    'vmid' => $previous,
                    'generation' => $mapping->pivot->generation,
                    'retired_at' => now(),
                ]);
            }

            $template->targetMappings()->updateExistingPivot($mapping->id, [
                'template_vmid' => $vmid,
                'generation' => $generation,
                'version' => $version,
                'availability_status' => 'available',
                'last_built_at' => now(),
            ]);
        });
    }

    /**
     * Move a sequential batch on, or abandon the nodes still waiting when a build fails.
     */
    public function advanceBatch(ImageBuild $build): void
    {
        if ($build->rebuild_batch_id === null) {
            return;
        }

        $remaining = ImageBuild::where('rebuild_batch_id', $build->rebuild_batch_id)
            ->where('status', BuildStatus::Queued->value)
            ->where('sequence', '>', $build->sequence)
            ->orderBy('sequence')
            ->get();

        if ($remaining->isEmpty()) {
            return;
        }

        if ($build->status === BuildStatus::Succeeded) {
            BuildImageJob::dispatch($remaining->first()->id);

            return;
        }

        Log::warning('Cancelling the rest of a template rebuild batch after a failure', [
            'batch' => $build->rebuild_batch_id,
            'failed_build' => $build->id,
        ]);

        $remaining->each(fn (ImageBuild $pending) => $pending->forceFill([
            'status' => BuildStatus::Cancelled,
            'finished_at' => now(),
        ])->save());
    }
}
