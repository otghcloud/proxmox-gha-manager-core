<?php

namespace Tests\Unit;

use App\Enums\BuildStatus;
use App\Enums\BuildTarget;
use App\Enums\PoolOs;
use App\Enums\RunnerState;
use App\Exceptions\ProvisioningException;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\ImageBuild;
use App\Models\ProxmoxTarget;
use App\Models\RetiredTemplateVmid;
use App\Models\Runner;
use App\Models\RunnerTemplate;
use App\Services\Provisioning\VmidAllocator;
use App\Services\Proxmox\ProxmoxClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VmidAllocatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_and_runner_allocations_use_separate_target_ranges(): void
    {
        $target = $this->makeTarget();

        $this->fakeCluster([801, 901]);
        $allocator = new VmidAllocator(new ProxmoxClient($target));

        $templateVmid = $allocator->allocate($target, 'template', fn (int $vmid): int => $vmid);
        $runnerVmid = $allocator->allocate($target, 'runner', fn (int $vmid): int => $vmid);

        $this->assertSame(802, $templateVmid);
        $this->assertSame(902, $runnerVmid);
    }

    public function test_template_allocation_skips_vmids_this_installation_has_claimed(): void
    {
        $target = $this->makeTarget();
        $template = $this->makeTemplate();

        // 801 is live, 802 is retired but not yet deleted, 803 is mid-build: none are free even
        // though Proxmox only knows about the first.
        $template->targetMappings()->attach($target->id, ['template_vmid' => 801, 'availability_status' => 'available']);

        RetiredTemplateVmid::create([
            'runner_template_id' => $template->id,
            'proxmox_target_id' => $target->id,
            'vmid' => 802,
            'generation' => 1,
            'retired_at' => now(),
        ]);

        ImageBuild::create([
            'environment_id' => $template->environment_id,
            'runner_template_id' => $template->id,
            'proxmox_target_id' => $target->id,
            'target' => BuildTarget::Ubuntu2404->value,
            'status' => BuildStatus::Running,
            'template_vmid' => 803,
        ]);

        $this->fakeCluster([801]);

        $vmid = (new VmidAllocator(new ProxmoxClient($target)))
            ->allocate($target, 'template', fn (int $vmid): int => $vmid);

        $this->assertSame(804, $vmid);
    }

    public function test_a_finished_build_no_longer_reserves_its_vmid(): void
    {
        $target = $this->makeTarget();
        $template = $this->makeTemplate();

        ImageBuild::create([
            'environment_id' => $template->environment_id,
            'runner_template_id' => $template->id,
            'proxmox_target_id' => $target->id,
            'target' => BuildTarget::Ubuntu2404->value,
            'status' => BuildStatus::Failed,
            'template_vmid' => 801,
        ]);

        $this->fakeCluster([]);

        $vmid = (new VmidAllocator(new ProxmoxClient($target)))
            ->allocate($target, 'template', fn (int $vmid): int => $vmid);

        $this->assertSame(801, $vmid);
    }

    public function test_runner_allocation_skips_vmids_held_by_live_runners(): void
    {
        $target = $this->makeTarget();
        $template = $this->makeTemplate();

        $this->makeRunner($template->environment_id, $target->id, 901, RunnerState::Idle);
        $this->makeRunner($template->environment_id, $target->id, 902, RunnerState::Destroyed);

        $this->fakeCluster([]);

        $vmid = (new VmidAllocator(new ProxmoxClient($target)))
            ->allocate($target, 'runner', fn (int $vmid): int => $vmid);

        // 901 is in use; 902 belongs to a destroyed runner and is free again.
        $this->assertSame(902, $vmid);
    }

    public function test_an_exhausted_range_is_rejected_rather_than_spilling_into_the_next_one(): void
    {
        $target = $this->makeTarget();

        $this->fakeCluster([801, 802, 803, 804, 805]);

        $this->expectException(ProvisioningException::class);
        $this->expectExceptionMessage('No free template VMID in range 801-805');

        (new VmidAllocator(new ProxmoxClient($target)))
            ->allocate($target, 'template', fn (int $vmid): int => $vmid);
    }

    private function makeTarget(): ProxmoxTarget
    {
        return ProxmoxTarget::create([
            'name' => 'PVE 01',
            'slug' => 'pve-01',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!runner',
            'proxmox_token_secret' => 'secret',
            'template_vmid_range_start' => 801,
            'template_vmid_range_end' => 805,
            'runner_vmid_range_start' => 901,
            'runner_vmid_range_end' => 905,
        ]);
    }

    private function makeTemplate(): RunnerTemplate
    {
        $account = GitHubAccount::create([
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'github_token' => 'token',
            'github_webhook_secret' => 'secret',
        ]);

        $environment = Environment::create([
            'name' => 'Production',
            'slug' => 'production',
            'github_account_id' => $account->id,
        ]);

        return RunnerTemplate::create([
            'environment_id' => $environment->id,
            'name' => 'ubuntu2404',
            'os' => PoolOs::Linux,
        ]);
    }

    private function makeRunner(int $environmentId, int $targetId, int $vmid, RunnerState $state): Runner
    {
        return Runner::create([
            'environment_id' => $environmentId,
            'proxmox_target_id' => $targetId,
            'vmid' => $vmid,
            'runner_name' => 'gha-pool-'.$vmid.'-abc',
            'state' => $state,
            'state_changed_at' => now(),
        ]);
    }

    /**
     * @param  array<int, int>  $vmids
     */
    private function fakeCluster(array $vmids): void
    {
        Http::fake([
            'https://pve.example.com:8006/api2/json/cluster/resources*' => Http::response([
                'data' => array_map(fn (int $vmid): array => ['vmid' => $vmid], $vmids),
            ]),
        ]);
    }
}
