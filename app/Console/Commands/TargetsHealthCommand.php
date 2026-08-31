<?php

namespace App\Console\Commands;

use App\Models\ProxmoxTarget;
use App\Services\Health\HealthCheckService;
use Illuminate\Console\Command;

class TargetsHealthCommand extends Command
{
    protected $signature = 'targets:health';

    protected $description = 'Check the health and capacity of every enabled Proxmox target';

    public function handle(HealthCheckService $health): int
    {
        $failed = false;

        foreach (ProxmoxTarget::query()->where('enabled', true)->orderBy('name')->get() as $target) {
            $healthy = $health->checkTarget($target);
            $failed = $failed || ! $healthy;
            $target->refresh();

            $this->components->{ $healthy ? 'info' : 'error' }(
                "{$target->name}: {$target->health_status}, {$target->current_vm_count} VM(s) visible"
            );
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
