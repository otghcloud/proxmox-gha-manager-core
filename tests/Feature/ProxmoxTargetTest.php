<?php

namespace Tests\Feature;

use App\Models\ProxmoxTarget;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxmoxTargetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());
        $this->actingAs(User::factory()->create());
    }

    public function test_standalone_target_create_page_renders_without_an_environment(): void
    {
        $this->get(route('nodes.create'))
            ->assertOk()
            ->assertSee('Create node');
    }

    public function test_standalone_target_detail_page_renders(): void
    {
        $target = ProxmoxTarget::create([
            'name' => 'PVE 01',
            'slug' => 'pve-01',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!runner',
            'proxmox_token_secret' => 'secret',
        ]);

        $this->get(route('nodes.show', $target))
            ->assertOk()
            ->assertSee('Template coverage');
    }

    public function test_standalone_target_can_be_created(): void
    {
        $this->post(route('nodes.store'), [
            'name' => 'PVE 01',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!runner',
            'proxmox_token_secret' => 'secret',
            'max_total_vms' => 12,
            'template_vmid_range_start' => 100,
            'template_vmid_range_end' => 8999,
            'runner_vmid_range_start' => 9000,
            'runner_vmid_range_end' => 9999,
            'enabled' => true,
            'proxmox_verify_tls' => false,
        ])->assertRedirect(route('nodes.index'));

        $this->assertDatabaseHas('proxmox_targets', [
            'name' => 'PVE 01',
            'slug' => 'pve-01',
            'proxmox_node' => 'pve',
        ]);

        $this->assertSame('secret', ProxmoxTarget::firstOrFail()->proxmox_token_secret);
    }

    public function test_storage_options_use_unsaved_node_connection_details(): void
    {
        Http::fake([
            'https://pve.example.com:8006/api2/json/nodes/pve/storage*' => Http::response(['data' => [
                ['storage' => 'local', 'type' => 'dir', 'avail' => 10 * 1024 ** 3, 'enabled' => 1],
            ]]),
            'https://pve.example.com:8006/api2/json/cluster/resources*' => Http::response(['data' => []]),
        ]);

        $this->postJson(route('nodes.storage-options'), [
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!runner',
            'proxmox_token_secret' => 'secret',
        ])->assertOk()->assertJsonPath('iso.0.name', 'local');

        $this->assertDatabaseCount('proxmox_targets', 0);
    }

    public function test_node_connection_can_be_tested_from_the_nodes_page(): void
    {
        $target = ProxmoxTarget::create([
            'name' => 'PVE 01',
            'slug' => 'pve-01',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!runner',
            'proxmox_token_secret' => 'secret',
        ]);

        Http::fake([
            'https://pve.example.com:8006/api2/json/cluster/resources*' => Http::response(['data' => []]),
        ]);

        $this->post(route('nodes.test', $target))
            ->assertRedirect()
            ->assertSessionHas('success', 'Proxmox node PVE 01 is reachable (0 VMs visible).');
    }
}
