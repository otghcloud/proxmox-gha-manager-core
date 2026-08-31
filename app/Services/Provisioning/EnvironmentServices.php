<?php

namespace App\Services\Provisioning;

use App\Exceptions\ProvisioningException;
use App\Models\Environment;
use App\Models\ProxmoxTarget;
use App\Services\GitHub\GitHubClient;
use App\Services\Proxmox\ProxmoxClient;

/**
 * Builds the per-environment service graph.
 *
 * Every client is bound to one environment's credentials, so they cannot be resolved from
 * the container as singletons.
 */
class EnvironmentServices
{
    public function proxmox(Environment $environment): ProxmoxClient
    {
        $target = (new TargetSelector)->selectFor(['self-hosted']);

        if ($target === null) {
            throw new ProvisioningException('No Proxmox target is configured.');
        }

        return new ProxmoxClient($target);
    }

    public function github(Environment $environment): GitHubClient
    {
        return new GitHubClient($environment->githubAccount);
    }

    public function provisioner(Environment $environment): Provisioner
    {
        $target = $this->target($environment);
        $proxmox = new ProxmoxClient($target);

        return new Provisioner(
            $environment,
            $target,
            $proxmox,
            $this->github($environment),
            new VmidAllocator($proxmox),
            new SshRunnerLauncher,
            new TargetSelector,
        );
    }

    public function reaper(Environment $environment, ProxmoxTarget $target): Reaper
    {
        return new Reaper(
            $environment,
            $target,
            new ProxmoxClient($target),
            $this->github($environment),
            $this->provisionerForTarget($environment, $target),
        );
    }

    public function provisionerForTarget(Environment $environment, ProxmoxTarget $target): Provisioner
    {
        $proxmox = new ProxmoxClient($target);

        return new Provisioner(
            $environment,
            $target,
            $proxmox,
            $this->github($environment),
            new VmidAllocator($proxmox),
            new SshRunnerLauncher,
            new TargetSelector,
        );
    }

    public function target(Environment $environment): ProxmoxTarget
    {
        $target = (new TargetSelector)->selectFor(['self-hosted']);

        if ($target === null) {
            throw new ProvisioningException('No eligible Proxmox target is configured.');
        }

        return $target;
    }
}
