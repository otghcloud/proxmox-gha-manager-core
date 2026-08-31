<?php

namespace App\Console\Commands;

use App\Enums\RunnerState;
use App\Models\Runner;
use App\Services\Provisioning\EnvironmentServices;
use Illuminate\Console\Command;
use Throwable;

class RunnersDestroyCommand extends Command
{
    protected $signature = 'runners:destroy {vmid : The Proxmox VMID} {--environment= : Environment slug, required when VMIDs collide}';

    protected $description = 'Destroy a runner VM and deregister its runner';

    public function handle(EnvironmentServices $services): int
    {
        $runners = Runner::with('environment')
            ->where('vmid', (int) $this->argument('vmid'))
            ->whereNot('state', RunnerState::Destroyed->value)
            ->when($this->option('environment'), fn ($query, $slug) => $query->whereRelation('environment', 'slug', $slug))
            ->get();

        if ($runners->isEmpty()) {
            $this->components->error("No live runner is tracked for VMID {$this->argument('vmid')}.");

            return self::FAILURE;
        }

        if ($runners->count() > 1) {
            $this->components->error('That VMID exists in several environments; pass --environment.');

            return self::FAILURE;
        }

        $runner = $runners->first();

        try {
            $services->provisioner($runner->environment)->destroy($runner, 'destroyed from the CLI');
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Destroyed VM {$runner->vmid}.");

        return self::SUCCESS;
    }
}
