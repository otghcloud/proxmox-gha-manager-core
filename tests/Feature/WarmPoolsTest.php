<?php

namespace Tests\Feature;

use App\Enums\PoolOs;
use App\Enums\RunnerState;
use App\Jobs\ProvisionRunnerJob;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\Runner;
use App\Models\RunnerTemplate;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WarmPoolsTest extends TestCase
{
    use RefreshDatabase;

    private GitHubAccount $account;

    private Environment $environment;

    private ProxmoxTarget $target;

    private RunnerTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());
        $this->actingAs(User::factory()->create());

        $this->account = GitHubAccount::create([
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'github_token' => 'ghp_example',
            'github_webhook_secret' => 'webhook-secret',
        ]);

        $this->environment = Environment::create([
            'name' => 'Production',
            'slug' => 'production',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'user@pve!token',
            'proxmox_token_secret' => 'proxmox-secret',
            'github_account_id' => $this->account->id,
        ]);

        $this->template = RunnerTemplate::create([
            'environment_id' => $this->environment->id,
            'name' => 'ubuntu2404',
            'vmid' => 100,
            'os' => PoolOs::Linux,
        ]);

        $this->target = $this->makeTarget('PVE 01', 'pve-01', 801);
    }

    public function test_pool_stores_limits_per_node(): void
    {
        $this->post(route('pools.store'), $this->formPayload('ubuntu2404-warm', [
            $this->target->id => ['enabled' => '1', 'min_idle_runners' => 2, 'max_concurrent' => 4],
        ]))->assertRedirect();

        $pool = Pool::where('name', 'ubuntu2404-warm')->firstOrFail();

        $this->assertSame(2, $pool->minIdleRunnersOn($this->target));
        $this->assertSame(4, $pool->maxConcurrentOn($this->target));

        $this->put(route('pools.update', $pool), $this->formPayload('ubuntu2404-warm', [
            $this->target->id => ['enabled' => '1', 'min_idle_runners' => 3, 'max_concurrent' => 4],
        ]))->assertRedirect();

        $this->assertSame(3, $pool->fresh()->minIdleRunnersOn($this->target));
    }

    public function test_pool_totals_are_the_sum_of_its_nodes(): void
    {
        $other = $this->makeTarget('PVE 02', 'pve-02', 802);

        $pool = $this->makePool('totals', [
            $this->target->id => ['min_idle_runners' => 2, 'max_concurrent' => 6],
            $other->id => ['min_idle_runners' => 4, 'max_concurrent' => 6],
        ]);

        $this->assertSame(6, $pool->totalMinIdleRunners());
        $this->assertSame(12, $pool->totalMaxConcurrent());
    }

    public function test_min_idle_runners_cannot_exceed_max_concurrent_on_a_node(): void
    {
        $this->post(route('pools.store'), $this->formPayload('invalid-warm', [
            $this->target->id => ['enabled' => '1', 'min_idle_runners' => 4, 'max_concurrent' => 2],
        ]))->assertSessionHasErrors(["nodes.{$this->target->id}.min_idle_runners"]);
    }

    public function test_a_pool_must_have_at_least_one_node(): void
    {
        $this->post(route('pools.store'), $this->formPayload('no-nodes', [
            $this->target->id => ['min_idle_runners' => 2, 'max_concurrent' => 4],
        ]))->assertSessionHasErrors(['nodes']);
    }

    public function test_warm_pools_command_dispatches_provision_runner_jobs(): void
    {
        Queue::fake();

        $pool = $this->makePool('warm-pool', [
            $this->target->id => ['min_idle_runners' => 2, 'max_concurrent' => 4],
        ]);

        $this->artisan('runners:warm-pools')->assertExitCode(0);

        Queue::assertPushed(ProvisionRunnerJob::class, 2);
        Queue::assertPushed(ProvisionRunnerJob::class, function (ProvisionRunnerJob $job) use ($pool) {
            return $job->environmentId === $this->environment->id && $job->poolId === $pool->id;
        });
    }

    public function test_each_node_is_topped_up_to_its_own_minimum(): void
    {
        Queue::fake();

        $other = $this->makeTarget('PVE 02', 'pve-02', 802);

        $this->makePool('warm-pool-per-node', [
            $this->target->id => ['min_idle_runners' => 2, 'max_concurrent' => 6],
            $other->id => ['min_idle_runners' => 3, 'max_concurrent' => 6],
        ]);

        $this->artisan('runners:warm-pools')->assertExitCode(0);

        Queue::assertPushed(ProvisionRunnerJob::class, 5);
        Queue::assertPushed(ProvisionRunnerJob::class, fn (ProvisionRunnerJob $job): bool => $job->proxmoxTargetId === $this->target->id);
        Queue::assertPushed(ProvisionRunnerJob::class, fn (ProvisionRunnerJob $job): bool => $job->proxmoxTargetId === $other->id);
    }

    public function test_a_node_is_never_topped_up_past_its_own_max_concurrent(): void
    {
        Queue::fake();

        $this->makePool('warm-pool-node-cap', [
            $this->target->id => ['min_idle_runners' => 4, 'max_concurrent' => 1],
        ]);

        $this->artisan('runners:warm-pools')->assertExitCode(0);

        Queue::assertPushed(ProvisionRunnerJob::class, 1);
    }

    public function test_a_node_the_pool_does_not_run_on_is_never_used(): void
    {
        Queue::fake();

        $other = $this->makeTarget('PVE 02', 'pve-02', 802);

        $this->makePool('warm-pool-single-node', [
            $this->target->id => ['min_idle_runners' => 2, 'max_concurrent' => 4],
        ]);

        $this->artisan('runners:warm-pools')->assertExitCode(0);

        Queue::assertPushed(ProvisionRunnerJob::class, 2);
        Queue::assertNotPushed(ProvisionRunnerJob::class, fn (ProvisionRunnerJob $job): bool => $job->proxmoxTargetId === $other->id);
    }

    public function test_warm_pools_command_does_nothing_when_auto_spawning_is_disabled(): void
    {
        Queue::fake();

        $this->makePool('warm-pool-disabled', [
            $this->target->id => ['min_idle_runners' => 2, 'max_concurrent' => 4],
        ]);

        app(SettingsRepository::class)->set(SettingsRepository::AUTO_SPAWN_ENABLED, '0');

        $this->artisan('runners:warm-pools')->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_warm_pools_command_accounts_for_existing_idle_and_spawning_runners(): void
    {
        Queue::fake();

        $pool = $this->makePool('warm-pool-existing', [
            $this->target->id => ['min_idle_runners' => 3, 'max_concurrent' => 4],
        ]);

        $this->makeRunner($pool, 901, RunnerState::Idle);

        $this->artisan('runners:warm-pools')->assertExitCode(0);

        // Needed 3, 1 existing idle -> dispatches 2 jobs
        Queue::assertPushed(ProvisionRunnerJob::class, 2);
    }

    public function test_warm_pools_command_respects_max_concurrent_capacity_limit(): void
    {
        Queue::fake();

        $pool = $this->makePool('warm-pool-constrained', [
            $this->target->id => ['min_idle_runners' => 3, 'max_concurrent' => 3],
        ]);

        $this->makeRunner($pool, 901, RunnerState::Busy);
        $this->makeRunner($pool, 902, RunnerState::Busy);

        $this->artisan('runners:warm-pools')->assertExitCode(0);

        // Max 3 on this node, 2 busy -> only 1 job despite needing 3
        Queue::assertPushed(ProvisionRunnerJob::class, 1);
    }

    public function test_running_the_command_twice_does_not_over_dispatch(): void
    {
        Queue::fake();

        $pool = $this->makePool('warm-pool-idempotent', [
            $this->target->id => ['min_idle_runners' => 2, 'max_concurrent' => 4],
        ]);

        $this->artisan('runners:warm-pools')->assertExitCode(0);

        // The first pass' jobs have not run yet; once their runners exist a second pass must be a no-op.
        $this->makeRunner($pool, 901, RunnerState::Spawning);
        $this->makeRunner($pool, 902, RunnerState::Spawning);

        $this->artisan('runners:warm-pools')->assertExitCode(0);

        Queue::assertPushed(ProvisionRunnerJob::class, 2);
    }

    public function test_warm_pools_command_skips_when_target_is_unhealthy(): void
    {
        Queue::fake();

        $this->target->update(['health_status' => 'unhealthy']);

        $this->makePool('warm-pool-unhealthy', [
            $this->target->id => ['min_idle_runners' => 2, 'max_concurrent' => 4],
        ]);

        $this->artisan('runners:warm-pools')->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    private function makeTarget(string $name, string $slug, int $templateVmid): ProxmoxTarget
    {
        $target = ProxmoxTarget::create([
            'name' => $name,
            'slug' => $slug,
            'proxmox_url' => "https://{$slug}.example.com:8006/api2/json",
            'proxmox_node' => $slug,
            'proxmox_token_id' => 'root@pam!token',
            'proxmox_token_secret' => 'token-secret',
            'health_status' => 'healthy',
            'enabled' => true,
            'max_total_vms' => 12,
            'current_vm_count' => 0,
        ]);

        $target->runnerTemplates()->attach($this->template->id, [
            'template_vmid' => $templateVmid,
            'availability_status' => 'available',
        ]);

        return $target;
    }

    /**
     * @param  array<int, array<string, int>>  $nodes
     */
    private function makePool(string $name, array $nodes): Pool
    {
        $pool = Pool::create([
            'environment_id' => $this->environment->id,
            'runner_template_id' => $this->template->id,
            'name' => $name,
            'enabled' => true,
            'labels' => ['self-hosted', 'linux', 'x64'],
            'cores' => 4,
            'memory' => 8192,
            'boot_timeout_seconds' => 300,
        ]);

        $pool->proxmoxTargets()->sync($nodes);

        return $pool->fresh();
    }

    private function makeRunner(Pool $pool, int $vmid, RunnerState $state, ?ProxmoxTarget $target = null): Runner
    {
        return Runner::create([
            'environment_id' => $this->environment->id,
            'proxmox_target_id' => ($target ?? $this->target)->id,
            'pool_id' => $pool->id,
            'vmid' => $vmid,
            'runner_name' => "gha-{$pool->name}-{$vmid}-abc",
            'state' => $state,
            'state_changed_at' => now(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    private function formPayload(string $name, array $nodes): array
    {
        return [
            'environment_id' => $this->environment->id,
            'runner_template_id' => $this->template->id,
            'name' => $name,
            'enabled' => true,
            'labels' => ['self-hosted', 'linux', 'x64', 'ubuntu-24.04'],
            'cores' => 4,
            'memory' => 8192,
            'boot_timeout_seconds' => 300,
            'nodes' => $nodes,
        ];
    }
}
