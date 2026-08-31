<?php

namespace Tests\Feature;

use App\Enums\PoolOs;
use App\Enums\RunnerState;
use App\Exceptions\ProvisioningException;
use App\Exceptions\ProxmoxException;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\Runner;
use App\Models\RunnerTemplate;
use App\Services\Provisioning\EnvironmentServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers what the provisioner asks Proxmox for. The VM never boots here, so spawning always ends in
 * a failure; what matters is the clone source and the configuration written on the way.
 */
class ProvisionerClonesFromNodeTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private RunnerTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $account = GitHubAccount::create([
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'github_token' => 'token',
            'github_webhook_secret' => 'secret',
            'linux_ssh_username' => 'runner',
            'linux_ssh_password' => 'password',
        ]);

        $this->environment = Environment::create([
            'name' => 'Production',
            'slug' => 'production',
            'github_account_id' => $account->id,
            'keep_failed_vms' => true,
        ]);

        $this->template = RunnerTemplate::create([
            'environment_id' => $this->environment->id,
            'name' => 'ubuntu2404',
            'os' => PoolOs::Linux,
        ]);
    }

    public function test_a_runner_clones_from_its_own_nodes_template_vmid(): void
    {
        $first = $this->makeNode('pve01', 'https://pve01.example.com:8006/api2/json', templateVmid: 801);
        $second = $this->makeNode('pve02', 'https://pve02.example.com:8006/api2/json', templateVmid: 802);
        $pool = $this->makePool([$first, $second]);

        $this->fakeProxmox();

        $this->spawnAndExpectNoBoot($pool, $second);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'https://pve02.example.com:8006/api2/json/nodes/pve02/qemu/802/clone'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/qemu/801/clone'));
    }

    public function test_a_runner_is_configured_onto_the_nodes_bridge_and_vlan(): void
    {
        $node = $this->makeNode('pve01', 'https://pve01.example.com:8006/api2/json', templateVmid: 801, bridge: 'vmbr7', vlan: 120);
        $pool = $this->makePool([$node]);

        $this->fakeProxmox();

        $this->spawnAndExpectNoBoot($pool, $node);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/config')
            && $request->method() === 'PUT'
            && ($request->data()['net0'] ?? null) === 'virtio,bridge=vmbr7,tag=120');
    }

    public function test_a_node_without_a_vlan_gets_an_untagged_adapter(): void
    {
        $node = $this->makeNode('pve01', 'https://pve01.example.com:8006/api2/json', templateVmid: 801, bridge: 'vmbr0');
        $pool = $this->makePool([$node]);

        $this->fakeProxmox();

        $this->spawnAndExpectNoBoot($pool, $node);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/config')
            && $request->method() === 'PUT'
            && ($request->data()['net0'] ?? null) === 'virtio,bridge=vmbr0');
    }

    public function test_a_node_whose_template_was_never_built_cannot_be_cloned_from(): void
    {
        $node = $this->makeNode('pve01', 'https://pve01.example.com:8006/api2/json', templateVmid: null);
        $pool = $this->makePool([$node]);

        $this->fakeProxmox();

        $this->expectException(ProvisioningException::class);
        $this->expectExceptionMessage('has no physical VMID on target');

        app(EnvironmentServices::class)
            ->provisionerForTarget($this->environment, $node)
            ->spawn($pool, preferredTarget: $node);
    }

    private function spawnAndExpectNoBoot(Pool $pool, ProxmoxTarget $node): void
    {
        try {
            app(EnvironmentServices::class)->provisionerForTarget($this->environment, $node)->spawn($pool, preferredTarget: $node);
            $this->fail('The VM never reports an IP in these tests, so spawning must fail.');
        } catch (ProxmoxException $e) {
            $this->assertStringContainsString('never reported an IPv4 address', $e->getMessage());
        }

        $this->assertSame(RunnerState::Failed, Runner::firstOrFail()->state);
    }

    private function makeNode(string $slug, string $url, ?int $templateVmid, string $bridge = 'vmbr0', ?int $vlan = null): ProxmoxTarget
    {
        $target = ProxmoxTarget::create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'proxmox_url' => $url,
            'proxmox_node' => $slug,
            'proxmox_token_id' => 'root@pam!token',
            'proxmox_token_secret' => 'secret',
            'health_status' => 'healthy',
            'enabled' => true,
            'max_total_vms' => 12,
            'current_vm_count' => 0,
            'template_vmid_range_start' => 801,
            'template_vmid_range_end' => 899,
            'runner_vmid_range_start' => 901,
            'runner_vmid_range_end' => 999,
            'network_bridge' => $bridge,
            'vlan_tag' => $vlan,
        ]);

        $this->template->targetMappings()->attach($target->id, [
            'template_vmid' => $templateVmid,
            'availability_status' => $templateVmid === null ? 'unavailable' : 'available',
        ]);

        return $target;
    }

    /**
     * @param  array<int, ProxmoxTarget>  $nodes
     */
    private function makePool(array $nodes): Pool
    {
        $pool = Pool::create([
            'environment_id' => $this->environment->id,
            'runner_template_id' => $this->template->id,
            'name' => 'ubuntu2404',
            'labels' => ['self-hosted', 'linux', 'x64'],
            'cores' => 2,
            'memory' => 2048,
            // The guest agent is faked to never answer, so keep the wait short.
            'boot_timeout_seconds' => 1,
        ]);

        $pool->proxmoxTargets()->sync(collect($nodes)->mapWithKeys(fn (ProxmoxTarget $node): array => [
            $node->id => ['min_idle_runners' => 0, 'max_concurrent' => 4],
        ])->all());

        return $pool->fresh();
    }

    private function fakeProxmox(): void
    {
        Http::fake([
            '*/cluster/resources*' => Http::response(['data' => []]),
            '*/agent/network-get-interfaces*' => Http::response(['data' => ['result' => []]]),
            '*/status/current*' => Http::response(['data' => ['status' => 'running']]),
            '*/tasks/*' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
            '*' => Http::response(['data' => 'UPID:pve:0000:0000:0000:qmclone:900:root@pam:']),
        ]);
    }
}
