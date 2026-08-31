<?php

namespace App\Services\Builds;

use App\Enums\BuildStatus;
use App\Enums\BuildTarget;
use App\Exceptions\ProvisioningException;
use App\Models\ImageBuild;
use App\Models\RunnerTemplate;
use App\Services\Builds\Packer\RunnerImagesLocator;
use App\Services\Builds\Packer\TemplateCatalog;
use App\Services\Builds\Packer\UserDataRenderer;
use App\Services\Proxmox\ProxmoxClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Builds a Proxmox runner image with Packer, from the templates published by
 * proxmox-gha-manager-templates.
 *
 * The build itself executes on the Proxmox host over its API, so this only needs the Packer
 * binary; there is no QEMU or KVM involvement here.
 */
class ImageBuilder
{
    /** Builds can take several hours depending on guest OS and toolset. */
    private const TIMEOUT_SECONDS = 43200;

    public function __construct(
        private readonly ProxmoxClient $proxmox,
        private readonly TemplateRebuilder $rebuilder = new TemplateRebuilder,
        private readonly TemplateCatalog $catalog = new TemplateCatalog,
        private readonly UserDataRenderer $userData = new UserDataRenderer,
        private readonly RunnerImagesLocator $runnerImages = new RunnerImagesLocator,
    ) {}

