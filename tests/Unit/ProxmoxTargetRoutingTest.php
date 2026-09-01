<?php

namespace Tests\Unit;

use App\Enums\PoolOs;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\RunnerTemplate;
use App\Services\Provisioning\TargetSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxmoxTargetRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_best_target_prefers_healthy_capacity_and_template_coverage(): void
    {
        $account = GitHubAccount::create([
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'github_token' => 'ghp_example',
            'github_webhook_secret' => 'webhook-secret',
        ]);

        $environment = Environment::create([
            'name' => 'Test',
            'slug' => 'test',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'user@pve!token',
            'proxmox_token_secret' => 'proxmox-secret',
            'github_account_id' => $account->id,
        ]);

        $healthy = ProxmoxTarget::create([
            'name' => 'pve01',
            'slug' => 'pve01',
            'proxmox_url' => 'https://pve01.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!token',
            'proxmox_token_secret' => 'token-secret',
            'health_status' => 'healthy',
            'enabled' => true,
            'max_total_vms' => 8,
            'current_vm_count' => 2,
        ]);

        $degraded = ProxmoxTarget::create([
            'name' => 'pve02',
            'slug' => 'pve02',
            'proxmox_url' => 'https://pve02.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!token',
            'proxmox_token_secret' => 'token-secret',
            'health_status' => 'degraded',
            'enabled' => true,
            'max_total_vms' => 10,
            'current_vm_count' => 1,
        ]);

        $template = RunnerTemplate::create([
            'environment_id' => $environment->id,
            'name' => 'ubuntu2404',
            'vmid' => 100,
            'os' => PoolOs::Linux,
        ]);

        $healthy->runnerTemplates()->attach($template->id, ['template_vmid' => 801, 'availability_status' => 'available']);
        $degraded->runnerTemplates()->attach($template->id, ['template_vmid' => 801, 'availability_status' => 'available']);

        $selector = new TargetSelector;
        $target = $selector->selectFor(['self-hosted', 'ubuntu-24.04'], $template);

        $this->assertTrue($target->is($healthy));
        $this->assertNotTrue($target->is($degraded));
    }

    public function test_pool_node_preference_overrides_available_capacity_ordering(): void
    {
        $account = GitHubAccount::create(['account_type' => 'organization', 'login' => 'otghcloud', 'github_token' => 'token', 'github_webhook_secret' => 'secret']);
        $environment = Environment::create(['name' => 'Test', 'slug' => 'test', 'github_account_id' => $account->id]);
        $preferred = $this->target($environment, 'pve02', 6);
        $fallback = $this->target($environment, 'pve03', 1);
        $template = RunnerTemplate::create(['environment_id' => $environment->id, 'name' => 'ubuntu2404', 'os' => PoolOs::Linux]);
        $template->targetMappings()->attach($preferred->id, ['template_vmid' => 801, 'availability_status' => 'available']);
        $template->targetMappings()->attach($fallback->id, ['template_vmid' => 802, 'availability_status' => 'available']);
        $pool = Pool::create(['environment_id' => $environment->id, 'runner_template_id' => $template->id, 'name' => 'ubuntu2404', 'labels' => ['self-hosted'], 'cores' => 2, 'memory' => 2048, 'boot_timeout_seconds' => 180]);
        $pool->proxmoxTargets()->attach($preferred->id, ['preference' => 0, 'min_idle_runners' => 0, 'max_concurrent' => 4]);
        $pool->proxmoxTargets()->attach($fallback->id, ['preference' => 10, 'min_idle_runners' => 0, 'max_concurrent' => 4]);

        $selected = (new TargetSelector)->selectFor($pool->labels, $template, $pool);

        $this->assertTrue($selected->is($preferred));
    }

    private function target(Environment $environment, string $slug, int $currentVmCount): ProxmoxTarget
    {
        return ProxmoxTarget::create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'proxmox_url' => "https://{$slug}.example.com:8006/api2/json",
            'proxmox_node' => $slug,
            'proxmox_token_id' => 'root@pam!token',
            'proxmox_token_secret' => 'token-secret',
            'health_status' => 'healthy',
            'enabled' => true,
            'max_total_vms' => 8,
            'current_vm_count' => $currentVmCount,
        ]);
    }
}
