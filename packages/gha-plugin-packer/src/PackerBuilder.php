<?php

namespace OTGH\ProxmoxGHA\Packer;

use App\Contracts\Builds\BuilderInterface;
use App\Contracts\Builds\BuildResult;
use App\Exceptions\ProvisioningException;
use App\Models\Credential;
use App\Models\ImageBuild;
use App\Services\Builds\RunnerImagesLocator;
use App\Services\Builds\TemplateCatalogEntry;
use Symfony\Component\Process\Process;

final class PackerBuilder implements BuilderInterface
{
    private const TIMEOUT_SECONDS = 43200;

    public function __construct(
        private readonly UserDataRenderer $userData = new UserDataRenderer,
        private readonly RunnerImagesLocator $runnerImages = new RunnerImagesLocator,
    ) {}

    public function type(): string
    {
        return 'packer';
    }

    public function build(
        ImageBuild $build,
        TemplateCatalogEntry $entry,
        string $templateDirectory,
    ): BuildResult {
        $credential = $build->credentialSnapshot ?: $build->credential ?: Credential::query()
            ->where('name', 'Default Linux SSH')
            ->first();

        if ($credential === null) {
            throw new ProvisioningException('The build has no credential snapshot.');
        }

        $this->userData->render(
            $templateDirectory,
            (string) $credential->username,
            (string) $credential->password,
        );

        $logPath = (string) $build->log_path;
        $process = $this->runPacker($build, $templateDirectory, $this->buildEnvironment($build, $entry), $logPath);

        return new BuildResult(
            successful: $process->isSuccessful(),
            exitCode: $process->getExitCode(),
            templateVmid: $this->builtVmidFromLog($logPath),
        );
    }

    /** @return array<string, string> */
    private function buildEnvironment(ImageBuild $build, TemplateCatalogEntry $entry): array
    {
        $variables = $this->environmentVariables($build);
        $scriptsRoot = $this->runnerImages->scriptsRoot($entry);

        if ($scriptsRoot !== null) {
            $variables['PKR_VAR_runner_images_root'] = $scriptsRoot;
        }

        return $variables;
    }

    /** @param array<string, string> $environment */
    private function runPacker(
        ImageBuild $build,
        string $templateDirectory,
        array $environment,
        string $logPath,
    ): Process {
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
                $process->start(function (string $type, string $chunk) use ($handle): void {
                    fwrite($handle, $chunk);
                });
                $build->forceFill(['process_pid' => $process->getPid()])->save();
                $process->wait();

                if (! $process->isSuccessful()) {
                    break;
                }
            }

            return $process;
        } finally {
            fclose($handle);
        }
    }

    private function builtVmidFromLog(string $logPath): ?int
    {
        if (! is_readable($logPath)) {
            return null;
        }

        $contents = file_get_contents($logPath);

        if ($contents === false || ! preg_match_all('/A template was created:\s*(\d+)/', $contents, $matches)) {
            return null;
        }

        return (int) end($matches[1]);
    }

    /** @return array<string, string> */
    private function environmentVariables(ImageBuild $build): array
    {
        $account = $build->environment->githubAccount;
        $credential = $build->credentialSnapshot ?: $build->credential ?: Credential::query()
            ->where('name', 'Default Linux SSH')
            ->first();

        if ($credential === null) {
            throw new ProvisioningException('The build has no credential snapshot.');
        }

        $template = $build->runnerTemplate;
        $targetNode = $build->proxmoxTarget;
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
            'PKR_VAR_ssh_username' => (string) $credential->username,
            'PKR_VAR_ssh_password' => (string) $credential->password,
            'PKR_VAR_pmx_iso_file' => (string) $mapping->pivot->build_iso_file,
            'PACKER_GITHUB_API_TOKEN' => (string) $account->github_token,
            'PKR_VAR_github_api_token' => (string) $account->github_token,
            'PACKER_PLUGIN_PATH' => config('builds.packer_plugin_path'),
            'HOME' => config('builds.working_directory'),
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        ];

        if ($targetNode->vlan_tag !== null) {
            $variables['PKR_VAR_pmx_vlan_tag'] = (string) $targetNode->vlan_tag;
        }

        foreach (['PKR_VAR_build_cpu_cores' => 'build_cores', 'PKR_VAR_build_memory_mb' => 'build_memory_mb', 'PKR_VAR_build_disk_gb' => 'build_disk_gb'] as $variable => $column) {
            $value = $mapping->pivot->{$column};

            if ($value !== null) {
                $variables[$variable] = (string) $value;
            }
        }

        return $variables;
    }
}
