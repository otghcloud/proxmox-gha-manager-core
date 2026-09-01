<?php

namespace Tests\Feature;

use App\Enums\BuildStatus;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\ImageBuild;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\RunnerTemplate;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunnerTemplateMappingTest extends TestCase
{
    use RefreshDatabase;

    private string $catalogDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogDirectory = sys_get_temp_dir().'/template-mapping-'.bin2hex(random_bytes(4));
        mkdir($this->catalogDirectory, 0755, true);
        config(['builds.image_builder_path' => $this->catalogDirectory]);
        file_put_contents($this->catalogDirectory.'/templates.json', json_encode(['templates' => [[
            'id' => 'ubuntu-24.04-proxmox-x64',
            'target' => 'ubuntu-24.04',
            'name' => 'Ubuntu 24.04',
            'description' => 'Ubuntu runner image',
            'platform' => 'linux',
            'version' => '1.0.0',
            'template_path' => 'templates/proxmox/ubuntu/ubuntu2404',
            'build_capability' => ['enabled' => true, 'disabled_reason' => null, 'communicator' => 'ssh'],
            'packer' => ['interface_version' => 1, 'runner_images_directory' => 'images/ubuntu', 'scripts_root_required' => true, 'installation_media' => []],
            'build_requirements' => ['cpu_cores' => 6, 'memory_mb' => 8192, 'disk_gb' => 75],
        ]]], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        unlink($this->catalogDirectory.'/templates.json');
        rmdir($this->catalogDirectory);

        parent::tearDown();
    }

    public function test_template_stores_physical_configuration_per_node(): void
    {
        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());
        $this->actingAs(User::factory()->create());

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
        $target = ProxmoxTarget::create([
            'name' => 'PVE 01',
            'slug' => 'pve-01',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!runner',
            'proxmox_token_secret' => 'secret',
        ]);

        $this->post(route('templates.store'), [
            'environment_id' => $environment->id,
            'template_catalog_id' => 'ubuntu-24.04-proxmox-x64',
            'target_ids' => [$target->id],
            'mappings' => [
                $target->id => [
                    'build_iso_file' => 'local:iso/ubuntu.iso',
                    'build_cores' => 6,
                    'build_memory_mb' => 8192,
                    'build_disk_gb' => 160,
                ],
            ],
        ])->assertRedirect();

        $template = RunnerTemplate::firstOrFail();
        $mapping = $template->targetMappings()->firstOrFail()->pivot;

        $this->assertNull($mapping->template_vmid);
        $this->assertSame('local:iso/ubuntu.iso', $mapping->build_iso_file);
        $this->assertSame(6, $mapping->build_cores);
        $this->assertSame(8192, $mapping->build_memory_mb);
        $this->assertSame(160, $mapping->build_disk_gb);
    }

    public function test_template_create_page_renders_with_available_nodes(): void
    {
        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());
        $this->actingAs(User::factory()->create());

        $account = GitHubAccount::create([
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'github_token' => 'token',
            'github_webhook_secret' => 'secret',
        ]);
        Environment::create(['name' => 'Production', 'slug' => 'production', 'github_account_id' => $account->id]);
        ProxmoxTarget::create([
            'name' => 'PVE 01', 'slug' => 'pve-01', 'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve', 'proxmox_token_id' => 'root@pam!runner', 'proxmox_token_secret' => 'secret',
        ]);

        $this->get(route('templates.create'))->assertOk()->assertSee('Proxmox nodes');
    }

    public function test_image_list_ajax_endpoints_render_catalog_aligned_records(): void
    {
        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());
        $this->actingAs(User::factory()->create());

        $account = GitHubAccount::create(['account_type' => 'organization', 'login' => 'otghcloud', 'github_token' => 'token', 'github_webhook_secret' => 'secret']);
        $environment = Environment::create(['name' => 'Production', 'slug' => 'production', 'github_account_id' => $account->id]);
        $node = ProxmoxTarget::create(['name' => 'PVE 02', 'slug' => 'pve-02', 'proxmox_url' => 'https://pve02.example.com:8006/api2/json', 'proxmox_node' => 'pve02', 'proxmox_token_id' => 'root@pam!runner', 'proxmox_token_secret' => 'secret']);
        $template = RunnerTemplate::create(['environment_id' => $environment->id, 'name' => 'Ubuntu 24.04', 'os' => 'linux', 'template_catalog_id' => 'ubuntu-24.04-proxmox-x64']);
        $template->targetMappings()->attach($node->id);
        $pool = Pool::create(['environment_id' => $environment->id, 'runner_template_id' => $template->id, 'name' => 'ubuntu2404', 'labels' => ['self-hosted'], 'cores' => 2, 'memory' => 2048, 'boot_timeout_seconds' => 180]);
        $pool->proxmoxTargets()->attach($node->id, ['min_idle_runners' => 0, 'max_concurrent' => 1]);
        ImageBuild::create(['environment_id' => $environment->id, 'runner_template_id' => $template->id, 'proxmox_target_id' => $node->id, 'template_catalog_id' => 'ubuntu-24.04-proxmox-x64', 'status' => BuildStatus::Queued]);

        foreach ([route('templates.index'), route('builds.index'), route('pools.index')] as $route) {
            $this->getJson($route.'?draw=1&start=0&length=10', ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }
    }

    public function test_build_now_rejects_an_unconfigured_node_without_server_error(): void
    {
        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());
        $this->actingAs(User::factory()->create());

        $account = GitHubAccount::create([
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'github_token' => 'token',
            'github_webhook_secret' => 'secret',
        ]);
        $environment = Environment::create(['name' => 'Production', 'slug' => 'production', 'github_account_id' => $account->id]);
        $target = ProxmoxTarget::create([
            'name' => 'PVE 01', 'slug' => 'pve-01', 'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve', 'proxmox_token_id' => 'root@pam!runner', 'proxmox_token_secret' => 'secret',
        ]);
        $template = RunnerTemplate::create(['environment_id' => $environment->id, 'name' => 'Ubuntu', 'os' => 'linux', 'template_catalog_id' => 'ubuntu-24.04-proxmox-x64']);
        $template->targetMappings()->attach($target->id, ['template_vmid' => 801, 'build_iso_file' => 'local:ubuntu.iso']);

        $this->post(route('templates.build', [$template, $target]))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_builds_for_the_same_template_can_run_on_different_nodes(): void
    {
        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());
        $this->actingAs(User::factory()->create());
        Queue::fake();
        Http::fake(['https://pve.example.com:8006/api2/json/cluster/resources*' => Http::response(['data' => []])]);

        $account = GitHubAccount::create(['account_type' => 'organization', 'login' => 'otghcloud', 'github_token' => 'token', 'github_webhook_secret' => 'secret']);
        $environment = Environment::create(['name' => 'Production', 'slug' => 'production', 'github_account_id' => $account->id]);
        $nodeA = ProxmoxTarget::create(['name' => 'PVE A', 'slug' => 'pve-a', 'proxmox_url' => 'https://pve.example.com:8006/api2/json', 'proxmox_node' => 'pve', 'proxmox_token_id' => 'root@pam!runner', 'proxmox_token_secret' => 'secret', 'build_iso_storage' => 'local', 'build_vm_storage' => 'local']);
        $nodeB = ProxmoxTarget::create(['name' => 'PVE B', 'slug' => 'pve-b', 'proxmox_url' => 'https://pve.example.com:8006/api2/json', 'proxmox_node' => 'pve', 'proxmox_token_id' => 'root@pam!runner', 'proxmox_token_secret' => 'secret', 'build_iso_storage' => 'local', 'build_vm_storage' => 'local']);
        $template = RunnerTemplate::create(['environment_id' => $environment->id, 'name' => 'Ubuntu', 'os' => 'linux', 'template_catalog_id' => 'ubuntu-24.04-proxmox-x64']);
        $template->targetMappings()->attach($nodeA->id, ['template_vmid' => 801, 'build_iso_file' => 'local:ubuntu.iso']);
        $template->targetMappings()->attach($nodeB->id, ['template_vmid' => 802, 'build_iso_file' => 'local:ubuntu.iso']);
        ImageBuild::create(['environment_id' => $environment->id, 'runner_template_id' => $template->id, 'proxmox_target_id' => $nodeA->id, 'template_catalog_id' => 'ubuntu-24.04-proxmox-x64', 'status' => BuildStatus::Running]);

        $this->post(route('templates.build', [$template, $nodeB]))->assertRedirect();

        $this->assertDatabaseHas('image_builds', ['runner_template_id' => $template->id, 'proxmox_target_id' => $nodeB->id, 'status' => BuildStatus::Queued->value]);

        $this->get(route('templates.show', $template))
            ->assertOk()
            ->assertSee('Building')
            ->assertDontSee('Build now');

        $this->get(route('environments.show', $environment))
            ->assertOk()
            ->assertSee('Building');
    }
}
