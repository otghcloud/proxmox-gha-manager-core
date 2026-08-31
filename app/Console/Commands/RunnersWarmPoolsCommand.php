<?php

namespace App\Console\Commands;

use App\Jobs\ProvisionRunnerJob;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Services\Provisioning\TargetSelector;
use App\Services\SettingsRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunnersWarmPoolsCommand extends Command
{
    protected $signature = 'runners:warm-pools {--pool= : Optional pool name filter} {--environment= : Optional environment slug filter}';

    protected $description = 'Maintain minimum idle runner counts for enabled pools';

    public function handle(TargetSelector $targetSelector, SettingsRepository $settings): int
    {
        if (! $settings->bool(SettingsRepository::AUTO_SPAWN_ENABLED)) {
            $this->components->warn('Auto spawning is disabled in the debug settings; skipping.');

            return self::SUCCESS;
        }

        $pools = Pool::query()
            ->where('enabled', true)
            ->whereHas('environment', fn ($q) => $q->where('enabled', true))
            ->when($this->option('pool'), fn ($q, $name) => $q->where('name', $name))
            ->when($this->option('environment'), fn ($q, $slug) => $q->whereRelation('environment', 'slug', $slug))
            ->with(['environment', 'runnerTemplate', 'proxmoxTargets'])
            ->get();

        $totalDispatched = 0;

        foreach ($pools as $pool) {
            $dispatched = $this->evaluatePool($pool, $targetSelector);
            $totalDispatched += $dispatched;
        }

        if ($totalDispatched > 0) {
            $this->components->info("Dispatched {$totalDispatched} warm pool runner job(s).");
        } else {
            $this->components->info('All warm pools are at or above minimum idle targets.');
        }

        return self::SUCCESS;
    }

    private function evaluatePool(Pool $pool, TargetSelector $targetSelector): int
    {
        $template = $pool->runnerTemplate;
        if ($template === null) {
            $this->components->error("Pool {$pool->name} has no runner template linked.");

            return 0;
        }

        $targets = $targetSelector->eligibleFor($pool->labels, $template, $pool);
        if ($targets->isEmpty()) {
            if ($pool->totalMinIdleRunners() > 0) {
                $this->components->warn("Pool {$pool->name} needs warm runners, but no eligible Proxmox target was found.");
            }

            return 0;
        }

        $dispatched = 0;

        // Each node is topped up to its own minimum; there is no pool-wide pot to distribute.
        foreach ($targets as $target) {
            $dispatched += $this->dispatchFor($pool, $target, $pool->warmRunnersToSpawnOn($target));
        }

        return $dispatched;
    }

    private function dispatchFor(Pool $pool, ProxmoxTarget $target, int $toSpawn): int
    {
        if ($toSpawn <= 0) {
            return 0;
        }

        for ($i = 0; $i < $toSpawn; $i++) {
            ProvisionRunnerJob::dispatch(
                environmentId: $pool->environment_id,
                poolId: $pool->id,
                proxmoxTargetId: $target->id,
            );
        }

        Log::info("Dispatched {$toSpawn} warm runner provisioning job(s) for pool {$pool->name}", [
            'pool' => $pool->name,
            'target' => $target->slug,
            'min_idle_runners' => $pool->minIdleRunnersOn($target),
            'current_idle_spawning' => $pool->idleAndSpawningRunnerCountOn($target),
            'dispatched' => $toSpawn,
        ]);

        $this->components->info("Dispatched {$toSpawn} warm runner job(s) for pool {$pool->name} on {$target->name}.");

        return $toSpawn;
    }
}
