<?php

namespace App\Console\Commands;

use App\Models\ProxmoxTarget;
use Illuminate\Console\Command;

class TargetsDrainCommand extends Command
{
    protected $signature = 'targets:drain {target : The target slug} {--restore : Restore instead of drain}';

    protected $description = 'Gracefully drain or restore a Proxmox target node';

    public function handle(): int
    {
        $targetSlug = $this->argument('target');
        $restore = $this->option('restore');

        $target = ProxmoxTarget::where('slug', $targetSlug)->first();

        if ($target === null) {
            $this->components->error("Target '{$targetSlug}' not found.");

            return self::FAILURE;
        }

        if ($restore) {
            $target->update(['drained_at' => null]);
            $this->components->info("Target '{$target->name}' has been restored and will accept new runners.");
        } else {
            $target->update(['drained_at' => now()]);
            $this->components->info("Target '{$target->name}' is now drained. No new runners will be provisioned on it.");
            $this->components->info('Existing runners will continue to run; they will be destroyed when idle or completed.');
        }

        return self::SUCCESS;
    }
}