    public function run(ImageBuild $build): void
    {
        $template = $build->runnerTemplate;
        $mapping = $template?->targetMappings()->whereKey($build->proxmox_target_id)->first();
        $target = BuildTarget::from($build->target);

        if ($template === null || $mapping === null) {
            throw new ProvisioningException('The build has no template attached.');
        }

        $logPath = $this->logPath($build);
        $templateDirectory = $this->catalog->templateDirectory($target);

        if ($templateDirectory === null) {
            throw new ProvisioningException('No installed template matches the build target '.$target->value.'.');
        }

        DB::transaction(function () use ($build, $logPath) {
            $build->forceFill([
                'status' => BuildStatus::Running,
                'started_at' => now(),
                'log_path' => $logPath,
            ])->save();
        });

        $account = $build->environment->githubAccount;

        $this->userData->render(
            $templateDirectory,
            (string) $account->linux_ssh_username,
            (string) $account->linux_ssh_password,
        );

        $process = $this->runPacker($templateDirectory, $this->buildEnvironment($build, $target), $logPath);

        DB::transaction(function () use ($build, $process, $template, $target) {
            $build->forceFill([
                'status' => $process->isSuccessful() ? BuildStatus::Succeeded : BuildStatus::Failed,
                'exit_code' => $process->getExitCode(),
                'finished_at' => now(),
            ])->save();

            if (! $process->isSuccessful()) {
                Log::error('Image build failed', [
                    'build' => $build->id,
                    'target' => $target->value,
                    'exit_code' => $process->getExitCode(),
                ]);

                return;
            }

            try {
                $this->rebuilder->promote($build, $this->builtVmid($build, $template));
            } catch (\Throwable $e) {
                Log::error('Image build succeeded but the template record could not be updated', [
                    'build' => $build->id,
                    'template' => $template->id,
                    'target' => $target->value,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->rebuilder->advanceBatch($build->refresh());
    }

    /**
     * Kept separate from environmentVariables(): locating the scripts root can fetch from the network.
     *
     * @return array<string, string>
     */
    private function buildEnvironment(ImageBuild $build, BuildTarget $target): array
    {
        $variables = $this->environmentVariables($build);
        $scriptsRoot = $this->runnerImages->scriptsRoot($target);

        if ($scriptsRoot !== null) {
            $variables[$target->scriptsRootVariable()] = $scriptsRoot;
        }

        return $variables;
    }

    /**
     * Runs `packer init`, `validate` and `build` in turn, stopping at the first failure.
     *
     * @param  array<string, string>  $environment
     */
    private function runPacker(string $templateDirectory, array $environment, string $logPath): Process
    {
        $packer = (string) config('builds.packer_binary');
        $handle = fopen($logPath, 'w');

        if ($handle === false) {
            throw new ProvisioningException('The build log could not be opened: '.$logPath);
        }

        try {
            $process = null;

            foreach (['init', 'validate', 'build'] as $command) {
                fwrite($handle, sprintf("==> packer %s\n", $command));

                $process = new Process(
                    [$packer, $command, '.'],
                    $templateDirectory,
                    $environment,
                    null,
                    self::TIMEOUT_SECONDS,
                );

                $process->run(function (string $type, string $chunk) use ($handle): void {
                    fwrite($handle, $chunk);
                });

                if (! $process->isSuccessful()) {
                    break;
                }
            }

            return $process;
        } finally {
            fclose($handle);
        }
    }

    /**
     * The VMID the build was told to use, falling back to whatever Packer reported if it drifted.
     */
    private function builtVmid(ImageBuild $build, RunnerTemplate $template): int
    {
        $fromLog = $this->builtVmidFromLog($build);

        if ($fromLog !== null) {
            return $fromLog;
        }

        if ($build->template_vmid !== null) {
            return (int) $build->template_vmid;
        }

        foreach ($this->proxmox->clusterVms() as $vmid => $vm) {
            if (($vm['name'] ?? null) === $template->vmName()) {
                return (int) $vmid;
            }
        }

        throw new ProvisioningException('The build produced no identifiable template VMID.');
    }

    private function builtVmidFromLog(ImageBuild $build): ?int
    {
        $path = $build->log_path;

        if ($path === null || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        if (! preg_match_all('/A template was created:\s*(\d+)/', $contents, $matches)) {
            return null;
        }

        return (int) end($matches[1]);
    }

    /**
     * @return array<string, string>
     */
    private function environmentVariables(ImageBuild $build): array
    {
        $account = $build->environment->githubAccount;
        $template = $build->runnerTemplate;
        $targetNode = $build->proxmoxTarget;
        $mapping = $template->targetMappings()->whereKey($targetNode->id)->first();
        $target = BuildTarget::from($build->target);
        $sizing = $target->sizingVariables();
        $mapping = $template->targetMappings()->whereKey($targetNode->id)->firstOrFail();

        $variables = [
            'PKR_VAR_pmx_url' => $targetNode->proxmox_url,
            'PKR_VAR_pmx_node' => $targetNode->proxmox_node,
            'PKR_VAR_pmx_token_id' => (string) $targetNode->proxmox_token_id,
            'PKR_VAR_pmx_token_secret' => (string) $targetNode->proxmox_token_secret,
            'PKR_VAR_pmx_iso_storage' => (string) $targetNode->build_iso_storage,
            'PKR_VAR_pmx_vm_storage' => (string) $targetNode->build_vm_storage,
            'PKR_VAR_pmx_template_vmid' => (string) ($build->template_vmid ?? $mapping->pivot->template_vmid),
            'PKR_VAR_pmx_template_name' => $template->vmName(),
            'PKR_VAR_pmx_cpu_type' => (string) ($targetNode->build_cpu_type ?: 'host'),
            'PKR_VAR_pmx_network_bridge' => (string) ($targetNode->network_bridge ?: 'vmbr0'),
            'PKR_VAR_ssh_username' => (string) $account->linux_ssh_username,
            'PKR_VAR_ssh_password' => (string) $account->linux_ssh_password,
            $target->isoVariable() => (string) $mapping->pivot->build_iso_file,

            // Authenticated plugin and tool downloads, which otherwise hit GitHub's anonymous rate limit.
            'PACKER_GITHUB_API_TOKEN' => (string) $account->github_token,
            'PKR_VAR_github_api_token' => (string) $account->github_token,
            'PACKER_PLUGIN_PATH' => config('builds.packer_plugin_path'),

            'HOME' => config('builds.working_directory'),
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        ];

        // Left unset on an untagged node: Proxmox rejects `tag=0`, so the adapter must carry no tag.
        if ($targetNode->vlan_tag !== null) {
            $variables['PKR_VAR_pmx_vlan_tag'] = (string) $targetNode->vlan_tag;
        }

        // Sizing is per node; anything left blank falls back to the Packer template's own default.
        foreach (['cores' => 'build_cores', 'memory' => 'build_memory_mb', 'disk' => 'build_disk_gb'] as $key => $column) {
            $value = $mapping->pivot->{$column};

            if ($value !== null) {
                $variables[$sizing[$key]] = (string) $value;
            }
        }

        return $variables;
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
