<?php

namespace App\Services\Provisioning;

use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\RunnerTemplate;
use Illuminate\Database\Eloquent\Collection;

class TargetSelector
{
    /**
     * Select the best Proxmox target for a runner request.
     *
     * Clean-install behavior assumes a target is chosen only from a GitHub org and only from
     * healthy hosts that still have capacity for more runners.
     */
    public function selectFor(array $labels, ?RunnerTemplate $template = null, ?Pool $pool = null): ?ProxmoxTarget
    {
        return $this->eligibleFor($labels, $template, $pool)->first();
    }

    /**
     * Every target that could host this request, emptiest first.
     *
     * When a pool is given, only the nodes it is configured to run on are eligible and they are
     * ordered by the headroom left in that pool's per-node limit, so spawns spread across nodes.
     *
     * @return Collection<int, ProxmoxTarget>
     */
    public function eligibleFor(array $labels, ?RunnerTemplate $template = null, ?Pool $pool = null): Collection
    {
        $query = ProxmoxTarget::query()
            ->where('enabled', true)
            ->where('health_status', 'healthy')
            ->whereNull('drained_at')
            ->whereColumn('current_vm_count', '<', 'max_total_vms');

        if ($template !== null) {
            $query->whereHas('runnerTemplates', fn ($q) => $q
                ->whereKey($template->getKey())
                ->whereNotNull('runner_template_target.template_vmid')
                ->where('runner_template_target.availability_status', 'available'));
        }

        if ($pool !== null) {
            $query->whereHas('pools', fn ($q) => $q->whereKey($pool->getKey()));
        }

        // Host-level label matching is not yet part of the clean-install schema. The routing layer
        // still prefers healthy, non-saturated targets and leaves label-based filtering to the pool
        // and template selection stage until the target metadata model grows those fields.
        $query->when($labels !== [], fn ($scope) => $scope);

        $targets = $query
            ->orderBy('current_vm_count', 'asc')
            ->orderBy('max_total_vms', 'desc')
            ->get();

        if ($pool === null) {
            return $targets;
        }

        $pool->loadMissing('proxmoxTargets');

        return $targets
            ->sortByDesc(fn (ProxmoxTarget $target): int => $pool->availableCapacityOn($target))
            ->values();
    }
}
