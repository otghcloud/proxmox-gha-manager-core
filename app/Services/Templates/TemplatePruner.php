<?php

namespace App\Services\Templates;

use App\Enums\RunnerState;
use App\Models\RetiredTemplateVmid;
use App\Models\Runner;
use App\Services\Proxmox\ProxmoxClient;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Destroys template VMs superseded by a rebuild, for both the scheduled prune and manual purges.
 */
class TemplatePruner
{
    /**
     * Prunes every superseded template that nothing is cloned from, honouring the retention setting.
     */
    public function pruneRetained(SettingsRepository $settings): int
    {
        $keep = $settings->templateRetentionMode() === SettingsRepository::RETENTION_KEEP_LAST_N
            ? $settings->templateRetentionGenerations()
            : 0;

        $pruned = 0;

        $retired = RetiredTemplateVmid::with('proxmoxTarget')
            ->whereNull('deleted_at')
            ->orderByDesc('generation')
            ->get()
            ->groupBy(fn (RetiredTemplateVmid $row): string => $row->runner_template_id.':'.$row->proxmox_target_id);

        foreach ($retired as $generations) {
            foreach ($generations->skip($keep) as $row) {
                if ($this->stillInUse($row)) {
                    continue;
                }

                $pruned += $this->purge($row) ? 1 : 0;
            }
        }

        return $pruned;
    }

    /**
     * Purges every superseded template for one runner template, ignoring the retention setting.
     *
     * @return array{purged: int, skipped: int}
     */
    public function purgeForTemplate(int $runnerTemplateId): array
    {
        $purged = 0;
        $skipped = 0;

        $retired = RetiredTemplateVmid::with('proxmoxTarget')
            ->where('runner_template_id', $runnerTemplateId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($retired as $row) {
            if ($this->stillInUse($row)) {
                $skipped++;

                continue;
            }

            $this->purge($row) ? $purged++ : $skipped++;
        }

        return ['purged' => $purged, 'skipped' => $skipped];
    }

    public function stillInUse(RetiredTemplateVmid $retired): bool
    {
        return Runner::where('proxmox_target_id', $retired->proxmox_target_id)
            ->where('source_template_vmid', $retired->vmid)
            ->whereNot('state', RunnerState::Destroyed->value)
            ->exists();
    }

    public function purge(RetiredTemplateVmid $retired): bool
    {
        try {
            (new ProxmoxClient($retired->proxmoxTarget))->destroy($retired->vmid);
        } catch (Throwable $e) {
            Log::warning('Could not destroy a superseded template', [
                'vmid' => $retired->vmid,
                'node' => $retired->proxmoxTarget?->name,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $retired->forceFill(['deleted_at' => now()])->save();

        return true;
    }
}
