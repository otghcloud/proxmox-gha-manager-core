<?php

namespace OTGH\ProxmoxGHA\Cloudimg;

use App\Contracts\Builds\BuilderInterface;
use App\Contracts\Builds\BuildResult;
use App\Exceptions\ProvisioningException;
use App\Models\Credential;
use App\Models\ImageBuild;
use App\Services\Builds\RunnerImagesLocator;
use App\Services\Builds\TemplateCatalog;
use App\Services\Builds\TemplateCatalogEntry;
use App\Services\Proxmox\ProxmoxClient;
use App\Services\Ssh\SshConnection;

final class CloudimgBuilder implements BuilderInterface
{
    private const GUEST_IP_TIMEOUT_SECONDS = 300;

    public function __construct(
        private readonly RunnerImagesLocator $runnerImages = new RunnerImagesLocator,
        private readonly TemplateCatalog $catalog = new TemplateCatalog,
    ) {}

    public function type(): string
    {
        return 'cloudimg';
    }

    public function build(
        ImageBuild $build,
        TemplateCatalogEntry $entry,
        string $templateDirectory,
    ): BuildResult {
        $target = $build->proxmoxTarget;
        $template = $build->runnerTemplate;
        $credential = $build->credentialSnapshot ?: $build->credential ?: Credential::query()
            ->where('name', 'Default Linux SSH')
            ->first();

        if ($target === null || $template === null || $build->template_vmid === null) {
            throw new ProvisioningException('The cloud image build has incomplete Proxmox metadata.');
        }

        if ($credential === null || ! $credential->hasAuthenticationMaterial()) {
            throw new ProvisioningException('The cloud image build has no usable SSH credential.');
        }

        $artifact = $entry->builder()['artifact'] ?? [];
        $requirements = $entry->requirements();
        $proxmox = new ProxmoxClient($target);
        $vmid = (int) $build->template_vmid;
        $created = false;

        try {
            $source = $artifact['file'] ?? null;
            $isoStorage = (string) ($target->build_iso_storage ?: $target->build_vm_storage);
            $vmStorage = (string) $target->build_vm_storage;

            if (! is_string($source) || $source === '') {
                $url = $artifact['url'] ?? null;

                if (! is_string($url) || $url === '' || $isoStorage === '') {
                    throw new ProvisioningException('The cloud image builder requires an artifact file or URL and image storage.');
                }

                $source = $proxmox->downloadCloudImage($isoStorage, $url);
            }

            if ($vmStorage === '') {
                throw new ProvisioningException('The target has no VM storage configured for cloud images.');
            }

            $proxmox->createCloudImageVm(
                vmid: $vmid,
                name: $template->vmName(),
                cores: (int) ($requirements['cpu_cores'] ?? 2),
                memory: (int) ($requirements['memory_mb'] ?? 4096),
                networkAdapter: $target->networkAdapter(),
            );
            $created = true;
            $proxmox->importCloudImage($vmid, $vmStorage, $source);
            $proxmox->resizeCloudImageDisk($vmid, ((int) ($requirements['disk_gb'] ?? 25)).'G');
            $proxmox->configureCloudInit(
                vmid: $vmid,
                username: (string) $credential->resolvedUsername(),
                password: $credential->password,
                publicKey: $credential->public_key,
                ipConfig: 'ip=dhcp',
            );
            $proxmox->start($vmid);

            $ip = $this->awaitGuestIp($proxmox, $vmid);
            $this->runManifestCommands($build, $entry, $templateDirectory, $ip, $credential);
            $proxmox->stop($vmid);
            $proxmox->convertToTemplate($vmid);

            return new BuildResult(true, 0, $vmid);
        } catch (\Throwable $exception) {
            if ($created) {
                try {
                    $proxmox->destroy($vmid);
                } catch (\Throwable) {
                    // Preserve the original build failure; cleanup is retried by reconciliation.
                }
            }

            throw $exception;
        }
    }

    private function awaitGuestIp(ProxmoxClient $proxmox, int $vmid): string
    {
        $deadline = microtime(true) + self::GUEST_IP_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            $ip = $proxmox->guestIpv4($vmid);

            if ($ip !== null) {
                return $ip;
            }

            sleep(3);
        }

