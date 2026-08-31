<?php

namespace App\Services\Provisioning;

use App\Enums\BuildStatus;
use App\Enums\RunnerState;
use App\Exceptions\ProvisioningException;
use App\Models\ImageBuild;
use App\Models\ProxmoxTarget;
use App\Models\RetiredTemplateVmid;
use App\Models\Runner;
use App\Models\RunnerTemplateTarget;
use App\Services\Proxmox\ProxmoxClient;
use Illuminate\Support\Facades\Cache;

class VmidAllocator
{
    private const LOCK_SECONDS = 30;

    public function __construct(private readonly ProxmoxClient $proxmox) {}

    /**
     * Reserve the lowest free VMID in a target's purpose-specific range.
     *
     * Held under a lock because two concurrent spawns would otherwise pick the same ID;
     * target-scoped locks prevent concurrent allocations from choosing the same ID.
     */
    public function allocate(ProxmoxTarget $target, string $purpose, callable $reserve): mixed
    {
        $lock = Cache::lock("vmid-allocator:{$target->id}:{$purpose}", self::LOCK_SECONDS);

        if (! $lock->block(self::LOCK_SECONDS)) {
            throw new ProvisioningException('Timed out waiting to allocate a VMID.');
        }

        try {
            $taken = array_keys($this->proxmox->clusterVms());

            $used = array_flip(array_merge($taken, $this->reservedVmids($target, $purpose)));

            [$start, $end] = $purpose === 'template'
                ? [$target->template_vmid_range_start, $target->template_vmid_range_end]
                : [$target->runner_vmid_range_start, $target->runner_vmid_range_end];

            if ($start === null || $end === null) {
                throw new ProvisioningException(
                    "Proxmox target {$target->name} has no {$purpose} VMID range configured."
                );
            }

            for ($vmid = $start; $vmid <= $end; $vmid++) {
                if (! isset($used[$vmid])) {
                    return $reserve($vmid);
                }
            }

            throw new ProvisioningException(
                "No free {$purpose} VMID in range {$start}-{$end}."
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * VMIDs this application has claimed but Proxmox may not have created yet.
     *
     * @return array<int, int>
     */
    private function reservedVmids(ProxmoxTarget $target, string $purpose): array
    {
        if ($purpose !== 'template') {
            return Runner::where('proxmox_target_id', $target->id)
                ->whereNot('state', RunnerState::Destroyed->value)
                ->pluck('vmid')
                ->all();
        }

        $live = RunnerTemplateTarget::where('proxmox_target_id', $target->id)
            ->whereNotNull('template_vmid')
            ->pluck('template_vmid')
            ->all();

        $retired = RetiredTemplateVmid::where('proxmox_target_id', $target->id)
            ->whereNull('deleted_at')
            ->pluck('vmid')
            ->all();

        $building = ImageBuild::where('proxmox_target_id', $target->id)
            ->whereNotNull('template_vmid')
            ->whereIn('status', [BuildStatus::Queued->value, BuildStatus::Running->value])
            ->pluck('template_vmid')
            ->all();

        return array_map('intval', array_merge($live, $retired, $building));
    }
}
