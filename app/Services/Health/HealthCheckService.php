<?php

namespace App\Services\Health;

use App\Models\ProxmoxTarget;
use App\Services\Proxmox\ProxmoxClient;
use Throwable;

class HealthCheckService
{
    public function checkTarget(ProxmoxTarget $target): bool
    {
        try {
            $client = new ProxmoxClient($target);
            $vms = $client->clusterVms();
            $targetVms = $client->filterTargetVms($vms, $target);

            $target->forceFill([
                'health_status' => 'healthy',
                'current_vm_count' => count($targetVms),
                'last_health_check_at' => now(),
            ])->save();

            return true;
        } catch (Throwable $e) {
            $target->forceFill([
                'health_status' => 'unhealthy',
                'last_health_check_at' => now(),
            ])->save();

            report($e);

            return false;
        }
    }
}
