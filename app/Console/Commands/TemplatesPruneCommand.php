<?php

namespace App\Console\Commands;

use App\Services\SettingsRepository;
use App\Services\Templates\TemplatePruner;
use Illuminate\Console\Command;

class TemplatesPruneCommand extends Command
{
    protected $signature = 'templates:prune';

    protected $description = 'Destroy template VMs superseded by a rebuild once nothing is cloned from them';

    public function handle(SettingsRepository $settings, TemplatePruner $pruner): int
    {
        $pruned = $pruner->pruneRetained($settings);

        $this->components->info($pruned > 0
            ? "Pruned {$pruned} superseded template(s)."
            : 'No superseded templates were ready to prune.');

        return self::SUCCESS;
    }
}
