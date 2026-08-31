<?php

namespace Tests\Unit;

use App\Models\ProxmoxTarget;
use App\Services\Health\HealthCheckService;
use App\Services\Proxmox\ProxmoxClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxmoxTargetCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_network_adapter_reflects_the_nodes_bridge_and_vlan(): void
    {
        $target = new ProxmoxTarget(['network_bridge' => 'vmbr1', 'vlan_tag' => 120]);

        $this->assertSame('virtio,bridge=vmbr1,tag=120', $target->networkAdapter());

        $target->vlan_tag = null;

        $this->assertSame('virtio,bridge=vmbr1', $target->networkAdapter());
    }

    public function test_target_vm_filtering_only_counts_node_specific_managed_or_pool_vms(): void
    {
        $target = ProxmoxTarget::create([
            'name' => 'LD PVE02',
            'slug' => 'ld-pve02',
            'proxmox_url' => 'https://pve02.example.com:8006/api2/json',
            'proxmox_node' => 'pve04',
            'proxmox_resource_pool' => 'gha-runners',
            'proxmox_token_id' => 'root@pam!token',
            'proxmox_token_secret' => 'token-secret',
            'max_total_vms' => 12,
            'current_vm_count' => 54,
        ]);

        $clusterResources = [
            // VM on different node in cluster -> should be ignored
            ['vmid' => 101, 'node' => 'pve01', 'pool' => 'gha-runners', 'name' => 'gha-pve01-101'],
            // Non-managed VM on same node outside pool -> should be ignored
            ['vmid' => 201, 'node' => 'pve04', 'pool' => 'other-pool', 'name' => 'unrelated-vm'],
            // VM template on same node in pool -> should be ignored
            ['vmid' => 801, 'node' => 'pve04', 'pool' => 'gha-runners', 'name' => 'tmpl-ubuntu2404', 'template' => 1],
            // Managed runner VM on same node in pool -> should be counted
            ['vmid' => 901, 'node' => 'pve04', 'pool' => 'gha-runners', 'name' => 'gha-ubuntu2404-901-abc', 'tags' => 'gha-runner'],
        ];

        $client = new ProxmoxClient($target);
        $filtered = $client->filterTargetVms($clusterResources, $target);

        $this->assertCount(1, $filtered);
        $this->assertArrayHasKey(901, $filtered);

        Http::fake([
            'https://pve02.example.com:8006/api2/json/cluster/resources*' => Http::response(['data' => $clusterResources]),
        ]);

        $health = new HealthCheckService;
        $this->assertTrue($health->checkTarget($target));

        $target->refresh();
        $this->assertSame(1, $target->current_vm_count);
        $this->assertSame('healthy', $target->health_status);
    }
}
