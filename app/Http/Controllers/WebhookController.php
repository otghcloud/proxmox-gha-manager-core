<?php

namespace App\Http\Controllers;

use App\Enums\RunnerState;
use App\Jobs\ProvisionRunnerJob;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\ProxmoxTarget;
use App\Models\Runner;
use App\Models\WebhookDelivery;
use App\Services\GitHub\WorkflowJobRecorder;
use App\Services\Provisioning\TargetSelector;
use App\Services\WebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookSignature $signature,
        private readonly WorkflowJobRecorder $jobs,
    ) {}

    public function handle(Request $request, string $webhookId): JsonResponse
    {
        $account = GitHubAccount::where('webhook_id', $webhookId)->first();

        if ($account === null) {
            return response()->json(['status' => 'unknown GitHub account'], 404);
        }

        $payload = $request->getContent();

        if (! $this->signature->verify($account, $payload, $request->header(WebhookSignature::header()))) {
            $this->record($account, $request, null, false, 'invalid signature');

            Log::warning('Rejected webhook with an invalid signature', [
                'account' => $account->login,
                'delivery' => $request->header('X-GitHub-Delivery'),
            ]);

            return response()->json(['status' => 'invalid signature'], 401);
        }

        $body = json_decode($payload, true);

        if (! is_array($body)) {
            $this->record($account, $request, null, true, 'malformed payload');

            return response()->json(['status' => 'malformed payload'], 400);
        }

        $event = (string) $request->header('X-GitHub-Event');

        $result = match ($event) {
            'ping' => 'pong',
            'workflow_job' => $this->workflowJob($account, $body),
            default => 'ignored',
        };

        $this->record($account, $request, $body, true, $result);

        return response()->json(['status' => $result], $result === 'queued' ? 202 : 200);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function workflowJob(GitHubAccount $account, array $body): string
    {
        $action = (string) ($body['action'] ?? '');
        $job = is_array($body['workflow_job'] ?? null) ? $body['workflow_job'] : [];

        return match ($action) {
            'queued' => $this->jobQueued($account, $job, $body),
            'in_progress' => $this->jobStateChange($account, $job, $body, RunnerState::Busy, 'busy'),
            'completed' => $this->jobStateChange($account, $job, $body, RunnerState::Reaping, 'reaping'),
            default => 'ignored',
        };
    }

    /**
     * @param  array<string, mixed>  $job
     * @param  array<string, mixed>  $body
     */
    private function jobQueued(GitHubAccount $account, array $job, array $body): string
    {
        $jobId = $job['id'] ?? null;

        if (! is_numeric($jobId)) {
            return 'ignored';
        }

        $labels = array_values(array_filter((array) ($job['labels'] ?? []), 'is_string'));
        $environments = $account->environments()->where('enabled', true)->get();
        $matches = $environments->filter(fn (Environment $environment): bool => $environment->poolForLabels($labels) !== null);

        if ($matches->count() !== 1) {
            return $matches->isEmpty() ? 'no matching pool' : 'ambiguous environment';
        }

        $environment = $matches->first();
        $pool = $environment->poolForLabels($labels);

        if ($pool === null) {
            return 'no matching pool';
        }

        // Recorded before provisioning so jobs we could not serve still appear in the history.
        $this->jobs->record($environment, $job, $body);

        $alreadyKnown = Runner::forEnvironment($environment)
            ->where('workflow_job_id', (int) $jobId)
            ->whereNot('state', RunnerState::Destroyed->value)
            ->exists();

        if ($alreadyKnown || ! $this->claimJob($account, (int) $jobId)) {
            return 'duplicate';
        }

        // Check if we have any eligible targets before queuing the job
        $targetStatus = $this->checkTargetAvailability($environment, $pool);
        if ($targetStatus !== null) {
            return $targetStatus;
        }

        ProvisionRunnerJob::dispatch(
            environmentId: $environment->id,
            poolId: $pool->id,
            workflowJobId: (int) $jobId,
            repositoryFullName: is_string($body['repository']['full_name'] ?? null) ? $body['repository']['full_name'] : null,
        );

        return 'queued';
    }

    /**
     * Check if a target and template mapping are available for provisioning.
     * Returns a status string if not available, or null if at least one target is viable.
     */
    private function checkTargetAvailability(Environment $environment, mixed $pool): ?string
    {
        $template = $pool->runnerTemplate;
        $target = (new TargetSelector)->selectFor($pool->labels, $template, $pool);

        if ($target === null) {
            if ($pool->proxmoxTargets()->count() === 0) {
                return 'pool has no nodes assigned';
            }
            // No healthy, non-drained targets with capacity
            $allTargets = ProxmoxTarget::where('enabled', true)->count();
            $healthyTargets = ProxmoxTarget::where('enabled', true)->where('health_status', 'healthy')->count();

            if ($allTargets === 0) {
                return 'no configured targets';
            }

            if ($healthyTargets === 0) {
                return 'all targets unhealthy';
            }

            // Check if template is missing on all targets
            $targetsWithMapping = ProxmoxTarget::where('enabled', true)
                ->where('health_status', 'healthy')
                ->whereNull('drained_at')
                ->whereHas('runnerTemplates', fn ($q) => $q
                    ->whereKey($template->getKey())
                    ->whereNotNull('runner_template_target.template_vmid'))
                ->count();

            if ($targetsWithMapping === 0) {
                return 'template not mapped on any healthy target';
            }

            // No capacity on any eligible target
            return 'no capacity on any target';
        }

        return null;
    }

    /**
     * Claim a job id for a short window so redelivered webhooks do not double-provision
     * before the runner record exists.
     */
    private function claimJob(GitHubAccount $account, int $jobId): bool
    {
        return Cache::add("webhook-job:{$account->id}:{$jobId}", true, now()->addMinutes(15));
    }

    /**
     * @param  array<string, mixed>  $job
     * @param  array<string, mixed>  $body
     */
    private function jobStateChange(GitHubAccount $account, array $job, array $body, RunnerState $state, string $result): string
    {
        $runnerName = $job['runner_name'] ?? null;

        if (! is_string($runnerName) || $runnerName === '') {
            return 'not ours';
        }

        $runner = Runner::whereHas('environment', fn ($query) => $query->where('github_account_id', $account->id))
            ->where('runner_name', $runnerName)->first();

        if ($runner === null) {
            return 'not ours';
        }

        $record = $this->jobs->record($runner->environment, $job, $body, $runner);

        // Warm runners are spawned without a job, so this is where they gain one.
        if ($record !== null) {
            $this->jobs->assignRunner($record, $runner);
        }

        $runner->transitionTo($state, 'workflow_job webhook');

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function record(GitHubAccount $account, Request $request, ?array $payload, bool $signatureValid, string $result): void
    {
        WebhookDelivery::updateOrCreate(
            ['github_delivery_id' => $request->header('X-GitHub-Delivery')],
            [
                'github_account_id' => $account->id,
                'event' => $request->header('X-GitHub-Event'),
                'action' => is_array($payload) ? ($payload['action'] ?? null) : null,
                'signature_valid' => $signatureValid,
                'result' => $result,
                'payload' => $payload,
                'created_at' => now(),
            ]
        );
    }
}
