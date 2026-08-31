<?php

namespace Tests\Feature;

use App\Enums\JobConclusion;
use App\Enums\RunnerState;
use App\Enums\SpawnReason;
use App\Jobs\FetchWorkflowJobLogJob;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\Runner;
use App\Models\RunnerTemplate;
use App\Models\User;
use App\Models\WorkflowJob;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WorkflowJobTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-secret';

    private GitHubAccount $account;

    private Environment $environment;

    private Pool $pool;

    private ProxmoxTarget $target;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());

        $this->account = GitHubAccount::create([
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'github_token' => 'ghp_example',
            'github_webhook_secret' => self::SECRET,
            'linux_ssh_password' => 'password',
        ]);

        $this->environment = Environment::create([
            'name' => 'Test',
            'slug' => 'test',
            'github_account_id' => $this->account->id,
        ]);

        $this->target = ProxmoxTarget::create([
            'name' => 'pve01',
            'slug' => 'pve01',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'user@pve!token',
            'proxmox_token_secret' => 'proxmox-secret',
        ]);

        $template = RunnerTemplate::create([
            'environment_id' => $this->environment->id,
            'name' => 'ubuntu2404',
            'os' => 'linux',
        ]);

        $template->targetMappings()->attach($this->target->id, [
            'template_vmid' => 801,
            'availability_status' => 'available',
        ]);

        $this->pool = Pool::create([
            'environment_id' => $this->environment->id,
            'runner_template_id' => $template->id,
            'name' => 'ubuntu2404',
            'labels' => ['self-hosted', 'linux', 'x64', 'ubuntu-24.04'],
            'cores' => 4,
            'memory' => 8192,
            'boot_timeout_seconds' => 180,
        ]);

        $this->pool->proxmoxTargets()->sync([
            $this->target->id => ['min_idle_runners' => 0, 'max_concurrent' => 4],
        ]);
    }

    public function test_a_warm_runner_records_the_job_it_picks_up(): void
    {
        Queue::fake();

        $runner = $this->makeRunner('gha-ubuntu2404-9000-warm', RunnerState::Idle, SpawnReason::Warm);

        $this->deliver('workflow_job', $this->jobPayload('in_progress', [
            'runner_name' => $runner->runner_name,
            'started_at' => '2026-08-29T21:24:00Z',
        ]))->assertJson(['status' => 'busy']);

        $job = WorkflowJob::firstOrFail();

        $this->assertSame(4242, $job->github_job_id);
        $this->assertSame($runner->id, $job->runner_id);
        $this->assertSame('Build', $job->job_name);
        $this->assertSame('otghcloud/demo', $job->repository_full_name);

        $runner->refresh();

        // The spawn reason stays warm; only the job it served is recorded.
        $this->assertSame(SpawnReason::Warm, $runner->spawn_reason);
        $this->assertSame(4242, $runner->workflow_job_id);
        $this->assertTrue($job->is($runner->servedJob));
    }

    public function test_a_redundant_on_demand_runner_releases_the_job_it_lost(): void
    {
        Queue::fake();

        $onDemand = $this->makeRunner('gha-ubuntu2404-9001-ondemand', RunnerState::Spawning, SpawnReason::Job, 4242);
        $warm = $this->makeRunner('gha-ubuntu2404-9002-warm', RunnerState::Idle, SpawnReason::Warm);

        $this->deliver('workflow_job', $this->jobPayload('in_progress', [
            'runner_name' => $warm->runner_name,
        ]))->assertJson(['status' => 'busy']);

        $this->assertNull($onDemand->fresh()->workflow_job_id);
        $this->assertSame(4242, $warm->fresh()->workflow_job_id);
        $this->assertNotSame(RunnerState::Destroyed, $onDemand->fresh()->state);
    }

    public function test_completion_stores_steps_and_queues_the_log_fetch(): void
    {
        Queue::fake();

        $runner = $this->makeRunner('gha-ubuntu2404-9003-warm', RunnerState::Busy, SpawnReason::Warm);

        $this->deliver('workflow_job', $this->jobPayload('completed', [
            'runner_name' => $runner->runner_name,
            'conclusion' => 'success',
            'started_at' => '2026-08-29T21:24:00Z',
            'completed_at' => '2026-08-29T21:29:00Z',
            'steps' => [
                ['name' => 'Set up job', 'status' => 'completed', 'conclusion' => 'success', 'number' => 1],
                ['name' => 'Run tests', 'status' => 'completed', 'conclusion' => 'failure', 'number' => 2],
            ],
        ]))->assertJson(['status' => 'reaping']);

        $job = WorkflowJob::firstOrFail();

        $this->assertSame(JobConclusion::Success, $job->conclusion);
        $this->assertCount(2, $job->steps);
        $this->assertSame(300, $job->durationSeconds());

        Queue::assertPushed(FetchWorkflowJobLogJob::class);
    }

    public function test_the_jobs_page_lists_and_shows_a_job(): void
    {
        $this->actingAs(User::factory()->create());

        $job = WorkflowJob::create([
            'environment_id' => $this->environment->id,
            'github_job_id' => 99165049223,
            'github_run_id' => 123,
            'repository_full_name' => 'otghcloud/demo',
            'job_name' => 'Build',
            'status' => 'completed',
            'conclusion' => 'success',
        ]);

        $this->get(route('jobs.index'))->assertOk();
        $this->get(route('jobs.show', $job))->assertOk()->assertSee('Build');
    }

    public function test_a_missing_log_is_a_404(): void
    {
        $this->actingAs(User::factory()->create());

        $job = WorkflowJob::create([
            'environment_id' => $this->environment->id,
            'github_job_id' => 5150,
            'repository_full_name' => 'otghcloud/demo',
            'job_name' => 'Build',
            'status' => 'completed',
        ]);

        $this->get(route('jobs.log', $job))->assertNotFound();
    }

    private function makeRunner(string $name, RunnerState $state, SpawnReason $reason, ?int $jobId = null): Runner
    {
        return Runner::create([
            'environment_id' => $this->environment->id,
            'proxmox_target_id' => $this->target->id,
            'pool_id' => $this->pool->id,
            'vmid' => crc32($name) % 1000 + 9000,
            'runner_name' => $name,
            'spawn_reason' => $reason,
            'workflow_job_id' => $jobId,
            'state' => $state,
            'state_changed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function jobPayload(string $action, array $overrides = []): array
    {
        return [
            'action' => $action,
            'workflow_job' => array_merge([
                'id' => 4242,
                'run_id' => 777,
                'run_attempt' => 1,
                'name' => 'Build',
                'workflow_name' => 'CI',
                'head_branch' => 'main',
                'head_sha' => 'abc1234def5678',
                'status' => $action === 'completed' ? 'completed' : 'in_progress',
                'labels' => ['self-hosted', 'linux', 'x64', 'ubuntu-24.04'],
                'html_url' => 'https://github.com/otghcloud/demo/actions/runs/777/job/4242',
            ], $overrides),
            'repository' => ['full_name' => 'otghcloud/demo'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deliver(string $event, array $payload, string $delivery = 'delivery-1'): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call(
            'POST',
            '/webhook/'.$this->account->webhook_id,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_GITHUB_EVENT' => $event,
                'HTTP_X_GITHUB_DELIVERY' => $delivery,
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, self::SECRET),
            ],
            content: $body,
        );
    }
}
