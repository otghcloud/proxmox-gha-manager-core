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
use App\Services\GitHub\GitHubClient;
use App\Services\GitHub\GitHubRunner;
use App\Services\Provisioning\Provisioner;
use App\Services\Provisioning\Reaper;
use App\Services\Proxmox\ProxmoxClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class ReaperDecisionsTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private ProxmoxTarget $target;

    private RunnerTemplate $template;

    private Pool $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $account = GitHubAccount::create([
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'github_token' => 'token',
            'github_webhook_secret' => 'secret',
        ]);

        $this->environment = Environment::create([
            'name' => 'Production',
            'slug' => 'production',
            'github_account_id' => $account->id,
            'idle_timeout_seconds' => 900,
            'max_lifetime_seconds' => 43200,
        ]);

        $this->target = ProxmoxTarget::create([
            'name' => 'PVE 01',
            'slug' => 'pve-01',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!token',
            'proxmox_token_secret' => 'secret',
            'template_vmid_range_start' => 801,
            'template_vmid_range_end' => 899,
            'runner_vmid_range_start' => 901,
            'runner_vmid_range_end' => 999,
        ]);

        $this->template = RunnerTemplate::create([
            'environment_id' => $this->environment->id,
            'name' => 'ubuntu-slim',
            'os' => PoolOs::Linux,
        ]);

        $this->template->targetMappings()->attach($this->target->id, [
            'template_vmid' => 801,
            'generation' => 1,
            'availability_status' => 'available',
        ]);

        $this->pool = Pool::create([
            'environment_id' => $this->environment->id,
            'runner_template_id' => $this->template->id,
            'name' => 'ubuntu-slim',
            'labels' => ['self-hosted'],
            'cores' => 2,
            'memory' => 2048,
            'boot_timeout_seconds' => 300,
        ]);

        $this->pool->proxmoxTargets()->sync([
            $this->target->id => ['min_idle_runners' => 2, 'max_concurrent' => 6],
        ]);
    }

    public function test_an_idle_runner_holding_the_warm_pool_floor_is_kept(): void
    {
        $runner = $this->makeIdleRunner(901, 801);
        $this->makeIdleRunner(902, 801);

        $this->assertNull($this->reasonFor($runner));
    }

    public function test_an_idle_runner_above_the_warm_pool_floor_is_reaped(): void
    {
        $runner = $this->makeIdleRunner(901, 801);
        $this->makeIdleRunner(902, 801);
        $this->makeIdleRunner(903, 801);

        $this->assertSame('idle for too long', $this->reasonFor($runner));
    }

    public function test_an_idle_runner_from_a_superseded_template_is_reaped_immediately(): void
    {
        $runner = $this->makeIdleRunner(901, 801, secondsIdle: 5);
        $this->makeIdleRunner(902, 801, secondsIdle: 5);

        $this->template->targetMappings()->updateExistingPivot($this->target->id, ['template_vmid' => 802]);

        $this->assertSame('template superseded by a rebuild', $this->reasonFor($runner->fresh()));
    }

    public function test_a_busy_runner_from_a_superseded_template_is_left_to_finish(): void
    {
        $runner = $this->makeIdleRunner(901, 801, secondsIdle: 5);
        $runner->forceFill(['state' => RunnerState::Busy])->save();

        $this->template->targetMappings()->updateExistingPivot($this->target->id, ['template_vmid' => 802]);

        $this->assertNull($this->reasonFor($runner->fresh(), busy: true));
    }

    public function test_a_vm_in_the_template_range_is_never_reconciled(): void
    {
        $reaper = $this->reaper();
        $method = new ReflectionMethod($reaper, 'isTemplateVmid');

        // A template build runs for hours as an ordinary VM before Packer converts it.
        $this->assertTrue($method->invoke($reaper, 802));
        $this->assertFalse($method->invoke($reaper, 901));
    }

    public function test_a_sweep_re_reads_each_runner_so_a_slow_destroy_cannot_kill_a_live_one(): void
    {
        $this->pool->proxmoxTargets()->sync([
            $this->target->id => ['min_idle_runners' => 0, 'max_concurrent' => 6],
        ]);

        // Destroying a VM waits on Proxmox, so by the time the sweep reaches the second runner the
        // snapshot it started from can be minutes old.
        $finished = $this->makeRunner(901, RunnerState::Reaping, now());
        $stillSpawning = $this->makeRunner(902, RunnerState::Spawning, now()->subHour());

        $provisioner = $this->createMock(Provisioner::class);
        $destroyed = [];

        $provisioner->method('destroy')->willReturnCallback(function (Runner $runner) use (&$destroyed, $stillSpawning): void {
            $destroyed[] = $runner->runner_name;

            // The slow destroy gives the queue worker time to finish provisioning the other runner.
            $stillSpawning->forceFill([
                'state' => RunnerState::Idle,
                'state_changed_at' => now(),
            ])->save();
        });

        $reaper = new Reaper(
            $this->environment,
            $this->target,
            $this->proxmoxReporting([901, 902]),
            $this->githubReporting([$finished, $stillSpawning]),
            $provisioner,
        );

        $this->assertSame(1, $reaper->runOnce());
        $this->assertSame([$finished->runner_name], $destroyed);
        $this->assertSame(RunnerState::Idle, $stillSpawning->fresh()->state);
    }

    public function test_a_stale_spawning_runner_is_still_reaped_when_nothing_rescues_it(): void
    {
        $this->pool->proxmoxTargets()->sync([
            $this->target->id => ['min_idle_runners' => 0, 'max_concurrent' => 6],
        ]);

        $stuck = $this->makeRunner(902, RunnerState::Spawning, now()->subHour());

        $provisioner = $this->createMock(Provisioner::class);
        $provisioner->expects($this->once())
            ->method('destroy')
            ->with($this->anything(), 'stuck while spawning');

        $reaper = new Reaper(
            $this->environment,
            $this->target,
            $this->proxmoxReporting([902]),
            $this->githubReporting([$stuck]),
            $provisioner,
        );

        $this->assertSame(1, $reaper->runOnce());
    }

    private function makeRunner(int $vmid, RunnerState $state, Carbon $stateChangedAt): Runner
    {
        return Runner::create([
            'environment_id' => $this->environment->id,
            'proxmox_target_id' => $this->target->id,
            'pool_id' => $this->pool->id,
            'vmid' => $vmid,
            'runner_name' => "gha-ubuntu-slim-{$vmid}-abc",
            'state' => $state,
            'state_changed_at' => $stateChangedAt,
            'created_at' => $stateChangedAt,
        ]);
    }

    /**
     * @param  array<int, int>  $vmids
     */
    private function proxmoxReporting(array $vmids): ProxmoxClient
    {
        $proxmox = $this->createMock(ProxmoxClient::class);
        $proxmox->method('clusterVms')->willReturn(
            collect($vmids)->mapWithKeys(fn (int $vmid): array => [$vmid => ['vmid' => $vmid]])->all()
        );

        return $proxmox;
    }

    /**
     * @param  array<int, Runner>  $runners
     */
    private function githubReporting(array $runners): GitHubClient
    {
        $github = $this->createMock(GitHubClient::class);
        $github->method('listRunners')->willReturn(
            collect($runners)->mapWithKeys(fn (Runner $runner, int $index): array => [
                $runner->runner_name => new GitHubRunner($index + 1, $runner->runner_name, 'online', false),
            ])->all()
        );

        return $github;
    }

    private function makeIdleRunner(int $vmid, int $sourceTemplateVmid, int $secondsIdle = 1800): Runner
    {
        return Runner::create([
            'environment_id' => $this->environment->id,
            'proxmox_target_id' => $this->target->id,
            'pool_id' => $this->pool->id,
            'vmid' => $vmid,
            'runner_name' => "gha-ubuntu-slim-{$vmid}-abc",
            'source_template_vmid' => $sourceTemplateVmid,
            'state' => RunnerState::Idle,
            'state_changed_at' => now()->subSeconds($secondsIdle),
        ]);
    }

    private function reasonFor(Runner $runner, bool $busy = false): ?string
    {
        $method = new ReflectionMethod(Reaper::class, 'destructionReason');

        return $method->invoke($this->reaper(), $runner, new GitHubRunner(1, $runner->runner_name, 'online', $busy));
    }

    private function reaper(): Reaper
    {
        return new Reaper(
            $this->environment,
            $this->target,
            $this->createMock(ProxmoxClient::class),
            $this->createMock(GitHubClient::class),
            $this->createMock(Provisioner::class),
        );
    }
}
