<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\ProxmoxTarget;
use App\Services\Provisioning\EnvironmentServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisionerUsesSelectedTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_environment_services_selects_the_best_target_for_the_provisioner(): void
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
            'current_vm_count' => 2,
            'max_total_vms' => 8,
        ]);

        ProxmoxTarget::create([
            'name' => 'pve02',
            'slug' => 'pve02',
            'proxmox_url' => 'https://pve02.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!token',
            'proxmox_token_secret' => 'token-secret',
            'health_status' => 'degraded',
            'enabled' => true,
            'current_vm_count' => 1,
            'max_total_vms' => 8,
        ]);

        $provisioner = app(EnvironmentServices::class)->provisioner($environment);

        $this->assertNotNull($provisioner->selectedTarget());
        $this->assertTrue($provisioner->selectedTarget()->is($healthy));
    }
}
