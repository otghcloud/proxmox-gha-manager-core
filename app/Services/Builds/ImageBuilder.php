<?php

namespace App\Services\Builds;

use App\Enums\BuildStatus;
use App\Exceptions\ProvisioningException;
use App\Models\ImageBuild;
use App\Services\Proxmox\ProxmoxClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Builds a Proxmox runner image with Packer, from the templates published by
 * proxmox-gha-manager-templates.
 *
 * The build itself executes on the Proxmox host over its API, so this only needs the Packer
 * binary; there is no QEMU or KVM involvement here.
 */
class ImageBuilder
{
    public function __construct(
        private readonly ProxmoxClient $proxmox,
        private readonly TemplateRebuilder $rebuilder = new TemplateRebuilder,
        private readonly TemplateCatalog $catalog = new TemplateCatalog,
        private readonly ?BuilderRegistry $builders = null,
    ) {}

    public function run(ImageBuild $build): void
    {
        $template = $build->runnerTemplate;
        $mapping = $template?->targetMappings()->whereKey($build->proxmox_target_id)->first();
        $entry = $this->catalog->entryForId($build->template_catalog_id, $build->builder_type);

        if ($template === null || $mapping === null || $entry === null) {
            throw new ProvisioningException('The build has no template attached.');
        }

        $logPath = $this->logPath($build);
        $templateDirectory = $this->catalog->templateDirectory($entry);

        if ($templateDirectory === null) {
            throw new ProvisioningException('No installed template matches the build catalog ID '.$build->template_catalog_id.'.');
        }

        DB::transaction(function () use ($build, $logPath) {
            $build->forceFill([
                'status' => BuildStatus::Running,
                'started_at' => now(),
                'log_path' => $logPath,
            ])->save();
        });

        $result = $this->builderRegistry()
            ->forType($entry->builderType())
            ->build($build, $entry, $templateDirectory);

        $this->storeLog($build, $logPath);

        // A force kill already finalised the record; the non-zero exit is the kill, not a build failure.
        if ($build->fresh()?->status === BuildStatus::Cancelled) {
            $this->rebuilder->advanceBatch($build->refresh());

            return;
        }

        DB::transaction(function () use ($build, $result, $template, $entry) {
            $build->forceFill([
                'status' => $result->successful ? BuildStatus::Succeeded : BuildStatus::Failed,
                'exit_code' => $result->exitCode,
                'process_pid' => null,
                'finished_at' => now(),
            ])->save();

            if (! $result->successful) {
                Log::error('Image build failed', [
                    'build' => $build->id,
                    'template_catalog_id' => $entry->id(),
                    'exit_code' => $result->exitCode,
                ]);

                return;
            }

            try {
                $this->rebuilder->promote($build, $result->templateVmid ?? (int) $build->template_vmid);
            } catch (\Throwable $e) {
                Log::error('Image build succeeded but the template record could not be updated', [
                    'build' => $build->id,
                    'template' => $template->id,
                    'template_catalog_id' => $entry->id(),
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->rebuilder->advanceBatch($build->refresh());
    }

    private function builderRegistry(): BuilderRegistry
    {
        return $this->builders ?: app(BuilderRegistry::class);
    }

    public function logPath(ImageBuild $build): string
    {
        $directory = config('builds.log_directory');

        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        return $directory.'/build-'.$build->id.'.log';
    }

    public static function isAvailable(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $path = rtrim(config('builds.image_builder_path'), '/');

        return is_dir($path) && is_file($path.'/templates.json');
    }
}
