<?php

namespace App\Console\Commands;

use App\Models\Pool;
use App\Services\Provisioning\EnvironmentServices;
use Illuminate\Console\Command;
use Throwable;

class RunnersSpawnCommand extends Command
{
    protected $signature = 'runners:spawn {pool : Pool name} {--environment= : Environment slug, required when pool names collide}';

    protected $description = 'Manually provision one runner from a pool';

    public function handle(EnvironmentServices $services): int
    {
        $pools = Pool::with('environment')
            ->where('name', $this->argument('pool'))
            ->when($this->option('environment'), fn ($query, $slug) => $query->whereRelation('environment', 'slug', $slug))
            ->get();

        if ($pools->isEmpty()) {
            $this->components->error("No pool named '{$this->argument('pool')}' was found.");

            return self::FAILURE;
        }

        if ($pools->count() > 1) {
            $this->components->error('That pool name exists in several environments; pass --environment.');

            return self::FAILURE;
        }

        $pool = $pools->first();

        try {
            $runner = $services->provisioner($pool->environment)->spawn($pool);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Provisioned VM {$runner->vmid} ({$runner->runner_name}) in pool {$pool->name}.");

        return self::SUCCESS;
    }
}
