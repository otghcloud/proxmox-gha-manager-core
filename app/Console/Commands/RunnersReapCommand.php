<?php

namespace App\Console\Commands;

use App\Models\ProxmoxTarget;
use App\Services\Provisioning\EnvironmentServices;
use App\Services\SettingsRepository;
use Throwable;

class RunnersReapCommand extends EnvironmentCommand
{
    protected $signature = 'runners:reap
        {--environment= : Limit the pass to one environment slug}
        {--all : Destroy every tracked runner VM, regardless of its state}
        {--force : Run even when reaping is disabled in the debug settings}';

    protected $description = 'Reconcile against Proxmox and destroy spent runner VMs';

    public function handle(EnvironmentServices $services, SettingsRepository $settings): int
    {
        $requestedForceReap = $settings->bool(SettingsRepository::FORCE_REAP_ALL_REQUESTED, false);
        $all = (bool) $this->option('all') || $requestedForceReap;

        if (! $settings->bool(SettingsRepository::REAPING_ENABLED) && ! $all && ! $this->option('force')) {
            $this->components->warn('Reaping is disabled in the debug settings; skipping.');

            return self::SUCCESS;
        }

        $total = 0;
        $hasErrors = false;

        foreach ($this->environments($all) as $environment) {
            foreach (ProxmoxTarget::query()->when(! $all, fn ($query) => $query->where('enabled', true))->orderBy('name')->get() as $target) {
                try {
                    $reaper = $services->reaper($environment, $target);
                    $destroyed = $all ? $reaper->destroyAll() : $reaper->runOnce();
                    $total += $destroyed;

                    $this->components->info("{$environment->slug}/{$target->slug}: destroyed {$destroyed} VM(s)");
                } catch (Throwable $e) {
                    $this->components->error("{$environment->slug}/{$target->slug}: {$e->getMessage()}");
                    $hasErrors = true;
                }
            }
        }

        $this->line("Destroyed {$total} VM(s) in total.");

        if ($requestedForceReap && ! $hasErrors) {
            $settings->set(SettingsRepository::FORCE_REAP_ALL_REQUESTED, '0');
        }

        if (! $all) {
            $this->call('runners:warm-pools');
        }

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }
}
