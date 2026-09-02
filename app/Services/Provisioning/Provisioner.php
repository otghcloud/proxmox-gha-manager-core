<?php

namespace App\Services\Provisioning;

use App\Enums\RunnerState;
use App\Enums\SpawnReason;
use App\Exceptions\CapacityException;
use App\Exceptions\JobNotClaimedException;
use App\Exceptions\ProvisioningException;
use App\Exceptions\ProxmoxException;
use App\Models\Environment;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\Runner;
use App\Services\GitHub\GitHubClient;
use App\Services\Proxmox\ProxmoxClient;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class Provisioner
{
    private const POLL_SECONDS = 3;

    private const PORT_CHECK_TIMEOUT = 5;

    private ?ProxmoxTarget $selectedTarget = null;

    public function __construct(
        private readonly Environment $environment,
        private ProxmoxTarget $target,
        private ProxmoxClient $proxmox,
        private readonly GitHubClient $github,
        private VmidAllocator $allocator,
        private readonly SshRunnerLauncher $launcher,
        private readonly TargetSelector $targetSelector,
    ) {
        $this->selectedTarget = $target;
    }

    /**
     * Clone a VM, boot it, register a single-use runner and start it.
     *
     * Errors after the record exists destroy the VM unless the environment is configured to keep
     * failures for inspection.
     */
    public function spawn(Pool $pool, ?int $workflowJobId = null, ?string $repositoryFullName = null, ?ProxmoxTarget $preferredTarget = null): Runner
    {
        $this->selectTarget($pool, $preferredTarget);

        // Capacity and VMID reservation are serialised per pool+node so parallel queue workers
        // cannot both read the same headroom and overshoot the limit.
        $runner = Cache::lock("pool-capacity:{$pool->id}:{$this->target->id}", 30)->block(15, function () use ($pool, $workflowJobId, $repositoryFullName): Runner {
            $this->assertCapacity($pool);

            return $this->allocator->allocate($this->target, 'runner', fn (int $vmid): Runner => DB::transaction(function () use ($vmid, $pool, $workflowJobId, $repositoryFullName) {
                $prefix = app(SettingsRepository::class)->runnerNamePrefix();

                return Runner::create([
                    'environment_id' => $this->environment->id,
                    'proxmox_target_id' => $this->target->id,
                    'pool_id' => $pool->id,
                    'vmid' => $vmid,
                    'runner_name' => sprintf('%s-%s', rtrim($prefix, '-'), Str::lower(Str::random(16))),
                    'spawn_reason' => $workflowJobId === null ? SpawnReason::Warm : SpawnReason::Job,
                    'workflow_job_id' => $workflowJobId,
                    'repository_full_name' => $repositoryFullName,
                    'state' => RunnerState::Spawning,
                    'state_changed_at' => now(),
                ]);
            }));
        });

        try {
            $this->build($runner, $pool);

            if ($workflowJobId !== null && $repositoryFullName !== null) {
                $this->confirmJobClaimed($runner, $repositoryFullName, $workflowJobId);
            }

            DB::transaction(fn () => $runner->transitionTo(RunnerState::Idle, 'provisioned'));

            return $runner;
        } catch (JobNotClaimedException $e) {
            // The VM is healthy and can still serve another job; only the association is released.
            DB::transaction(function () use ($runner) {
                $runner->forceFill(['workflow_job_id' => null])->save();
                $runner->transitionTo(RunnerState::Idle, 'job was never claimed');
            });

            throw $e;
        } catch (Throwable $e) {
            $this->fail($runner, $e);

            throw $e;
        }
    }

    public function selectedTarget(): ?ProxmoxTarget
    {
        return $this->selectedTarget;
    }

    /**
     * Deregister the runner with GitHub and remove its VM.
     */
    public function destroy(Runner $runner, string $reason): void
    {
        if ($runner->proxmoxTarget !== null && ! $runner->proxmoxTarget->is($this->target)) {
            $this->target = $runner->proxmoxTarget;
            $this->proxmox = new ProxmoxClient($this->target);
        }

        $runner->transitionTo(RunnerState::Reaping, $reason);

        if ($runner->github_runner_id !== null) {
            try {
                $this->github->deleteRunner($runner->github_runner_id);
            } catch (Throwable $e) {
                Log::warning('Could not deregister runner from GitHub', [
                    'runner' => $runner->runner_name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $this->proxmox->destroy($runner->vmid);
        } catch (Throwable $e) {
            Log::error('Could not destroy VM', [
                'vmid' => $runner->vmid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $runner->transitionTo(RunnerState::Destroyed, $reason);
    }

    private function build(Runner $runner, Pool $pool): void
    {
        $template = $pool->runnerTemplate;
        $mapping = $template->targetMappings()
            ->whereKey($this->target->id)
            ->first();

        if ($mapping === null || $mapping->pivot->template_vmid === null) {
            throw new ProvisioningException("Template {$template->name} has no physical VMID on target {$this->target->name}.");
        }

        $this->proxmox->clone($mapping->pivot->template_vmid, $runner->vmid, $runner->runner_name);

        // Recorded so a later template rebuild can tell which runners are running the old image.
        $runner->forceFill(['source_template_vmid' => $mapping->pivot->template_vmid])->save();

        $this->proxmox->configure($runner->vmid, $pool->cores, $pool->memory, $pool->name, [
            'managed_by' => ProxmoxClient::MANAGED_BY,
            'environment' => $this->environment->slug,
            'pool' => $pool->name,
            'runner_name' => $runner->runner_name,
            'created_at' => now()->timestamp,
        ], $this->target->networkAdapter());

        $this->proxmox->start($runner->vmid);

        $ip = $this->awaitGuestIp($runner, $pool->boot_timeout_seconds);
        $runner->forceFill(['ip_address' => $ip])->save();

        $this->awaitPort($ip, $template->os->remotePort(), $pool->boot_timeout_seconds);

        $jit = $this->github->generateJitConfig($runner->runner_name, $pool->labels);

        // Recorded before launching so a failed launch can still be cleaned up by the reaper.
        $runner->forceFill(['github_runner_id' => $jit->runnerId])->save();

        $this->launcher->launch($this->environment->githubAccount, $pool, $ip, $jit->encodedJitConfig, $runner->runner_name);
    }

    private function selectTarget(Pool $pool, ?ProxmoxTarget $preferredTarget = null): void
    {
        $target = $preferredTarget ?? $this->targetSelector->selectFor($pool->labels, $pool->runnerTemplate, $pool);

        if ($target === null) {
            throw new ProvisioningException("No eligible Proxmox target covers template {$pool->runnerTemplate->name}.");
        }

        $this->target = $target;
        $this->selectedTarget = $target;
        $this->proxmox = new ProxmoxClient($target);
        $this->allocator = new VmidAllocator($this->proxmox);
    }

    private function assertCapacity(Pool $pool): void
    {
        $total = $this->target->runners()->active()->count();

        if ($total >= $this->target->max_total_vms) {
            throw new CapacityException(
                "Proxmox target {$this->target->name} is at its limit of {$this->target->max_total_vms} VMs."
            );
        }

        $pool->loadMissing('proxmoxTargets');

        if (! $pool->runsOn($this->target)) {
            throw new CapacityException(
                "Pool {$pool->name} is not configured to run on {$this->target->name}."
            );
        }

        if (! $pool->hasCapacityOn($this->target)) {
            throw new CapacityException(
                "Pool {$pool->name} is at its limit of {$pool->maxConcurrentOn($this->target)} VMs on {$this->target->name}."
            );
        }
    }

    private function awaitGuestIp(Runner $runner, int $timeout): string
    {
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            $ip = $this->proxmox->guestIpv4($runner->vmid);

            if ($ip !== null) {
                return $ip;
            }

            sleep(self::POLL_SECONDS);
        }

        throw new ProxmoxException(
            "VM {$runner->vmid} never reported an IPv4 address; check the QEMU guest agent in the template."
        );
    }

    private function awaitPort(string $host, int $port, int $timeout): void
    {
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            $socket = @fsockopen($host, $port, $errno, $errstr, self::PORT_CHECK_TIMEOUT);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            sleep(self::POLL_SECONDS);
        }

        throw new ProvisioningException("Port {$port} on {$host} never became reachable.");
    }

    /**
     * Wait for GitHub to hand the triggering job to a runner.
     */
    private function confirmJobClaimed(Runner $runner, string $repositoryFullName, int $jobId): void
    {
        $deadline = microtime(true) + $this->environment->job_claim_timeout_seconds;

        while (microtime(true) < $deadline) {
            if ($this->github->jobStatus($repositoryFullName, $jobId) !== 'queued') {
                return;
            }

            sleep(self::POLL_SECONDS);
        }

        throw new JobNotClaimedException(
            "Job {$jobId} was still queued after {$this->environment->job_claim_timeout_seconds}s; runner {$runner->runner_name} stays available."
        );
    }

    private function fail(Runner $runner, Throwable $e): void
    {
        $runner->forceFill(['failure_reason' => Str::limit($e->getMessage(), 1000)])->save();
        $runner->transitionTo(RunnerState::Failed, 'provisioning failed');

        Log::error('Provisioning failed', [
            'runner' => $runner->runner_name,
            'vmid' => $runner->vmid,
            'error' => $e->getMessage(),
        ]);

        if ($this->environment->keep_failed_vms) {
            return;
        }

        try {
            $this->destroy($runner, 'provisioning failed');
        } catch (Throwable $cleanup) {
            Log::error('Could not clean up after a failed spawn', [
                'vmid' => $runner->vmid,
                'error' => $cleanup->getMessage(),
            ]);
        }
    }
}
