<?php

namespace App\Console\Commands;

use App\Enums\RunnerState;
use App\Models\RetiredTemplateVmid;
use App\Models\Runner;
use App\Services\Proxmox\ProxmoxClient;
use App\Services\SettingsRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class TemplatesPruneCommand extends Command
{
    protected $signature = 'templates:prune';

    protected $description = 'Destroy template VMs superseded by a rebuild once nothing is cloned from them';

    public function handle(SettingsRepository $settings): int
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

                $pruned += $this->destroy($row) ? 1 : 0;
            }
        }

        $this->components->info($pruned > 0
            ? "Pruned {$pruned} superseded template(s)."
            : 'No superseded templates were ready to prune.');

        return self::SUCCESS;
    }

    private function stillInUse(RetiredTemplateVmid $retired): bool
    {
        return Runner::where('proxmox_target_id', $retired->proxmox_target_id)
            ->where('source_template_vmid', $retired->vmid)
            ->whereNot('state', RunnerState::Destroyed->value)
            ->exists();
    }

    private function destroy(RetiredTemplateVmid $retired): bool
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
