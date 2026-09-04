<?php

namespace App\Console\Commands;

use App\Models\ProxmoxTarget;
use App\Models\RunnerTemplate;
use App\Services\Builds\TemplateCatalog;
use App\Services\Builds\TemplateRebuilder;
use App\Services\SettingsRepository;
use App\Services\Templates\TemplateDownloadService;
use App\Services\Templates\TemplateUpdateService;
use Illuminate\Console\Command;
use Throwable;

class CheckTemplateUpdatesCommand extends Command
{
    protected $signature = 'templates:check-updates';

    protected $description = 'Check GitHub for updated template definitions.';

    public function handle(
        TemplateUpdateService $service,
        TemplateDownloadService $downloader,
        TemplateRebuilder $rebuilder,
        SettingsRepository $settings,
    ): int {
        if (! $settings->bool(SettingsRepository::TEMPLATE_AUTO_CHECK_ENABLED, false)) {
            $this->info('Automatic template update check is disabled in settings.');

            return self::SUCCESS;
        }

        $this->info('Checking remote templates index...');
        $result = $service->checkForUpdates();

        if (! $result['available']) {
            $this->info('All templates are up to date.');

            return self::SUCCESS;
        }

        $this->info('Updates available: '.count($result['updates']).' template(s) updated.');

        if (! $settings->templateAutoDownloadEnabled()) {
            return self::SUCCESS;
        }

        try {
            $download = $downloader->download();
            $this->info("Downloaded and activated template bundle v{$download['version']}.");
        } catch (Throwable $e) {
            $this->error('Failed to download template update: '.$e->getMessage());

            return self::SUCCESS;
        }

        if ($settings->templateAutoBuildEnabled()) {
            $this->rebuildAffectedTemplates($rebuilder);
        }

        return self::SUCCESS;
    }

    /**
     * Queue a rebuild for every buildable target whose installed template is now stale.
     */
    private function rebuildAffectedTemplates(TemplateRebuilder $rebuilder): void
    {
        $catalog = new TemplateCatalog;

        RunnerTemplate::whereNotNull('template_catalog_id')->get()->each(function (RunnerTemplate $template) use ($rebuilder, $catalog): void {
            $entry = $catalog->entryForId($template->template_catalog_id);

            if ($entry === null) {
                return;
            }

            $stale = $template->buildableTargets()->filter(
                fn (ProxmoxTarget $target): bool => $target->pivot->version === null || version_compare($entry->version(), $target->pivot->version, '>')
            );

            if ($stale->isEmpty()) {
                return;
            }

            $rebuilder->queue($template, $stale, TemplateRebuilder::MODE_SEQUENTIAL);
            $this->info("Queued rebuild for {$template->name} on ".$stale->count().' node(s).');
        });
    }
}
