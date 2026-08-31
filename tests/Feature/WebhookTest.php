<?php

namespace Tests\Feature;

use App\Enums\RunnerState;
use App\Jobs\ProvisionRunnerJob;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\Runner;
use App\Models\RunnerTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-secret';

    private Environment $environment;

    private Pool $pool;

    private ProxmoxTarget $target;

    private GitHubAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_a_valid_delivery_queues_provisioning(): void
    {
        Queue::fake();

        $this->deliver('workflow_job', $this->queuedJob())
            ->assertStatus(202)
            ->assertJson(['status' => 'queued']);

        Queue::assertPushed(ProvisionRunnerJob::class);
    }

    public function test_a_tampered_signature_is_rejected_before_the_payload_is_read(): void
    {
        Queue::fake();

        $this->postJson('/webhook/'.$this->account->webhook_id, $this->queuedJob(), [
            'X-GitHub-Event' => 'workflow_job',
            'X-GitHub-Delivery' => 'delivery-1',
            'X-Hub-Signature-256' => 'sha256=deadbeef',
        ])->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        $this->postJson('/webhook/'.$this->account->webhook_id, $this->queuedJob(), [
            'X-GitHub-Event' => 'workflow_job',
        ])->assertStatus(401);
    }

    public function test_a_ping_is_answered(): void
    {
        $this->deliver('ping', ['zen' => 'Keep it simple.'])
            ->assertOk()
            ->assertJson(['status' => 'pong']);
    }

    public function test_labels_that_match_no_pool_are_ignored(): void
    {
        Queue::fake();

        $payload = $this->queuedJob();
        $payload['workflow_job']['labels'] = ['self-hosted', 'windows'];

        $this->deliver('workflow_job', $payload)->assertJson(['status' => 'no matching pool']);

        Queue::assertNothingPushed();
    }

    public function test_hosted_jobs_are_ignored_even_when_their_labels_overlap_a_pool(): void
    {
        Queue::fake();

        $this->pool->update(['labels' => ['self-hosted', 'linux', 'x64', 'ubuntu-24.04', 'ubuntu-latest']]);

        $payload = $this->queuedJob();
        $payload['workflow_job']['labels'] = ['ubuntu-latest'];

        $this->deliver('workflow_job', $payload)->assertJson(['status' => 'no matching pool']);

        Queue::assertNothingPushed();
    }

    public function test_self_hosted_only_jobs_are_ignored(): void
    {
        Queue::fake();

        $payload = $this->queuedJob();
        $payload['workflow_job']['labels'] = ['self-hosted'];

        $this->deliver('workflow_job', $payload)->assertJson(['status' => 'no matching pool']);

        Queue::assertNothingPushed();
    }

    public function test_a_redelivered_job_is_not_provisioned_twice(): void
    {
        Queue::fake();

        $this->deliver('workflow_job', $this->queuedJob(), 'delivery-a')->assertStatus(202);
        $this->deliver('workflow_job', $this->queuedJob(), 'delivery-b')->assertJson(['status' => 'duplicate']);

        Queue::assertPushed(ProvisionRunnerJob::class, 1);
    }

    public function test_a_completed_job_moves_its_runner_to_reaping(): void
    {
        $runner = Runner::create([
            'environment_id' => $this->environment->id,
            'proxmox_target_id' => $this->target->id,
            'pool_id' => $this->pool->id,
            'vmid' => 9000,
            'runner_name' => 'gha-ubuntu2404-9000-abcd1234',
            'state' => RunnerState::Busy,
            'state_changed_at' => now(),
        ]);

        $this->deliver('workflow_job', [
            'action' => 'completed',
            'workflow_job' => ['id' => 4242, 'runner_name' => $runner->runner_name],
        ])->assertJson(['status' => 'reaping']);

        $this->assertSame(RunnerState::Reaping, $runner->fresh()->state);
    }

    public function test_an_unknown_environment_is_a_404(): void
    {
        $this->postJson('/webhook/'.fake()->uuid(), [])->assertStatus(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function queuedJob(): array
    {
        return [
            'action' => 'queued',
            'workflow_job' => [
                'id' => 4242,
                'labels' => ['self-hosted', 'linux', 'x64', 'ubuntu-24.04'],
            ],
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