        throw new ProvisioningException("Cloud image VM {$vmid} never reported an IPv4 address.");
    }

    private function runManifestCommands(
        ImageBuild $build,
        TemplateCatalogEntry $entry,
        string $templateDirectory,
        string $ip,
        Credential $credential,
    ): void {
        $manifestPath = rtrim($templateDirectory, '/').'/build.json';

        if (! is_readable($manifestPath)) {
            return;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            throw new ProvisioningException('The cloud image build manifest is invalid.');
        }

        $ssh = new SshConnection(
            host: $ip,
            port: 22,
            username: (string) $credential->resolvedUsername(),
            password: $credential->password,
            privateKey: $credential->private_key,
        );

        try {
            foreach ($manifest['stage_groups'] ?? [] as $group) {
                foreach ($group['stages'] ?? [] as $stage) {
                    $this->uploadStageFiles($ssh, $entry, $templateDirectory, $stage);

                    $environment = $this->stageEnvironment($build, $stage['environment'] ?? []);
                    foreach ($stage['commands'] ?? [] as $command) {
                        if (! is_string($command) || trim($command) === '') {
                            continue;
                        }

                        $prefixed = $environment === []
                            ? $command
                            : 'export '.$this->exportEnvironment($environment).' && '.$command;
                        $output = $ssh->run($prefixed);
                        file_put_contents((string) $build->log_path, ($stage['marker'] ?? '')."\n".$output."\n", FILE_APPEND);

                        if ($ssh->exitCode() !== 0) {
                            throw new ProvisioningException('Cloud image stage failed: '.($stage['id'] ?? 'unknown').'.');
                        }
                    }
                }
            }
        } finally {
            $ssh->disconnect();
        }
    }

    /** @param array<string, mixed> $stage */
    private function uploadStageFiles(SshConnection $ssh, TemplateCatalogEntry $entry, string $templateDirectory, array $stage): void
    {
        $runnerRoot = $this->runnerImages->scriptsRoot($entry);

        foreach ($stage['uploads'] ?? [] as $upload) {
            if (! is_array($upload)) {
                continue;
            }

            $source = $upload['source'] ?? null;
            $destination = $upload['destination'] ?? null;

            if (! is_string($source) || ! is_string($destination) || $source === '' || $destination === '' || str_contains($source, '..')) {
                throw new ProvisioningException('Cloud image manifest contains an invalid upload path.');
            }

            $root = ($upload['source_root'] ?? 'runner_images') === 'catalog_root'
                ? $this->catalog->root()
                : (($upload['source_root'] ?? 'runner_images') === 'template' ? $templateDirectory : $runnerRoot);

            if ($root === null) {
                throw new ProvisioningException('The cloud image manifest requires runner-images files, but no scripts root is available.');
            }

            $localPath = rtrim($root, '/').'/'.ltrim($source, '/');
            $sourceIsDirectory = is_dir($localPath);
            $files = $sourceIsDirectory ? $this->filesIn($localPath) : [$localPath];

            if ($files === [] || ! is_readable($localPath)) {
                throw new ProvisioningException('Cloud image upload source does not exist: '.$localPath);
            }

            $remoteRoot = $sourceIsDirectory ? $destination : dirname($destination);
            $ssh->run('sudo mkdir -p '.escapeshellarg($remoteRoot).' && sudo chmod 0777 '.escapeshellarg($remoteRoot));

            foreach ($files as $file) {
                $relative = $sourceIsDirectory ? ltrim(substr($file, strlen(rtrim($localPath, '/'))), '/') : basename($destination);
                $remote = $sourceIsDirectory ? rtrim($destination, '/').'/'.$relative : $destination;
                $ssh->run('mkdir -p '.escapeshellarg(dirname($remote)));
                $ssh->putFile($remote, $file);
                $mode = (int) ($upload['mode'] ?? 0644);
                $ssh->chmod($mode, $remote);
            }
        }
    }

    /** @return list<string> */
    private function filesIn(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /** @param array<string, mixed> $declared */
    private function stageEnvironment(ImageBuild $build, array $declared): array
    {
        $account = $build->environment?->githubAccount;
        $available = [
            'github_api_token' => (string) ($account?->github_token ?? ''),
            'github_api_min_remaining' => (string) config('builds.github_api_min_remaining', 1000),
            'github_api_wait_buffer_seconds' => (string) config('builds.github_api_wait_buffer_seconds', 30),
        ];
        $resolved = [];

        foreach ($declared as $name => $reference) {
            if (! is_string($name) || ! is_string($reference) || ! array_key_exists($reference, $available)) {
                throw new ProvisioningException('Cloud image manifest references an unavailable environment value.');
            }

            $resolved[$name] = $available[$reference];
        }

        return $resolved;
    }

    /** @param array<string, string> $environment */
    private function exportEnvironment(array $environment): string
    {
        return implode(' ', array_map(
            fn (string $name, string $value): string => $name.'='.escapeshellarg($value),
            array_keys($environment),
            $environment,
        ));
    }
}
