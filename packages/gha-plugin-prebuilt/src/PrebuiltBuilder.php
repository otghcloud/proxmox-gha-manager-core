<?php

namespace OTGH\ProxmoxGHA\Prebuilt;

use App\Contracts\Builds\BuilderInterface;
use App\Contracts\Builds\BuildResult;
use App\Exceptions\ProvisioningException;
use App\Models\ImageBuild;
use App\Services\Builds\TemplateCatalogEntry;
use App\Services\Proxmox\ProxmoxClient;

final class PrebuiltBuilder implements BuilderInterface
{
    public function type(): string
    {
        return 'prebuilt';
    }

    public function build(
        ImageBuild $build,
        TemplateCatalogEntry $entry,
        string $templateDirectory,
    ): BuildResult {
        $target = $build->proxmoxTarget;
        $template = $build->runnerTemplate;

        if ($target === null || $template === null || $build->template_vmid === null) {
            throw new ProvisioningException('The prebuilt build has incomplete Proxmox metadata.');
        }

        $artifact = $entry->builder()['artifact'] ?? [];
        $source = $artifact['file'] ?? null;
        $storage = (string) ($target->build_iso_storage ?: $target->build_vm_storage);
        $vmStorage = (string) $target->build_vm_storage;

        if (! is_string($source) || $source === '') {
            $url = $artifact['url'] ?? null;

            if (! is_string($url) || $url === '' || $storage === '') {
                throw new ProvisioningException('The prebuilt builder requires an artifact file or URL and image storage.');
            }

            $source = (new ProxmoxClient($target))->downloadImage($storage, $url);
        }

        if ($vmStorage === '') {
            throw new ProvisioningException('The target has no VM storage configured for prebuilt artifacts.');
        }

        $proxmox = new ProxmoxClient($target);
        $vmid = (int) $build->template_vmid;
        $requirements = $entry->requirements();

        try {
            $proxmox->createCloudImageVm(
                vmid: $vmid,
                name: $template->vmName(),
                cores: (int) ($requirements['cpu_cores'] ?? 2),
                memory: (int) ($requirements['memory_mb'] ?? 4096),
                networkAdapter: $target->networkAdapter(),
            );
            $proxmox->importCloudImage($vmid, $vmStorage, $source);

            if (($requirements['disk_gb'] ?? null) !== null) {
                $proxmox->resizeCloudImageDisk($vmid, ((int) $requirements['disk_gb']).'G');
            }

            $proxmox->convertToTemplate($vmid);

            return new BuildResult(true, 0, $vmid);
        } catch (\Throwable $exception) {
            try {
                $proxmox->destroy($vmid);
            } catch (\Throwable) {
                // Preserve the original import failure; cleanup is retried by reconciliation.
            }

            throw $exception;
        }
    }
}
