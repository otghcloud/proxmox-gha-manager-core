<?php

namespace App\Services\Provisioning;

use App\Enums\RunnerState;
use App\Models\Environment;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\Runner;
use App\Services\GitHub\GitHubClient;
use App\Services\GitHub\GitHubRunner;
use App\Services\Proxmox\ProxmoxClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class Reaper
{
    /** Grace period before a runner that never registered is considered abandoned. */
    private const REGISTRATION_GRACE_SECONDS = 120;

    public function __construct(
        private readonly Environment $environment,
        private readonly ProxmoxTarget $target,
        private readonly ProxmoxClient $proxmox,
        private readonly GitHubClient $github,
        private readonly Provisioner $provisioner,
    ) {}

    /**
     * Reconcile against reality, then destroy everything that has outlived its usefulness.
     *
     * @return int the number of VMs destroyed
     */
    public function runOnce(): int
    {
        $this->reconcile();

        $runners = $this->github->listRunners();
        $destroyed = 0;

        foreach ($this->trackedRunners()->get() as $runner) {
            // Destroying a runner waits on Proxmox, so a long sweep can leave later entries in this
            // snapshot minutes out of date. Re-read before judging one, or a runner that has since
            // been provisioned and picked up a job gets destroyed on its stale state.
            if ($runner->refresh()->state === RunnerState::Destroyed) {
                continue;
            }

            $reason = $this->destructionReason($runner, $runners[$runner->runner_name] ?? null);

            if ($reason === null) {
                continue;
            }

            try {
                $this->provisioner->destroy($runner, $reason);
                $destroyed++;
            } catch (Throwable $e) {
                Log::error('Reaper could not destroy a runner', [
                    'runner' => $runner->runner_name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->deleteOrphanedRegistrations($runners);

        return $destroyed;
    }

    /**
     * Count VMs this reaper is expected to destroy before the destructive pass.
     *
     * The normal pass uses GitHub registration state and lifecycle rules, so
     * the preflight performs the same reconciliation and eligibility checks.
     */
    public function pendingCount(bool $all = false): int
    {
        if ($all) {
            return $this->trackedRunners()->count();
        }

        $this->reconcile();
        $runners = $this->github->listRunners();
        $pending = 0;

        foreach ($this->trackedRunners()->get() as $runner) {
            if ($runner->refresh()->state === RunnerState::Destroyed) {
                continue;
            }

            if ($this->destructionReason($runner, $runners[$runner->runner_name] ?? null) !== null) {
                $pending++;
            }
        }

        return $pending;
    }

    /**
     * Destroy every tracked runner on this target, whatever state it is in.
     *
     * @return int the number of VMs destroyed
     */
    public function destroyAll(): int
    {
        $destroyed = 0;

        foreach ($this->trackedRunners()->get() as $runner) {
            try {
                $this->provisioner->destroy($runner, 'manually reaped from the debug console');
                $destroyed++;
            } catch (Throwable $e) {
                Log::error('Reaper could not destroy a runner', [
                    'runner' => $runner->runner_name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $destroyed;
    }

    /**
     * Bring the database back in line with what Proxmox actually has.
     *
     * @return int the number of corrections made
     */
    public function reconcile(): int
    {
        $vms = $this->proxmox->clusterVms();
        $corrections = 0;

        foreach ($this->trackedRunners()->get() as $runner) {
            if (! isset($vms[$runner->vmid])) {
                $runner->transitionTo(RunnerState::Destroyed, 'VM no longer exists in Proxmox');
                $corrections++;
            }
        }

        $known = $this->trackedRunners()->pluck('vmid')->flip();

        foreach ($vms as $vmid => $vm) {
            if (isset($known[$vmid]) || $this->isTemplateVmid($vmid) || ! $this->isManaged($vm)) {
                continue;
            }

            $corrections += $this->adopt($vmid) ? 1 : 0;
        }

        return $corrections;
    }

    /**
     * A VMID inside the node's template range belongs to a template or an in-flight build of one,
     * neither of which the reaper may touch. Builds run for hours as an ordinary VM before Packer
     * converts them, so name and tag heuristics alone are not enough to protect them.
     */
    private function isTemplateVmid(int $vmid): bool
    {
        $start = $this->target->template_vmid_range_start;
        $end = $this->target->template_vmid_range_end;

        return $start !== null && $end !== null && $vmid >= $start && $vmid <= $end;
    }

    /**
     * Adopt a tagged VM that has no database row, using the metadata written at creation.
     */
    private function adopt(int $vmid): bool
    {
        try {
            $config = $this->proxmox->config($vmid);
            $metadata = json_decode((string) ($config['description'] ?? ''), true);
        } catch (Throwable) {
            return false;
        }

        $runnerName = is_array($metadata) ? ($metadata['runner_name'] ?? null) : null;
        $poolName = is_array($metadata) ? ($metadata['pool'] ?? null) : null;
        $environmentSlug = is_array($metadata) ? ($metadata['environment'] ?? null) : null;

        if (! is_string($runnerName) || ! is_string($poolName) || $environmentSlug !== $this->environment->slug) {
            Log::warning('Destroying a tagged VM that carries no usable metadata', ['vmid' => $vmid]);

            try {
                $this->proxmox->destroy($vmid);
            } catch (Throwable $e) {
                Log::error('Could not destroy an unrecognised VM', ['vmid' => $vmid, 'error' => $e->getMessage()]);
            }

            return false;
        }

        $pool = Pool::where('environment_id', $this->environment->id)->where('name', $poolName)->first();

        $runner = Runner::create([
            'environment_id' => $this->environment->id,
            'proxmox_target_id' => $this->target->id,
            'pool_id' => $pool?->id,
            'vmid' => $vmid,
            'runner_name' => $runnerName,
            'state' => RunnerState::Idle,
            'state_changed_at' => now(),
        ]);

        $runner->events()->create([
            'to_state' => RunnerState::Idle->value,
            'reason' => 'adopted during reconciliation',
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Why this runner should be destroyed, or null to leave it alone.
     */
    private function destructionReason(Runner $runner, ?GitHubRunner $githubRunner): ?string
    {
        if ($runner->state === RunnerState::Failed && $this->environment->keep_failed_vms) {
            return null;
        }

        if ($runner->state === RunnerState::Reaping) {
            return 'job completed';
        }

        if ($runner->ageSeconds() > $this->environment->max_lifetime_seconds) {
            return 'exceeded maximum lifetime';
        }

        if ($runner->state === RunnerState::Spawning) {
            $timeout = ($runner->pool?->boot_timeout_seconds ?? $this->environment->idle_timeout_seconds) * 2;

            return $runner->secondsInState() > $timeout ? 'stuck while spawning' : null;
        }

        if ($this->isFromSupersededTemplate($runner)) {
            return 'template superseded by a rebuild';
        }

        if ($githubRunner === null) {
            // Runners that consumed their job deregister themselves, so absence means finished.
            return $runner->ageSeconds() > self::REGISTRATION_GRACE_SECONDS
                ? 'runner is no longer registered with GitHub'
                : null;
        }

        if ($githubRunner->busy) {
            if ($runner->state !== RunnerState::Busy) {
                $runner->transitionTo(RunnerState::Busy, 'GitHub reports the runner is busy');
            }

            return null;
        }

        if (! $githubRunner->isOnline()) {
            return 'runner went offline';
        }

        if ($runner->state === RunnerState::Idle && $runner->secondsInState() > $this->environment->idle_timeout_seconds) {
            return $this->wouldBreachWarmPool($runner) ? null : 'idle for too long';
        }

        return null;
    }

    /**
     * Whether destroying this idle runner would drop its pool below the node's warm pool minimum.
     */
    private function wouldBreachWarmPool(Runner $runner): bool
    {
        $pool = $runner->pool;

        if ($pool === null || $runner->proxmoxTarget === null) {
            return false;
        }

        $pool->loadMissing('proxmoxTargets');

        return $pool->idleAndSpawningRunnerCountOn($runner->proxmoxTarget) <= $pool->minIdleRunnersOn($runner->proxmoxTarget);
    }

    /**
     * Whether this runner was cloned from a template the node has since rebuilt.
     *
     * Busy runners are left to finish their job; the webhook moves them on when it completes.
     */
    private function isFromSupersededTemplate(Runner $runner): bool
    {
        if ($runner->state === RunnerState::Busy || $runner->source_template_vmid === null) {
            return false;
        }

        $current = $runner->pool?->runnerTemplate
            ?->targetMappings()
            ->whereKey($this->target->id)
            ->value('runner_template_target.template_vmid');

        return $current !== null && (int) $current !== (int) $runner->source_template_vmid;
    }

    /**
     * Remove GitHub registrations we created but have no VM for.
     *
     * @param  array<string, GitHubRunner>  $runners
     */
    private function deleteOrphanedRegistrations(array $runners): void
    {
        $tracked = $this->trackedRunners()->pluck('runner_name')->flip();

        foreach ($runners as $name => $runner) {
            if (! str_starts_with($name, 'gha-') || $runner->isOnline() || isset($tracked[$name])) {
                continue;
            }

            try {
                $this->github->deleteRunner($runner->id);

                Log::info('Removed an orphaned GitHub runner registration', ['runner' => $name]);
            } catch (Throwable $e) {
                Log::warning('Could not remove an orphaned registration', ['runner' => $name, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $vm
     */
    private function isManaged(array $vm): bool
    {
        // Template VMs carry the same gha- prefix as runners but must never be adopted or destroyed.
        if (! empty($vm['template'])) {
            return false;
        }

        $tags = explode(';', (string) ($vm['tags'] ?? ''));

        // The name prefix is the fallback for clusters where the token may not write tags.
        return in_array(ProxmoxClient::MANAGED_TAG, $tags, true)
            || str_starts_with((string) ($vm['name'] ?? ''), 'gha-');
    }

    private function trackedRunners()
    {
        return Runner::forEnvironment($this->environment)
            ->where('proxmox_target_id', $this->target->id)
            ->whereNot('state', RunnerState::Destroyed->value);
    }
}
