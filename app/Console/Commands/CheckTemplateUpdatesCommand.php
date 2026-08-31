<?php

namespace App\Console\Commands;

use App\Services\SettingsRepository;
use App\Services\Templates\TemplateUpdateService;
use Illuminate\Console\Command;

class CheckTemplateUpdatesCommand extends Command
{
    protected $signature = 'templates:check-updates';

    protected $description = 'Check GitHub for updated template definitions.';

    public function handle(TemplateUpdateService $service, SettingsRepository $settings): int
    {
        if (! $settings->bool(SettingsRepository::TEMPLATE_AUTO_CHECK_ENABLED, false)) {
            $this->info('Automatic template update check is disabled in settings.');

            return self::SUCCESS;
        }

        $this->info('Checking remote templates index...');
        $result = $service->checkForUpdates();

        if ($result['available']) {
            $this->info('Updates available: '.count($result['updates']).' template(s) updated.');
        } else {
            $this->info('All templates are up to date.');
        }

        return self::SUCCESS;
    }
}
