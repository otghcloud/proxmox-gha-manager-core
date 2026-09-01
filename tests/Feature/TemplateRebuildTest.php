<?php

namespace Tests\Feature;

use App\Enums\BuildStatus;
use App\Enums\PoolOs;
use App\Enums\RunnerState;
use App\Jobs\BuildImageJob;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\ImageBuild;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\RetiredTemplateVmid;
use App\Models\Runner;
use App\Models\RunnerTemplate;
use App\Services\Builds\ImageBuilder;
use App\Services\Builds\TemplateRebuilder;
use App\Services\Proxmox\ProxmoxClient;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;
use Tests\TestCase;

class TemplateRebuildTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private ProxmoxTarget $target;

    private RunnerTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());

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
        ]);

        $this->target = ProxmoxTarget::create([
            'name' => 'PVE 01',
            'slug' => 'pve-01',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!token',
            'proxmox_token_secret' => 'secret',
            'health_status' => 'healthy',
            'template_vmid_range_start' => 801,
            'template_vmid_range_end' => 899,
        ]);

        $this->template = RunnerTemplate::create([
            'environment_id' => $this->environment->id,
            'name' => 'ubuntu-slim',
            'os' => PoolOs::Linux,
            'template_catalog_id' => 'ubuntu-24.04-proxmox-x64',
        ]);

        $this->template->targetMappings()->attach($this->target->id, [
            'template_vmid' => 801,
            'generation' => 1,
            'availability_status' => 'available',
        ]);
    }

    public function test_promoting_a_build_swaps_the_vmid_and_retires_the_old_one(): void
    {
        $build = $this->makeBuild(802);

        app(TemplateRebuilder::class)->promote($build, 802);

        $mapping = $this->template->fresh()->targetMappings()->firstOrFail()->pivot;

        $this->assertSame(802, $mapping->template_vmid);
        $this->assertSame(2, $mapping->generation);

        $retired = RetiredTemplateVmid::firstOrFail();
        $this->assertSame(801, $retired->vmid);
        $this->assertNull($retired->deleted_at);
    }

    public function test_a_first_build_retires_nothing(): void
    {
        $this->template->targetMappings()->updateExistingPivot($this->target->id, [
            'template_vmid' => null,
            'generation' => 0,
        ]);

        app(TemplateRebuilder::class)->promote($this->makeBuild(805), 805);

        $this->assertSame(805, $this->template->fresh()->targetMappings()->firstOrFail()->pivot->template_vmid);
        $this->assertSame(0, RetiredTemplateVmid::count());
    }

    public function test_a_sequential_batch_only_starts_the_next_node_when_one_succeeds(): void
    {
        Queue::fake();

        $first = $this->makeBuild(802, 'batch-1', 0);
        $second = $this->makeBuild(803, 'batch-1', 1);

        $first->forceFill(['status' => BuildStatus::Succeeded])->save();
        app(TemplateRebuilder::class)->advanceBatch($first);

        Queue::assertPushed(BuildImageJob::class, fn (BuildImageJob $job): bool => $job->imageBuildId === $second->id);
    }

    public function test_a_failed_build_cancels_the_rest_of_its_batch(): void
    {
        Queue::fake();

        $first = $this->makeBuild(802, 'batch-2', 0);
        $second = $this->makeBuild(803, 'batch-2', 1);

        $first->forceFill(['status' => BuildStatus::Failed])->save();
        app(TemplateRebuilder::class)->advanceBatch($first);

        Queue::assertNotPushed(BuildImageJob::class);
        $this->assertSame(BuildStatus::Cancelled, $second->fresh()->status);
    }

    public function test_pruning_leaves_a_retired_template_that_runners_still_use(): void
    {
        $this->retire(801);
        $this->makeRunnerFrom(801);

        $this->artisan('templates:prune')->assertExitCode(0);

        $this->assertNull(RetiredTemplateVmid::firstOrFail()->deleted_at);
    }

    public function test_keeping_generations_leaves_the_most_recent_retired_templates(): void
    {
        app(SettingsRepository::class)->setMany([
            SettingsRepository::TEMPLATE_RETENTION_MODE => SettingsRepository::RETENTION_KEEP_LAST_N,
            SettingsRepository::TEMPLATE_RETENTION_GENERATIONS => 2,
        ]);

        $this->retire(801, 1);
        $this->retire(802, 2);
        $this->retire(803, 3);

        // Proxmox is unreachable in tests, so the only prunable row fails to delete and stays put;
        // what matters is that the two newest generations are never even considered.
        $this->artisan('templates:prune')->assertExitCode(0);

        $this->assertSame(3, RetiredTemplateVmid::whereNull('deleted_at')->count());
    }

    public function test_the_template_vm_is_named_after_the_template(): void
    {
        $this->assertSame('tpl-ubuntu-slim', $this->template->vmName());

        $this->template->update(['name' => 'Ubuntu 24.04']);

        $this->assertSame('tpl-ubuntu-2404', $this->template->fresh()->vmName());
    }

    public function test_packer_is_given_the_allocated_vmid_and_template_name(): void
    {
        $this->target->update([
            'build_iso_storage' => 'local',
            'build_vm_storage' => 'local-lvm',
            'network_bridge' => 'vmbr9',
            'vlan_tag' => 42,
        ]);

        $this->template->targetMappings()->updateExistingPivot($this->target->id, [
            'build_iso_file' => 'local:iso/ubuntu.iso',
        ]);

        $build = $this->makeBuild(806);
        $build->load(['environment.githubAccount', 'runnerTemplate', 'proxmoxTarget']);

        $method = new ReflectionMethod(ImageBuilder::class, 'environmentVariables');
        $variables = $method->invoke(new ImageBuilder($this->createMock(ProxmoxClient::class)), $build);

        $this->assertSame('806', $variables['PKR_VAR_pmx_template_vmid']);
        $this->assertSame('tpl-ubuntu-slim', $variables['PKR_VAR_pmx_template_name']);
        $this->assertSame('vmbr9', $variables['PKR_VAR_pmx_network_bridge']);
        $this->assertSame('42', $variables['PKR_VAR_pmx_vlan_tag']);
        $this->assertSame('local', $variables['PKR_VAR_pmx_iso_storage']);
        $this->assertSame('local-lvm', $variables['PKR_VAR_pmx_vm_storage']);
        $this->assertSame('local:iso/ubuntu.iso', $variables['PKR_VAR_pmx_iso_file']);
    }

    public function test_a_node_without_a_vlan_does_not_pass_one_to_packer(): void
    {
        $this->target->update([
            'build_iso_storage' => 'local',
            'build_vm_storage' => 'local-lvm',
            'network_bridge' => 'vmbr0',
            'vlan_tag' => null,
        ]);

        $this->template->targetMappings()->updateExistingPivot($this->target->id, [
            'build_iso_file' => 'local:iso/ubuntu.iso',
        ]);

        $build = $this->makeBuild(806);
        $build->load(['environment.githubAccount', 'runnerTemplate', 'proxmoxTarget']);

        $method = new ReflectionMethod(ImageBuilder::class, 'environmentVariables');
        $variables = $method->invoke(new ImageBuilder($this->createMock(ProxmoxClient::class)), $build);

        // Proxmox rejects `tag=0`, so the variable has to be absent rather than zero.
        $this->assertArrayNotHasKey('PKR_VAR_pmx_vlan_tag', $variables);
        $this->assertSame('vmbr0', $variables['PKR_VAR_pmx_network_bridge']);
    }

    public function test_build_sizing_comes_from_the_node_mapping(): void
    {
        $this->target->update(['build_iso_storage' => 'local', 'build_vm_storage' => 'local-lvm']);

        $this->template->targetMappings()->updateExistingPivot($this->target->id, [
            'build_iso_file' => 'local:iso/ubuntu.iso',
            'build_cores' => 8,
            'build_memory_mb' => 16384,
            'build_disk_gb' => 200,
        ]);

        $build = $this->makeBuild(806);
        $build->load(['environment.githubAccount', 'runnerTemplate', 'proxmoxTarget']);

        $method = new ReflectionMethod(ImageBuilder::class, 'environmentVariables');
        $variables = $method->invoke(new ImageBuilder($this->createMock(ProxmoxClient::class)), $build);

        $this->assertSame('8', $variables['PKR_VAR_build_cpu_cores']);
        $this->assertSame('16384', $variables['PKR_VAR_build_memory_mb']);
        $this->assertSame('200', $variables['PKR_VAR_build_disk_gb']);
    }

    public function test_blank_build_sizing_is_left_to_the_packer_template(): void
    {
        $this->target->update(['build_iso_storage' => 'local', 'build_vm_storage' => 'local-lvm']);

        $this->template->targetMappings()->updateExistingPivot($this->target->id, [
            'build_iso_file' => 'local:iso/ubuntu.iso',
        ]);

        $build = $this->makeBuild(806);
        $build->load(['environment.githubAccount', 'runnerTemplate', 'proxmoxTarget']);

        $method = new ReflectionMethod(ImageBuilder::class, 'environmentVariables');
        $variables = $method->invoke(new ImageBuilder($this->createMock(ProxmoxClient::class)), $build);

        $this->assertArrayNotHasKey('PKR_VAR_build_cpu_cores', $variables);
        $this->assertArrayNotHasKey('PKR_VAR_build_memory_mb', $variables);
        $this->assertArrayNotHasKey('PKR_VAR_build_disk_gb', $variables);
    }

    public function test_a_failed_build_leaves_every_node_on_its_current_template(): void
    {
        Queue::fake();

        $other = ProxmoxTarget::create([
            'name' => 'PVE 02',
            'slug' => 'pve-02',
            'proxmox_url' => 'https://pve02.example.com:8006/api2/json',
            'proxmox_node' => 'pve02',
            'proxmox_token_id' => 'root@pam!token',
            'proxmox_token_secret' => 'secret',
            'template_vmid_range_start' => 801,
            'template_vmid_range_end' => 899,
        ]);

        $this->template->targetMappings()->attach($other->id, [
            'template_vmid' => 811,
            'generation' => 3,
            'availability_status' => 'available',
        ]);

        $first = $this->makeBuild(802, 'batch-fail', 0);
        $second = ImageBuild::create([
            'environment_id' => $this->environment->id,
            'runner_template_id' => $this->template->id,
            'proxmox_target_id' => $other->id,
            'template_catalog_id' => $this->template->template_catalog_id,
            'status' => BuildStatus::Queued,
            'template_vmid' => 812,
            'rebuild_batch_id' => 'batch-fail',
            'sequence' => 1,
        ]);

        $first->forceFill(['status' => BuildStatus::Failed])->save();
        app(TemplateRebuilder::class)->advanceBatch($first);

        $mappings = $this->template->fresh()->targetMappings()->get()->keyBy('id');

        $this->assertSame(801, $mappings[$this->target->id]->pivot->template_vmid);
        $this->assertSame(1, $mappings[$this->target->id]->pivot->generation);
        $this->assertSame(811, $mappings[$other->id]->pivot->template_vmid);
        $this->assertSame(3, $mappings[$other->id]->pivot->generation);
        $this->assertSame(BuildStatus::Cancelled, $second->fresh()->status);
        $this->assertSame(0, RetiredTemplateVmid::count());
    }

    private function makeBuild(int $vmid, ?string $batch = null, int $sequence = 0): ImageBuild
    {
        return ImageBuild::create([
            'environment_id' => $this->environment->id,
            'runner_template_id' => $this->template->id,
            'proxmox_target_id' => $this->target->id,
            'template_catalog_id' => $this->template->template_catalog_id,
            'status' => BuildStatus::Queued,
            'template_vmid' => $vmid,
            'rebuild_batch_id' => $batch,
            'sequence' => $sequence,
        ]);
    }

    private function retire(int $vmid, int $generation = 1): RetiredTemplateVmid
    {
        return RetiredTemplateVmid::create([
            'runner_template_id' => $this->template->id,
            'proxmox_target_id' => $this->target->id,
            'vmid' => $vmid,
            'generation' => $generation,
            'retired_at' => now(),
        ]);
    }

    private function makeRunnerFrom(int $templateVmid): Runner
    {
        $pool = Pool::create([
            'environment_id' => $this->environment->id,
            'runner_template_id' => $this->template->id,
            'name' => 'ubuntu-slim',
            'labels' => ['self-hosted'],
            'cores' => 2,
            'memory' => 2048,
            'boot_timeout_seconds' => 300,
        ]);

        return Runner::create([
            'environment_id' => $this->environment->id,
            'proxmox_target_id' => $this->target->id,
            'pool_id' => $pool->id,
            'vmid' => 901,
            'runner_name' => 'gha-ubuntu-slim-901-abc',
            'source_template_vmid' => $templateVmid,
            'state' => RunnerState::Idle,
            'state_changed_at' => now(),
        ]);
    }
}
