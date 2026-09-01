<?php

namespace App\Services\Builds\Packer;

use App\Exceptions\ProvisioningException;
use Symfony\Component\Process\Process;

/**
 * Resolves the actions/runner-images build scripts a Packer template provisions from.
 *
 * The templates default to a `vendor/runner-images` tree beside them. When the installed
 * catalog does not carry one, the commit it was generated against is fetched on demand and
 * cached, so an image build always matches the templates that requested it.
 */
class RunnerImagesLocator
{
    private const FETCH_TIMEOUT_SECONDS = 1800;

    public function __construct(
        private readonly TemplateCatalog $catalog = new TemplateCatalog,
    ) {}

    /**
     * The scripts root to override the template default with, or null when the bundled tree is usable.
     */
    public function scriptsRoot(TemplateCatalogEntry $template): ?string
    {
        if (! $template->requiresScriptsRoot()) {
            return null;
        }

        if (is_dir($this->bundledPath($template))) {
            return null;
        }

        $commit = $this->catalog->runnerImagesCommit();

        if ($commit === null) {
            throw new ProvisioningException(
                'The installed templates bundle no runner-images tree and record no runner_images_commit to fetch.'
            );
        }

        $checkout = rtrim((string) config('builds.runner_images_path'), '/').'/'.$commit;
        $scripts = $checkout.'/'.$template->runnerImagesDirectory();

        if (! is_dir($scripts)) {
            $this->fetch($checkout, $commit, $template->runnerImagesDirectory());
        }

        if (! is_dir($scripts)) {
            throw new ProvisioningException('The runner-images checkout is missing '.$template->runnerImagesDirectory().'.');
        }

        return $scripts;
    }

    private function bundledPath(TemplateCatalogEntry $template): string
    {
        return $this->catalog->root().'/vendor/runner-images/'.$template->runnerImagesDirectory();
    }

    /**
     * Sparse, single-commit fetch: the full history is hundreds of megabytes and none of it is needed.
     */
    private function fetch(string $checkout, string $commit, string $directory): void
    {
        if (! is_dir($checkout) && ! mkdir($checkout, 0750, true) && ! is_dir($checkout)) {
            throw new ProvisioningException('The runner-images cache directory could not be created: '.$checkout);
        }

        $repository = (string) config('builds.runner_images_repository');

        $steps = [
            ['git', 'init', '-q'],
            ['git', 'config', 'remote.origin.url', $repository],
            ['git', 'sparse-checkout', 'set', '--no-cone', $directory],
            ['git', 'fetch', '-q', '--depth', '1', 'origin', $commit],
            ['git', 'checkout', '-q', 'FETCH_HEAD'],
        ];

        foreach ($steps as $step) {
            $process = new Process($step, $checkout, null, null, self::FETCH_TIMEOUT_SECONDS);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProvisioningException(
                    'Fetching runner-images at '.$commit.' failed: '.trim($process->getErrorOutput() ?: $process->getOutput())
                );
            }
        }
    }
}
