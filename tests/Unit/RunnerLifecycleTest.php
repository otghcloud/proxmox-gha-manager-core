<?php

namespace Tests\Unit;

use App\Enums\PoolOs;
use App\Enums\RunnerState;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\Runner;
use App\Models\RunnerTemplate;
use App\Services\SettingsRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunnerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private ProxmoxTarget $target;

    private GitHubAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = GitHubAccount::create([
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'github_token' => 'ghp_example',
            'github_webhook_secret' => 'webhook-secret',
            'linux_ssh_password' => 'ssh-secret',
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
    }

    public function test_secrets_are_encrypted_at_rest(): void
    {
        $stored = \DB::table('github_accounts')->where('id', $this->account->id)->value('github_webhook_secret');

        $this->assertNotSame('webhook-secret', $stored);
        $this->assertSame('webhook-secret', $this->account->fresh()->github_webhook_secret);
    }

    public function test_secrets_are_never_serialised(): void
    {
        $serialised = $this->environment->toArray();

        foreach (['github_token', 'github_webhook_secret', 'linux_ssh_password', 'windows_password'] as $secret) {
            $this->assertArrayNotHasKey($secret, $this->account->toArray(), "{$secret} must not appear in a JSON payload");
        }

        // Direct access must still decrypt, since the API clients rely on it.
        $this->assertSame('webhook-secret', $this->account->github_webhook_secret);
    }

    public function test_webhook_url_uses_the_saved_external_app_url(): void
    {
        app(SettingsRepository::class)->set('app_url', 'https://github-runner-wh.otgh.cloud/');

        $this->assertSame(
            'https://github-runner-wh.otgh.cloud/webhook/'.$this->account->webhook_id,
            $this->environment->webhook_url,
        );
    }

    public function test_a_live_vmid_cannot_be_reused(): void
    {
        $this->makeRunner(9000, 'gha-a', RunnerState::Idle);

        $this->expectException(QueryException::class);
        $this->makeRunner(9000, 'gha-b', RunnerState::Spawning);
    }

    public function test_a_destroyed_runner_frees_its_vmid_and_job(): void
    {
        $this->makeRunner(9000, 'gha-a', RunnerState::Destroyed, 4242);

        $reused = $this->makeRunner(9000, 'gha-b', RunnerState::Spawning, 4242);

        $this->assertTrue($reused->exists);
    }

    public function test_a_live_job_cannot_be_provisioned_twice(): void
    {
        $this->makeRunner(9000, 'gha-a', RunnerState::Idle, 4242);

        $this->expectException(QueryException::class);
        $this->makeRunner(9001, 'gha-b', RunnerState::Spawning, 4242);
    }

    public function test_transitions_are_recorded(): void
    {
        $runner = $this->makeRunner(9000, 'gha-a', RunnerState::Spawning);

        $runner->transitionTo(RunnerState::Idle, 'provisioned');
        $runner->transitionTo(RunnerState::Busy, 'job started');

        $this->assertSame(RunnerState::Busy, $runner->fresh()->state);
        $this->assertSame(
            ['spawning->idle', 'idle->busy'],
            $runner->events()->orderBy('id')->get()->map(fn ($e): string => "{$e->from_state}->{$e->to_state}")->all()
        );
    }

    public function test_only_spawning_idle_and_busy_count_as_active(): void
    {
        $this->assertSame(['spawning', 'idle', 'busy'], RunnerState::activeValues());
    }

    public function test_a_pool_matches_when_the_requested_labels_are_a_subset(): void
    {
        $pool = $this->makePool(['self-hosted', 'linux', 'x64', 'ubuntu-24.04', 'ubuntu-latest']);

        $this->assertTrue($this->environment->poolForLabels(['self-hosted', 'ubuntu-24.04'])?->is($pool));
        $this->assertNull($this->environment->poolForLabels(['self-hosted', 'windows']));
        $this->assertNull($this->environment->poolForLabels([]));
    }

    public function test_a_disabled_pool_never_matches(): void
    {
        $this->makePool(['self-hosted', 'linux'])->update(['enabled' => false]);

        $this->assertNull($this->environment->poolForLabels(['self-hosted', 'linux']));
    }

    public function test_the_runner_directory_falls_back_to_the_os_default(): void
    {
        $pool = $this->makePool(['self-hosted', 'linux']);

        $this->assertSame('/opt/actions-runner', $pool->runnerDirectory());
        $this->assertSame('C:\\actions-runner', PoolOs::Windows->defaultRunnerDir());
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function makePool(array $labels): Pool
    {
        $template = RunnerTemplate::create([
            'environment_id' => $this->environment->id,
            'name' => 'ubuntu2404-'.count($labels),
            'vmid' => 100 + count($labels),
            'os' => 'linux',
        ]);

        $pool = Pool::create([
            'environment_id' => $this->environment->id,
            'runner_template_id' => $template->id,
            'name' => 'pool-'.count($labels),
            'labels' => $labels,
            'cores' => 4,
            'memory' => 8192,
            'boot_timeout_seconds' => 180,
        ]);

        $pool->proxmoxTargets()->sync([
            $this->target->id => ['min_idle_runners' => 0, 'max_concurrent' => 4],
        ]);

        return $pool;
    }

    private function makeRunner(int $vmid, string $name, RunnerState $state, ?int $jobId = null): Runner
    {
        return Runner::create([
            'environment_id' => $this->environment->id,
            'proxmox_target_id' => $this->target->id,
            'vmid' => $vmid,
            'runner_name' => $name,
            'workflow_job_id' => $jobId,
            'state' => $state,
            'state_changed_at' => now(),
        ]);
    }
}
