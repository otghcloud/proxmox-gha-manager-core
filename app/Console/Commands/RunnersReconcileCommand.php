<?php

namespace App\Console\Commands;

use App\Models\ProxmoxTarget;
use App\Services\Provisioning\EnvironmentServices;
use Throwable;

class RunnersReconcileCommand extends EnvironmentCommand
{
    protected $signature = 'runners:reconcile {--environment= : Limit the pass to one environment slug}';

    protected $description = 'Re-sync the database against Proxmox without destroying anything';

    public function handle(EnvironmentServices $services): int
    {
        $hasErrors = false;

        foreach ($this->environments() as $environment) {
            foreach (ProxmoxTarget::query()->where('enabled', true)->orderBy('name')->get() as $target) {
                try {
                    $corrections = $services->reaper($environment, $target)->reconcile();

                    $this->components->info("{$environment->slug}/{$target->slug}: made {$corrections} correction(s)");
                } catch (Throwable $e) {
                    $this->components->error("{$environment->slug}/{$target->slug}: {$e->getMessage()}");
                    $hasErrors = true;
                }
            }
        }

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }
}
