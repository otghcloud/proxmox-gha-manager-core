<?php

namespace App\Console\Commands;

use App\Services\Templates\TemplateDownloadService;
use Illuminate\Console\Command;

class TemplatesPruneBundlesCommand extends Command
{
    protected $signature = 'templates:prune-bundles';

    protected $description = 'Delete downloaded template bundles beyond the configured retention.';

    public function handle(TemplateDownloadService $downloader): int
    {
        $downloader->prune();

        $this->components->info('Pruned superseded template bundles.');

        return self::SUCCESS;
    }
}
