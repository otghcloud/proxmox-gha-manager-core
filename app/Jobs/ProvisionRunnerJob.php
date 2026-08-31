<?php

namespace App\Jobs;

use App\Exceptions\CapacityException;
use App\Exceptions\JobNotClaimedException;
use App\Models\Environment;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Services\Provisioning\EnvironmentServices;
use App\Services\SettingsRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProvisionRunnerJob implements ShouldQueue
{
    use Queueable;

    /** Retry a transient provisioning failure a small, bounded number of times. */
    public int $tries = 3;

    /** A whole spawn - clone, boot, register - can legitimately take several minutes. */
    public int $timeout = 900;

    private const RETRY_SECONDS = 30;

    private const DEFERRAL_SECONDS = 15;

    private const MAX_DEFERRALS = 60;

    public function __construct(
        public readonly int $environmentId,
        public readonly int $poolId,
        public readonly ?int $workflowJobId = null,
        public readonly ?string $repositoryFullName = null,
        public readonly int $deferrals = 0,
        public readonly ?int $proxmoxTargetId = null,
    ) {
        $this->onQueue('provision');
    }

    public function handle(EnvironmentServices $services, SettingsRepository $settings): void
    {
        if (! $settings->bool(SettingsRepository::AUTO_SPAWN_ENABLED)) {
            Log::info('Auto spawning is disabled; dropping provisioning job', [
                'pool' => $this->poolId,
                'job' => $this->workflowJobId,
            ]);

            return;
        }

        $environment = Environment::find($this->environmentId);
        $pool = Pool::find($this->poolId);

        if ($environment === null || $pool === null || ! $environment->enabled || ! $pool->enabled) {
            return;
        }

        try {
            $target = $this->proxmoxTargetId === null ? null : ProxmoxTarget::find($this->proxmoxTargetId);

            $services->provisioner($environment)->spawn($pool, $this->workflowJobId, $this->repositoryFullName, $target);
        } catch (CapacityException $e) {
            $this->defer($e->getMessage());
        } catch (JobNotClaimedException $e) {
            // The runner stays up; only this job's association was released, so try again fresh.
            Log::warning('Job was not claimed, retrying with a new VM', [
                'job' => $this->workflowJobId,
                'reason' => $e->getMessage(),
            ]);

            $this->release(self::RETRY_SECONDS);
        }
    }

    /**
     * Capacity is expected to free up, so deferral is not a failure and does not burn an attempt.
     */
    private function defer(string $reason): void
    {
        if ($this->deferrals >= self::MAX_DEFERRALS) {
            Log::error('Giving up waiting for capacity', [
                'job' => $this->workflowJobId,
                'pool' => $this->poolId,
                'reason' => $reason,
            ]);

            return;
        }

        Log::info('Deferring runner provisioning', [
            'job' => $this->workflowJobId,
            'deferral' => $this->deferrals + 1,
            'reason' => $reason,
        ]);

        self::dispatch(
            $this->environmentId,
            $this->poolId,
            $this->workflowJobId,
            $this->repositoryFullName,
            $this->deferrals + 1,
            $this->proxmoxTargetId,
        )->delay(now()->addSeconds(self::DEFERRAL_SECONDS));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [self::RETRY_SECONDS, self::RETRY_SECONDS];
    }
}
