<?php

namespace Tests\Feature;

use App\Enums\RunnerState;
use App\Helpers\BreadcrumbHelpers;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\Runner;
use App\Models\RunnerTemplate;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class BreadcrumbLabelTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());
        $this->actingAs(User::factory()->create());

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
    }

    public function test_a_runner_page_shows_the_runner_name_in_the_breadcrumbs(): void
    {
        $target = ProxmoxTarget::create([
            'name' => 'PVE 01',
            'slug' => 'pve-01',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!runner',
            'proxmox_token_secret' => 'secret',
        ]);
        $template = RunnerTemplate::create([
            'environment_id' => $this->environment->id,
            'name' => 'Ubuntu 24.04',
            'os' => 'linux',
            'template_catalog_id' => 'ubuntu-24.04',
        ]);
        $pool = Pool::create([
            'environment_id' => $this->environment->id,
            'runner_template_id' => $template->id,
            'name' => 'ubuntu',
            'labels' => ['self-hosted'],
            'cores' => 2,
            'memory' => 2048,
            'boot_timeout_seconds' => 180,
        ]);
        $runner = Runner::create([
            'environment_id' => $this->environment->id,
            'pool_id' => $pool->id,
            'proxmox_target_id' => $target->id,
            'runner_name' => 'gha-mtn5sbymd0nvbpji',
            'vmid' => 901,
            'state' => RunnerState::Idle->value,
            'state_changed_at' => now(),
        ]);

        $this->get(route('runners.show', $runner))
            ->assertOk()
            ->assertSee('gha-mtn5sbymd0nvbpji');
    }

    public function test_a_pool_page_shows_the_pool_name_in_the_breadcrumbs(): void
    {
        $template = RunnerTemplate::create([
            'environment_id' => $this->environment->id,
            'name' => 'Ubuntu 24.04',
            'os' => 'linux',
            'template_catalog_id' => 'ubuntu-24.04',
        ]);
        $pool = Pool::create([
            'environment_id' => $this->environment->id,
            'runner_template_id' => $template->id,
            'name' => 'ubuntu-pool',
            'labels' => ['self-hosted'],
            'cores' => 2,
            'memory' => 2048,
            'boot_timeout_seconds' => 180,
        ]);

        $response = $this->get(route('pools.show', $pool))->assertOk();

        $this->assertStringContainsString('ubuntu-pool</span>', $response->getContent());
    }

    public function test_an_id_with_no_bound_model_falls_back_to_the_humanised_segment(): void
    {
        $this->app['request'] = Request::create('/config/environments/42');

        $labels = array_column(BreadcrumbHelpers::forRequest(), 'label');

        $this->assertSame(['Home', 'Config', 'Environments', 'Environment'], $labels);
    }
}
